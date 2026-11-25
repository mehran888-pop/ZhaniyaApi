# zhaniya-cli — راهنمای API زمانیا

ابزار خط فرمان (CLI) نوشته‌شده با **Node.js** که تمام endpoint های پروژهٔ `ZhaniyaApi` را فهرست می‌کند و استفادهٔ قدم‌به‌قدم از آن‌ها را به کاربر نشان می‌دهد.

## پیش‌نیاز

- Node.js نسخه ۱۸ به بالا (بدون وابستگی خارجی)

## اجرا

```bash
node zhaniya-cli.js help          # نمایش راهنما
node zhaniya-cli.js list          # فهرست همهٔ ۹۳ endpoint
node zhaniya-cli.js list Product  # فهرست کنترلرهای حاوی «Product»
node zhaniya-cli.js routes        # گروه‌بندی بر اساس مسیر
node zhaniya-cli.js show User/Login   # جزئیات یک endpoint
node zhaniya-cli.js search login      # جستجو
node zhaniya-cli.js curl User/Login --base http://localhost:5200
node zhaniya-cli.js guide          # راهنمای تعاملی قدم‌به‌قدم ★
```

## دستور `guide` (راهنمای تعاملی)

1. **انتخاب کنترلر** از فهرست ۱۶ کنترلر
2. **انتخاب action** همراه با متد HTTP و مسیر
3. نمایش **کامل جزئیات**: مسیر، متدها، پارامترها (نام و نوع)، خروجی، احراز هویت، نمونهٔ `curl` و نکات استفاده (مثل نیاز به توکن JWT)

در هر مرحله می‌توانید شماره یا بخشی از نام را تایپ کنید؛ `q` برای خروج.

## چه چیزی استخراج شده؟

از فایل `bin/ZhaniyaApi.dll` (بدون کد مبدأ):

| مورد | وضعیت |
|---|---|
| ۱۶ کنترلر، ۹۳ action | ✅ کامل |
| متدهای HTTP (HttpGet/Post/Put/Delete) | ✅ از Attribute ها |
| مسیرهای Attribute (`User/Login` و …) | ✅ دقیق |
| پارامترها (نام + نوع) و نوع خروجی | ✅ از امضای متدها |
| احراز هویت (`Authorize` / `AllowAnonymous`) | ✅ |
| مسیرهای قراردادی (`api/{controller}/{action}`) | ⚠️ قالب واقعی در DLL **رمزنگاری** شده و به‌صورت متن ساده موجود نیست؛ مسیر نمایش‌داده‌شده از قرارداد استاندارد Web API پیروی می‌کند |

نکته: مسیرهای `[attribute]` (سبز) دقیق‌اند؛ مسیرهای `[conventional]` (ستاره‌دار `*`) بر اساس قرارداد نمایش داده می‌شوند.

## فایل‌های مرتبط

- `zhaniya-cli.js` — خود ابزار
- `tools/api_catalog.json` — دادهٔ استخراج‌شده از DLL (ورودی CLI)
- `tools/extract_api.py` — اسکریپت استخراج (اجرای مجدد در صورت نیاز با Python + `dnfile`)

## نمونه خروجی

```
  UserController → Login
  ──────────────────────────────────────────────
  مسیر        : User/Login  [attribute]
  متدها       : POST
  احراز هویت  : —
  پارامترها   :
      • model  (Models.LoginModel)
  خروجی       : Web.Http.IHttpActionResult

  نمونه درخواست:
    curl -X POST 'http://localhost:PORT/User/Login' \
      -H 'Content-Type: application/json' \
      -d '{"model":{}}'
```
