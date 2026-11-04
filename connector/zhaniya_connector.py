#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
Zhaniya Connector — اپ رابط اتصال نرم‌افزار ژانیا به سرور ابری
===============================================================

این برنامه روی سیستم فروشگاه (همان رایانه‌ای که نرم‌افزار ژانیا و SQL Server
روی آن نصب است) اجرا می‌شود و داده‌های فروش را به‌صورت «خروجی» (outbound)
به سرور ابری ارسال می‌کند؛ بنابراین:

  * به IP ثابت نیاز نیست
  * نیازی به باز کردن پورت روی مودم فروشگاه نیست
  * پشت NAT/فایروال کار می‌کند

منابع داده (mode در config.json):
  * "api"       : فراخوانی REST همان ZhaniyaApi روی لوکال (JWT)
  * "sqlserver" : خواندن مستقیم دیتابیس ZhaniyaDB با pyodbc
  * "demo"      : تولید داده‌ی آزمایشی برای تست کل زنجیره

قابلیت‌ها:
  * همگام‌سازی افزایشی (watermark) — فقط داده‌ی جدید
  * صف آفلاین (SQLite) و ارسال مجدد با backoff
  * امضای HMAC-SHA256 هر بسته + جلوگیری از replay
  * Heartbeat دوره‌ای برای نمایش «متصل» در داشبورد

اجرا:
  python zhaniya_connector.py --config config.json
"""

from __future__ import annotations

import argparse
import hashlib
import hmac
import json
import os
import random
import sqlite3
import ssl
import sys
import time
import urllib.error
import urllib.request
import uuid
from datetime import datetime, timedelta, timezone

HERE = os.path.dirname(os.path.abspath(__file__))
DEFAULT_CONFIG = os.path.join(HERE, "config.json")


# --------------------------------------------------------------------------- #
#  ابزارها
# --------------------------------------------------------------------------- #
def log(msg: str) -> None:
    ts = datetime.now().strftime("%Y-%m-%d %H:%M:%S")
    print(f"[{ts}] {msg}", flush=True)


def dig(obj, path, default=None):
    """دریافت مقدار تو در تو با مسیر 'a.b.c'"""
    cur = obj
    for part in str(path).split("."):
        if isinstance(cur, dict) and part in cur:
            cur = cur[part]
        elif isinstance(cur, list) and part.isdigit() and int(part) < len(cur):
            cur = cur[int(part)]
        else:
            return default
    return cur if cur is not None else default


def to_float(v, default=0.0):
    try:
        return float(v)
    except (TypeError, ValueError):
        return default


def to_date(v):
    """نرمال‌سازی تاریخ به YYYY-MM-DD (میلادی)"""
    if v is None:
        return datetime.now().strftime("%Y-%m-%d")
    s = str(v).strip()
    for fmt in ("%Y-%m-%d", "%Y/%m/%d", "%d/%m/%Y", "%Y-%m-%dT%H:%M:%S", "%Y-%m-%d %H:%M:%S"):
        try:
            return datetime.strptime(s[:19], fmt).strftime("%Y-%m-%d")
        except ValueError:
            continue
    return datetime.now().strftime("%Y-%m-%d")


def to_time(v):
    if v is None:
        return datetime.now().strftime("%H:%M:%S")
    s = str(v).strip()
    if len(s) >= 8 and s[2] == ":":
        return s[:8]
    if len(s) >= 5 and s[2] == ":":
        return s[:5] + ":00"
    return datetime.now().strftime("%H:%M:%S")


# --------------------------------------------------------------------------- #
#  صف آفلاین (outbox) روی SQLite محلی
# --------------------------------------------------------------------------- #
class Outbox:
    def __init__(self, path: str):
        self.conn = sqlite3.connect(path, check_same_thread=False)
        self.conn.execute(
            """CREATE TABLE IF NOT EXISTS outbox (
                   id INTEGER PRIMARY KEY AUTOINCREMENT,
                   body TEXT NOT NULL,
                   created_at TEXT NOT NULL,
                   attempts INTEGER NOT NULL DEFAULT 0
               )"""
        )
        self.conn.commit()

    def push(self, body: str) -> None:
        self.conn.execute(
            "INSERT INTO outbox (body, created_at) VALUES (?, ?)",
            (body, datetime.now(timezone.utc).isoformat()),
        )
        self.conn.commit()

    def pop_batch(self, limit: int = 20):
        cur = self.conn.execute(
            "SELECT id, body FROM outbox ORDER BY id LIMIT ?", (limit,)
        )
        return cur.fetchall()

    def remove(self, row_id: int) -> None:
        self.conn.execute("DELETE FROM outbox WHERE id = ?", (row_id,))
        self.conn.commit()

    def bump(self, row_id: int) -> None:
        self.conn.execute(
            "UPDATE outbox SET attempts = attempts + 1 WHERE id = ?", (row_id,)
        )
        self.conn.commit()

    def size(self) -> int:
        return self.conn.execute("SELECT COUNT(*) FROM outbox").fetchone()[0]


# --------------------------------------------------------------------------- #
#  کلاینت سرور ابری — ارسال outbound با امضای HMAC
# --------------------------------------------------------------------------- #
class CloudClient:
    def __init__(self, cfg: dict):
        base = cfg["cloud_url"].rstrip("/")
        self.endpoint = base + "/" + cfg.get("ingest_path", "api/ingest.php").lstrip("/")
        self.health = base + "/" + cfg.get("health_path", "api/health.php").lstrip("/")
        self.device_key = cfg["device_key"]
        self.secret = cfg["device_secret"]
        self.timeout = int(cfg.get("http_timeout", 30))
        self.verify_tls = bool(cfg.get("verify_tls", True))
        self._ctx = None
        if self.endpoint.startswith("https") and not self.verify_tls:
            self._ctx = ssl.create_default_context()
            self._ctx.check_hostname = False
            self._ctx.verify_mode = ssl.CERT_NONE

    def _post(self, payload: dict) -> dict:
        body = json.dumps(payload, ensure_ascii=False).encode("utf-8")
        ts = str(int(time.time()))
        sig = hmac.new(
            self.secret.encode("utf-8"), (ts + ".").encode("utf-8") + body, hashlib.sha256
        ).hexdigest()
        req = urllib.request.Request(
            self.endpoint,
            data=body,
            method="POST",
            headers={
                "Content-Type": "application/json; charset=utf-8",
                "X-Device-Key": self.device_key,
                "X-Timestamp": ts,
                "X-Signature": sig,
                "User-Agent": "ZhaniyaConnector/1.0",
            },
        )
        with urllib.request.urlopen(req, timeout=self.timeout, context=self._ctx) as resp:
            return json.loads(resp.read().decode("utf-8"))

    def push(self, payload: dict) -> dict:
        return self._post(payload)

    def heartbeat(self) -> bool:
        try:
            self._post({"batch_id": str(uuid.uuid4()), "note": "heartbeat"})
            return True
        except Exception as exc:  # noqa: BLE001
            log(f"heartbeat ناموفق: {exc}")
            return False


# --------------------------------------------------------------------------- #
#  منابع داده
# --------------------------------------------------------------------------- #
class DemoSource:
    """تولید داده‌ی آزمایشی — برای تست داشبورد بدون ژانیای واقعی"""

    PRODUCTS = [
        ("P-100", "چلوکباب کوبیده", "غذای اصلی", 980000),
        ("P-101", "چلو جوجه کباب", "غذای اصلی", 860000),
        ("P-102", "قورمه سبزی", "غذای اصلی", 720000),
        ("P-104", "پیتزا مخصوص", "فست فود", 650000),
        ("P-107", "نوشابه خانواده", "نوشیدنی", 90000),
        ("P-110", "بستنی سنتی", "دسر", 120000),
    ]
    PERSONS = [
        ("C-1", "علی رضایی"),
        ("C-2", "مریم احمدی"),
        ("C-3", "حسین محمدی"),
    ]

    def __init__(self, cfg: dict):
        self.seq = int(time.time()) % 100000

    def fetch(self, since_iso: str) -> dict:
        self.seq += 1
        now = datetime.now()
        pay = random.choices(
            ["cash", "cash", "cash", "card", "credit"], weights=[5, 3, 2, 3, 2]
        )[0]
        items = []
        total = 0.0
        for _ in range(random.randint(1, 4)):
            p = random.choice(self.PRODUCTS)
            qty = random.randint(1, 3)
            line = p[3] * qty
            total += line
            items.append(
                {
                    "source_id": f"D{self.seq}-{p[0]}",
                    "product_source_id": p[0],
                    "product_name": p[1],
                    "quantity": qty,
                    "unit_price": p[3],
                    "discount": 0,
                    "total": line,
                }
            )
        person = random.choice(self.PERSONS) if pay == "credit" or random.random() < 0.2 else None
        inv = {
            "source_id": f"DEMO-{self.seq}",
            "invoice_number": str(900000 + self.seq),
            "invoice_date": now.strftime("%Y-%m-%d"),
            "invoice_time": now.strftime("%H:%M:%S"),
            "person_source_id": person[0] if person else "",
            "person_name": person[1] if person else "",
            "desk": random.choice(["سالن ۱", "تراس", "VIP"]),
            "user_name": "کاربر صندوق",
            "pay_type": pay,
            "total": total,
            "discount": 0,
            "payable": total,
            "paid": 0 if pay == "credit" else total,
            "status": "done",
            "items": items,
        }
        batch = {"invoices": [inv]}
        if pay == "credit" and person:
            batch["credit_transactions"] = [
                {
                    "source_id": f"DCR-{self.seq}",
                    "person_source_id": person[0],
                    "person_name": person[1],
                    "invoice_source_id": inv["source_id"],
                    "type": "sale",
                    "amount": total,
                    "txn_date": now.strftime("%Y-%m-%d"),
                    "description": "فروش اعتباری (دمو)",
                }
            ]
        return batch


class ApiSource:
    """
    خواندن از ZhaniyaApi نصب‌شده روی لوکال (پیش‌فرض http://127.0.0.1/ZhaniyaApi)

    نکته: نام فیلدها در نسخه‌های مختلف ممکن است متفاوت باشد؛ از HelpPage
    خود API (Areas/HelpPage) مدل دقیق پاسخ‌ها را ببینید و در صورت نیاز
    بخش "api_map" و "api_endpoints" را در config.json مطابقت دهید.
    """

    def __init__(self, cfg: dict):
        acfg = cfg.get("api", {})
        self.base = acfg.get("base_url", "http://127.0.0.1/ZhaniyaApi").rstrip("/")
        self.username = acfg.get("username", "admin")
        self.password = acfg.get("password", "")
        self.agency = acfg.get("agency", "")
        self.login_path = acfg.get("login_path", "api/User/LoginWithAgency")
        self.invoices_path = acfg.get("invoices_path", "api/Invoice/GetAll")
        self.products_path = acfg.get("products_path", "api/Product/GetAll")
        self.persons_path = acfg.get("persons_path", "api/Person/GetAll")
        self.map = acfg.get("api_map", {})
        self.timeout = int(acfg.get("timeout", 30))
        self.token = None
        self.token_exp = 0.0

    def _login(self) -> None:
        body = json.dumps(
            {"Username": self.username, "Password": self.password, "Agency": self.agency}
        ).encode("utf-8")
        req = urllib.request.Request(
            self.base + "/" + self.login_path,
            data=body,
            method="POST",
            headers={"Content-Type": "application/json"},
        )
        with urllib.request.urlopen(req, timeout=self.timeout) as resp:
            data = json.loads(resp.read().decode("utf-8"))
        self.token = dig(data, self.map.get("token", "Token"), dig(data, "token"))
        self.token_exp = time.time() + 55 * 60
        log("ورود به ZhaniyaApi موفق بود (JWT دریافت شد)")

    def _get(self, path: str, params: dict | None = None):
        if not self.token or time.time() > self.token_exp:
            self._login()
        url = self.base + "/" + path
        if params:
            qs = "&".join(f"{k}={v}" for k, v in params.items() if v not in (None, ""))
            if qs:
                url += "?" + qs
        req = urllib.request.Request(
            url, headers={"Authorization": "Bearer " + self.token, "Accept": "application/json"}
        )
        with urllib.request.urlopen(req, timeout=self.timeout) as resp:
            return json.loads(resp.read().decode("utf-8"))

    def fetch(self, since_iso: str) -> dict:
        m = self.map
        params = {"from": since_iso[:10], "to": datetime.now().strftime("%Y-%m-%d")}
        raw_invoices = dig(self._get(self.invoices_path, params), m.get("invoices", "Invoices"), []) or []
        batch: dict = {"invoices": []}

        for r in raw_invoices:
            items = []
            for it in dig(r, m.get("invoice_items", "InvoiceItems"), []) or []:
                items.append(
                    {
                        "source_id": str(dig(it, m.get("item_id", "IDInvoiceItem"), uuid.uuid4().hex[:12])),
                        "product_source_id": str(dig(it, m.get("item_product_id", "IDProduct"), "")),
                        "product_name": str(dig(it, m.get("item_name", "ProductName"), "—")),
                        "quantity": to_float(dig(it, m.get("item_qty", "Quantity"), 1), 1),
                        "unit_price": to_float(dig(it, m.get("item_price", "Price"))),
                        "discount": to_float(dig(it, m.get("item_discount", "Discount"))),
                        "total": to_float(dig(it, m.get("item_total", "Total"))),
                    }
                )
            total = to_float(dig(r, m.get("inv_total", "AbsTotalPrice")))
            payable = to_float(dig(r, m.get("inv_payable", "AbsPayable"), total))
            paid = to_float(dig(r, m.get("inv_paid", "AbsTotalPayment"), payable))
            batch["invoices"].append(
                {
                    "source_id": str(dig(r, m.get("inv_id", "IDInvoice"), uuid.uuid4().hex[:12])),
                    "invoice_number": str(dig(r, m.get("inv_number", "InvoiceNumber"), "")),
                    "invoice_date": to_date(dig(r, m.get("inv_date", "InvoiceDate"))),
                    "invoice_time": to_time(dig(r, m.get("inv_time", "InvoiceTime"))),
                    "person_source_id": str(dig(r, m.get("inv_person_id", "IDPerson"), "")),
                    "person_name": str(dig(r, m.get("inv_person_name", "PersonName"), "")),
                    "desk": str(dig(r, m.get("inv_desk", "DeskName"), "")),
                    "user_name": str(dig(r, m.get("inv_user", "UserName"), "")),
                    "pay_type": self._pay_type(dig(r, m.get("inv_pay_type", "PayType"))),
                    "total": total,
                    "discount": to_float(dig(r, m.get("inv_discount", "AbsTotalDiscount"))),
                    "payable": payable,
                    "paid": paid,
                    "status": str(dig(r, m.get("inv_status", "Status"), "done")),
                    "items": items,
                }
            )
        return batch

    @staticmethod
    def _pay_type(v):
        s = str(v or "").lower()
        if "credit" in s or "اعتبار" in s or s == "2":
            return "credit"
        if "card" in s or "کارت" in s or s == "1":
            return "card"
        if "mixed" in s or "ترکیب" in s:
            return "mixed"
        return "cash"


class SqlServerSource:
    """
    خواندن مستقیم از ZhaniyaDB (نیازمند pyodbc)
    کوئری‌ها در config.json بخش "sql" قابل تنظیم‌اند؛ کوئری‌های پیش‌فرض
    بر اساس نام جداول متداول ژانیاست (DBInvoice / DBInvoiceItem / DBProduct ...)
    و باید با اسکیمای نسخه‌ی شما تطبیق داده شوند.
    """

    def __init__(self, cfg: dict):
        try:
            import pyodbc  # noqa: PLC0415
        except ImportError as exc:
            raise RuntimeError(
                "برای mode=sqlserver باید pyodbc نصب باشد: pip install pyodbc"
            ) from exc
        self.pyodbc = pyodbc
        scfg = cfg.get("sql", {})
        self.conn_str = scfg.get(
            "connection_string",
            "DRIVER={ODBC Driver 17 for SQL Server};SERVER=.\\SQLEXPRESS;"
            "DATABASE=ZhaniyaDB;UID=sa;PWD=change-me",
        )
        self.queries = scfg.get("queries", {})
        self.conn = None

    def _ensure(self):
        if self.conn is None:
            self.conn = self.pyodbc.connect(self.conn_str, timeout=10)

    def fetch(self, since_iso: str) -> dict:
        self._ensure()
        cur = self.conn.cursor()
        q_inv = self.queries.get(
            "invoices",
            "SELECT TOP (200) * FROM DBInvoice WHERE InvoiceDate >= ? ORDER BY InvoiceDate DESC",
        )
        cur.execute(q_inv, since_iso[:10])
        cols = [c[0] for c in cur.description]
        rows = [dict(zip(cols, r)) for r in cur.fetchall()]
        batch: dict = {"invoices": []}
        for r in rows:
            batch["invoices"].append(
                {
                    "source_id": str(dig(r, "IDInvoice", uuid.uuid4().hex[:12])),
                    "invoice_number": str(dig(r, "InvoiceNumber", "")),
                    "invoice_date": to_date(dig(r, "InvoiceDate")),
                    "invoice_time": to_time(dig(r, "InvoiceTime")),
                    "person_source_id": str(dig(r, "IDPerson", "") or ""),
                    "person_name": str(dig(r, "PersonName", "") or ""),
                    "desk": str(dig(r, "DeskName", "") or ""),
                    "user_name": str(dig(r, "UserName", "") or ""),
                    "pay_type": "credit"
                    if to_float(dig(r, "AbsTotalPayment")) < to_float(dig(r, "AbsPayable"))
                    else "cash",
                    "total": to_float(dig(r, "AbsTotalPrice")),
                    "discount": to_float(dig(r, "AbsTotalDiscount")),
                    "payable": to_float(dig(r, "AbsPayable")),
                    "paid": to_float(dig(r, "AbsTotalPayment")),
                    "status": "done",
                    "items": [],
                }
            )
        # اقلام فاکتور
        q_items = self.queries.get(
            "items",
            "SELECT * FROM DBInvoiceItem WHERE IDInvoice IN (SELECT IDInvoice FROM DBInvoice WHERE InvoiceDate >= ?)",
        )
        try:
            cur.execute(q_items, since_iso[:10])
            icols = [c[0] for c in cur.description]
            by_inv: dict = {}
            for row in cur.fetchall():
                it = dict(zip(icols, row))
                inv_id = str(dig(it, "IDInvoice", ""))
                by_inv.setdefault(inv_id, []).append(
                    {
                        "source_id": str(dig(it, "IDInvoiceItem", uuid.uuid4().hex[:12])),
                        "product_source_id": str(dig(it, "IDProduct", "") or ""),
                        "product_name": str(dig(it, "ProductName", "—")),
                        "quantity": to_float(dig(it, "AbsQuantity"), 1),
                        "unit_price": to_float(dig(it, "AbsPrice")),
                        "discount": to_float(dig(it, "AbsDiscount")),
                        "total": to_float(dig(it, "AbsFinalAmount")),
                    }
                )
            for inv in batch["invoices"]:
                inv["items"] = by_inv.get(inv["source_id"], [])
        except Exception as exc:  # noqa: BLE001
            log(f"هشدار: خواندن اقلام ناموفق بود ({exc})")
        return batch


def make_source(cfg: dict):
    mode = cfg.get("mode", "demo")
    if mode == "api":
        return ApiSource(cfg)
    if mode == "sqlserver":
        return SqlServerSource(cfg)
    return DemoSource(cfg)


# --------------------------------------------------------------------------- #
#  حلقه‌ی اصلی
# --------------------------------------------------------------------------- #
def load_config(path: str) -> dict:
    with open(path, encoding="utf-8") as f:
        return json.load(f)


def save_state(path: str, state: dict) -> None:
    with open(path, "w", encoding="utf-8") as f:
        json.dump(state, f, ensure_ascii=False, indent=2)


def load_state(path: str) -> dict:
    if os.path.exists(path):
        with open(path, encoding="utf-8") as f:
            return json.load(f)
    return {"since": (datetime.now() - timedelta(days=1)).strftime("%Y-%m-%d %H:%M:%S")}


def drain_outbox(client: CloudClient, outbox: Outbox) -> None:
    """ارسال بسته‌های معوق (زمانی که اینترنت قطع بوده)"""
    while True:
        rows = outbox.pop_batch(10)
        if not rows:
            return
        for row_id, body in rows:
            try:
                client.push(json.loads(body))
                outbox.remove(row_id)
            except Exception as exc:  # noqa: BLE001
                outbox.bump(row_id)
                log(f"ارسال بسته‌ی معوق ناموفق ({exc}) — بعداً تلاش می‌شود")
                return


def main() -> int:
    ap = argparse.ArgumentParser(description="Zhaniya Connector")
    ap.add_argument("--config", default=DEFAULT_CONFIG, help="مسیر فایل تنظیمات")
    ap.add_argument("--once", action="store_true", help="فقط یک چرخه اجرا شود (برای تست)")
    args = ap.parse_args()

    cfg = load_config(args.config)
    state_path = cfg.get("state_file", os.path.join(HERE, "connector_state.json"))
    outbox = Outbox(cfg.get("outbox_file", os.path.join(HERE, "connector_outbox.sqlite")))
    client = CloudClient(cfg)
    source = make_source(cfg)
    state = load_state(state_path)

    interval = int(cfg.get("sync_interval_sec", 20))
    heartbeat_every = int(cfg.get("heartbeat_sec", 120))
    log(f"Zhaniya Connector شروع شد — mode={cfg.get('mode', 'demo')} → {client.endpoint}")
    log(f"همگام‌سازی هر {interval} ثانیه؛ صف معوق: {outbox.size()} بسته")

    last_heartbeat = 0.0
    while True:
        try:
            drain_outbox(client, outbox)
            batch = source.fetch(state.get("since", ""))
            n_inv = len(batch.get("invoices", []))
            if n_inv > 0:
                batch["batch_id"] = str(uuid.uuid4())
                batch["note"] = "incremental sync"
                try:
                    resp = client.push(batch)
                    newest = max(
                        (i.get("invoice_date", "") + " " + i.get("invoice_time", "")
                         for i in batch["invoices"]),
                        default="",
                    )
                    if newest:
                        state["since"] = newest
                        save_state(state_path, state)
                    log(
                        f"ارسال موفق: {n_inv} فاکتور "
                        f"({resp.get('received', {}).get('items', '?')} قلم)"
                    )
                except Exception as exc:  # noqa: BLE001
                    log(f"ارسال ناموفق ({exc}) — بسته به صف آفلاین رفت")
                    outbox.push(json.dumps(batch, ensure_ascii=False))
            else:
                # داده‌ی جدیدی نیست؛ فقط heartbeat
                if time.time() - last_heartbeat > heartbeat_every:
                    if client.heartbeat():
                        log("heartbeat ارسال شد (اتصال برقرار است)")
                    last_heartbeat = time.time()
        except Exception as exc:  # noqa: BLE001
            log(f"خطای چرخه: {exc}")

        if args.once:
            return 0
        time.sleep(interval)


if __name__ == "__main__":
    sys.exit(main())
