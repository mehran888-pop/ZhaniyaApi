# ⚡ فهرست سریع تمام دستورات API — ZhaniyaApi (Cheat Sheet)

> Base URL: `http://<server>/` — الگوی مسیر: `api/{controller}/{action}/{id}`
> 🔐 = نیاز به هدر `Authorization: Bearer <token>` (دریافت‌شده از `POST /api/User/Login`)
> ❌ = عمومی (بدون توکن) — به‌جز موارد مشخص‌شده

## 🔑 احراز هویت

| # | متد | مسیر | Auth | بدنه | پاسخ | توضیح |
|---|---|---|---|---|---|---|
| 1 | POST | `/api/User/Login` | ❌ | `LoginModel {Username, Password, SubSystem}` | `UserInfo` (با `Token`) | ورود و دریافت توکن JWT |
| 2 | POST | `/api/User/LoginWithAgency` | 🔐 | `AgencyFinancialYear {IDAgency, IDFinancialYear}` | `IHttpActionResult` | انتخاب شعبه/سال مالی |
| 3 | GET | `/api/User/GetAll` | ❌ | — | `string[]` | لیست نام کاربری‌ها |

## 🪑 سالن و میز

| # | متد | مسیر | Auth | پارامترها | بدنه | پاسخ | توضیح |
|---|---|---|---|---|---|---|---|
| 4 | GET | `/api/Salon/GetAll` | 🔐 | — | — | `Salon[]` | همه‌ی سالن‌ها |
| 5 | GET | `/api/Salon/GetByAgencyId?idAgency=` | 🔐 | `idAgency: int` | — | `Salon[]` | سالن‌های یک شعبه |
| 6 | GET | `/api/Salon/Get/{id}` | 🔐 | `id` در مسیر | — | `Salon` | یک سالن |
| 7 | POST | `/api/Salon` | 🔐 | — | `Salon` | `string` | ثبت سالن |
| 8 | PUT | `/api/Salon` | 🔐 | — | `Salon` | `string` | ویرایش سالن |
| 9 | DELETE | `/api/Salon/Delete/{id}` | ❌⚠️ | `id` در مسیر | — | `string` | حذف سالن |
| 10 | GET | `/api/Desk/GetAll?idSalon=` | 🔐 | `idSalon: short?` | — | `DeskModel[]` | میزهای salón |
| 11 | GET | `/api/Desk/Get/{id}` | 🔐 | `id: int` | — | `Desk` | یک میز |
| 12 | POST | `/api/Desk` | 🔐 | — | `Desk` | `string` | ثبت میز |
| 13 | PUT | `/api/Desk` | 🔐 | — | `Desk` | `string` | ویرایش میز |
| 14 | DELETE | `/api/Desk/Delete/{id}` | ❌⚠️ | `id: int` | — | `string` | حذف میز |

## 📦 گروه کالا، برند، کالا

| # | متد | مسیر | Auth | پارامترها | بدنه | پاسخ | توضیح |
|---|---|---|---|---|---|---|---|
| 15 | GET | `/api/ProductGroup/GetAll?idParent=&idProductType=&setPicture=&JustParent=` | 🔐 | query | — | `ProductGroupModel[]` | درخت گروه‌ها |
| 16 | GET | `/api/ProductGroup/Get/{id}` | 🔐 | `id` | — | `ProductGroup` | یک گروه |
| 17 | POST | `/api/ProductGroup/ofhgeFPkAE` | 🔐 | — | `ProductGroup` | `string` | ثبت گروه (نام obfuscated) |
| 18 | PUT | `/api/ProductGroup/AipgrPdhgV` | 🔐 | — | `ProductGroup` | `string` | ویرایش گروه |
| 19 | DELETE | `/api/ProductGroup/FSRghiqrde?id=` | ❌⚠️ | `id: int` | — | `string` | حذف گروه |
| 20 | GET | `/api/ProductBrand/GetAll?idParent=&setPicture=` | ❌ | query | — | `ProductGroupModel[]` | گروه‌های دارای برند |
| 21 | GET | `/api/ProductBrand/Get/{id}` | ❌ | `id` | — | `ProductBrand` | یک برند |
| 22 | POST | `/api/ProductBrand/jKEgay2I8R` | ❌ | — | `ProductBrand` | `string` | ثبت برند |
| 23 | PUT | `/api/ProductBrand/oM8gR4LN61` | ❌ | — | `ProductBrand` | `string` | ویرایش برند |
| 24 | DELETE | `/api/ProductBrand/eqngI91K9r?id=` | ❌⚠️ | `id: int` | — | `string` | حذف برند |
| 25 | GET | `/api/Product/GetAll?idGroup=&idBrand=&idOrderType=&idProductType=&setPicture=&sortType=&canSync=&setInventory=` | 🔐 | query | — | `ProductModel[]` | لیست کالا با فیلتر |
| 26 | GET | `/api/Product/Get/{id}` | 🔐 | `id` | — | `ProductModel` | یک کالا |
| 27 | GET | `/api/Product/GetProductBarcode?barcode=` | 🔐 | `barcode` | — | `ProductModel` | جستجو با بارکد |
| 28 | GET | `/api/Product/GetByGroupId?idGroup=` | 🔐 | `idGroup: int` | — | `Product[]` | کالاهای یک گروه |
| 29 | GET | `/api/Product/GetInventory?id=&idFinancialYear=&idStore=&idCostCenter=` | 🔐 | query | — | `StockModel` | موجودی انبار |
| 30 | GET | `/api/Product/GetSlider` | 🔐 | — | — | `HttpResponseMessage[]` | تصاویر اسلایدر |
| 31 | GET | `/api/Product/GetSliderID` | 🔐 | — | — | `int[]` | id اسلایدرها |
| 32 | GET | `/api/Product/GetTodayProducts` | 🔐 | — | — | `int[]` | کالاهای امروز |
| 33 | GET | `/api/Product/GetDailyProductList?date=` | 🔐 | `date: byte` | — | `ProductModel[]` | کالای روز |
| 34 | GET | `/api/Product/GetByIdInvoice?value=` | 🔐 | `value: int` | — | `ProductRating` | امتیازهای فاکتور |
| 35 | GET | `/api/Product/GetByInvoiceNumber?value=` | 🔐 | `value: int` | — | `ProductRating` | امتیاز بر اساس شماره فاکتور |
| 36 | GET | `/api/Product/GetByOrderNo?value=` | 🔐 | `value: short` | — | `ProductRating` | امتیاز بر اساس شماره سفارش |
| 37 | POST | `/api/Product/icIgnpwXUR` | 🔐 | — | `Product` | `string` | ثبت کالا |
| 38 | PUT | `/api/Product/KrTg66hP3M` | 🔐 | — | `Product` | `string` | ویرایش کالا |

## 👤 اشخاص / مشتریان / دفترکل

| # | متد | مسیر | Auth | پارامترها | بدنه | پاسخ | توضیح |
|---|---|---|---|---|---|---|---|
| 39 | GET | `/api/Person/GetAll?canVisible=&setPicture=` | 🔐 | query | — | `PersonModel[]` | لیست اشخاص |
| 40 | GET | `/api/Person/Get/{id}` | 🔐 | `id` | — | `PersonModel` | یک شخص |
| 41 | GET | `/api/Person/GetByTel?value=` | 🔐 | `value: string` | — | `PersonModel` | جستجو با شماره تماس |
| 42 | GET | `/api/Person/GetBill?idAccount=&since=&until=&showDetails=` | 🔐 | query (تاریخ رشته‌ای) | — | `LedgerItem[]` | صورت-hساب/دفترکل |
| 43 | POST | `/api/Person/Insert` | 🔐 | — | `NewPersonModel` | `ResultModel` | ثبت شخص جدید |

## 🧾 فاکتور و پرداخت

| # | متد | مسیر | Auth | پارامترها | بدنه | پاسخ | توضیح |
|---|---|---|---|---|---|---|---|
| 44 | POST | `/api/Invoice/Insert` | 🔐 | — | `InvoiceModel` | `InvoiceResult` | ثبت فاکتور |
| 45 | POST | `/api/Invoice/Update` | 🔐 | — | `InvoiceModel` | `InvoiceResult` | ویرایش فاکتور |
| 46 | POST | `/api/Invoice/CheckOut` | 🔐 | — | `InvoiceModel` (با Payments) | `InvoiceResult` | تسویه/بستن فاکتور |
| 47 | GET | `/api/Invoice/Get/{id}` | 🔐 | `id` | — | `InvoiceModel` | یک فاکتور |
| 48 | GET | `/api/Invoice/GetAll?idFinancialYear=&since=&until=&idAccount=&invoiceNo=&isPersianDate=` | 🔐 | query (`since/until`: long ticks) | — | `InvoiceModel[]` | لیست فاکتورها با فیلتر تاریخ |
| 49 | POST | `/api/Invoice/Delete` | 🔐 | — | `int` (id در بدنه) | `ResultModel` | حذف فاکتور |
| 50 | POST | `/api/Invoice/ChangeDesk` | 🔐 | — | `InvoiceDeskModel {IDInvoice, Desks[]}` | `ResultModel` | جابجایی فاکتور بین میزها |
| 51 | POST | `/api/Invoice/InsertCar` | 🔐 | — | `NewCar` | `ResultModel` | ثبت خودرو (کارواش) |
| 52 | POST | `/api/Invoice/pkEgsudbFd` | 🔐 | — | `PaymentModel` | `bool` | تراکنش کارتخوان (obfuscated) |
| 53 | GET | `/api/Invoice/Print?id=&setFactor=&setKitchen=&isTakeAway=&forInsert=&idTemplate=` | 🔐 | query | — | `ResultModel` | چاپ فاکتور/آشپزخانه |
| 54 | GET | `/api/Invoice/GetBillPrint?id=&exportType=&idTemplate=` | 🔐 | query | — | فایل (PDF/Excel/...) | خروجی فاکتور با Stimulsoft |
| 55 | GET | `/api/Invoice/kDhg4CxoNf?id=&...` | 🔐 | query | — | صفحه گزارش Stimulsoft | Web Viewer گزارش (obfuscated) |

## 🍽️ منوی دیجیتال (Digital Menu)

| # | متد | مسیر | Auth | پارامترها | بدنه | پاسخ | توضیح |
|---|---|---|---|---|---|---|---|
| 56 | GET | `/api/DigitalMenu/LogOn?tableId=` | ❌ | `tableId: short` | — | `MenuUserInfo` (با Token) | ورود میز (QR) |
| 57 | GET | `/api/DigitalMenu/GetMenu?idGroup=&idBrand=&idOrderType=&idProductType=&setPicture=&sortType=&canSync=&setInventory=` | 🔐 | query | — | `MenuModel {Categories, Products}` | منوی کامل |
| 58 | GET | `/api/DigitalMenu/GetProductGroups?idParent=&idProductType=&setPicture=&JustParent=` | ❌ | query | — | `ProductGroupModel[]` | گروه‌های منو |
| 59 | GET | `/api/DigitalMenu/GetCustomer?mobileNo=` | 🔐 | `mobileNo` | — | `Customer` | جستجوی مشتری با موبایل |
| 60 | POST | `/api/DigitalMenu/InsertCustomer` | 🔐 | — | `Customer` | `ResultModel` | ثبت مشتری |
| 61 | GET | `/api/DigitalMenu/GetImage?id=` | ❌ | `id: int` | — | تصویر (کش 24h) | تصویر محصول/گروه |
| 62 | POST | `/api/DigitalMenu/InsertOrder` | 🔐 | — | `InvoiceDto` | `InvoiceResult` | ثبت سفارش از منو |

## 🖼️ فایل‌های چندرسانه‌ای

| # | متد | مسیر | Auth | پارامترها | پاسخ | توضیح |
|---|---|---|---|---|---|---|
| 63 | GET | `/api/MediaFile/Get/{id}` | ❌ | `id` | `MediaFile` (کش 24h + SqlDependency) | متادیتا/داده فایل |
| 64 | GET | `/api/MediaFile/GetFile/{id}` | ❌ | `id` | دانلود فایل | دریافت باینری فایل |
| 65 | GET | `/api/MediaFile/GetClipFile` | 🔐 | — | کلیپ | کلیپ تبلیغاتی |
| 66 | GET | `/api/MediaFile/GetVideo?videoName=` | ❌ | `videoName` | ویدیو | استریم ویدیو |

## ⭐ امتیاز و lookup عمومی

| # | متد | مسیر | Auth | پارامترها | بدنه | پاسخ | توضیح |
|---|---|---|---|---|---|---|---|
| 67 | GET | `/api/Rating/GetCommentSample` | 🔐 | — | — | `CommentSampleModel[]` | نمونه‌ی نظرهای آماده |
| 68 | POST | `/api/Rating/Insert` | 🔐 | — | `RatingList` | `ResultModel` | ثبت امتیاز |
| 69 | GET | `/api/BaseGetter/Get?prefix=&tableName=` | 🔐 | query | — | `ItemTypeModel[]` | lookup عمومی جدول‌ها |
| 70 | GET | `/api/BaseGetter/GetCarwashTables` | 🔐 | — | — | `CarwashTable` — Personnel/CarModel/CarCompany/ColorType |
| 71 | GET | `/api/BaseGetter/GetAllDiscountLevel` | 🔐 | — | — | `DiscountLevelModel[]` | سطوح تخفیف |
| 72 | GET | `/api/General/InsertCallHistory?appId=&callerId=&calledId=` | ❌ | query | — | `int` | ثبت تماس تلفنی |

## 🌐 صفحات دیگر

| # | متد | مسیر | توضیح |
|---|---|---|---|
| 73 | GET | `/` | صفحه اصلی «Zhaniya API» |
| 74 | GET | `/Help` | صفحه مستندات خودکار Web API (Help Page) |
| 75 | WS | `/signalr` | SignalR Hub — `NotificationsHub` (نوتیفیکیشن لحظه‌ای) |

---

### ⚡ نمونه سریع (curl)

```bash
# ورود
TOKEN=$(curl -s -X POST http://server/api/User/Login \
  -H "Content-Type: application/json" \
  -d '{"Username":"admin","Password":"123456","SubSystem":1}' | jq -r '.Token')

# فراخوانی‌های محافظت‌شده
curl -s "http://server/api/Desk/GetAll?idSalon=1" -H "Authorization: Bearer $TOKEN"
curl -s "http://server/api/Product/GetAll?setPicture=true&sortType=0" -H "Authorization: Bearer $TOKEN"
```

> ⚠️ اکشن‌های با `❌⚠️` (`AllowAnonymous`) عمومی هستند — دسترسی حذف را در سطح سرور محدود کنید.
> 📄 برای جزئیات کامل هر دستور (DTOها، جدول‌ها، معماری) → سند [02-API-Technical-Reference-FA.md](./02-API-Technical-Reference-FA.md)
