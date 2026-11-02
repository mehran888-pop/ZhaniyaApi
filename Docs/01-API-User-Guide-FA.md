# 📖 دفترچه راهنمای API ژانی‌یا (ZhaniyaApi)

**نسخه:** 1.0 — تاریخ تدوین: 1405/08/11
**مخاطب:** توسعه‌دهندگان و یکپارچه‌سازان (نرم‌افزار صندوق WPF، منوی دیجیتال وب، اپلیکیشن موبایل، درگاه پرداخت)
**منبع:** استخراج کامل از `ZhaniyaApi.dll` / `Model.dll` / `Web.config` / فایل‌های PDB پروژه

---

## 1. این API چیست؟

`ZhaniyaApi` یک **REST API** مبتنی بر **ASP.NET Web API 2** (روی .NET Framework 4.8) است که داده‌های سیستم کامل صندوق فروش و ERP «ژانی‌یا» را فراهم می‌کند: رستوران، سالن، کارواش، انبار، مشتری، فاکتور، پرداخت، چاپگر، منوی دیجیتال و...

<div dir="ltr">

```
┌──────────────┐   HTTP/JSON     ┌───────────────────────────┐   EF6 (EDMX)   ┌──────────────┐
│   Client     │ ──────────────▶ │  IIS  →  ZhaniyaApi.dll   │ ─────────────▶ │ SQL Server   │
│ (WPF / Web / │   Bearer JWT    │  ├─ Controllers (api/*)   │               │  ZhaniyaDB   │
│   Mobile)    │ ◀────────────── │  ├─ SignalR Hub (/signalr)│               └──────────────┘
│              │                 │  ├─ Help Page (/Help)     │
└──────────────┘                 │  └─ Stimulsoft reports    │
                                 └───────────────────────────┘
                                           │ Model.dll (264 entity)
```

</div>

### 1.1 پشته‌ی فنی

| مولفه | توضیحات |
|---|---|
| Framework | .NET Framework **4.8**، ASP.NET Web API 2 (System.Web.Http 5.3) + MVC 5.3 (برای صفحه Help) |
| Hosting | IIS — `Global.asax` → کلاس `WebApiApplication` |
| Data | Entity Framework **6** (مدل EDMX داخل `Model.dll` — اتصال `ModelDBEntities`)؛ 264 entity در `Model.DomainModel` و 22 کلاس/ویو در `Model.Entity` |
| Database | SQL Server — پایگاه `ZhaniyaDB` (پیش‌فرض `192.168.1.100` با کاربر `pana` — فایل `Web.config` را ببینید) |
| Auth | **JWT Bearer** (کتابخانه `JWT.dll` — کلاس `JWTManager`؛ فیلتر `AppAuthenticationAttribute`) |
| Real-time | **SignalR 2.4** — `NotificationsHub` در مسیر `/signalr` |
| Report/Print | **Stimulsoft 2024.3** (جدول `ReportTemplate` + `Printer`) |
| JSON | Newtonsoft.Json 13 — نام propertyها در خروجی **PascalCase**؛ ورودی نسبت به حروف بزرگ/کوچک حساس نیست |
| CORS | باز برای همه‌◆ `[EnableCors("*", "*", "*")]` |
| Cache | `OutputCache` با مدت 24 ساعت و `SqlDependency` روی جدول `MediaFile` + `sqlCacheDependency` در web.config |

---

## 2. استقرار (Deployment)

1. Windows Server + **IIS** با ASP.NET 4.8 نصب باشد.
2. محتوای فایل `ZhaniyaApi.zip` (نسخه‌ی publish) را در Virtual Directory کپی کنید (پوشه‌ی `bin/` همراه DLLها الزامی است).
3. رشته‌ی اتصال را در `Web.config` تنظیم کنید:
   ```xml
   <add name="ModelDBEntities" connectionString="metadata=res://*/DomainModel.DataModel.csdl|res://*/DomainModel.DataModel.ssdl|res://*/DomainModel.DataModel.msl;
        provider=System.Data.SqlClient;
        provider connection string=&quot;data source=SERVER;initial catalog=ZhaniyaDB;user id=USER;password=PASS;MultipleActiveResultSets=True&quot;" .../>
   ```
   همچنین فایل `Connector.link`: `{ "DbName":"ZhaniyaDB", "Server":".\\SQLEXPRESS", "UserId":"sa", "Password":"...", "IsSingleUser":false }`
4. برای فعال‌سازی `sqlCacheDependency` (کش مبتنی بر تغییر داده‌های DB) روی دیتابیس اجرا کنید:
   ```sql
   EXEC aspnet_regsql -S <server> -U <user> -P <pass> -d ZhaniyaDB -ed
   ```
5. تنظیمات مهم `web.config`: `executionTimeout="20000"` (20 ثانیه)، `customErrors mode="Off"`.

> ⚠️ توصیه امنیتی: اطلاعات اتصال دیتابیس داخل `Web.config` و `Connector.link` نگهداری می‌شود؛ حتماً HTTPS فعال کنید و این فایل‌ها را در دسترس عموم قرار ندهید.

---

## 3. آدرس‌دهی (Routing)

الگوی اصلی مسیرها: **`api/{controller}/{action}/{id}`** (بخش `{id}` اختیاری است). چند نمونه:

| عمل | URL |
|---|---|
| لیست میزها | `GET /api/Desk/GetAll?idSalon=1` |
| دریافت یک میز | `GET /api/Desk/Get/5` |
| ثبت فاکتور | `POST /api/Invoice/Insert` |
| ورود کاربر | `POST /api/User/Login` |
| صفحه اصلی | `GET /` |
| مستندات خودکار | `GET /Help` |
| SignalR | `/signalr` (Hub = `NotificationsHub`) |

> کنترلر `UserController` دارای `[RoutePrefix("User")]` است؛ اکشن‌های آن با مسیر `api/User/...` در دسترس‌اند.

---

## 4. احراز هویت (JWT Bearer) 🔐

تمام اندپوینت‌ها — به‌جز موارد عمومی — توسط فیلتر **`AppAuthenticationAttribute`** (یک `IAuthenticationFilter` با متدهای `AuthenticateAsync`/`ChallengeAsync`) محافظت می‌شوند؛ اکشن‌هایی با `[AllowAnonymous]` استثنا هستند.

### 4.1 مرحله اول — ورود و دریافت توکن

```http
POST /api/User/Login
Content-Type: application/json

{
  "Username": "admin",
  "Password": "123456",
  "SubSystem": 1
}
```

پاسخ — مدل `UserInfo`:

```json
{
  "ID": 1,
  "Username": "admin",
  "FullName": "مدیر سیستم",
  "AgencyList": [ { "ID": 1, "ItemName": "شعبه مرکزی", "IDFinancialYear": 1403 } ],
  "Token": "eyJhbGciOiJIUzI1NiIs...",
  "TokenExpirationTime": 638661234567890000,
  "AppSetting": { "TerminalIP": "...", "EnableDesk": true, "EnableSalon": true, "EnableTakeAway": true, "CurrencySymbol": "ریال", "...": "..." }
}
```

### 4.2 استفاده از توکن

در تمام درخواست‌های محافظت‌شده، هدر زیر را ارسال کنید:

```http
Authorization: Bearer eyJhbGciOiJIUzI1NiIs...
```

- توکن توسط `JWTManager.GetToken(...)` ساخته و با `DecodeToken(...)` اعتبارسنجی می‌شود (کلید/issuer/audience در کلاس `Security`).
- اگر توکن نامعتبر یا منقضی باشد → **401 Unauthorized**.
- فیلد `TokenExpirationTime` زمان انقضای توکن را به‌صورت ticks برمی‌گرداند.

### 4.3 انتخاب شعبه / سال مالی

پس از Login، برای تعویض شعبه‌ی فعال:

```http
POST /api/User/LoginWithAgency
Authorization: Bearer <token>
Content-Type: application/json

{ "IDAgency": 1, "IDFinancialYear": 1403 }
```

### 4.4 اکشن‌های عمومی (بدون نیاز به توکن)

| Endpoint | توضیح |
|---|---|
| `POST /api/User/Login` | ورود و دریافت توکن |
| `GET /api/User/GetAll` | لیست نام کاربری‌ها (`string[]`) |
| `GET /api/DigitalMenu/LogOn?tableId=` | ورود میز منوی دیجیتال (خودش توکن می‌دهد) |
| `GET /api/DigitalMenu/GetProductGroups`, `GET /api/DigitalMenu/GetImage` | منوی عمومی |
| اکشن‌های `Delete` در Desk/Salon/ProductGroup/ProductBrand | با `[AllowAnonymous]` (⚠️ بخش 8 را ببینید) |

---

## 5. قراردادهای chung (Conventions)

- **محتوا:** `application/json; charset=utf-8` — همه‌ی بدنه‌ها JSON و UTF-8 هستند.
- **GET:** پارامترها در Query String (مثل `?idSalon=1&setPicture=true`).
- **POST/PUT:** بدنه‌ی JSON با `[FromBody]` (شیء کامل مدل).
- **DELETE:** بیشتر کنترلرها `id` را در مسیر می‌گیرند (`/api/Desk/Delete/5`)؛ در `InvoiceController.Delete` بدنه‌اش یک `int` است.
- **Boolean در query:** به‌صورت `true/false`.
- **Nullable:** اگر مقدار ندارید، paramètre را ارسال نکنید یا `null` بفرستید.
- **تاریخ:**
  - `Invoice/GetAll`: پارامترهای `since`/`until` به‌صورت `long` (ticks) + `isPersianDate=true/false`
  - `Person/GetBill`: `since`/`until` به‌صورت رشته‌ی تاریخ (تقویم شمسی/میلادی)
  - فیلدهای `*PersianDate` در مدل‌ها: رشته‌ی تاریخ فارسی برای نمایش.
- **Enum:** به‌صورت عدد (byte/int).
- **فیلتر/صفحه‌بندی:** پارامترهای اختیاری query مثل `idGroup`, `idBrand`, `idOrderType`, `canSync`, `setPicture`, `sortType`.

### 5.1 لفاف پاسخ استاندارد (Envelope)

اکثر اکشن‌های نوشتنی مدل `ResultModel` را برمی‌گردانند:

```json
{ "ResponseCode": 1, "ResponseMessage": "انجام شد", "Id": 152, "Code": 0 }
```

عملیات فاکتور مدل `InvoiceResult` را برمی‌گردانند:

```json
{ "ResponseCode": 1, "ResponseMessage": "...", "ID": 152, "OrderNo": 12, "InvoiceNumber": 1042 }
```

### 5.2 کدهای وضعیت HTTP

| کد | معنی |
|---|---|
| 200 | موفقیت — نتیجه در بدنه |
| 400 | خطای اعتبارسنجی (ModelState) |
| 401 | توکن نامعتبر/منقضی — دوباره Login کنید |
| 404 | منبع یافت نشد (id اشتباه) |
| 500 | خطای سرور (چون `customErrors=Off` جزئیات برمی‌گردد) |

### 5.3 ساختار خطا

خطای استاندارد Web API به‌صورت JSON:

```json
{ "Message": "The request is invalid.", "ModelState": { "value": ["..."] } }
```

---

## 6. نمونه‌کد (نمونه فراخوانی)

### 6.1 cURL

```bash
# 1) ورود
TOKEN=$(curl -s -X POST http://server/api/User/Login \
  -H "Content-Type: application/json" \
  -d '{"Username":"admin","Password":"123456","SubSystem":1}' | jq -r '.Token')

# 2) فراخوانی محافظت‌شده
curl -s "http://server/api/Desk/GetAll?idSalon=1" -H "Authorization: Bearer $TOKEN"

# 3) ثبت فاکتور
curl -s -X POST http://server/api/Invoice/Insert \
  -H "Authorization: Bearer $TOKEN" -H "Content-Type: application/json" \
  -d @invoice.json
```

### 6.2 JavaScript (fetch)

```js
const BASE = "http://server";
let token = null;

async function login(username, password, subSystem) {
  const r = await fetch(`${BASE}/api/User/Login`, {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify({ Username: username, Password: password, SubSystem: subSystem })
  });
  const info = await r.json();
  token = info.Token;
  return info;
}

function authHeaders() {
  return { "Authorization": `Bearer ${token}`, "Content-Type": "application/json" };
}

// مثال: لیست محصولات
const products = await (await fetch(
  `${BASE}/api/Product/GetAll?setPicture=true&sortType=0`,
  { headers: authHeaders() }
)).json();
```

### 6.3 C# (HttpClient)

```csharp
var client = new HttpClient { BaseAddress = new Uri("http://server/") };

// ورود
var login = await client.PostAsJsonAsync("/api/User/Login",
    new { Username = "admin", Password = "123456", SubSystem = 1 });
var userInfo = await login.Content.ReadAsAsync<UserInfo>();
client.DefaultRequestHeaders.Authorization =
    new AuthenticationHeaderValue("Bearer", userInfo.Token);

// فراخوانی محافظت‌شده
var desks = await client.GetFromJsonAsync<List<DeskModel>>("/api/Desk/GetAll?idSalon=1");
```

---

## 7. سناریوهای کاری (Workflows)

### 7.1 منوی دیجیتال (Digital Menu) 🍽️

```
GET  /api/DigitalMenu/LogOn?tableId=7            → MenuUserInfo (Token + TableName + AppSetting)
GET  /api/DigitalMenu/GetProductGroups?idProductType=&setPicture=true&JustParent=true
                                                 → ProductGroupModel[]
GET  /api/DigitalMenu/GetMenu?idBrand=&idGroup=&idOrderType=&idProductType=&setPicture=true&sortType=0&canSync=&setInventory=false
                                                 → MenuModel { Categories, Products }
GET  /api/DigitalMenu/GetImage?id=12             → تصویر محصول (کش 24 ساعته + SqlDependency)
GET  /api/DigitalMenu/GetCustomer?mobileNo=0912... → Customer (اگر موجود نبود →)
POST /api/DigitalMenu/InsertCustomer             → ResultModel (ثبت مشتری جدید)
POST /api/DigitalMenu/InsertOrder   (بدنه: InvoiceDto) → InvoiceResult
```

### 7.2 صندوق فروش — ثبت و تسویه فاکتور 🧾

```
GET  /api/Salon/GetAll                              → Salon[]
GET  /api/Desk/GetAll?idSalon=1                     → DeskModel[]   (میزهای سالن)
GET  /api/Product/GetAll?idGroup=&idOrderType=1&setPicture=true&sortType=0&canSync=&setInventory=
POST /api/Invoice/Insert      (InvoiceModel)        → InvoiceResult { ID, OrderNo, InvoiceNumber }
POST /api/Invoice/CheckOut    (InvoiceModel با Payments) → InvoiceResult
POST /api/Invoice/ChangeDesk  (InvoiceDeskModel { IDInvoice, Desks[] }) → ResultModel
GET  /api/Invoice/GetAll?idFinancialYear=1403&since=&until=&idAccount=&invoiceNo=&isPersianDate=true → InvoiceModel[]
GET  /api/Invoice/Print?id=&setFactor=true&setKitchen=true&isTakeAway=false&forInsert=false&idTemplate=1 → ResultModel
GET  /api/Invoice/GetBillPrint?id=&exportType=1&idTemplate=1   → فایل فاکتور (PDF/Excel/تصویر)
```

### 7.3 مشتری و دفتر کل (Ledger)

```
GET  /api/Person/GetAll?canVisible=true&setPicture=true   → PersonModel[]
GET  /api/Person/GetByTel?value=09120000000               → PersonModel
GET  /api/Person/GetBill?idAccount=&since=&until=&showDetails=true  → LedgerItem[]
POST /api/Person/Insert    (NewPersonModel)               → ResultModel
POST /api/Invoice/InsertCar (NewCar)                      → ResultModel  (ثبت خودرو — کارواش)
```

### 7.4 محصولات / گروه‌ها / برندها

```
GET  /api/ProductGroup/GetAll?idParent=&idProductType=&setPicture=true&JustParent=false → ProductGroupModel[]
GET  /api/ProductBrand/GetAll?idParent=&setPicture=true                               → ProductGroupModel[]
POST /api/ProductGroup   / PUT /api/ProductGroup    (بدنه: ProductGroup)  → string
POST /api/Product        / PUT /api/Product         (بدنه: Product)       → string
POST /api/ProductBrand   / PUT /api/ProductBrand    (بدنه: ProductBrand)  → string
GET  /api/Product/GetInventory?id=&idFinancialYear=&idStore=&idCostCenter= → StockModel
GET  /api/Product/GetProductBarcode?barcode=...                              → ProductModel
```

### 7.5 امتیازدهی (Rating) و کارواش

```
GET  /api/Rating/GetCommentSample  → CommentSampleModel[]
POST /api/Rating/Insert (RatingList { Ratings:[{IDPerson, IDProduct, IDInvoice, Rate, RateDate}] }) → ResultModel
GET  /api/BaseGetter/GetCarwashTables    → CarwashTable { Personnel, CarModel, CarCompany, ColorType }
GET  /api/BaseGetter/GetAllDiscountLevel → DiscountLevelModel[]
GET  /api/BaseGetter/Get?prefix=&tableName= → ItemTypeModel[]  (lookup عمومی)
```

### 7.6 فایل‌های چندرسانه‌ای (Media)

```
GET /api/MediaFile/Get/12                 → MediaFile (متادیتا + FileData)
GET /api/MediaFile/GetFile/12             → دانلود فایل (application/octet-stream)
GET /api/MediaFile/GetClipFile            → کلیپ تبلیغاتی
GET /api/MediaFile/GetVideo?videoName=promo.mp4  → استریم ویدیو
```

### 7.7 نوتیفیکیشن لحظه‌ای (SignalR)

```js
const conn = new signalR.HubConnectionBuilder().withUrl("http://server/signalr").build();
conn.on("ReceiveNotification", n => console.log(n)); // NotificationType = Message
await conn.start();
```

### 7.8 ثبت تماس تلفنی (CallHistory)

```http
GET /api/General/InsertCallHistory?appId=pos&callerId=0912...&calledId=021...
```

---

## 8. نکات امنیتی ⚠️ (توصیه‌ها)

- اکشن‌های `Delete` در کنترلر Desk/Salon/ProductGroup/ProductBrand با `[AllowAnonymous]` public هستند — **دسترسی را در سطح IIS/Firewall محدود کنید**.
- CORS روی `*` باز است؛ در محیط production دامنه‌ها را محدود کنید.
- `customErrors mode="Off"` باعث افشای جزئیات خطا می‌شود؛ در production روی `RemoteOnly` بگذارید.
- Credentialهای دیتابیس در `Web.config` و `Connector.link` — به Secret Store منتقل شود.
- شماری از اکشن‌ها نام obfuscated دارند (مثل `pkEgsudbFd`) — برای استفاده‌ی امن فقط به مستندات فنی (سند 02) مراجعه کنید.

---

## 9. پرسش‌های متداول (FAQ)

| مشکل | راه‌حل |
|---|---|
| 401 | توکن منقضی/اشتباه است → دوباره `Login` کنید؛ هدر دقیق `Authorization: Bearer <token>` |
| 400 با ModelState | نام propertyهای JSON را با مدل‌های سند 02 تطبیق دهید (ورودی case-insensitive است) |
| 404 | کنترلر/اکشن یا id اشتباه است؛ صفحه `/Help` مسیر دقیق را نشان می‌دهد |
| Timeout | درخواست‌های سنگین بیش از 20 ثانیه طول می‌کشند — `executionTimeout` را در web.config افزایش دهید |
| تصویر لود نمی‌شود | کش/SqlDependency نیاز به اجرای `aspnet_regsql` روی دیتابیس دارد |
| CORS | policy روی `*` است؛ از همان دامنه‌ی API فراخوانی کنید |

> 💡 **نکته طلایی:** صفحه‌ی `/Help` تمام مسیرها را به‌صورت زنده با sample request/response نمایش می‌دهد — بهترین مرجع برای تست سریع است.

---

*تهیه‌شده بر اساس بررسی کامل سورس ZhaniyaApi — برای جزئیات تک‌تک دستورات به سند [02-API-Technical-Reference-FA.md](./02-API-Technical-Reference-FA.md) و جدول سریع [03-API-Commands-QuickRef.md](./03-API-Commands-QuickRef.md) مراجعه کنید.*
