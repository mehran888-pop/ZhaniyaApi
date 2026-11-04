# بررسی فنی API نرم‌افزار ژانیا (ZhaniyaApi)

> تاریخ بررسی: ۲۰۲۶/۱۱/۰۴ — بر اساس استقرار موجود در همین مخزن (فقط باینری)

## ۱. شناسایی کلی

| مورد | مقدار |
|---|---|
| نوع پروژه | ASP.NET Web API 2 روی .NET Framework (میزبانی IIS / `Global.asax`) |
| کامپایل | فقط خروجی کامپایل‌شده (`bin/ZhaniyaApi.dll`) — **سورس کد در مخزن وجود ندارد** |
| پایگاه‌داده | SQL Server (نمونه‌ی `.\SQLEXPRESS`) روی دیتابیس `ZhaniyaDB` — اتصال در فایل `Connector.link` |
| ORM | Entity Framework 6 (`Model.dll` شامل موجودیت‌های دامنه) |
| احراز هویت | JWT (`JWT.dll`, `JWTManager`, `DecodeToken`, `IsExpiredToken`, `TokenExpirationTime`) |
| گزارش‌ساز | Stimulsoft Reports (`Stimulsoft.Report*.dll`) |
| بلادرنگ | SignalR (`Microsoft.AspNet.SignalR.*`) + سرویس‌های اطلاع‌رسانی (`ZhaniyaApi.Notifications`) |
| CORS | فعال (`System.Web.Cors`, `System.Web.Http.Cors`) |
| موارد جانبی | مالیات/عوارض فروش، کارتکس، سند حسابداری، فرمول تولید، PC-POS، پیامک |

## ۲. کنترلرهای REST (استخراج‌شده از متادیتای اسمبلی)

| کنترلر | متدها |
|---|---|
| `UserController` | `GetAll` , `LoginWithAgency` (صدور JWT) |
| `InvoiceController` | `Insert` , `Update` , `Get` , `GetAll` , `CheckOut` , `ChangeDesk` |
| `ProductController` | `GetAll` , `Get` , `GetSlider` , `GetTodayProducts` , `GetDailyProduct` , `GetDailyProductList` , `GetByGroupId` , `GetProductBarcode` , `GetByIdInvoice` , `GetByInvoiceNumber` |
| `PersonController` | `GetAll` , `Get` , `Insert` |
| `DeskController` / `SalonController` | `GetAll` , `Get` |
| `ProductGroupController` / `ProductBrandController` | `GetAll` |
| `DigitalMenuController` | `LogOn` , `GetMenu` , `GetProductGroups` , `GetCustomer` , `GetImage` , `InsertOrder` |
| `RatingController` | `Insert` , `GetCommentSample` |
| `BaseGetterController` | `Get` , `GetBase` , `GetCarwashTables` , `GetAllDiscountLevel` |
| `MediaFileController` | `Get` , `GetFile` |

مسیریابی از نوع Attribute Routing (`RoutePrefix` / `Route`) به‌همراه مسیر پیش‌فرض `api/{controller}/{id}` و HelpPage استاندارد Web API.

## ۳. مدل داده‌ی کلیدی (از `Model.dll`)

- **فاکتور**: `InvoiceNumber`, `InvoiceDate`, `InvoicePersianDate`, `InvoiceTime`, `InvoiceItems`, `InvoiceDesks`, `AbsTotalPrice`, `AbsTotalDiscount`, `AbsPayable`, `AbsTotalPayment`
- **اقلام فاکتور**: `AbsQuantity`, `AbsPrice`, `AbsDiscount`, `AbsFinalAmount`, `AbsProductTotalCost`
- **اعتباری**: `TotalCredit`, `EnableCreditPay`, `IDCreditor`, `AddInvoiceCardex`, `AddInvoiceJournalVoucher`, `AddPayment`, `AdvancePayment`
- **کالا/شخص/میز**: `DBProduct`, `ProductBarcodes`, `ProductPriceLevels`, `DBPerson`, `DBPersonContact`, `DBDesk`, `DBUser`

## ۴. نقاط قوت

1. معماری REST استاندارد و تفکیک‌شده (Invoice / Product / Person / Desk / Menu).
2. احراز هویت JWT با قابلیت انقضا (`TokenExpirationTime`) — مناسب برای کلاینت‌های موبایل/منوی دیجیتال.
3. پشتیبانی از منوی دیجیتال، رزرو میز، نظرسنجی و چاپ فاکتور.
4. وجود HelpPage داخلی برای مستندسازی زنده‌ی API.
5. SignalR برای رویدادهای بلادرنگ.

## ۵. ریسک‌ها و نقاط ضعف (مهم)

| # | ریسک | شدت | توضیح و توصیه |
|---|---|---|---|
| 1 | **نبود سورس کد** | بالا | مخزن فقط DLL/PDB است؛ نگه‌داری، دیباگ و امن‌سازی ناممکن است. سورس باید به مخزن اضافه و باینری‌ها از گیت حذف شوند. |
| 2 | **اعتبارنامه‌ی `sa` در مخزن** | بحرانی | `Connector.link` شامل `UserId=sa` و رمز عبور (Obfuscation ضعیف/Base64) است که به‌صورت عمومی کامیت شده. باید فوراً رمز `sa` عوض، از اکانت با حداقل دسترسی و Integrated Security استفاده و فایل اتصال از مخزن حذف شود. |
| 3 | **فایل‌های PDB و ZIP در استقرار** | متوسط | `bin/*.pdb` و `ZhaniyaApi.zip` (۳۵MB) اطلاعات داخلی را افشا می‌کنند؛ باید از انتشار عمومی حذف شوند. |
| 4 | **وابستگی به IP/شبکه‌ی ثابت** | بالا | SignalR و مسیرهای API فقط با دسترسی مستقیم به سرور چانیا کار می‌کنند؛ برای گزارش‌گیری مدیریتی از بیرون به IP ثابت/پورت‌فوروارد نیاز است. **راه‌حل این پروژه: کانکتور outbound بدون نیاز به IP ثابت.** |
| 5 | **نبود endpoint گزارش‌گیری** | بالا | هیچ متد «گزارش فروش از تاریخ تا تاریخ / اقلام فروش / اعتباری‌ها» وجود ندارد؛ تمام بار گزارش روی دیتابیس است. لایه‌ی همگام‌ساز + گزارش این پروژه این خلأ را می‌پوشاند. |
| 6 | **CORS باز** | متوسط | فعال‌بودن CORS بدون محدودیت Origin احتمال سوءاستفاده‌ی سایت‌های ثالث را بالا می‌برد. لیست Origin مجاز تعریف شود. |
| 7 | **JWT بدون چرخش کلید مشخص** | متوسط | قدرت کلید امضا و چرخش آن از متادیتا قابل تأیید نیست؛ کلید قوی + HTTPS اجباری + refresh token توصیه می‌شود. |
| 8 | **بدون Rate limiting / لاگ امنیتی** | متوسط | روی endpointهای عمومی (LoginWithAgency, DigitalMenu) باید محدودیت نرخ و مانیتورینگ اضافه شود. |
| 9 | **SQL Express محلی** | پایین | `.\SQLEXPRESS` محدودیت حجم/اتصال دارد؛ برای چندشعبه‌ای باید به SQL Server کامل مهاجرت کرد. |

## ۶. جمع‌بندی

API ژانیا یک لایه‌ی REST کاربردی روی دیتابیس صندوق فروش است، اما **برای مدیریت چندشعبه‌ای و گزارش‌گیری از راه دور طراحی نشده**. معماری پیشنهادی این مخزن (`cloud/` + `connector/`) بدون تغییر در نرم‌افزار ژانیا و بدون نیاز به IP ثابت، داده‌ها را به‌صورت outbound به سرور ابری منتقل کرده و داشبورد مدیریتی PHP با گزارش‌های فروش، اقلام، اعتباری و لحظه‌ای ارائه می‌دهد.
