@echo off
rem -----------------------------------------------------------------
rem  ZhaniyaTray - Manual step-by-step build
rem  Runs one step at a time and waits for a key press after each.
rem  The window never closes by itself.
rem -----------------------------------------------------------------
if not "%~1"=="--run" (
  cmd /k ""%~f0" --run"
  exit /b
)
setlocal EnableExtensions
chcp 65001 >nul 2>nul
title ZhaniyaTray - Manual Build
set "DIR=%~dp0"

echo ============================================================
echo    ZhaniyaTray - Manual step-by-step build
echo    Each step waits for you to press a key.
echo ============================================================

echo.
echo [STEP 1/6] Check Python ...
python --version
if errorlevel 1 goto :nopy
python -m pip --version
if errorlevel 1 goto :nopip
echo.
echo STEP 1 OK. Press any key to continue ...
pause >nul

echo.
echo [STEP 2/6] Upgrade pip ...
python -m pip install --upgrade pip
echo.
echo STEP 2 done. Press any key to continue ...
pause >nul

echo.
echo [STEP 3/6] Install packages: pystray, Pillow, PyInstaller ...
python -m pip install pystray pillow pyinstaller
if errorlevel 1 goto :pipfail
echo.
echo STEP 3 OK. Press any key to continue ...
pause >nul

echo.
echo [STEP 4/6] Make program icon ...
python "%DIR%make_icon.py"
if errorlevel 1 goto :fail
echo.
echo STEP 4 OK. Press any key to build the EXE ...
pause >nul

echo.
echo [STEP 5/6] Build ZhaniyaTray.exe (takes a few minutes) ...
python -m PyInstaller --noconfirm --onefile --windowed --name ZhaniyaTray --icon "%DIR%icon.ico" --distpath "%DIR%dist" --workpath "%DIR%build_tmp" --specpath "%DIR%build_tmp" "%DIR%tray_app.py"
if errorlevel 1 goto :fail
echo.
echo STEP 5 OK. Press any key to finish ...
pause >nul

echo.
echo [STEP 6/6] Copy needed files next to the EXE ...
copy /Y "%DIR%zhaniya_connector.py" "%DIR%dist\" >nul
copy /Y "%DIR%config.example.json" "%DIR%dist\" >nul
if exist "%DIR%config.json" copy /Y "%DIR%config.json" "%DIR%dist\" >nul

echo.
echo ============================================================
echo    [OK] DONE!  File created:
echo    %DIR%dist\ZhaniyaTray.exe
echo ------------------------------------------------------------
echo    Next: copy the "dist" folder somewhere safe, run the EXE,
echo    open Settings from the tray icon, set user/password.
echo ============================================================
explorer /select,"%DIR%dist\ZhaniyaTray.exe"
pause
exit /b 0

:nopy
echo.
echo [X] STEP 1 FAILED: Python not found.
echo     Install it from https://www.python.org/downloads/
echo     and TICK "Add python.exe to PATH" in the installer.
pause
exit /b 1

:nopip
echo.
echo [X] STEP 1 FAILED: pip not found. Try:
echo     python -m ensurepip --upgrade
pause
exit /b 1

:pipfail
echo.
echo [X] STEP 3 FAILED: could not install packages.
echo     Check internet / proxy and run this file again.
pause
exit /b 1

:fail
echo.
echo [X] Build step failed. Read the error above.
pause
exit /b 1
