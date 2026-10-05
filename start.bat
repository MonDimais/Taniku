@echo off
title TaniKu Launcher
color 0A
echo ============================================
echo    TANIku - Auto Setup & Run
echo ============================================
echo.

REM ==================== DETECT PHP ====================
where php >nul 2>&1
if %errorlevel% equ 0 (
    for /f "delims=" %%i in ('where php') do set "PHPPATH=%%~dpi"
    set "PHPPATH=%PHPPATH:~0,-1%"
    goto :php_found
)

for %%P in (
    "C:\laragon\bin\php"
    "C:\Program Files\Laragon\bin\php"
    "D:\laragon\bin\php"
    "E:\laragon\bin\php"
) do (
    if exist "%%~P" (
        for /d %%D in ("%%~P\*") do (
            if exist "%%~D\php.exe" (
                set "PHPPATH=%%~D"
                goto :php_found
            )
        )
    )
)

echo [ERROR] PHP tidak ditemukan!
echo Install Laragon: https://laragon.org/download
pause
exit /b 1

:php_found
echo [OK] PHP: %PHPPATH%\php.exe
"%PHPPATH%\php.exe" -v
echo.

REM ==================== DETECT COMPOSER ====================
set "COMPOSERPATH="

if exist "C:\laragon\bin\composer\composer.phar" (
    set "COMPOSERPATH=C:\laragon\bin\composer\composer.phar"
    goto :composer_found
)

where composer >nul 2>&1
if %errorlevel% equ 0 (
    for /f "delims=" %%i in ('where composer') do (
        if exist "%%~i\composer.phar" set "COMPOSERPATH=%%~i\composer.phar"
        if exist "%%~di\composer.phar" set "COMPOSERPATH=%%~di\composer.phar"
    )
)

if defined COMPOSERPATH goto :composer_found

for %%C in ("C:\Program Files\Composer" "C:\ProgramData\ComposerSetup\bin") do (
    if exist "%%~C\composer.phar" (
        set "COMPOSERPATH=%%~C\composer.phar"
        goto :composer_found
    )
)

if exist "composer.phar" (
    set "COMPOSERPATH=%cd%\composer.phar"
    goto :composer_found
)

echo [!] Composer tidak ditemukan. Downloading...
powershell -ExecutionPolicy Bypass -Command "Invoke-WebRequest -Uri 'https://getcomposer.org/download/latest-stable/composer.phar' -OutFile '%cd%\composer.phar'"
if exist "composer.phar" (
    set "COMPOSERPATH=%cd%\composer.phar"
    goto :composer_found
)

echo [ERROR] Gagal download Composer! Download manual: https://getcomposer.org/download/
pause
exit /b 1

:composer_found
echo [OK] Composer: %COMPOSERPATH%
echo.

REM ==================== COMPOSER INSTALL (MUST BE FIRST) ====================
if not exist "vendor" (
    echo [..] Composer install...
    "%PHPPATH%\php.exe" "%COMPOSERPATH%" install --no-interaction
    if %errorlevel% neq 0 (
        echo [ERROR] Composer install gagal!
        pause
        exit /b 1
    )
    echo [OK] Dependencies di-install
) else (
    echo [OK] Vendor sudah ada
)
echo.

REM ==================== SETUP .ENV ====================
echo [..] Setup .env...

if not exist ".env" (
    copy .env.example .env >nul 2>&1
    if errorlevel 1 (
        REM .env.example juga belum ada, buat manual
        > .env echo APP_NAME=TaniKu
        >> .env echo APP_ENV=local
        >> .env echo APP_KEY=
        >> .env echo APP_DEBUG=true
        >> .env echo APP_URL=http://localhost:5001
        >> .env echo DB_CONNECTION=sqlite
    )
    echo [OK] .env dibuat
)

REM Fix .env dengan PHP (aman untuk path dengan spasi/kurung)
"%PHPPATH%\php.exe" -r "
\$env = file_get_contents('.env');
\$dir = str_replace('\\\\', '/', getcwd());
\$env = preg_replace('/^DB_DATABASE=.*/m', 'DB_DATABASE=' . \$dir . '/database/database.sqlite', \$env);
\$env = preg_replace('/^APP_URL=.*/m', 'APP_URL=http://localhost:5001', \$env);
\$env = preg_replace('/^APP_NAME=.*/m', 'APP_NAME=TaniKu', \$env);
if(!preg_match('/^DB_DATABASE=/m', \$env)){ \$env .= \"\nDB_DATABASE=\" . \$dir . \"/database/database.sqlite\n\"; }
file_put_contents('.env', \$env);
echo 'OK';
"
echo [OK] .env di-update
echo.

REM ==================== APP KEY ====================
echo [..] Generate APP_KEY...
"%PHPPATH%\php.exe" artisan key:generate --force
echo [OK] APP_KEY di-generate
echo.

REM ==================== CHECK EXTENSIONS ====================
echo [..] Cek PHP extensions...
set "EXT_MISSING=0"

"%PHPPATH%\php.exe" -r "exit(extension_loaded('sqlite3') ? 0 : 1);"
if errorlevel 1 (echo [!] Missing: sqlite3 & set EXT_MISSING=1)

"%PHPPATH%\php.exe" -r "exit(extension_loaded('pdo_sqlite') ? 0 : 1);"
if errorlevel 1 (echo [!] Missing: pdo_sqlite & set EXT_MISSING=1)

"%PHPPATH%\php.exe" -r "exit(extension_loaded('mbstring') ? 0 : 1);"
if errorlevel 1 (echo [!] Missing: mbstring & set EXT_MISSING=1)

"%PHPPATH%\php.exe" -r "exit(extension_loaded('xml') ? 0 : 1);"
if errorlevel 1 (echo [!] Missing: xml & set EXT_MISSING=1)

"%PHPPATH%\php.exe" -r "exit(extension_loaded('curl') ? 0 : 1);"
if errorlevel 1 (echo [!] Missing: curl & set EXT_MISSING=1)

"%PHPPATH%\php.exe" -r "exit(extension_loaded('zip') ? 0 : 1);"
if errorlevel 1 (echo [!] Missing: zip & set EXT_MISSING=1)

if "%EXT_MISSING%" equ "1" (
    echo.
    echo [!] Extension missing di atas.
    echo    Buka php.ini Laragon, uncomment:
    echo      extension=pdo_sqlite
    echo      extension=sqlite3
    echo      extension=mbstring
    echo      extension=xml
    echo      extension=curl
    echo      extension=zip
    echo.
)

REM ==================== CREATE DB FILE ====================
echo [..] Buat database SQLite...
if not exist "database\database.sqlite" (
    mkdir database >nul 2>&1
    type nul > database\database.sqlite
    echo [OK] database.sqlite dibuat
) else (
    echo [OK] database.sqlite sudah ada
)
echo.

REM ==================== MIGRATE + SEED (ONE COMMAND) ====================
echo [..] Migrate dan seed database...
"%PHPPATH%\php.exe" artisan migrate:fresh --seed --force
if %errorlevel% neq 0 (
    echo [!] Gagal! Coba manual:
    echo    "%PHPPATH%\php.exe" artisan migrate:fresh --seed --force
    pause
    exit /b 1
)
echo.

REM ==================== VERIFY DATA ====================
echo [..] Verifikasi data...
"%PHPPATH%\php.exe" -r "
require 'vendor/autoload.php';
\$app = require 'bootstrap/app.php';
\$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();
echo 'Users: ' . DB::table('users')->count() . ' | Products: ' . DB::table('products')->count() . ' | Categories: ' . DB::table('categories')->count() . \"\n\";
"
echo.

REM ==================== CLEAR CACHE ====================
echo [..] Clear cache...
"%PHPPATH%\php.exe" artisan config:clear >nul 2>&1
"%PHPPATH%\php.exe" artisan route:clear >nul 2>&1
"%PHPPATH%\php.exe" artisan view:clear >nul 2>&1
echo [OK] Cache di-clear
echo.

REM ==================== SHOW CREDENTIALS ====================
echo ============================================
echo    TANIku Siap Dijalankan!
echo ============================================
echo.
echo  Akses:  http://localhost:5001
echo.
echo  Akun Test:
echo  --------
echo  Seller:  budi@taniku.com   / budi123
echo  Seller:  siti@taniku.com   / siti123
echo  Admin:   admin@taniku.com  / admin123
echo  Buyer:   andi@taniku.com   / andi123
echo  Buyer:   dewi@taniku.com   / dewi123
echo.
echo ============================================
echo.

REM ==================== START SERVER ====================
echo [INFO] Server: http://localhost:5001
echo [INFO] CTRL+C untuk stop.
echo.
"%PHPPATH%\php.exe" artisan serve --host=0.0.0.0 --port=5001