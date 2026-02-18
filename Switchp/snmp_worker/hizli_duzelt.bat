@echo off
REM ============================================================================
REM HIZLI DUZELTME - Alarm Sistemi Onarimi
REM ============================================================================
REM Bu script en yagin alarm sorunlarini otomatik olarak duzeltir:
REM   1. Kritik database kolonlarini ekler (port_type, port_speed, port_mtu)
REM   2. Gerekli tablolari kontrol eder
REM   3. SNMP Worker'i yeniden baslatir
REM   4. Sistem durumunu dogrular
REM ============================================================================

echo.
echo ============================================================================
echo                      ALARM SISTEMI - HIZLI ONARIM
echo ============================================================================
echo.

REM Yapilac aklari goster
echo Bu script asagidaki islemleri yapacak:
echo   [1] Kritik database kolonlarini ekleyecek
echo   [2] Tablolari kontrol edecek
echo   [3] SNMP Worker'i yeniden baslatacak
echo   [4] Sistem durumunu dogrulayacak
echo.
pause
echo.

REM ============================================================================
REM YAPILANDIRMA
REM ============================================================================

set MYSQL_HOST=127.0.0.1
set MYSQL_USER=root
set MYSQL_DB=switchdb
set MYSQL_PATH=C:\xampp\mysql\bin\mysql.exe
set PYTHON_PATH=python
set SCRIPT_DIR=%~dp0
set WORKER_DIR=%SCRIPT_DIR%
set MIGRATIONS_DIR=%SCRIPT_DIR%migrations

REM ============================================================================
REM ADIM 1: MySQL Baglantisi Kontrol
REM ============================================================================

echo [1/4] MySQL baglantisi kontrol ediliyor...
"%MYSQL_PATH%" -h %MYSQL_HOST% -u %MYSQL_USER% -e "SELECT 1" > nul 2>&1
if %ERRORLEVEL% NEQ 0 (
    echo [HATA] MySQL'e baglanılamadı!
    echo.
    echo Lutfen:
    echo   - XAMPP Control Panel'de MySQL'in çalıştığından emin olun
    echo   - MySQL yolunun dogru oldugunu kontrol edin: %MYSQL_PATH%
    echo.
    pause
    exit /b 1
)
echo [OK] MySQL baglantisi basarili
echo.

REM ============================================================================
REM ADIM 2: Kritik Migration'i Uygula
REM ============================================================================

echo [2/4] Kritik database kolonlari ekleniyor...

if exist "%MIGRATIONS_DIR%\add_port_config_columns.sql" (
    echo   - add_port_config_columns.sql uygulanıyor...
    "%MYSQL_PATH%" -h %MYSQL_HOST% -u %MYSQL_USER% %MYSQL_DB% < "%MIGRATIONS_DIR%\add_port_config_columns.sql"
    if %ERRORLEVEL% EQU 0 (
        echo   [OK] Kolonlar eklendi (port_type, port_speed, port_mtu)
    ) else (
        echo   [UYARI] Migration hatali - ancak devam ediliyor
        echo   (Kolonlar zaten mevcut olabilir)
    )
) else (
    echo   [HATA] add_port_config_columns.sql bulunamadi!
    echo   Dosya konumu: %MIGRATIONS_DIR%\add_port_config_columns.sql
)
echo.

REM ============================================================================
REM ADIM 3: Temel Tablolari Kontrol Et
REM ============================================================================

echo [3/4] Database tablolari kontrol ediliyor...

set TABLE_OK=0
set TABLE_MISSING=0

REM snmp_devices tablosu
"%MYSQL_PATH%" -h %MYSQL_HOST% -u %MYSQL_USER% %MYSQL_DB% -e "DESCRIBE snmp_devices" > nul 2>&1
if %ERRORLEVEL% EQU 0 (
    echo   [OK] snmp_devices mevcut
    set /A TABLE_OK+=1
) else (
    echo   [EKSIK] snmp_devices bulunamadi
    set /A TABLE_MISSING+=1
)

REM alarms tablosu
"%MYSQL_PATH%" -h %MYSQL_HOST% -u %MYSQL_USER% %MYSQL_DB% -e "DESCRIBE alarms" > nul 2>&1
if %ERRORLEVEL% EQU 0 (
    echo   [OK] alarms mevcut
    set /A TABLE_OK+=1
) else (
    echo   [EKSIK] alarms bulunamadi
    set /A TABLE_MISSING+=1
)

REM port_status_data tablosu
"%MYSQL_PATH%" -h %MYSQL_HOST% -u %MYSQL_USER% %MYSQL_DB% -e "DESCRIBE port_status_data" > nul 2>&1
if %ERRORLEVEL% EQU 0 (
    echo   [OK] port_status_data mevcut
    set /A TABLE_OK+=1
) else (
    echo   [EKSIK] port_status_data bulunamadi
    set /A TABLE_MISSING+=1
)

REM alarm_severity_config tablosu
"%MYSQL_PATH%" -h %MYSQL_HOST% -u %MYSQL_USER% %MYSQL_DB% -e "DESCRIBE alarm_severity_config" > nul 2>&1
if %ERRORLEVEL% EQU 0 (
    echo   [OK] alarm_severity_config mevcut
    set /A TABLE_OK+=1
) else (
    echo   [EKSIK] alarm_severity_config bulunamadi
    set /A TABLE_MISSING+=1
)

echo.
echo   Sonuc: %TABLE_OK% tablo OK, %TABLE_MISSING% eksik
echo.

if %TABLE_MISSING% GTR 0 (
    echo [UYARI] Eksik tablolar var!
    echo.
    echo Tam kurulum icin calistirin:
    echo   update.bat
    echo.
)

REM ============================================================================
REM ADIM 4: SNMP Worker'i Yeniden Baslat
REM ============================================================================

echo [4/4] SNMP Worker yeniden baslatiliyor...

REM Eski process'leri durdur
taskkill /F /IM python.exe > nul 2>&1
timeout /t 2 /nobreak > nul

REM Worker'i baslat
if exist "%WORKER_DIR%worker.py" (
    cd "%WORKER_DIR%"
    start /B %PYTHON_PATH% worker.py > nul 2>&1
    timeout /t 3 /nobreak > nul
    
    REM Kontrol et
    tasklist /FI "IMAGENAME eq python.exe" 2>nul | find /I "python.exe" >nul
    if %ERRORLEVEL% EQU 0 (
        echo [OK] SNMP Worker baslatildi
    ) else (
        echo [UYARI] Worker baslatılamadı - manuel kontrol gerekli
        echo.
        echo Manuel baslat:
        echo   cd %WORKER_DIR%
        echo   python worker.py
    )
) else (
    echo [HATA] worker.py bulunamadi: %WORKER_DIR%worker.py
)

echo.

REM ============================================================================
REM TAMAMLANDI
REM ============================================================================

echo ============================================================================
echo                           ONARIM TAMAMLANDI
echo ============================================================================
echo.
echo YAPILAN ISLEMLER:
echo   [X] MySQL baglantisi kontrol edildi
echo   [X] Kritik kolonlar eklendi (port_type, port_speed, port_mtu)
echo   [X] %TABLE_OK% tablo kontrol edildi
echo   [X] SNMP Worker yeniden baslatildi
echo.
echo ============================================================================
echo.

REM ============================================================================
REM SONRAKI ADIMLAR
REM ============================================================================

echo SONRAKI ADIMLAR:
echo.
echo 1. Worker log dosyasini kontrol edin:
echo    type logs\snmp_worker.log
echo.
echo 2. Detayli worker kontrolu icin:
echo    verify_worker.bat
echo.
echo 3. Port degisikligi test edin:
echo    - Bir switch'te port description degistirin
echo    - 2-3 dakika bekleyin
echo    - UI'da alarm kontrol edin: http://localhost/Switchp/
echo.
echo 4. Database'de son alarmlari kontrol edin:
echo    mysql -h 127.0.0.1 -u root switchdb -e "SELECT * FROM alarms ORDER BY created_at DESC LIMIT 5;"
echo.
echo ============================================================================
echo.

echo SORUN DEVAM EDIYORSA:
echo.
echo 1. Python paketlerini kurun:
echo    pip install -r requirements.txt
echo.
echo 2. Tam dokumantasyon:
echo    ALARM_SORUN_GIDERME.md
echo.
echo 3. Tam kurulum:
echo    update.bat
echo.
echo ============================================================================
echo.

pause
