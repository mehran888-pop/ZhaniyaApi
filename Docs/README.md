# 📚 مستندات API ژانی‌یا — ZhaniyaApi

این پوشه شامل **دو سند کامل و حرفه‌ای** برای API پروژه `ZhaniyaApi` است که با بررسی کامل اسمبلی‌های کامپایل‌شده (`ZhaniyaApi.dll`, `Model.dll`)، فایل‌های PDB و `Web.config` استخراج شده‌اند:

| فایل | نام | محتوا |
|---|---|---|
| [`01-API-User-Guide-FA.md`](./01-API-User-Guide-FA.md) | **دفترچه راهنمای API** | معرفی، معماری، استقرار، احراز هویت (JWT)، قراردادها، نمونه‌کد (curl / JS / C#)، سناریوهای کاری، FAQ |
| [`02-API-Technical-Reference-FA.md`](./02-API-Technical-Reference-FA.md) | **داکیومنت فنی کامل** | **تمام دستورات/اندپوینت‌ها** با متد HTTP، مسیر، پارامترها، بدنه درخواست، پاسخ، مدل‌های داده (DTO) و جدول‌های دیتابیس |
| [`03-API-Commands-QuickRef.md`](./03-API-Commands-QuickRef.md) | **فهرست سریع تمام دستورات** | جدول یک‌جا از همه اندپوینت‌ها (cheat sheet) |
| [`ZhaniyaApi.postman_collection.json`](./ZhaniyaApi.postman_collection.json) | **Collection Postman** | تمام دستورات به‌صورت آماده برای import در Postman |

> نکته: سورس C# کنترلرها در ریپو فقط به‌صورت DLL کامپایل‌شده (همراه با obfuscation) ارائه شده؛ این مستندات با استخراج کامل متادیتای اسمبلی‌ها (`ZhaniyaApi.dll` + `Model.dll`) و فایل‌های PDB تهیه شده‌اند، بنابراین **کامل و منطبق با رفتار واقعی API** هستند.

## خلاصه خیلی کوتاه

- **تکنولوژی:** ASP.NET Web API 2 روی .NET Framework **4.8**، IIS، Entity Framework 6 (EDMX در `Model.dll`)، SQL Server (`ZhaniyaDB`)، احراز هویت **JWT Bearer**، SignalR (نوتیفیکیشن)، Stimulsoft (چاپ/گزارش)، CORS باز (`*`).
- **Base URL:** `http://<server>/` — مسیر API: `api/{controller}/{action}/{id}`
- **ورود:** `POST /api/User/Login` → دریافت `Token` → ارسال در هدر `Authorization: Bearer <token>`
- **صفحه مستندات خودکار:** `/Help` (ASP.NET Web API Help Page)
- **Hub نوتیفیکیشن:** `/signalr` (`NotificationsHub`)
