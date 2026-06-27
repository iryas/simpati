<?php
// ============================================================
//  KAHFINET - Klien RouterOS API (implementasi ringan, tanpa Composer)
//  Protokol: https://help.mikrotik.com/docs/display/ROS/API
// ============================================================

class RouterosApiException extends RuntimeException {}

class RouterosApi {
    private $socket = null;

    public function connect(string $host, int $port, bool $useSsl = false, int $timeout = 5): void {
        $errno  = 0;
        $errstr = '';
        $transport = $useSsl ? 'ssl' : 'tcp';
        $context   = stream_context_create(
            $useSsl ? ['ssl' => ['verify_peer' => false, 'verify_peer_name' => false]] : []
        );

        $this->socket = @stream_socket_client(
            "$transport://$host:$port",
            $errno,
            $errstr,
            $timeout,
            STREAM_CLIENT_CONNECT,
            $context
        );

        if (!$this->socket) {
            throw new RouterosApiException("Gagal konek ke $host:$port — $errstr");
        }
        stream_set_timeout($this->socket, $timeout);
    }

    public function login(string $username, string $password): void {
        $this->write('/login', ['name' => $username, 'password' => $password]);
        $response = $this->readSentences();

        if (empty($response) || $response[0]['__tag'] !== '!done') {
            throw new RouterosApiException('Login Mikrotik gagal: respons tidak dikenali.');
        }
        if (isset($response[0]['!trap'])) {
            throw new RouterosApiException('Login Mikrotik gagal: kredensial salah.');
        }
    }

    public function close(): void {
        if ($this->socket) {
            fclose($this->socket);
            $this->socket = null;
        }
    }

    // ── Kirim command + baca semua baris hasil (!re) sampai !done ──
    public function comm(string $command, array $params = []): array {
        $this->write($command, $params);
        $sentences = $this->readSentences();

        $rows = [];
        foreach ($sentences as $s) {
            if ($s['__tag'] === '!trap') {
                $msg = $s['message'] ?? 'Perintah Mikrotik gagal.';
                throw new RouterosApiException($msg);
            }
            if ($s['__tag'] === '!re') {
                unset($s['__tag']);
                $rows[] = $s;
            }
        }
        return $rows;
    }

    private function write(string $command, array $params = []): void {
        $words = [$command];
        foreach ($params as $key => $value) {
            // Filter query (cth: "?name=foo") tidak diawali "=" — beda dari atribut biasa.
            $words[] = str_starts_with($key, '?') ? "$key=$value" : "=$key=$value";
        }
        foreach ($words as $word) {
            $this->writeWord($word);
        }
        $this->writeWord('');
    }

    private function writeWord(string $word): void {
        $length = strlen($word);
        $this->writeLength($length);
        if ($length > 0) {
            $this->socketWrite($word);
        }
    }

    private function writeLength(int $length): void {
        if ($length < 0x80) {
            $this->socketWrite(chr($length));
        } elseif ($length < 0x4000) {
            $length |= 0x8000;
            $this->socketWrite(chr(($length >> 8) & 0xFF) . chr($length & 0xFF));
        } elseif ($length < 0x200000) {
            $length |= 0xC00000;
            $this->socketWrite(chr(($length >> 16) & 0xFF) . chr(($length >> 8) & 0xFF) . chr($length & 0xFF));
        } else {
            throw new RouterosApiException('Panjang kata API tidak didukung.');
        }
    }

    private function socketWrite(string $data): void {
        if (@fwrite($this->socket, $data) === false) {
            throw new RouterosApiException('Koneksi ke Mikrotik terputus saat menulis data.');
        }
    }

    // ── Baca seluruh sentence sampai ketemu !done ───────────────
    private function readSentences(): array {
        $sentences = [];
        do {
            $sentence = $this->readSentence();
            if ($sentence !== null) {
                $sentences[] = $sentence;
            }
        } while ($sentence !== null && $sentence['__tag'] !== '!done');
        return $sentences;
    }

    private function readSentence(): ?array {
        $sentence = [];
        $tag      = null;

        while (true) {
            $word = $this->readWord();
            if ($word === '') break;

            if ($tag === null) {
                $tag = $word;
                continue;
            }
            if (str_starts_with($word, '=')) {
                $parts = explode('=', substr($word, 1), 2);
                $sentence[$parts[0]] = $parts[1] ?? '';
            } else {
                $sentence[$word] = true;
            }
        }

        if ($tag === null) return null;
        $sentence['__tag'] = $tag;
        return $sentence;
    }

    private function readWord(): string {
        $length = $this->readLength();
        if ($length === 0) return '';
        return $this->socketRead($length);
    }

    private function readLength(): int {
        $byte1 = ord($this->socketRead(1));

        if (($byte1 & 0x80) === 0) {
            return $byte1;
        }
        if (($byte1 & 0xC0) === 0x80) {
            $byte2 = ord($this->socketRead(1));
            return (($byte1 & 0x3F) << 8) | $byte2;
        }
        if (($byte1 & 0xE0) === 0xC0) {
            $rest = $this->socketRead(2);
            return (($byte1 & 0x1F) << 16) | (ord($rest[0]) << 8) | ord($rest[1]);
        }
        if (($byte1 & 0xF0) === 0xE0) {
            $rest = $this->socketRead(3);
            return (($byte1 & 0x0F) << 24) | (ord($rest[0]) << 16) | (ord($rest[1]) << 8) | ord($rest[2]);
        }
        $rest = $this->socketRead(4);
        return (ord($rest[0]) << 24) | (ord($rest[1]) << 16) | (ord($rest[2]) << 8) | ord($rest[3]);
    }

    private function socketRead(int $length): string {
        $data = '';
        while (strlen($data) < $length) {
            $chunk = fread($this->socket, $length - strlen($data));
            if ($chunk === false || $chunk === '') {
                throw new RouterosApiException('Koneksi ke Mikrotik terputus saat membaca data.');
            }
            $data .= $chunk;
        }
        return $data;
    }
}
