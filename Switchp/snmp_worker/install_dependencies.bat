@echo off
REM ============================================================================
REM   SNMP Worker - Dependency Installation Script
REM ============================================================================
echo.
echo ============================================================================
echo   SNMP Worker - Bagimlilik Yukleyici
echo ============================================================================
echo.

REM Check if Python is installed
python --version >nul 2>&1
if errorlevel 1 (
    echo HATA: Python bulunamadi!
    echo Lutfen Python yukleyin: https://www.python.org/downloads/
    echo.
    pause
    exit /b 1
)

echo [1/3] Python kontrol ediliyor...
python --version
echo       OK
echo.

REM Check if pip is installed
pip --version >nul 2>&1
if errorlevel 1 (
    echo HATA: pip bulunamadi!
    echo pip genellikle Python ile birlikte gelir.
    echo.
    pause
    exit /b 1
)

echo [2/3] pip kontrol ediliyor...
pip --version
echo       OK
echo.

REM Install requirements
echo [3/3] Bagimliliklari yukleniyor...
echo.
pip install -r requirements.txt

if errorlevel 1 (
    echo.
    echo ============================================================================
    echo HATA: Bagimliliklari yukleme basarisiz oldu!
    echo ============================================================================
    echo.
    echo Lutfen hatalari kontrol edin ve tekrar deneyin.
    echo.
    echo Yardim icin:
    echo   - Internet baglantisini kontrol edin
    echo   - pip'i guncelleyin: python -m pip install --upgrade pip
    echo   - Yoneticisi olarak calistirin (Run as Administrator)
    echo.
    pause
    exit /b 1
)

echo.
echo ============================================================================
echo   TAMAMLANDI!
echo ============================================================================
echo.
echo Tum bagimlilliklar basariyla yuklendi.
echo.
echo Simdi worker'i calistirabilirsiniz:
echo   python worker.py
echo.
echo Veya dependency kontrolu yapabilirsiniz:
echo   python check_dependencies.py
echo.
echo ============================================================================
echo.
pause
