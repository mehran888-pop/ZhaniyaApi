# 🛠️ داکیومنت فنی کامل API — ZhaniyaApi (مرجع تمام دستورات)

**نسخه:** 1.0 — 1405/08/11
**منبع:** استخراج مستقیم از متادیتای `ZhaniyaApi.dll` و `Model.dll` (کنترلرها، attributeها، امضاهای متدها، propertyهای مدل‌ها) + `Web.config` + PDB
**نحوه‌ی آدرس‌دهی:** `api/{controller}/{action}/{id}` — `{id}` اختیاری است. به‌صورت پیش‌فرض خروجی JSON با نام propertyهای **PascalCase** است و ورودی JSON نسبت به حروف بزرگ/کوچک حساس نیست.

> نکته: کنترلر `UserController` دارای `[RoutePrefix("User")]` است. routing مبتنی بر attribute (`MapHttpAttributeRoutes`) فعال است؛ با این حال attributeهای `[Route]` از MVC (`System.Web.Mvc.RouteAttribute`) که روی برخی اکشن‌ها دیده می‌شوند در Web API بی‌اثر هستند و مسیرها از طریق الگوی مرسوم `api/{controller}/{action}` resolve می‌شوند.

---

## 1. معماری

```
Client (WPF POS / Web Digital Menu / Mobile)
   │  HTTP + JSON + Bearer JWT        CORS: *
   ▼
IIS ── ZhaniyaApi.dll ──► Controllers (namespace ZhaniyaApi.Controllers)
   │                        ├─ BaseApiController (propertyهای کاربر/توکن/پرینتر/پرینت)
   │                        └─ 12 کنترلر public
   ├─► Areas/HelpPage  →  GET /Help
   ├─► SignalR         →  /signalr  (NotificationsHub)
   └─► Model.dll (EF6 EDMX "ModelDBEntities") ──► SQL Server ZhaniyaDB
        Stimulsoft 2024.3 (گزارش/چاپ)  │  JWT.dll  │  SSP1126.PcPos.dll (کارتخوان)  │  TaxCollectData.Library.dll (مالیات)
```

### 1.1 کنترلرها

| # | کنترلر | کلاس پایه | auth کلاس | توضیح |
|---|---|---|---|---|
| 1 | `UserController` | BaseApiController | `[RoutePrefix("User")]` — عمومی | ورود/توکن/کاربران |
| 2 | `BaseGetterController` | BaseApiController | `[AppAuthentication]` | lookup عمومی جدول‌ها، کارواش، تخفیف |
| 3 | `DeskController` | BaseApiController | `[AppAuthentication]` | میزها |
| 4 | `SalonController` | BaseApiController | `[AppAuthentication]` | سالن‌ها |
| 5 | `ProductGroupController` | BaseApiController | `[AppAuthentication]` | گروه کالا |
| 6 | `ProductBrandController` | BaseApiController | عمومی | برندها |
| 7 | `ProductController` | BaseApiController | `[AppAuthentication]` | کالا/موجودی/اسلایدر |
| 8 | `PersonController` | BaseApiController | `[AppAuthentication]` | اشخاص/مشتری/دفترکل |
| 9 | `InvoiceController` | BaseApiController | `[AppAuthentication]` | فاکتور/پرداخت/چاپ |
| 10 | `DigitalMenuController` | BaseApiController | عمومی (برخی اکشن [AppAuthentication]) | منوی دیجیتال |
| 11 | `MediaFileController` | BaseApiController | عمومی (GetClipFile: [AppAuthentication]) | fichiers médias |
| 12 | `RatingController` | BaseApiController | `[AppAuthentication]` | امتیاز/نظر |
| 13 | `GeneralController` | BaseApiController | عمومی | تماس تلفنی (CallHistory) |
| — | `BaseApiController` | ApiController | `[AllowAnonymous]` + `[EnableCors("*","*","*")]` | اکشن‌های کمکی داخلی |
| — | `HomeController` | MVC Controller | — | صفحه‌ی اصلی `GET /` |

---

## 2. احراز هویت — جزئیات فنی

### 2.1 فیلتر `AppAuthenticationAttribute`
- نوع: `IAuthenticationFilter` با `AuthenticateAsync(context, ct)` و `ChallengeAsync(context, ct)` — WS nach Header `Authorization: Bearer <token>` (کلاس مرجع: `AuthenticationHeaderValue`).
- توکن با `JWTManager.DecodeToken(token)` اعتبارسنجی می‌شود؛ در صورت نامعتبر بودن → 401.
- property `AllowMultiple`.

### 2.2 `JWTManager` (namespace `Services.Extensions.JWTHelper`)

| متد | امضا |
|---|---|
| `GetToken()` | `Token GetToken()` |
| `GetToken(TimeSpan expiredTime)` | توکن با انقضای مشخص |
| `GetToken(IDictionary<string,object> payload, TimeSpan expiredTime)` | توکن با payload دلخواه |
| `GetToken(IDictionary<string,object> payload, bool IsExpiredToken, DateTime? ExpireDate)` | توکن (حتی منقضی برای تست) |
| `DecodeToken(string token)` | `Token DecodeToken(string)` |

کلاس `Token`: `IsExpired`, `IsExpiredToken`, `DataInDictionaryFormat`, `DataInStringFormat`, `ExpiredTime`.
کلاس `Security`: `issuer`, `audience`, `SecretKey`.

### 2.3 Claimهای کاربر — `AppUser` (ClaimsPrincipal)

`ID` (short), `Username`, `FullName`, `IDAgency` (int? — claim), `IDFinancialYear` (int?), `AppSetting` (`AppSettingProperty`).

### 2.4 propertyهای `BaseApiController` (در دسترس همه‌ی کنترلرها)

`CurrentAgency` (Agency), `CurrentUser` (User), `CurrentSetting` (UserSettingProperty), `Agencies`, `OrderTypes`, `AppUser`, `SessionId`, `ID` (short), `Username`, `FullName`, `IDAgency`, `IDFinancialYear`, `Identity` (claims), `CurrentOrderType(byte idOrderType)`, `IntegratedPrinters`/`BillPrinters` (List<Printer>) + `RefreshIntegratedPrinters()`/`RefreshBillPrinters()`, `ReportFilePath`, `Write(ReportTemplate)`, `GetMediaFile(MediaFile)`, `OrderPrint(int id, bool setFactor, bool setKitchen, bool forInsert, bool isTakeAway, int idTemplate, AppSettingProperty appSetting)` → `ResultModel`.

---

## 3. فیلترها، کش و کشینگ

| جزء | توضیحات |
|---|---|
| `ModelStateCheckActionFilter` | بررسی ModelState در MVC |
| `ArrayInputAttribute` | تجزیه‌ی پارامتر آرایه‌ای با `Separator` |
| `CacheHelper` | MemoryCache — `Set(key,data,minutes)` / `Get` / `Contains` / `Remove` |
| `OutputCache` روی اکشن‌های média | `Duration=86400` (24h), `SqlDependency=ZhaniyaSqlCacheDependency:MediaFile`, `VaryByParam=id` |
| `sqlCacheDependency` (web.config) | دیتابیس `ModelDBEntities`, `pollTime=500` |
| CORS | `[EnableCors("*","*","*")]` روی BaseApiController |
| `EnableCorsAttribute` سراسری | فعال‌شده از طریق config |

---

## 4. مرجع کنترلرها — تمام دستورات

> امضاها به‌صورت C# استخراج شده‌اند. `short?` یعنی Nullable<int16> و غیره.

### 4.1 UserController — `[RoutePrefix("User")]` — ورود و توکن

| متد HTTP | مسیر | Auth | پارامترها | بدنه | پاسخ |
|---|---|---|---|---|---|
| POST | `/api/User/Login` | ❌ عمومی | — | `LoginModel` | `IHttpActionResult` → `UserInfo` |
| POST | `/api/User/LoginWithAgency` | ✅ `[AppAuthentication]` | — | `AgencyFinancialYear` | `IHttpActionResult` |
| GET | `/api/User/GetAll` | ❌ عمومی | — | — | `IEnumerable<string>` (نام کاربری‌ها) |

**بدنه Login:** `LoginModel { Username: string, Password: string, SubSystem: int }`

**پاسخ Login:** `UserInfo { ID: short, Username, FullName, AgencyList: UserAgency[] { ID: short, ItemName, IDFinancialYear: int }, Token: string, TokenExpirationTime: long, AppSetting: AppSettingModel }`

```http
POST /api/User/Login
Content-Type: application/json

{"Username":"admin","Password":"123456","SubSystem":1}
```

```json
{
  "ID": 1, "Username": "admin", "FullName": "مدیر",
  "AgencyList": [{"ID":1,"ItemName":"شعبه مرکزی","IDFinancialYear":1403}],
  "Token": "eyJhbGciOi...", "TokenExpirationTime": 638661234567890000,
  "AppSetting": { "IdTerminal": 1, "TerminalIP": "192.168.1.50", "TerminalPort": 5000,
                  "MerchantNo": "...", "EnableDesk": true, "EnableSalon": true,
                  "EnableTakeAway": true, "CurrencySymbol": "ریال", "CurrencyDecimalDigits": 0, "...": "..." }
}
```

**بدنه LoginWithAgency:** `AgencyFinancialYear { IDAgency: short, IDFinancialYear: short }`

---

### 4.2 BaseGetterController — `[AppAuthentication]` — lookup عمومی

| متد HTTP | مسیر | پارامترها | پاسخ |
|---|---|---|---|
| GET | `/api/BaseGetter/Get` | `prefix: string`, `tableName: string` (query) | `List<ItemTypeModel>` |
| GET | `/api/BaseGetter/GetCarwashTables` | — | `CarwashTable` |
| GET | `/api/BaseGetter/GetAllDiscountLevel` | — | `List<DiscountLevelModel>` |

مدل‌ها: `ItemTypeModel { ID: int, ItemName, ItemCode: int, IDParent: int }` — `CarwashTable { Personnel, CarModel, CarCompany, ColorType: List<ItemTypeModel> }` — `DiscountLevelModel { ID: byte, IDAgency: short?, IDDiscountType: byte, ItemName, Amount: decimal, CanVisible: bool }`

---

### 4.3 DeskController — `[AppAuthentication]` — میزها

| متد HTTP | مسیر | پارامترها | بدنه | پاسخ |
|---|---|---|---|---|
| GET | `/api/Desk/GetAll` | `idSalon: short?` (query, `[FromUri]`) | — | `List<DeskModel>` |
| GET | `/api/Desk/Get/{id}` | `id: int` (route) | — | `Desk` (entity) |
| POST | `/api/Desk` | — | `Desk` `[FromBody]` | `string` (پیام نتیجه) |
| PUT | `/api/Desk` | — | `Desk` `[FromBody]` | `string` |
| DELETE | `/api/Desk/Delete/{id}` | `id: int` | — | `string` — ⚠️ `[AllowAnonymous]` |

`DeskModel { ID: short, IDSalon: short, IDInvoice: int?, ItemName, Capacity: byte?, CanVisible: bool, OrderNo: int?, InvoiceNumber: int?, InvoiceTime, PayableString, Payable: decimal, Remaining: decimal, IsBussy: bool }`

---

### 4.4 SalonController — `[AppAuthentication]` — سالن‌ها

| متد HTTP | مسیر | پارامترها | بدنه | پاسخ |
|---|---|---|---|---|
| GET | `/api/Salon/GetAll` | — | — | `List<Salon>` |
| GET | `/api/Salon/GetByAgencyId` | `idAgency: int` (query) | — | `List<Salon>` |
| GET | `/api/Salon/Get/{id}` | `id: int` | — | `Salon` |
| POST | `/api/Salon` | — | `Salon` | `string` |
| PUT | `/api/Salon` | — | `Salon` | `string` |
| DELETE | `/api/Salon/Delete/{id}` | `id: int` | — | `string` — ⚠️ `[AllowAnonymous]` |

`Salon (entity) { ID, IDAgency, ItemName, EntranceAmount, CanVisible, Desks }`

---

### 4.5 ProductGroupController — `[AppAuthentication]` — گروه کالا

| متد HTTP | مسیر | پارامترها | بدنه | پاسخ |
|---|---|---|---|---|
| GET | `/api/ProductGroup/GetAll` | `idParent: short?, idProductType: byte?, setPicture: bool, JustParent: bool` | — | `List<ProductGroupModel>` |
| GET | `/api/ProductGroup/Get/{id}` | `id: int` | — | `ProductGroup` (entity) |
| POST | `/api/ProductGroup/ofhgeFPkAE` * | — | `ProductGroup` `[FromBody]` | `string` |
| PUT | `/api/ProductGroup/AipgrPdhgV` * | — | `ProductGroup` `[FromBody]` | `string` |
| DELETE | `/api/ProductGroup/FSRghiqrde` * | `id: int` (query) | — | `string` — ⚠️ `[AllowAnonymous]` |

\* نام اکشن obfuscated است؛ با همان نام (case-insensitive) فراخوانی می‌شود. POST/PUT/DELETE با الگو: `POST /api/ProductGroup` (بدنه) — چون نام اکشن باaba verb شروع نمی‌شود، مسیر با action explicit است: `/api/ProductGroup/ofhgeFPkAE`.

`ProductGroupModel { ID: short, IDParent: short?, HasChild: bool, HasBrand: bool, IDPicture: int?, ItemName, FileData: byte[], FileExtension }`

---

### 4.6 ProductBrandController — عمومی — برندها

| متد HTTP | مسیر | پارامترها | بدنه | پاسخ |
|---|---|---|---|---|
| GET | `/api/ProductBrand/GetAll` | `idParent: short?, setPicture: bool` | — | `List<ProductGroupModel>` (گروه‌هایی که برند دارند) |
| GET | `/api/ProductBrand/Get/{id}` | `id: int` | — | `ProductBrand` (entity) |
| POST | `/api/ProductBrand/jKEgay2I8R` * | — | `ProductBrand` `[FromBody]` | `string` |
| PUT | `/api/ProductBrand/oM8gR4LN61` * | — | `ProductBrand` `[FromBody]` | `string` |
| DELETE | `/api/ProductBrand/eqngI91K9r` * | `id: int` (query) | — | `string` — ⚠️ `[AllowAnonymous]` |

\* اکشن obfuscated — POST/PUT/DELETE با نام اکشن در مسیر.

---

### 4.7 ProductController — `[AppAuthentication]` — کالاها

| متد HTTP | مسیر | پارامترها (query) | بدنه | پاسخ |
|---|---|---|---|---|
| GET | `/api/Product/GetAll` | `idGroup: short?, idBrand: short?, idOrderType: byte?, idProductType: byte?, setPicture: bool, sortType: byte, canSync: bool?, setInventory: bool` | — | `IEnumerable<ProductModel>` |
| GET | `/api/Product/GetSlider` | — | — | `IEnumerable<HttpResponseMessage>` (تصاویر اسلایدر) |
| GET | `/api/Product/GetSliderID` | — | — | `IEnumerable<int>` |
| GET | `/api/Product/GetTodayProducts` | — | — | `IEnumerable<int>` (id کالاهای امروز) |
| GET | `/api/Product/GetDailyProductList` | `date: byte` | — | `List<ProductModel>` |
| GET | `/api/Product/GetByGroupId` | `idGroup: int` | — | `List<Product>` (entity) |
| GET | `/api/Product/Get/{id}` | `id: int` | — | `ProductModel` |
| GET | `/api/Product/GetProductBarcode` | `barcode: string` | — | `ProductModel` |
| GET | `/api/Product/GetByIdInvoice` | `value: int` | — | `ProductRating` |
| GET | `/api/Product/GetByInvoiceNumber` | `value: int` | — | `ProductRating` |
| GET | `/api/Product/GetByOrderNo` | `value: short` | — | `ProductRating` |
| GET | `/api/Product/GetInventory` | `id: int, idFinancialYear: short?, idStore: short?, idCostCenter: short?` | — | `StockModel` |
| POST | `/api/Product/icIgnpwXUR` * | — | `Product` `[FromBody]` | `string` |
| PUT | `/api/Product/KrTg66hP3M` * | — | `Product` `[FromBody]` | `string` |

\* اکشن obfuscated. اکشن‌های داخلی obfuscated دیگر: `itIgmdbOeH(byte)` → `Task<IEnumerable<int>>` و `Firg28xacs(byte)` → `IQueryable<Product>` (-query داخلی'«كالای روز»).

---

### 4.8 PersonController — `[AppAuthentication]` — اشخاص / مشتریان / دفترکل

| متد HTTP | مسیر | پارامترها | بدنه | پاسخ |
|---|---|---|---|---|
| GET | `/api/Person/GetAll` | `canVisible: bool, setPicture: bool` | — | `List<PersonModel>` |
| GET | `/api/Person/GetBill` | `idAccount: int, since: string, until: string, showDetails: bool` | — | `IEnumerable<LedgerItem>` (تراکنش‌های دفترکل) |
| GET | `/api/Person/Get/{id}` | `id: int` | — | `PersonModel` |
| GET | `/api/Person/GetByTel` | `value: string` (شماره تماس) | — | `PersonModel` |
| POST | `/api/Person/Insert` | — | `NewPersonModel` | `Task<ResultModel>` |

اکشن‌های obfuscated: `hSDgGB2QYa(string value [FromBody])`, `aETgdeYOdk(int, string value [FromBody])`, `vvogWMQcnn(int)` — همگی `void` (عملیات تکمیلی روی شخص).

---

### 4.9 InvoiceController — `[AppAuthentication]` — فاکتور، پرداخت، چاپ

| متد HTTP | مسیر | پارامترها (query) | بدنه | پاسخ |
|---|---|---|---|---|
| POST | `/api/Invoice/Insert` | — | `InvoiceModel` `[FromBody]` | `Task<InvoiceResult>` |
| POST | `/api/Invoice/Update` | — | `InvoiceModel` `[FromBody]` | `Task<InvoiceResult>` |
| GET | `/api/Invoice/Get/{id}` | `id: int` | — | `Task<InvoiceModel>` |
| GET | `/api/Invoice/GetAll` | `idFinancialYear: short, since: long, until: long, idAccount: int, invoiceNo: int, isPersianDate: bool` | — | `List<InvoiceModel>` |
| POST | `/api/Invoice/Delete` | — | `id: int` `[FromBody]` | `ResultModel` |
| POST | `/api/Invoice/InsertCar` | — | `NewCar` | `ResultModel` |
| POST | `/api/Invoice/CheckOut` | — | `InvoiceModel` `[FromBody]` | `Task<InvoiceResult>` (تسویه) |
| POST | `/api/Invoice/ChangeDesk` | — | `InvoiceDeskModel` `[FromBody]` | `Task<ResultModel>` |
| GET | `/api/Invoice/Print` | `id: int, setFactor: bool, setKitchen: bool, isTakeAway: bool, forInsert: bool, idTemplate: int` | — | `ResultModel` (چاپ فاکتور/آشپزخانه) |
| GET | `/api/Invoice/GetBillPrint` | `id: int, exportType: byte, idTemplate: int` | — | `HttpResponseMessage` (PDF/Excel/Image — Stimulsoft) |

اکشن‌های obfuscated:
- `pkEgsudbFd(PaymentModel)` → `bool` — احتمالاً ثبت/استعلام **تراکنش کارتخوان بانکی** (POS) با `TerminalTransaction`.
- `JVTgD6T6fh(UpdateResponse, AppSettingProperty)` → `ResultModel` — به‌روزرسانی تنظیمات.
- `kDhg4CxoNf(int, byte, int)` → `StiMvcActionResult` — **نمایشگر Stimulsoft** (Web Viewer گزارش) — مسیر: `/api/Invoice/kDhg4CxoNf?...`.

> تاریخ در `GetAll`: `since`/`until` به‌صورت `long` (ticks) و با `isPersianDate=true` تقویم فارسی اعمال می‌شود.

---

### 4.10 DigitalMenuController — عمومی (برخی اکشن‌ها `[AppAuthentication]`) — منوی دیجیتال

| متد HTTP | مسیر | Auth | پارامترها | بدنه | پاسخ |
|---|---|---|---|---|---|
| GET | `/api/DigitalMenu/LogOn` | ❌ | `tableId: short` | — | `Task<MenuUserInfo>` |
| GET | `/api/DigitalMenu/GetMenu` | ✅ | `idGroup: short?, idBrand: short?, idOrderType: byte?, idProductType: byte?, setPicture: bool, sortType: byte, canSync: bool?, setInventory: bool` | — | `Task<MenuModel>` |
| GET | `/api/DigitalMenu/GetProductGroups` | ❌ | `idParent: short?, idProductType: byte?, setPicture: bool, JustParent: bool` | — | `List<ProductGroupModel>` |
| GET | `/api/DigitalMenu/GetCustomer` | ✅ | `mobileNo: string` | — | `Task<Customer>` |
| POST | `/api/DigitalMenu/InsertCustomer` | ✅ | — | `Customer` `[FromBody]` | `ResultModel` |
| GET | `/api/DigitalMenu/GetImage` | ❌ | `id: int` | — | `Task<HttpResponseMessage>` — [OutputCache 86400 + SqlDependency:MediaFile] |
| POST | `/api/DigitalMenu/InsertOrder` | ✅ | — | `InvoiceDto` `[FromBody]` | `Task<InvoiceResult>` |

`MenuUserInfo { ID: short, Username, FullName, TableId: int, TableName, IDShop: int, IDFinancialYear: int, ShopName, Token, TokenExpirationTime: long, AppSetting: DigitalMenuSetting }`

---

### 4.11 MediaFileController — عمومی (GetClipFile: `[AppAuthentication]`)

| متد HTTP | مسیر | پارامترها | پاسخ | کش |
|---|---|---|---|---|
| GET | `/api/MediaFile/Get/{id}` | `id: int` | `Task<MediaFile>` (entity) | OutputCache 24h + SqlDependency:MediaFile, VaryByParam=id |
| GET | `/api/MediaFile/GetFile/{id}` | `id: int` | `Task<HttpResponseMessage>` — دانلود فایل | OutputCache 24h + SqlDependency |
| GET | `/api/MediaFile/GetClipFile` | — | `HttpResponseMessage` (کلیپ) | OutputCache 24h — [AppAuthentication] |
| GET | `/api/MediaFile/GetVideo` | `videoName: string` | `HttpResponseMessage` (ویدیو) | OutputCache 24h |

`MediaFile (entity) { ID, IDOwner, IDTable, IDMediaType, ItemName, Extension, FileData: byte[] }`

---

### 4.12 RatingController — `[AppAuthentication]` — امتیاز و نظرات

| متد HTTP | مسیر | پارامترها | بدنه | پاسخ |
|---|---|---|---|---|
| GET | `/api/Rating/GetCommentSample` | — | — | `List<CommentSampleModel>` |
| POST | `/api/Rating/Insert` | — | `RatingList` `[FromBody]` | `Task<ResultModel>` |

اکشن‌های obfuscated: `gVmgi8uSVu(int, string [FromBody])`, `DLTgX8LClp(int)` — هر دو `void`.

---

### 4.13 GeneralController — عمومی

| متد HTTP | مسیر | پارامترها | پاسخ |
|---|---|---|---|
| GET | `/api/General/InsertCallHistory` | `appId: string, callerId: string, calledId: string` | `int` (ID رکورد ثبت‌شده در جدول `CallHistory`) |

---

### 4.14 BaseApiController — اکشن‌های ارث‌بری (عمومی — `[AllowAnonymous]`)

| متد | مسیر | امضا | پاسخ |
|---|---|---|---|
| GET | `/api/BaseApi/CurrentOrderType` | `CurrentOrderType(byte idOrderType)` | `OrderType` (entity) |
| POST-ish | `/api/BaseApi/OrderPrint` | `OrderPrint(int id, bool setFactor, bool setKitchen, bool forInsert, bool isTakeAway, int idTemplate, AppSettingProperty appSetting)` | `ResultModel` |
| POST-ish | `/api/BaseApi/Write` | `Write(ReportTemplate pattern)` | `string` (مسیر فایل گزارش) |
| POST-ish | `/api/BaseApi/GetMediaFile` | `GetMediaFile(MediaFile img)` | `HttpResponseMessage` |

> این اکشن‌ها helper هستند و معمولاً به‌صورت مستقیم استفاده نمی‌شوند.

### 4.15 صفحات و SignalR

| مسیر | توضیح |
|---|---|
| `GET /` | `HomeController.Index()` — صفحه‌ی Welcome «Zhaniya API» |
| `GET /Help` | Help Page (Areas/HelpPage) — مستندات خودکار تمام API با sample |
| `/signalr` | SignalR Hub — `NotificationsHub`; enum `NotificationType { Message }`; `MemberIdProvider.GetUserId(request)` (userId از توکن/درخواست) |

---

## 5. مدل‌های داده (DTO) — بدنه‌ی request/response

### 5.1 پاسخ مشترک (Envelope)

| مدل | propertyها |
|---|---|
| `ResultModel` | `ResponseCode: byte`, `ResponseMessage: string`, `Id: int`, `Code: int` |
| `InvoiceResult` | `ResponseCode: byte`, `ResponseMessage: string`, `ID: int`, `OrderNo: short`, `InvoiceNumber: int` |

### 5.2 ورود و کاربر

| مدل | propertyها |
|---|---|
| `LoginModel` | `Username: string`, `Password: string`, `SubSystem: int` |
| `AgencyFinancialYear` | `IDAgency: short`, `IDFinancialYear: short` |
| `UserInfo` | `ID: short`, `Username`, `FullName`, `AgencyList: UserAgency[]`, `Token: string`, `TokenExpirationTime: long`, `AppSetting: AppSettingModel` |
| `UserAgency` | `ID: short`, `ItemName: string`, `IDFinancialYear: int` |
| `MenuUserInfo` | `ID: short`, `Username`, `FullName`, `TableId: int`, `TableName`, `IDShop: int`, `IDFinancialYear: int`, `ShopName`, `Token`, `TokenExpirationTime: long`, `AppSetting: DigitalMenuSetting` |

### 5.3 تنظیمات (`AppSettingModel` — ~30 فیلد)

`IdBankAccount: int?, IdTerminal: int?, IdCashBox: int?, IdDepartment: short?, IdStore: int?, IdPicture: int?, Tell, WebSite, Description, TerminalIP, TerminalPort: int?, IDPsp: byte?, ConsoleType: int?, TerminalNo, MerchantNo, CurrencyDisplayName, CurrencySymbol, CurrencyDecimalDigits: byte, EnableCashPay: bool, EnableCardPay: bool, EnableCreditPay: bool, EnableDesk: bool, EnablePager: bool, EnableTel: bool, ClipAddress, ProductSortType: byte, EnableSalon: bool, EnableTakeAway: bool, SliderDelay: short, IDInvoiceTemplate: int, DeskMultiSelect: bool`

`DigitalMenuSetting`: همان above + `EnableOnlinePay: bool`, `LogoFileName: string`

### 5.4 فاکتور و پرداخت

| مدل | propertyها |
|---|---|
| `InvoiceModel` | `ID: int, IdCreator: short, IdOrderType: byte, IdConfirmState: byte, IdDepartment: short, Pager: short, DiscountPrice: decimal, Comment, MoreDetails, IdAccount: int, PrintKitchen: bool, ConsoleType: byte, Address, PostalCode, GetterName, TelNo, IDCar: int, IDPersonnel: int, WageAmount: decimal, InvoicePersianDate: string, InvoiceNumber: decimal, Desks: List<short>, InvoiceItems: List<InvoiceItemModel>, Payments: List<PaymentModel>, Person: PersonModel` |
| `InvoiceItemModel` | `ID, IDInvoice, IDProduct: int, IDStore: short, Quantity: decimal, UnitPrice: decimal, Discount: decimal, DiscountPercent: decimal, DiscountType: byte, TaxPrice, TollPrice, TaxPercent, TollPercent: decimal, IDType: byte, Comment, SetPrice: bool, Product: ProductModel` |
| `PaymentModel` | `ID: int, IDFund: int, IDTerminal: int?, IDType: byte, Amount: decimal, TerminalTransaction: TerminalTransaction` |
| `InvoiceDeskModel` | `IDInvoice: int, Desks: List<short>` |
| `InvoiceDto` (منوی دیجیتال) | `ID, IDDepartment: int, Comment, MoreDetails, IdAccount: int, PrintKitchen: bool, InvoicePersianDate, InvoiceNumber: decimal, IDDesk: short?, Discount: decimal, DiscountType: byte, InvoiceItems: List<InvoiceItemDto>, Payments: List<PaymentModel>` + propertyهای محاسباتی `DiscountPrice, DiscountPercent, TotalOriginalPrice, TotalPriceAfterDiscount, TotalDiscount, TaxAmountForItem, HasDiscount` |
| `InvoiceItemDto` | `ID, IDInvoice, IDProduct: int, IDStore: short, OriginalPrice: decimal, UnitPrice: decimal, Quantity: decimal, Notes, TaxRate: decimal, Discount: decimal, DiscountType: byte` + محاسباتی‌ها |
| `NewCar` | `ID: int, IDPerson: int?, IDCarModel: short?, IDColor: byte?, PlaqueNo, ProductionDate: short?, ChasisNo, Kilometer: int?, ColorName, ModelName, CompanyName, Car: Car(entity)` |
| `BasketModel` | `ID, IDProduct: int, IDStore: short, Quantity: decimal, IDInvoice: int` |

### 5.5 اشخاص

| مدل | propertyها |
|---|---|
| `PersonModel` | `ID: int, IDDiscountGroup: short?, IDPresenter: int?, IDGenre: byte, IDPersonType: byte, IDTradingMethod: byte, IDPicture: int?, TrackCode: int, Barcode, EconomicCode, FirstName, LastName, FatherName, NickName, NationalCode, CertificateNo, SerialNo, BirthDate: DateTime?, Address, PostalCode, Lat, Long, Comment, DisplayContact: bool, Charge: decimal, CanVisible: bool, Name, ItemName, FileData: byte[], FileExtension` |
| `NewPersonModel` | `FirstName, LastName, Mobile, Tel, Address, Barcode, Email` |
| `Customer` | `ID: int, FirstName, LastName, MobileNo` |
| `LedgerItem` (دفترکل — Model.Entity) | `ID, IDParent, ItemName, ItemCode: int?, Debtor: decimal, Creditor: decimal, Balance: decimal, Kind, IsDebtor: bool, RemainingDebtor, RemainingCreditor, LedgerType, Parent, LedgerItems, Description` |

### 5.6 کالا و گروه

| مدل | propertyها |
|---|---|
| `ProductModel` | `ID: int, IDGroup: short, IDDefaultStore: short?, IDProductType: byte, IDNegativeBalance: byte, IDPicture: int?, IDOrderType: byte, ProductName, ProductCode: int, NickName, Alias, LatinName, UnitName, GroupName, Barcodes: List<string>, Calories, DecimalDigits: byte, UnitPrice: decimal, PriceAfterDiscount, TaxPercent, TollPercent: decimal, UnitRatio: decimal, MinimumWholeSale: decimal?, Weight, Discount, DiscountPercent, DiscountPrice: decimal, DiscountType: byte, Comment, Features, PluOrder: byte, CanVisible, CanSync: bool, Qty: decimal, FileData: byte[], FileExtension, ExplanationList: List<string>, RelatedList: List<int>, PriceLevels: List<PriceLevelModel>, ServicePrices: List<ServicePrice>, LocalCode, ImageUrl, ThumbnailUrl, ImageFileName, TakeAwayTollPercent, TakeAwayTaxPercent: decimal` |
| `ProductGroupModel` | `ID: short, IDParent: short?, HasChild, HasBrand: bool, IDPicture: int?, ItemName, FileData: byte[], FileExtension` |
| `PriceLevelModel` | `IDLevel: short, Price: decimal` |
| `ServicePrice` | `IDGroup: short, UnitPrice: decimal, Wage: decimal` |
| `StockModel` | `IDProduct: int, IDStore: int?, IDCostCenter: int?, Qty: decimal` |
| `MenuModel` | `Products: List<ProductModel>`, `Categories: List<Category>` — `Category { ID: short, ItemName }` |

### 5.7 امتیاز

`RatingList { Ratings: List<RatingModel> }` — `RatingModel { ID, IDPerson, IDProduct, IDInvoice: int, Rate, RateDate: DateTime }` — `ProductRating { ProductIdList: List<int>, IDPerson, IDInvoice: int, Enable: bool }` — `CommentSampleModel { ID: int, ItemName, IsPositive: bool }`

### 5.8 سایر

`DeskModel` (§4.3) — `IDParent { Id: int }` — `SmsModel` (قالب پیامک: ReceiverNumber, FullName, Price, Title, RFCode, TrackCode, DocNumber, Discount, User, Date, Context, SmsBody, Person, SmsTemplate) — `CarwashTable`, `ItemTypeModel`, `DiscountLevelModel` (§4.2)

---

## 6. مدل دیتابیس (Model.dll — EF6 EDMX)

- اتصال: `ModelDBEntities` → SQL Server `ZhaniyaDB` (Web.config).
- **264 entity** در namespace `Model.DomainModel` + **22 کلاس** در `Model.Entity`.
- جدول‌های اصلی: `Agency, FinancialYear, Salon, Desk, Section, Department, User, UserSetting, AppSetting, Product, ProductGroup, ProductBrand, ProductBarcode, ProductPriceLevel, Person, PersonContact, Invoice, InvoiceItem, InvoiceDesk, Payment, PaymentCheque, TerminalTransaction, Car, CarModel, DiscountLevel, DiscountGroup, MediaFile, ReportTemplate, Printer, OrderType, Rating, RatingComment, RatingCommentSample, CallHistory, StoreRoom, CostCenter, Account, JournalVoucher, Wallet, Notification...`

### 6.1 فیلدهای کلیدی چند جدول اصلی

- **Invoice:** `ID, IDOrderType, InvoiceDate, OrderNo, InvoiceNumber, DiscountPrice, RoundedAmount, Comment, PrintCount, MoreDetails, DiscountType, DiscountPercent, DueDate, IDDiscountCode, IDTrader, IDInvoiceType, idFinancialYear, IDDepartment, Payable, Remaining, TotalPrice, TaxPrice, TollPrice, TotalDiscount, TotalPayment, TotalCash, TotalPosCard, TotalBank, TotalCheque, TotalCredit, StateName, IsBuy, InvoiceItems, Payments, Desks, Person (خریدار), Creator, Editor...`
- **InvoiceItem:** `ID, IDInvoice, IDProduct, IDStore, IDType, Quantity, UnitPrice, DiscountPrice, Comment, DiscountType, DiscountPercent, TaxPercent, TollPercent, Price, Total, TaxPrice, TollPrice, Product, Invoice...`
- **Payment:** `ID, IDJournalVoucher, IDDebtor, IDCreditor, IDPaymentType, IDFund, IDTrader, Price, Amount, PaymentDate, Comment, IDCostCenter, Fund, Trader, Invoice, PaymentType...`
- **Person:** `ID, IDGenre, IDPersonType, IDTradingMethod, IDDiscountGroup, IDPresenter, TrackCode, Barcode, EconomicCode, FirstName, LastName, FatherName, NickName, NationalCode, BirthDate, Address, PostalCode, Lat, Long, Comment, DisplayContact, Charge, CanVisible, TelList, MobileList, WalletBalance...`
- **Product:** `ID, IDGroup, IDBrand, IDProductType, IDOrderType, IDDefaultStore, IDPicture, ProductName, ProductCode, NickName, LatinName, LocalCode, DecimalDigits, UnitSellingPrice, TaxPercent, TollPercent, UnitRatio, MinimumWholeSale, Weight, Discount, DiscountType, Comment, Features, PluOrder, CanVisible, CanSync, Alias, Barcodes, FileData...`
- **Desk:** `ID, IDSalon, IDInvoice, ItemName, Capacity, CanVisible, OrderNo, InvoiceNumber, InvoiceTime, PayableString, Payable, IsBussy`
- **Salon:** `ID, IDAgency, ItemName, EntranceAmount, CanVisible`
- **Agency:** `ID, IDPerson, IDParent, ItemName, ItemCode, SubSystemCode, Comment, AppSetting...`
- **User:** `ID, IDPerson, Username, Password, PasswordSalt, IDActivationState, IDAccessLevel, IsOnline, LastLoginDate, FullName, Permissions...`
- **TerminalTransaction:** `ID, IDTerminal, ErrorCode, RefrenceNo, Amount, TransactionDate, CardNo, Stan, Successful`
- **CallHistory**, **MediaFile** (§4.11), **ReportTemplate:** `ID, IDUser, IDForm, IDReportType, IDAgency, ItemName, Comment, FileData`, **Printer:** `ID, IDAgency, IDPaperType, ItemName, SystemName, ForIntegrated, ForBill, CanVisible`, **OrderType:** `ID, IDPrinter, ItemName, Tax, Toll, CanVisible, CanEdit, IsTakeAway`

---

## 7. اندپوینت‌های obfuscated (نام‌های داخلی) — جدول کامل

برخی اکشن‌ها پس از obfuscation نام تصادفی دارند؛ امضاها و کار احتمالی:

| کنترلر | نام اکشن | امضا | کار احتمالی |
|---|---|---|---|
| InvoiceController | `pkEgsudbFd` | `(PaymentModel) → bool` | ثبت/ callback تراکنش کارتخوان (POS terminal) |
| InvoiceController | `JVTgD6T6fh` | `(UpdateResponse, AppSettingProperty) → ResultModel` | ذخیره‌ی تنظیمات برنامه |
| InvoiceController | `kDhg4CxoNf` | `(int, byte, int) → StiMvcActionResult` | Web Viewer گزارش Stimulsoft |
| ProductController | `itIgmdbOeH` | `(byte) → Task<IEnumerable<int>>` | id کالاهای روز (query داخلی) |
| ProductController | `Firg28xacs` | `(byte) → IQueryable<Product>` | query خام کالاهای روز |
| ProductBrandController | `jKEgay2I8R` / `oM8gR4LN61` / `eqngI91K9r` | `(ProductBrand)→string` / `(ProductBrand)→string` / `(int)→string` | POST / PUT / DELETE برند |
| ProductGroupController | `ofhgeFPkAE` / `AipgrPdhgV` / `FSRghiqrde` | `(ProductGroup)→string` / `(ProductGroup)→string` / `(int)→string` | POST / PUT / DELETE گروه |
| ProductController | `icIgnpwXUR` / `KrTg66hP3M` | `(Product)→string` ×2 | POST / PUT کالا |
| PersonController | `hSDgGB2QYa` / `aETgdeYOdk` / `vvogWMQcnn` | `(string)→void` / `(int,string)→void` / `(int)→void` | عملیات تکمیلی روی شخص (حذف/ویرایش/...) |
| RatingController | `gVmgi8uSVu` / `DLTgX8LClp` | `(int,string)→void` / `(int)→void` | عملیات تکمیلی امتیاز |
| BaseApiController | `OkFgSMgofw` | `(Agency) → bool` | اعتبارسنجی agency |

> ⚠️ اکشن‌های obfuscated با مسیر `api/{Controller}/{نام}` (case-insensitive) در دسترس‌اند.

---

## 8. پیکربندی (Web.config)

| تنظیم | مقدار |
|---|---|
| targetFramework | **4.8** |
| executionTimeout | 20000 ms (20s) |
| customErrors | Off |
| owin:AutomaticAppStartup | false |
| connectionStrings | `ModelDBEntities` → `data source=192.168.1.100;initial catalog=ZhaniyaDB;user id=pana;password=***` (EDMX: `DomainModel.DataModel`) |
| sqlCacheDependency | دیتابیس `ZhaniyaSqlCacheDependency` روی `ModelDBEntities`, pollTime=500 |
| entityFramework | EF6 + SqlClient provider |
| Connector.link | `{DbName: ZhaniyaDB, Server: .\SQLEXPRESS, UserId: sa, Password: (encrypted), IsSingleUser: false}` |
| Assemblyهای کلیدی | System.Web.Http 5.3, MVC 5.3, Newtonsoft.Json 13, EF 6, JWT 11, SignalR 2.4, Stimulsoft 2024.3, Owin 4.2.3, CORS, SSP1126.PcPos (کارتخوان)، TaxCollectData.Library (مالیات)، FarsiLibrary (Persian utils)، Arvin.Model |

---

## 9. نکات امنیتی و دسترسی

1. `[AllowAnonymous]` روی اکشن‌های `Delete` (Desk/Salon/ProductGroup/ProductBrand) —ous leur accès را در IIS/Firewall محدود کنید.
2. CORS `*` — در production محدود کنید.
3. `customErrors=Off` — جزئیات خطا افشا می‌شود.
4. Credentialها در `Web.config`/`Connector.link` — به Secret Store منتقل شود + HTTPS.
5. توکن JWT با SecretKey در کلاس `Security` — کلید را در فایل کانفیگ امن نگهداری کنید.
6.CLK timeout 20s — برای گزارش‌های سنگین Stimulsoft افزایش دهید.

---

*پایان داکیومنت فنی — ZhaniyaApi*
