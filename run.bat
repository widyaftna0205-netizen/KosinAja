@echo off
title KosinAja Localhost Server - E-Commerce Sewa Kos
echo ====================================================================
echo   KosinAja - Sistem E-Commerce Sewa Kos Berbasis Web
echo   Mitra: Kos Putri Anak Lanang (Ibu Yeni, Pasir Pengaraian, Riau)
echo   Fitur: Pembayaran Calon, Sewa Penghuni, Rekap Kas Bulanan Pemilik
echo ====================================================================
echo.

set PHP_EXE=

if exist "D:\laragon\bin\php\php-8.1.10-Win32-vs16-x64\php.exe" (
    set "PHP_EXE=D:\laragon\bin\php\php-8.1.10-Win32-vs16-x64\php.exe"
) else if exist "C:\xampp\php\php.exe" (
    set "PHP_EXE=C:\xampp\php\php.exe"
) else if exist "D:\xampp\php\php.exe" (
    set "PHP_EXE=D:\xampp\php\php.exe"
) else (
    where php >nul 2>nul
    if %errorlevel% equ 0 (
        set "PHP_EXE=php"
    )
)

if "%PHP_EXE%"=="" (
    echo [ERROR] PHP tidak ditemukan di lokasi standar (Laragon/XAMPP/PATH).
    pause
    exit /b
)

echo Menggunakan PHP: %PHP_EXE%
echo Menjalankan web server lokal di http://localhost:8000 ...
echo Buka browser ke: http://localhost:8000
echo Tekan CTRL+C di jendela ini untuk menghentikan server.
echo.

start "" "http://localhost:8000"
"%PHP_EXE%" -S localhost:8000
pause
