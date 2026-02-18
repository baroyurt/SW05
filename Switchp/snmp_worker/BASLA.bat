@echo off
REM ============================================================
REM   SNMP Worker - Basit Başlatıcı
REM   Tek tıkla worker'ı başlatır
REM ============================================================

echo.
echo ============================================================
echo   SNMP Worker Başlatılıyor...
echo ============================================================
echo.

REM Virtual environment'in var olup olmadığını kontrol et
if not exist "venv" (
    echo [HATA] Virtual environment bulunamadı!
    echo.
    echo Lütfen önce kurulum yapın:
    echo   1. python -m venv venv
    echo   2. venv\Scripts\activate
    echo   3. pip install -r requirements.txt
    echo.
    pause
    exit /b 1
)

REM Virtual environment'i aktif et
echo [1/2] Virtual environment aktif ediliyor...
call venv\Scripts\activate
if %ERRORLEVEL% NEQ 0 (
    echo [HATA] Virtual environment aktif edilemedi!
    pause
    exit /b 1
)
echo   OK
echo.

REM Worker'ı başlat
echo [2/2] Worker başlatılıyor...
echo.
echo ============================================================
echo   Worker çalışıyor - durdurmak için Ctrl+C basın
echo ============================================================
echo.

python worker.py

REM Hata kontrolü
if %ERRORLEVEL% NEQ 0 (
    echo.
    echo ============================================================
    echo   HATA: Worker başlatılamadı!
    echo ============================================================
    echo.
    echo Olası çözümler:
    echo   1. Paketleri kontrol edin: pip install -r requirements.txt
    echo   2. Config dosyasını kontrol edin: config\config.yml
    echo   3. MySQL'in çalıştığını kontrol edin
    echo   4. Log dosyasını kontrol edin: logs\snmp_worker.log
    echo.
    pause
)
