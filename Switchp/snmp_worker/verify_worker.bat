@echo off
REM ============================================================================
REM SNMP Worker Verification Script
REM Purpose: Check if SNMP worker is running and functioning correctly
REM ============================================================================

setlocal enabledelayedexpansion

echo ============================================================================
echo            SNMP WORKER VERIFICATION
echo ============================================================================
echo.

REM Configuration
set PYTHON_PATH=python
set WORKER_DIR=%~dp0
set LOG_DIR=%WORKER_DIR%logs
set LATEST_LOG=%LOG_DIR%\snmp_worker.log

echo [1/5] Checking if Python is available...
%PYTHON_PATH% --version >nul 2>&1
if %ERRORLEVEL% EQU 0 (
    echo [OK] Python found
    %PYTHON_PATH% --version
) else (
    echo [WARNING] Python not found in PATH
    echo.
    echo Python alternatives to check:
    echo   - py --version
    echo   - C:\Python3\python.exe --version
    echo   - C:\Python39\python.exe --version
    echo.
)

echo.
echo [2/5] Checking if SNMP worker process is running...
tasklist /FI "IMAGENAME eq python.exe" /FI "WINDOWTITLE eq worker.py" 2>nul | find /I "python.exe" >nul
if %ERRORLEVEL% EQU 0 (
    echo [OK] Python process found running
    tasklist /FI "IMAGENAME eq python.exe" | find /I "python.exe"
) else (
    echo [WARNING] No Python worker process found
    echo.
    echo Checking all python processes:
    tasklist /FI "IMAGENAME eq python.exe" 2>nul
    if %ERRORLEVEL% NEQ 0 (
        echo [INFO] No python.exe processes running
    )
)

echo.
echo [3/5] Checking log directory...
if exist "%LOG_DIR%" (
    echo [OK] Log directory exists: %LOG_DIR%
    
    if exist "%LATEST_LOG%" (
        echo [OK] Worker log file exists
        echo.
        echo Last 20 lines of worker log:
        echo ----------------------------------------
        powershell -Command "Get-Content '%LATEST_LOG%' -Tail 20 -ErrorAction SilentlyContinue"
        echo ----------------------------------------
    ) else (
        echo [WARNING] Worker log file not found: %LATEST_LOG%
    )
) else (
    echo [ERROR] Log directory not found: %LOG_DIR%
)

echo.
echo [4/5] Checking required Python packages...
if exist "%WORKER_DIR%requirements.txt" (
    echo [OK] requirements.txt found
    echo.
    echo Checking if packages are installed:
    %PYTHON_PATH% -c "import sqlalchemy; print('SQLAlchemy:', sqlalchemy.__version__)" 2>nul
    if %ERRORLEVEL% EQU 0 (
        echo [OK] sqlalchemy installed
    ) else (
        echo [ERROR] sqlalchemy NOT installed
    )
    
    %PYTHON_PATH% -c "import pymysql; print('PyMySQL:', pymysql.__version__)" 2>nul
    if %ERRORLEVEL% EQU 0 (
        echo [OK] pymysql installed
    ) else (
        echo [ERROR] pymysql NOT installed
    )
    
    %PYTHON_PATH% -c "import pysnmp; print('PySNMP installed')" 2>nul
    if %ERRORLEVEL% EQU 0 (
        echo [OK] pysnmp installed
    ) else (
        echo [ERROR] pysnmp NOT installed
    )
) else (
    echo [WARNING] requirements.txt not found
)

echo.
echo [5/5] Checking database connectivity...
mysql -h 127.0.0.1 -u root -e "USE switchdb; SELECT COUNT(*) AS device_count FROM snmp_devices WHERE enabled=1;" 2>nul
if %ERRORLEVEL% EQU 0 (
    echo [OK] Database accessible and snmp_devices table exists
) else (
    echo [ERROR] Cannot access database or table missing
)

echo.
echo ============================================================================
echo                         VERIFICATION COMPLETE
echo ============================================================================
echo.

echo NEXT STEPS:
echo.
echo If worker is NOT running:
echo   1. Install Python packages: pip install -r requirements.txt
echo   2. Start worker: python worker.py
echo   3. Check logs: tail -f logs\snmp_worker.log
echo.
echo If worker IS running but not polling:
echo   1. Check logs for errors
echo   2. Verify database columns exist (run add_port_config_columns.sql)
echo   3. Restart worker
echo.
echo If worker is polling but no alarms:
echo   1. Check alarm_severity_config table
echo   2. Verify notification settings
echo   3. Manually trigger a change and monitor logs
echo.

pause
