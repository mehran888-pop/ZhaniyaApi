@echo off
rem Zhaniya Cloud — Setup سریع (ویندوز)
python "%~dp0setup.py" %*
if errorlevel 1 py -3 "%~dp0setup.py" %*
pause
