@echo off
chcp 65001 >nul 2>nul
title ZhaniyaTray - Build EXE | ساخت فایل اجرایی
echo ============================================================
echo   ساخت فایل اجرایی ZhaniyaTray.exe (برنامه سینی ویندوز)
echo ============================================================
set DIR=%~dp0

where python >nul 2>nul
if errorlevel 1 (
  where py >nul 2>nul
  if errorlevel 1 (
    echo [X] Python پیدا نشد. ابتدا Python را از python.org نصب کنید.
    echo     موقع نصب تیک "Add python.exe to PATH" را بزنید.
    pause & exit /b 1
  )
  set PY=py -3
) else (
  set PY=python
)

echo [1/4] نصب وابستگی‌ها (pystray, Pillow, PyInstaller) ...
%PY% -m pip install --upgrade pip >nul 2>nul
%PY% -m pip install pystray pillow pyinstaller
if errorlevel 1 ( echo [X] نصب وابستگی ناموفق بود & pause & exit /b 1 )

echo [2/4] ساخت آیکون برنامه ...
%PY% "%DIR%make_icon.py"

echo [3/4] ساخت فایل اجرایی (چند دقیقه طول می‌کشد) ...
%PY% -m PyInstaller --noconfirm --onefile --windowed ^
  --name ZhaniyaTray ^
  --icon "%DIR%icon.ico" ^
  --distpath "%DIR%dist" ^
  --workpath "%DIR%build_tmp" ^
  --specpath "%DIR%build_tmp" ^
  "%DIR%tray_app.py"
if errorlevel 1 ( echo [X] ساخت ناموفق بود & pause & exit /b 1 )

echo [4/4] آماده‌سازی پوشه‌ی نهایی ...
copy /Y "%DIR%zhaniya_connector.py" "%DIR%dist\" >nul
copy /Y "%DIR%config.example.json" "%DIR%dist\" >nul
if exist "%DIR%config.json" copy /Y "%DIR%config.json" "%DIR%dist\" >nul

echo.
echo ============================================================
echo   ✅ ساخته شد:  %DIR%dist\ZhaniyaTray.exe
echo ------------------------------------------------------------
echo   راهنمایی:
echo   1. کل پوشه dist را در هر جای ویندوز بگذارید (مثلاً C:\ZhaniyaTray)
echo   2. ZhaniyaTray.exe را اجرا کنید — آیکون کنار ساعت ظاهر می‌شود
echo   3. روی آیکون کلیک راست -^> تنظیمات -^> نام کاربری/رمز بسازید
echo   4. تیک "اجرا همراه با ویندوز" را بزنید
echo ============================================================
pause
