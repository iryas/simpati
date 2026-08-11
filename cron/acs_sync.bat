@echo off
setlocal
REM ============================================================
REM  SIMPATI - acs:sync (Windows Task Scheduler / manual)
REM  Sync cache ONU dari GenieACS ke aplikasi (status, RXPower,
REM  suhu, WiFi, dll) supaya Monitoring selalu terbaru.
REM
REM  Cara pakai di Task Scheduler > Start a Program:
REM    Program/script      : (path lengkap ke file .bat ini)
REM    Add arguments        : (kosongkan)
REM    Start in             : (kosongkan - .bat ini pindah folder sendiri)
REM  Lalu di Properties > Triggers > Edit, centang
REM    "Repeat task every: 1 minute" durasi "Indefinitely".
REM  (Boleh diperlama, mis. 2-3 menit — ONU lapor tiap ~100 detik.)
REM ============================================================

REM --- Path ke php.exe. Default "php" (bila sudah ada di PATH). ---
REM     Kalau belum, ganti manual, misal: set "PHP=C:\xampp\php\php.exe"
set "PHP=php"
if exist "C:\xampp\php\php.exe"   set "PHP=C:\xampp\php\php.exe"
if exist "C:\xampp82\php\php.exe" set "PHP=C:\xampp82\php\php.exe"

REM --- Pindah ke folder aplikasi (folder induk dari cron\) ---
cd /d "%~dp0.."

REM --- Jalankan. worker.php menulis log sendiri ke logs\acs_sync.log ---
"%PHP%" worker.php acs:sync

endlocal
