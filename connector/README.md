# Zhaniya Connector — راهنمای نصب روی سیستم فروشگاه

این برنامه پل ارتباطی بین نرم‌افزار ژانیا و سرور ابری (داشبورد مدیریت) است
و **نیازی به IP ثابت ندارد**؛ اتصال همیشه از سمت فروشگاه به بیرون برقرار می‌شود.

## ⭐ راه آسان — برنامه‌ی ویندوزی با آیکون کنار ساعت (ZhaniyaTray)

اگر می‌خواهید همه‌چیز گرافیکی و خودکار باشد، به‌جای اجرای دستی:

1. **یک‌بار** فایل `build_exe.bat` را اجرا کنید (Python باید نصب باشد).
   پنجره‌ی سیاه باز می‌ماند و مراحل را نشان می‌دهد (خودکار بسته نمی‌شود).
   فایل اجرایی `ZhaniyaTray.exe` در پوشه‌ی `dist` ساخته و پوشه باز می‌شود.
   - اگر پیام `Python was not found` دیدید: Python را از python.org نصب کنید و
     حتماً تیک **Add python.exe to PATH** را بزنید (اپلیکیشن Microsoft Store کافی نیست).
   - اگر نصب بسته‌ها (pip) خط داد: اینترنت/پروکسی را بررسی و دوباره اجرا کنید.
2. کل پوشه‌ی `dist` را مثلاً در `C:\ZhaniyaTray` بگذارید و `ZhaniyaTray.exe` را اجرا کنید.
3. آیکون برنامه **کنار ساعت ویندوز** ظاهر می‌شود؛ راست‌کلیک → **تنظیمات**:
   - اولین بار: نام کاربری و رمز دلخواه برای ورود به داشبورد تنظیمات بسازید.
   - تب‌ها: عمومی / اتصال ابری / اتصال به ژانیا / داشبورد وب.
   - تیک **«اجرا همراه با ویندوز»** را بزنید — دفعه‌ی بعد خودش بالا می‌آید.
4. در تب «داشبورد وب»، نام کاربری/رمز داشبورد مدیریت را ذخیره کنید؛
   منوی «باز کردن داشبورد وب» آن را در مرورگر باز و رمز را در کلیپ‌بورد کپی می‌کند.

> فایل `config.json` (تنظیمات کانکتور) و `tray_settings.json` (تنظیمات برنامه)
> کنار خودِ `ZhaniyaTray.exe` ساخته و نگهداری می‌شوند.

## اجرای دستی (بدون فایل اجرایی)

1. پایتون ۳.۸+ را روی همان رایانه‌ای که ژانیا نصب است نصب کنید.
2. فایل `config.example.json` را با نام `config.json` کپی کنید و ویرایش کنید:
   - `cloud_url`: آدرس سرور ابری (مثلاً `https://pos.example.com`)
   - `device_key` / `device_secret`: کلیدهای دستگاه که هنگام ثبت در جدول `devices`
     سرور ابری تعیین کرده‌اید.
   - `mode`:
     - `api` — فراخوانی REST همان ZhaniyaApi روی لوکال (`http://127.0.0.1/ZhaniyaApi`)
     - `sqlserver` — خواندن مستقیم دیتابیس `ZhaniyaDB` (نیازمند `pip install pyodbc`)
     - `demo` — داده‌ی آزمایشی برای تست زنجیره‌ی اتصال
3. (فقط حالت api) نام کاربری/رمز یک کاربر ژانیا را وارد کنید. برای تطبیق نام فیلدها
   به HelpPage خود API مراجعه کنید و در صورت نیاز `api_map` را تنظیم کنید.

## ۲. اجرای دستی (تست)

```bat
python zhaniya_connector.py --config config.json
```

برای یک اجرای آزمایشی:

```bat
python zhaniya_connector.py --config config.json --once
```

اگر اینترنت قطع باشد، بسته‌ها در `connector_outbox.sqlite` ذخیره و پس از
برقراری اتصال به‌صورت خودکار ارسال می‌شوند.

## ۳. نصب به‌عنوان سرویس ویندوز (NSSM)

1. [NSSM](https://nssm.cc) را دانلود و در مسیری مثل `C:\Tools\nssm` قرار دهید.
2. در CMD با دسترسی Administrator:

```bat
C:\Tools\nssm\nssm.exe install ZhaniyaConnector "C:\Python311\python.exe" "C:\ZhaniyaConnector\zhaniya_connector.py --config C:\ZhaniyaConnector\config.json"
C:\Tools\nssm\nssm.exe set ZhaniyaConnector AppDirectory C:\ZhaniyaConnector
C:\Tools\nssm\nssm.exe set ZhaniyaConnector Start SERVICE_AUTO_START
C:\Tools\nssm\nssm.exe start ZhaniyaConnector
```

با این روش، کانکتور بعد از هر ری‌استارت ویندوز به‌صورت خودکار بالا می‌آید.

> روش جایگزین بدون NSSM: Task Scheduler → Create Task → «At startup» →
> action = اجرای فرمان بالا (با گزینه‌ی Hidden).

## ۴. عیب‌یابی

| مشکل | راه‌حل |
|---|---|
| `403 دستگاه ناشناخته` | `device_key`/`device_secret` با جدول `devices` سرور یکسان نیست |
| `408 اختلاف ساعت` | ساعت سیستم فروشگاه را با اینترنت همگام کنید (Windows Time) |
| `401 امضای نامعتبر` | `device_secret` اشتباه است یا متن بدنه توسط پراکسی تغییر کرده |
| ارسال نمی‌شود | ابتدا `python zhaniya_connector.py --once` را اجرا و لاگ را ببینید |
