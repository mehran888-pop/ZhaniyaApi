@echo off
rem -----------------------------------------------------------------
rem  ZhaniyaTray - Build EXE
rem  (window stays open so you can always read the messages)
rem -----------------------------------------------------------------
if not "%~1"=="--run" (
  cmd /k ""%~f0" --run"
  exit /b
)
setlocal EnableExtensions
chcp 65001 >nul 2>nul
title ZhaniyaTray - Build EXE
echo ============================================================
echo    Build ZhaniyaTray.exe  (Windows system-tray app)
echo    Please keep this window open until it finishes.
echo ============================================================
set "DIR=%~dp0"

echo.
echo [1/5] Checking Python ...
set "PY="
python -c "import sys" >nul 2>nul
if not errorlevel 1 set "PY=python"
if defined PY goto :havepy
py -3 -c "import sys" >nul 2>nul
if not errorlevel 1 set "PY=py -3"
if not defined PY goto :nopy
:havepy
%PY% -c "import sys; print('  Found Python', sys.version.split()[0])"

echo.
echo [2/5] Installing packages: pystray, Pillow, PyInstaller ...
%PY% -m pip install --upgrade pip >nul 2>nul
%PY% -m pip install pystray pillow pyinstaller
if errorlevel 1 goto :pipfail

echo.
echo [3/5] Making program icon ...
%PY% "%DIR%make_icon.py"
if errorlevel 1 goto :fail

echo.
echo [4/5] Building EXE with PyInstaller (this can take a few minutes) ...
%PY% -m PyInstaller --noconfirm --onefile --windowed --name ZhaniyaTray --icon "%DIR%icon.ico" --distpath "%DIR%dist" --workpath "%DIR%build_tmp" --specpath "%DIR%build_tmp" "%DIR%tray_app.py"
if errorlevel 1 goto :fail

echo.
echo [5/5] Finalizing output folder ...
copy /Y "%DIR%zhaniya_connector.py" "%DIR%dist\" >nul
copy /Y "%DIR%config.example.json" "%DIR%dist\" >nul
if exist "%DIR%config.json" copy /Y "%DIR%config.json" "%DIR%dist\" >nul

echo.
echo ============================================================
echo    [OK] Done!  File created:
echo    %DIR%dist\ZhaniyaTray.exe
echo ------------------------------------------------------------
echo    Next steps:
echo    1. Copy the whole "dist" folder anywhere, e.g. C:\ZhaniyaTray
echo    2. Run ZhaniyaTray.exe - icon appears near the Windows clock
echo    3. Right-click icon - Settings - choose username and password
echo    4. Tick "Run with Windows" so it starts automatically
echo ============================================================
explorer /select,"%DIR%dist\ZhaniyaTray.exe"
pause
exit /b 0

:nopy
echo.
echo [X] Python was not found on this computer.
echo     1. Install Python from https://www.python.org/downloads/
echo     2. IMPORTANT: tick "Add python.exe to PATH" in the installer.
echo     3. Run this file again.
echo     (The Microsoft Store "python" stub does NOT count as Python.)
pause
exit /b 1

:pipfail
echo.
echo [X] Installing packages failed (pip error above).
echo     - Check internet connection / proxy, then run this file again.
echo     - Or run manually:  python -m pip install pystray pillow pyinstaller
pause
exit /b 1

:fail
echo.
echo [X] Build failed. Read the error messages above.
echo     If you need help, send a screenshot of this window.
pause
exit /b 1
