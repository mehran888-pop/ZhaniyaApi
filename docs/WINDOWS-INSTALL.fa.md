# نصب روی ویندوز — کنار نرم‌افزار ژانیا و ZhaniyaApi 🖥️

> سناریو: یک رایانه‌ی ویندوزی که **نرم‌افزار ژانیا + SQL Server Express + ZhaniyaApi (IIS)**
> از قبل روی آن نصب است. می‌خواهیم داشبورد مدیریت و کانکتور را **همان‌جا** راه بیندازیم.

## نقشه‌ی نهایی — همه‌چیز روی یک سیستم

```
┌─────────────────────────── یک رایانه‌ی ویندوز ────────────────────────────┐
│                                                                          │
│   ✅ از قبل نصب است:                                                     │
│   ├── نرم‌افزار ژانیا ── SQL Server Express (.\SQLEXPRESS / ZhaniyaDB)   │
│   └── IIS ── ZhaniyaApi        →  http://localhost/ZhaniyaApi            │
│                                                                          │
│   🆕 ما نصب می‌کنیم:                                                      │
│   ├── PHP + داشبورد ژانیا کلود (در همان IIS)  →  http://localhost/ZhaniyaCloud
│   │   یا با XAMPP روی پورت ۸۰۸۰               →  http://localhost:8080/...
│   └── Zhaniya Connector (سرویس ویندوز)                                   │
│         از ZhaniyaApi/SQL می‌خواند → به داشبورد می‌فرستد                  │
└──────────────────────────────────────────────────────────────────────────┘
```

---

## گام ۱ — نصب PHP در کنار IIS (روش توصیه‌شده)

چون ZhaniyaApi روی **IIS** است، داشبورد PHP را هم داخل **همان IIS** می‌گذاریم؛
نه پورتی اشغال می‌شود، نه نرم‌افزار اضافه‌ای لازم است.

### ۱.۱ دانلود و نصب PHP

1. از <https://windows.php.net/download> → آخرین نسخه‌ی **VS16/VS17 x64 Non Thread Safe ZIP**
   (مثلاً PHP 8.3 یا 8.4) را دانلود کنید.
2. ZIP را در مسیر **`C:\PHP`** باز کنید (باید داخل آن `php-cgi.exe` دیده شود).
3. داخل `C:\PHP` فایل **`php.ini-production`** را کپی کنید و نام کپی را بگذارید **`php.ini`**.
4. فایل `php.ini` را با Notepad باز کنید و این خط‌ها را پیدا و **بدون `;`** کنید
   (یا اگر نبودند، اضافه کنید):

```ini
extension_dir = "ext"
extension=pdo_sqlite
extension=sqlite3
extension=mbstring
extension=pdo_mysql
extension=openssl
date.timezone = Asia/Tehran
```

### ۱.۲ اتصال PHP به IIS (FastCGI)

**روش آسان — با PHP Manager:**

1. **PHP Manager for IIS** را نصب کنید:
   <https://www.iis.net/downloads/community/2018/05/php-manager-150-for-iis-10>
2. IIS Manager را باز کنید (کلید Win + `inetmgr`) → روی نام سرور کلیک کنید → **PHP Manager**.
3. **«Register new PHP version»** → فایل `C:\PHP\php-cgi.exe` را انتخاب کنید.
   تمام! PHP Manager اکستنشن‌ها و FastCGI را خودش تنظیم می‌کند.

**روش دستی — بدون نصب چیز اضافه:**

1. IIS Manager → روی نام سرور → **Handler Mappings** → **Add Module Mapping...**
2. پر کنید:
   - Request path: `*.php`
   - Module: `FastCgiModule`
   - Executable: `C:\PHP\php-cgi.exe`
   - Name: `PHP`
3. OK → پیام «Create a FastCGI application» آمد → **Yes**.

### ۱.۳ ساخت برنامه‌ی داشبورد در IIS

1. کل پوشه‌ی **`cloud`** را از ZIP مخزن در مسیر **`C:\ZhaniyaCloud`** بگذارید
   (ساختار: `C:\ZhaniyaCloud\public`، `C:\ZhaniyaCloud\src` و بقیه‌ی فایل‌ها).
2. IIS Manager → **Sites → Default Web Site** → راست‌کلیک → **Add Application...**
   - Alias: **`ZhaniyaCloud`**
   - Physical path: **`C:\ZhaniyaCloud\public`**
3. OK.

### ۱.۴ ⚠️ مجوز نوشتن (مهم‌ترین قسمت در ویندوز!)

ویزارد نصب باید فایل `config.php` و پوشه‌ی `data` را بسازد؛ برای این کار IIS
باید مجوز نوشتن داشته باشد. در **CMD با دسترسی Administrator**:

```bat
icacls C:\ZhaniyaCloud /grant "IIS_IUSRS:(OI)(CI)M"
```

> بدون این دستور، نصب با خطای «نوشتن config.php ممکن نشد» یا
> «unable to open database file» شکست می‌خورد.

### ۱.۵ اجرا

مرورگر را باز کنید:

```
http://localhost/ZhaniyaCloud/setup.php
```

ویزارد ۴ مرحله‌ای را انجام دهید (SQLite را بگذارید بماند — برای یک شعبه کافی است).
تمام! داشبورد آماده است: `http://localhost/ZhaniyaCloud/login.php`

---

## گزینه‌ی ۲ — XAMPP روی پورت ۸۰۸۰ (اگر IIS دست‌کاری نمی‌خواهید)

⚠️ چون IIS پورت ۸۰ را گرفته، Apache باید روی پورت دیگری باشد:

1. XAMPP را از <https://www.apachefriends.org> نصب کنید.
2. فایل `C:\xampp\apache\conf\httpd.conf` را باز کنید:
   - `Listen 80` → تغییر به **`Listen 8080`**
   - `ServerName localhost:80` → **`ServerName localhost:8080`**
3. در XAMPP Control Panel → **Apache → Start** (اگر خطای پورت داد، یعنی تغییر بالا اعمال نشده).
4. کل پوشه‌ی `cloud` را در `C:\xampp\htdocs\zhaniya-cloud` کپی کنید.
5. مرورگر:

```
http://localhost:8080/zhaniya-cloud/public/setup.php
```

---

## گزینه‌ی ۳ — سبک‌ترین راه: سرور داخلی PHP به‌عنوان سرویس

نه IIS، نه Apache! فقط PHP + NSSM:

```bat
:: فرض: PHP در C:\PHP و داشبورد در C:\ZhaniyaCloud
:: nssm.exe را از nssm.cc دانلود و در C:\ZhaniyaCloud بگذارید
C:\ZhaniyaCloud\nssm.exe install ZhaniyaDashboard C:\PHP\php.exe "-S 0.0.0.0:8080 -t C:\ZhaniyaCloud\public"
C:\ZhaniyaCloud\nssm.exe set ZhaniyaDashboard Start SERVICE_AUTO_START
net start ZhaniyaDashboard
```

آدرس: `http://localhost:8080/setup.php` — مناسب استفاده‌ی داخلی/تک‌شعبه.

---

## گام ۲ — نصب کانکتور (در همان سیستم)

1. **Python** را از <https://www.python.org/downloads/> نصب کنید
   (موقع نصب تیک **Add python.exe to PATH** را حتماً بزنید).
2. پوشه‌ی **`connector`** را در `C:\ZhaniyaConnector` کپی کنید.
3. فایل **`config.json`** دانلودشده از ویزارد را در همان پوشه بگذارید و
   برای اتصال به ژانیا ویرایش کنید — نمونه‌ی کامل برای همین سناریو:

```json
{
  "mode": "api",
  "cloud_url": "http://localhost/ZhaniyaCloud",
  "ingest_path": "api/ingest.php",
  "health_path": "api/health.php",
  "device_key": "کلید-دستگاه-از-ویزارد",
  "device_secret": "رمز-دستگاه-از-ویزارد",
  "sync_interval_sec": 20,
  "heartbeat_sec": 120,
  "api": {
    "base_url": "http://127.0.0.1/ZhaniyaApi",
    "username": "کاربر-ژانیا",
    "password": "رمز-ژانیا"
  }
}
```

> اگر داشبورد را با گزینه‌ی ۲ (XAMPP) نصب کردید، `cloud_url` را
> `http://localhost:8080/zhaniya-cloud/public` بگذارید.
>
> به‌جای `mode=api` می‌توانید `mode=sqlserver` بگذارید و مستقیم از `ZhaniyaDB`
> بخوانید (نیازمند `pip install pyodbc` و نصب «Microsoft ODBC Driver for SQL Server»).
> برای شروع، `api` ساده‌تر است چون ZhaniyaApi خودش روی همان سیستم است.

4. تست:

```bat
cd C:\ZhaniyaConnector
python zhaniya_connector.py --config config.json
```

پیام «ارسال موفق» = اتصال برقرار است ✅ (Ctrl+C برای پایان تست)

5. نصب خودکار: **`install_service.bat`** را راست‌کلیک → **Run as administrator**.

---

## چک‌لیست نهایی ✅

| # | بررسی | آدرس/دستور |
|---|---|---|
| ۱ | ZhaniyaApi بالا است | `http://localhost/ZhaniyaApi/Help` (یا آدرس همیشگی خودش) |
| ۲ | ویزارد نصب سبز بود | `http://localhost/ZhaniyaCloud/setup.php` |
| ۳ | ورود به داشبورد | `http://localhost/ZhaniyaCloud/login.php` |
| ۴ | کانکتور وصل است | صفحه‌ی «دستگاه‌ها» ← وضعیت: **متصل** |
| ۵ | داده می‌آید | صفحه‌ی «گزارش لحظه‌ای» ← فاکتورهای جدید |

## دسترسی سایر سیستم‌ها (مدیر داخل مغازه/شبکه)

به‌جای `localhost` از IP همان کامپیوتر استفاده کنید (مثلاً `http://192.168.1.10/ZhaniyaCloud/login.php`).
اگر باز نشد، در فایروال ویندوز برای پورت مربوطه (۸۰ برای IIS یا ۸۰۸۰) قانون ورود بگذارید:

```bat
netsh advfirewall firewall add rule name="ZhaniyaCloud" dir=in action=allow protocol=TCP localport=80
```

## دسترسی مدیر از بیرون (از راه دور)

نکته‌ی مهم: «بدون IP ثابت» مربوط به **کانکتور** است (او داده را بیرون می‌فرستد و
مشکلی ندارد). اما برای **بازکردن داشبورد از بیرون** دو راه دارید:

1. **ساده‌ترین:** داشبورد را روی یک هاست/VPS نصب کنید (بخش ۲ راهنما) و بگذارید
   کانکتور همان فروشگاه به آن وصل شود — نیازی به هیچ تنظیم مودم نیست. ← توصیه‌شده
2. **روی همان سیستم:** با مودم‌های اینترنت ایران، IP ثابت معمولاً در دسترس نیست؛
   باید از «داینامیک DNS + پورت‌فوروارد مودم» استفاده کنید (کارشناس شبکه لازم دارد).

## عیب‌یابی مخصوص همین سناریو

| مشکل | راه‌حل |
|---|---|
| `HTTP Error 500.19` در IIS | ماژول CGI فعال نیست: «Turn Windows features on/off» → Internet Information Services → World Wide Web Services → Application Development Features → **CGI** را تیک بزنید |
| `could not find driver` | اکستنشن `pdo_sqlite` در `php.ini` فعال نشده؛ بعد از تغییر، IIS را ری‌استارت کنید: `iisreset` |
| `نوشتن config.php ممکن نشد` | دستور `icacls` بخش ۱.۴ را اجرا کنید |
| `unable to open database file` | مجوز نوشتن روی `C:\ZhaniyaCloud` (بخش ۱.۴) |
| صفحه‌ی ۴۰۴ در IIS | Alias و Physical path را چک کنید؛ فایل باید در `...\public\setup.php` باشد |
| تداخل پورت با IIS | گزینه‌ی ۲: پورت Apache را از ۸۰ به ۸۰۸۰ ببرید |
| کانکتور به ژانیا وصل نمی‌شود | `http://127.0.0.1/ZhaniyaApi/Help` را در مرورگر چک کنید؛ نام کاربری/رمز بخش `api` را کامل کنید |
| بعد از تغییر php.ini اثر نکرد | `iisreset` (در CMD ادمین) یا Restart سرویس Apache |
