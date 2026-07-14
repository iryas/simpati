@echo off
chcp 65001 >nul
title SIMPATI - Deploy

echo.
echo ╔══════════════════════════════════════╗
echo ║       SIMPATI - Deploy Update        ║
echo ╚══════════════════════════════════════╝
echo.

:: Pindah ke folder script ini berada
cd /d "%~dp0"

echo [1/3] Mengambil update terbaru dari GitHub (branch SIMPATI)...
git fetch origin SIMPATI
if errorlevel 1 (
    echo [ERROR] Gagal fetch dari GitHub. Cek koneksi internet atau konfigurasi git.
    pause
    exit /b 1
)

echo.
echo [2/3] Menerapkan update...
git reset --hard origin/SIMPATI
if errorlevel 1 (
    echo [ERROR] Gagal reset. Cek status git secara manual.
    pause
    exit /b 1
)

echo.
echo [3/3] Selesai!
echo.
echo ✓ Aplikasi SIMPATI berhasil diperbarui.
echo   Tidak perlu restart XAMPP.
echo.
pause
