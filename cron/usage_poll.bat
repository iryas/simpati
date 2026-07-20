@echo off
setlocal
REM ============================================================
REM  SIMPATI - usage:poll (Windows Task Scheduler / manual)
REM  Polling pemakaian PPPoE (byte-out + uptime) dari Mikrotik.
REM
REM  Cara pakai di Task Scheduler > Start a Program:
REM    Program/script      : (path lengkap ke file .bat ini)
REM    Add arguments        : (kosongkan)
REM    Start in             : (kosongkan - .bat ini pindah folder sendiri)
REM  Lalu di Properties > Triggers > Edit, centang
REM    "Repeat task every: 5 minutes" durasi "Indefinitely".
REM ============================================================

REM --- Path ke php.exe. Default "php" (bila sudah ada di PATH). ---
REM     Kalau belum, ganti manual, misal: set "PHP=C:\xampp\php\php.exe"
set "PHP=php"
if exist "C:\xampp\php\php.exe"   set "PHP=C:\xampp\php\php.exe"
if exist "C:\xampp82\php\php.exe" set "PHP=C:\xampp82\php\php.exe"

REM --- Pindah ke folder aplikasi (folder induk dari cron\) ---
cd /d "%~dp0.."

REM --- Jalankan + catat log (worker.php sudah menambah timestamp) ---
"%PHP%" worker.php usage:poll >> "logs\usage_poll.log" 2>&1

endlocal
