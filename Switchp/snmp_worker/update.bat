@echo off
:: ============================================================================
:: SWITCH MANAGEMENT SYSTEM - AUTOMATED UPDATE SCRIPT
:: ============================================================================
:: Bu script tüm database migration'larını ve konfigürasyonları otomatik uygular
:: Kullanım: update.bat
:: ============================================================================

SETLOCAL EnableDelayedExpansion
COLOR 0A
TITLE Switch Management System - Otomatik Güncelleme

echo.
echo ============================================================================
echo            SWITCH MANAGEMENT SYSTEM - OTOMATIK GUNCELLEME
echo ============================================================================
echo.
echo Bu script asagidaki islemleri otomatik olarak yapacak:
echo   - Database backup olusturma
echo   - Tum SQL migration'lari uygulama
echo   - Tum Python migration'lari calistirma
echo   - SNMP Worker'i yeniden baslat
echo.
echo ============================================================================
echo.

:: ============================================================================
:: KONFIGÜRASYON
:: ============================================================================

:: MySQL Ayarları
SET MYSQL_HOST=127.0.0.1
SET MYSQL_USER=root
SET MYSQL_PASSWORD=
SET MYSQL_DB=switchdb
SET MYSQL_PATH=C:\xampp\mysql\bin\mysql.exe
SET MYSQLDUMP_PATH=C:\xampp\mysql\bin\mysqldump.exe

:: Python Ayarları  
SET PYTHON_PATH=python
SET WORKER_DIR=%~dp0
SET MIGRATIONS_DIR=%WORKER_DIR%migrations

:: Backup Dizini
SET BACKUP_DIR=%WORKER_DIR%backups
IF NOT EXIST "%BACKUP_DIR%" mkdir "%BACKUP_DIR%"

:: Log Dizini
SET LOG_DIR=%WORKER_DIR%logs
IF NOT EXIST "%LOG_DIR%" mkdir "%LOG_DIR%"

:: Tarih/Zaman için timestamp
FOR /f "tokens=2-4 delims=/ " %%a IN ('date /t') DO (SET DATE=%%c%%b%%a)
FOR /f "tokens=1-2 delims=/:" %%a IN ("%TIME%") DO (SET TIME=%%a%%b)
SET TIMESTAMP=%DATE%_%TIME: =0%
SET LOG_FILE=%LOG_DIR%\update_%TIMESTAMP%.log

:: ============================================================================
:: LOGGING FUNCTION
:: ============================================================================

echo [%DATE% %TIME%] Update script baslatildi > "%LOG_FILE%"

:: ============================================================================
:: ADIM 1: MYSQL BAGLANTISINISINI KONTROL ET
:: ============================================================================

echo [1/6] MySQL baglantisi kontrol ediliyor...
echo [%DATE% %TIME%] MySQL baglantisi kontrol ediliyor >> "%LOG_FILE%"

"%MYSQL_PATH%" -h %MYSQL_HOST% -u %MYSQL_USER% -e "SELECT 1" > nul 2>&1
IF %ERRORLEVEL% NEQ 0 (
    echo [HATA] MySQL'e baglanilamiyor!
    echo [HATA] Lutfen asagidakileri kontrol edin:
    echo   - XAMPP'in calistigindan emin olun
    echo   - MySQL ayarlarini kontrol edin: %MYSQL_HOST% / %MYSQL_USER%
    echo   - MySQL path: %MYSQL_PATH%
    echo.
    echo [%DATE% %TIME%] [HATA] MySQL baglantisi basarisiz >> "%LOG_FILE%"
    pause
    exit /b 1
)

echo [OK] MySQL baglantisi basarili
echo [%DATE% %TIME%] [OK] MySQL baglantisi basarili >> "%LOG_FILE%"
echo.

:: ============================================================================
:: ADIM 2: DATABASE BACKUP OLUSTUR
:: ============================================================================

echo [2/6] Database backup olusturuluyor...
echo [%DATE% %TIME%] Database backup olusturuluyor >> "%LOG_FILE%"

SET BACKUP_FILE=%BACKUP_DIR%\%MYSQL_DB%_backup_%TIMESTAMP%.sql

"%MYSQLDUMP_PATH%" -h %MYSQL_HOST% -u %MYSQL_USER% %MYSQL_DB% > "%BACKUP_FILE%" 2>> "%LOG_FILE%"
IF %ERRORLEVEL% NEQ 0 (
    echo [HATA] Backup olusturulamadi!
    echo [%DATE% %TIME%] [HATA] Backup basarisiz >> "%LOG_FILE%"
    pause
    exit /b 1
)

echo [OK] Backup olusturuldu: %BACKUP_FILE%
echo [%DATE% %TIME%] [OK] Backup: %BACKUP_FILE% >> "%LOG_FILE%"
echo.

:: ============================================================================
:: ADIM 3: SQL MIGRATION'LARINI UYGULA
:: ============================================================================

echo [3/6] SQL migration'lari uygulanıyor...
echo [%DATE% %TIME%] SQL migration'lari uygulanıyor >> "%LOG_FILE%"

:: Migration sırası - önemli!
SET SQL_MIGRATIONS[0]=create_alarm_severity_config.sql
SET SQL_MIGRATIONS[1]=add_mac_tracking_tables.sql
SET SQL_MIGRATIONS[2]=add_acknowledged_port_mac_table.sql
SET SQL_MIGRATIONS[3]=add_port_config_columns.sql
SET SQL_MIGRATIONS[4]=create_switch_change_log_view.sql
SET SQL_MIGRATIONS[5]=mac_device_import.sql
SET SQL_MIGRATIONS[6]=fix_status_enum_uppercase.sql
SET SQL_MIGRATIONS[7]=fix_alarms_status_enum_uppercase.sql
SET SQL_MIGRATIONS[8]=enable_description_change_notifications.sql

SET SQL_COUNT=0
FOR /L %%i IN (0,1,8) DO (
    IF DEFINED SQL_MIGRATIONS[%%i] (
        SET SQL_FILE=!SQL_MIGRATIONS[%%i]!
        IF EXIST "%MIGRATIONS_DIR%\!SQL_FILE!" (
            echo   - Uygulanıyor: !SQL_FILE!
            echo [%DATE% %TIME%]   Uygulanıyor: !SQL_FILE! >> "%LOG_FILE%"
            
            "%MYSQL_PATH%" -h %MYSQL_HOST% -u %MYSQL_USER% %MYSQL_DB% < "%MIGRATIONS_DIR%\!SQL_FILE!" 2>> "%LOG_FILE%"
            IF !ERRORLEVEL! NEQ 0 (
                echo   [UYARI] !SQL_FILE! hatali - devam ediliyor...
                echo [%DATE% %TIME%]   [UYARI] !SQL_FILE! hatali >> "%LOG_FILE%"
            ) ELSE (
                SET /A SQL_COUNT+=1
                echo   [OK] !SQL_FILE! basarili
                echo [%DATE% %TIME%]   [OK] !SQL_FILE! basarili >> "%LOG_FILE%"
            )
        ) ELSE (
            echo   [UYARI] Dosya bulunamadi: !SQL_FILE!
            echo [%DATE% %TIME%]   [UYARI] Dosya bulunamadi: !SQL_FILE! >> "%LOG_FILE%"
        )
    )
)

echo [OK] %SQL_COUNT% SQL migration uygulandi
echo [%DATE% %TIME%] [OK] %SQL_COUNT% SQL migration uygulandi >> "%LOG_FILE%"
echo.

:: ============================================================================
:: ADIM 4: PYTHON MIGRATION'LARINI CALISTIR
:: ============================================================================

echo [4/6] Python migration'lari calistiriliyor...
echo [%DATE% %TIME%] Python migration'lari calistiriliyor >> "%LOG_FILE%"

:: Python varlığını kontrol et
%PYTHON_PATH% --version > nul 2>&1
IF %ERRORLEVEL% NEQ 0 (
    echo [UYARI] Python bulunamadi - Python migration'lar atlanıyor
    echo [%DATE% %TIME%] [UYARI] Python bulunamadi >> "%LOG_FILE%"
    GOTO :SKIP_PYTHON
)

:: Migration sırası
SET PY_MIGRATIONS[0]=create_tables.py
SET PY_MIGRATIONS[1]=add_snmp_v3_columns.py
SET PY_MIGRATIONS[2]=add_system_info_columns.py
SET PY_MIGRATIONS[3]=add_engine_id.py
SET PY_MIGRATIONS[4]=add_polling_data_columns.py
SET PY_MIGRATIONS[5]=add_port_config_columns.py
SET PY_MIGRATIONS[6]=add_alarm_notification_columns.py
SET PY_MIGRATIONS[7]=fix_status_enum_uppercase.py

SET PY_COUNT=0
FOR /L %%i IN (0,1,7) DO (
    IF DEFINED PY_MIGRATIONS[%%i] (
        SET PY_FILE=!PY_MIGRATIONS[%%i]!
        IF EXIST "%MIGRATIONS_DIR%\!PY_FILE!" (
            echo   - Calistiriliyor: !PY_FILE!
            echo [%DATE% %TIME%]   Calistiriliyor: !PY_FILE! >> "%LOG_FILE%"
            
            cd "%MIGRATIONS_DIR%"
            %PYTHON_PATH% "!PY_FILE!" >> "%LOG_FILE%" 2>&1
            IF !ERRORLEVEL! NEQ 0 (
                echo   [UYARI] !PY_FILE! hatali - devam ediliyor...
                echo [%DATE% %TIME%]   [UYARI] !PY_FILE! hatali >> "%LOG_FILE%"
            ) ELSE (
                SET /A PY_COUNT+=1
                echo   [OK] !PY_FILE! basarili
                echo [%DATE% %TIME%]   [OK] !PY_FILE! basarili >> "%LOG_FILE%"
            )
            cd "%WORKER_DIR%"
        ) ELSE (
            echo   [UYARI] Dosya bulunamadi: !PY_FILE!
            echo [%DATE% %TIME%]   [UYARI] Dosya bulunamadi: !PY_FILE! >> "%LOG_FILE%"
        )
    )
)

echo [OK] %PY_COUNT% Python migration calistirildi
echo [%DATE% %TIME%] [OK] %PY_COUNT% Python migration calistirildi >> "%LOG_FILE%"

:SKIP_PYTHON
echo.

:: ============================================================================
:: ADIM 5: SNMP WORKER'I YENIDEN BASLAT
:: ============================================================================

echo [5/6] SNMP Worker yeniden baslatiliyor...
echo [%DATE% %TIME%] SNMP Worker yeniden baslatiliyor >> "%LOG_FILE%"

:: Eski worker process'lerini durdur
taskkill /F /IM python.exe /FI "WINDOWTITLE eq worker.py*" > nul 2>&1
timeout /t 2 /nobreak > nul

:: Worker'i başlat (arka planda)
IF EXIST "%WORKER_DIR%worker.py" (
    cd "%WORKER_DIR%"
    start /B %PYTHON_PATH% worker.py >> "%LOG_FILE%" 2>&1
    echo [OK] SNMP Worker baslatildi
    echo [%DATE% %TIME%] [OK] SNMP Worker baslatildi >> "%LOG_FILE%"
) ELSE (
    echo [UYARI] worker.py bulunamadi - manuel baslatmaniz gerekebilir
    echo [%DATE% %TIME%] [UYARI] worker.py bulunamadi >> "%LOG_FILE%"
)

echo.

:: ============================================================================
:: ADIM 6: VERITABANI DURUMUNU KONTROL ET
:: ============================================================================

echo [6/6] Veritabani durumu kontrol ediliyor...
echo [%DATE% %TIME%] Veritabani durumu kontrol ediliyor >> "%LOG_FILE%"

:: Önemli tabloları kontrol et (snmp_devices yerine switches kullaniliyor)
SET TABLES[0]=snmp_devices
SET TABLES[1]=alarms
SET TABLES[2]=port_status_data
SET TABLES[3]=acknowledged_port_mac
SET TABLES[4]=alarm_severity_config
SET TABLES[5]=port_change_history
SET TABLES[6]=mac_address_tracking

SET TABLE_OK=0
SET TABLE_MISSING=0

FOR /L %%i IN (0,1,6) DO (
    IF DEFINED TABLES[%%i] (
        SET TABLE_NAME=!TABLES[%%i]!
        "%MYSQL_PATH%" -h %MYSQL_HOST% -u %MYSQL_USER% %MYSQL_DB% -e "DESCRIBE !TABLE_NAME!" > nul 2>&1
        IF !ERRORLEVEL! EQU 0 (
            SET /A TABLE_OK+=1
            echo   [OK] !TABLE_NAME! mevcut
        ) ELSE (
            SET /A TABLE_MISSING+=1
            echo   [EKSIK] !TABLE_NAME! bulunamadi
        )
    )
)

echo.
echo [OK] %TABLE_OK% tablo kontrol edildi, %TABLE_MISSING% eksik
echo [%DATE% %TIME%] [OK] %TABLE_OK% tablo kontrol edildi, %TABLE_MISSING% eksik >> "%LOG_FILE%"
echo.

:: ============================================================================
:: TAMAMLANDI
:: ============================================================================

echo ============================================================================
echo                            GUNCELLEME TAMAMLANDI!
echo ============================================================================
echo.
echo OZET:
echo   - Backup: %BACKUP_FILE%
echo   - SQL Migration: %SQL_COUNT% uygulandi
echo   - Python Migration: %PY_COUNT% calistirildi
echo   - SNMP Worker: Yeniden baslatildi
echo   - Tablolar: %TABLE_OK% OK, %TABLE_MISSING% eksik
echo.
echo Log dosyasi: %LOG_FILE%
echo.
echo ============================================================================
echo.
echo Sonraki adimlar:
echo   1. Web tarayicinizda http://localhost/Switchp/ adresini acin
echo   2. Giris yapin
echo   3. "Port Degisiklik Alarmlari" sayfasini kontrol edin
echo   4. Alarm bildirimlerinin calistigini dogrulayin
echo.
echo Sorun yasarsaniz:
echo   - Log dosyasini inceleyin: %LOG_FILE%
echo   - Backup'i geri yukleyin: %BACKUP_FILE%
echo   - XAMPP'in calistigindan emin olun
echo.
echo ============================================================================
echo.

echo [%DATE% %TIME%] Update script tamamlandi >> "%LOG_FILE%"

pause
exit /b 0
