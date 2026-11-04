@echo off
chcp 65001 >nul 2>nul
title Zhaniya Connector - Install Service
echo ============================================================
echo   Zhaniya Connector - Install as Windows Service (NSSM)
echo   نصب خودکار کانکتور به عنوان سرویس ویندوز
echo ============================================================
set DIR=%~dp0

if not exist "%DIR%config.json" (
  echo [X] فایل config.json پیدا نشد. ابتدا با 'python setup.py --connector-config' بسازید.
  pause & exit /b 1
)

where nssm >nul 2>nul
if errorlevel 1 (
  if exist "%DIR%nssm.exe" (
    set NSSM=%DIR%nssm.exe
  ) else (
    echo [X] nssm.exe پیدا نشد. از https://nssm.cc دانلود و در همین پوشه بگذارید.
    pause & exit /b 1
  )
) else (
  set NSSM=nssm
)

where python >nul 2>nul
if errorlevel 1 (
  echo [X] Python پیدا نشد. ابتدا Python 3.8+ را نصب کنید.
  pause & exit /b 1
)

echo [1/3] نصب سرویس ZhaniyaConnector ...
"%NSSM%" install ZhaniyaConnector python "%DIR%zhaniya_connector.py" --config "%DIR%config.json" >nul 2>nul
"%NSSM%" set ZhaniyaConnector AppDirectory "%DIR%"
"%NSSM%" set ZhaniyaConnector Start SERVICE_AUTO_START
"%NSSM%" set ZhaniyaConnector DisplayName "Zhaniya POS Connector"
"%NSSM%" set ZhaniyaConnector Description "ارسال داده‌های فروش ژانیا به سرور ابری (بدون نیاز به IP ثابت)"

echo [2/3] شروع سرویس ...
net start ZhaniyaConnector

echo [3/3] انجام شد! وضعیت: sc query ZhaniyaConnector
sc query ZhaniyaConnector | findstr STATE
echo.
echo برای حذف سرویس: %NSSM% remove ZhaniyaConnector confirm
pause
