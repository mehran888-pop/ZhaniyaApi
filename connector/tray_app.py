#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
ZhaniyaTray — برنامه‌ی سینی ویندوز (کنار ساعت) برای کانکتور ژانیا
===============================================================

قابلیت‌ها:
  * آیکون در system tray (کنار ساعت ویندوز)
  * اجرا همراه با ویندوز (از پوشه‌ی Startup — بدون نیاز به Administrator)
  * داشبورد تنظیمات با نام کاربری و رمز (محافظت‌شده)
  * پیکربندی: آدرس هاست، کلید دستگاه، اتصال به ژانیا (API/SQL/دمو)
  * ذخیره‌ی نام کاربری/رمز داشبورد وب + بازکردن سریع آن
  * شروع/توقف کانکتور و نمایش وضعیت لحظه‌ای

ساخت فایل اجرایی (روی ویندوز):  اجرای build_exe.bat
خروجی: dist\\ZhaniyaTray.exe
"""

from __future__ import annotations

import hashlib
import json
import os
import subprocess
import sys
import threading
import traceback
import webbrowser
from pathlib import Path

# ---------------------------------------------------------------- مسیرها
FROZEN = getattr(sys, "frozen", False)
# در حالت اجرایی (exe)، فایل‌های قابل‌ویرایش کنار خودِ exe می‌مانند
APP_DIR = Path(sys.executable).resolve().parent if FROZEN else Path(__file__).resolve().parent
CONFIG_FILE = APP_DIR / "config.json"
TRAY_FILE = APP_DIR / "tray_settings.json"
ERROR_LOG = APP_DIR / "tray_error.log"
STARTUP_LNK = Path.home() / "AppData/Roaming/Microsoft/Windows/Start Menu/Programs/Startup/ZhaniyaTray.lnk"

APP_TITLE = "ژانیا — کانکتور فروشگاه"

# وارد کردن هسته‌ی کانکتور (در همان پوشه)
sys.path.insert(0, str(APP_DIR))
import zhaniya_connector as zc  # noqa: E402

# وابستگی‌های گرافیکی — با تأخیر تا در محیط‌های بدون گرافیک هم قابل import باشد
try:
    import tkinter as tk
    from tkinter import messagebox, ttk
    TK_OK = True
except Exception:  # noqa: BLE001
    TK_OK = False

try:
    from PIL import Image, ImageDraw
    PIL_OK = True
except Exception:  # noqa: BLE001
    PIL_OK = False

try:
    import pystray
    TRAY_OK = PIL_OK
except Exception:  # noqa: BLE001
    TRAY_OK = False


# ---------------------------------------------------------------- ذخیره‌سازی
def load_json(path: Path, default):
    try:
        with open(path, encoding="utf-8") as f:
            return json.load(f)
    except Exception:  # noqa: BLE001
        return default


def save_json(path: Path, obj) -> None:
    path.parent.mkdir(parents=True, exist_ok=True)
    with open(path, "w", encoding="utf-8") as f:
        json.dump(obj, f, ensure_ascii=False, indent=2)


def hash_pw(pw: str) -> str:
    return hashlib.sha256(("zhaniya-tray::" + pw).encode("utf-8")).hexdigest()


def load_tray_settings() -> dict:
    s = load_json(TRAY_FILE, {})
    s.setdefault("ui_user", "admin")
    s.setdefault("ui_pass_hash", "")
    s.setdefault("start_with_windows", False)
    s.setdefault("autostart_connector", True)
    s.setdefault("dashboard", {"url": "", "username": "", "password": ""})
    return s


def default_config() -> dict:
    return {
        "mode": "api",
        "cloud_url": "",
        "ingest_path": "api/ingest.php",
        "health_path": "api/health.php",
        "device_key": "",
        "device_secret": "",
        "sync_interval_sec": 20,
        "heartbeat_sec": 120,
        "http_timeout": 30,
        "verify_tls": True,
        "api": {
            "base_url": "http://127.0.0.1/ZhaniyaApi",
            "username": "",
            "password": "",
            "agency": "",
            "login_path": "api/User/LoginWithAgency",
            "invoices_path": "api/Invoice/GetAll",
            "timeout": 30,
        },
        "sql": {
            "connection_string": "DRIVER={ODBC Driver 17 for SQL Server};SERVER=.\\SQLEXPRESS;DATABASE=ZhaniyaDB;UID=sa;PWD=CHANGE_ME",
        },
    }


def deep_merge(base: dict, extra: dict) -> dict:
    out = dict(base)
    for k, v in (extra or {}).items():
        if isinstance(v, dict) and isinstance(out.get(k), dict):
            out[k] = deep_merge(out[k], v)
        else:
            out[k] = v
    return out


# ---------------------------------------------------------------- اجرای همراه ویندوز
def startup_enabled() -> bool:
    return STARTUP_LNK.exists()


def set_startup(enabled: bool) -> tuple[bool, str]:
    """ساخت/حذف میان‌بر در پوشه‌ی Startup ویندوز (بدون نیاز به دسترسی ادمین)"""
    if os.name != "nt":
        return False, "این قابلیت فقط در ویندوز در دسترس است."
    try:
        if enabled:
            if FROZEN:
                target = str(Path(sys.executable).resolve())
                args = ""
            else:
                # حالت اسکریپت: ساخت یک bat کمکی که همیشه همین برنامه را اجرا کند
                bat = APP_DIR / "ZhaniyaTray.bat"
                bat.write_text(
                    f'@echo off\r\nstart "" "{sys.executable}" "{Path(__file__).resolve()}"\r\n',
                    encoding="utf-8",
                )
                target = str(bat)
                args = ""
            ps = (
                "$s=(New-Object -COM WScript.Shell).CreateShortcut('%s');"
                "$s.TargetPath='%s';$s.Arguments='%s';$s.WorkingDirectory='%s';$s.Save()"
                % (str(STARTUP_LNK), target.replace("'", "''"), args, str(APP_DIR).replace("'", "''"))
            )
            subprocess.run(["powershell", "-NoProfile", "-Command", ps], check=True, capture_output=True, timeout=30)
            return True, "برنامه همراه با ویندوز اجرا می‌شود."
        else:
            if STARTUP_LNK.exists():
                STARTUP_LNK.unlink()
            return True, "اجرای خودکار همراه ویندوز غیرفعال شد."
    except Exception as exc:  # noqa: BLE001
        return False, f"خطا در تنظیم اجرای خودکار: {exc}"


# ---------------------------------------------------------------- کنترل کانکتور
class ConnectorController:
    """شروع/توقف هسته‌ی کانکتور در یک thread جدا"""

    def __init__(self):
        self.thread: threading.Thread | None = None
        self.stop_event = threading.Event()
        self.last_status = "آماده"
        self.lock = threading.Lock()

    @property
    def running(self) -> bool:
        return self.thread is not None and self.thread.is_alive()

    def _on_status(self, msg: str) -> None:
        with self.lock:
            self.last_status = msg

    def start(self) -> bool:
        if self.running:
            return True
        if not CONFIG_FILE.exists():
            self._on_status("فایل config.json وجود ندارد — ابتدا تنظیمات را ذخیره کنید")
            return False
        self.stop_event = threading.Event()
        self.thread = threading.Thread(
            target=self._run, daemon=True, name="zhaniya-connector"
        )
        self.thread.start()
        return True

    def _run(self) -> None:
        try:
            zc.run_loop(str(CONFIG_FILE), stop_event=self.stop_event, status_cb=self._on_status)
        except Exception as exc:  # noqa: BLE001
            self._on_status(f"خطای کانکتور: {exc}")

    def stop(self) -> None:
        self.stop_event.set()
        if self.thread and self.thread.is_alive():
            self.thread.join(timeout=8)


# ---------------------------------------------------------------- آیکون برنامه
def make_icon_image(size: int = 64):
    """آیکون ساده: مربع آبی‌فیروزه‌ای با سه میله‌ی نمودار"""
    if not PIL_OK:
        raise RuntimeError("Pillow نصب نیست — pip install pillow")
    img = Image.new("RGBA", (size, size), (0, 0, 0, 0))
    d = ImageDraw.Draw(img)
    r = size // 8
    d.rounded_rectangle([2, 2, size - 2, size - 2], radius=r, fill=(14, 165, 164, 255))
    bw = size // 7
    base = size - size // 5
    for i, h in enumerate((size // 3, size // 2, int(size * 0.7))):
        x = size // 5 + i * (bw + size // 14)
        d.rounded_rectangle([x, base - h, x + bw, base], radius=bw // 3, fill=(255, 255, 255, 255))
    return img


# ---------------------------------------------------------------- رابط گرافیکی
class TrayApp:
    def __init__(self):
        self.settings = load_tray_settings()
        self.ctrl = ConnectorController()
        self.icon = None
        self.root = None
        self.status_var = None

    # ---------------- tkinter
    def _ensure_root(self):
        if self.root is None:
            self.root = tk.Tk()
            self.root.withdraw()
            self.root.title(APP_TITLE)
        return self.root

    def _login_ok(self, user: str, pw: str) -> bool:
        return (
            user.strip() == self.settings.get("ui_user", "admin")
            and hash_pw(pw) == self.settings.get("ui_pass_hash", "")
        )

    def require_login(self, on_success):
        """پنجره‌ی ورود به داشبورد تنظیمات (با نام کاربری و رمز)"""
        root = self._ensure_root()
        win = tk.Toplevel(root)
        win.title("ورود — " + APP_TITLE)
        win.geometry("360x230")
        win.resizable(False, False)
        win.attributes("-topmost", True)

        first_run = not self.settings.get("ui_pass_hash")
        tk.Label(win, text="🔐 ورود به داشبورد تنظیمات", font=("Tahoma", 12, "bold")).pack(pady=(16, 10))

        frm = tk.Frame(win)
        frm.pack(pady=4)
        tk.Label(frm, text="نام کاربری:", anchor="e").grid(row=0, column=0, sticky="e", pady=6, padx=6)
        e_user = tk.Entry(frm, width=24, justify="left")
        e_user.grid(row=0, column=1, pady=6)
        e_user.insert(0, self.settings.get("ui_user", "admin"))

        tk.Label(frm, text="رمز عبور:", anchor="e").grid(row=1, column=0, sticky="e", pady=6, padx=6)
        e_pw = tk.Entry(frm, width=24, show="●", justify="left")
        e_pw.grid(row=1, column=1, pady=6)

        e_pw2 = None
        if first_run:
            win.title("ایجاد رمز — " + APP_TITLE)
            tk.Label(frm, text="تکرار رمز:", anchor="e").grid(row=2, column=0, sticky="e", pady=6, padx=6)
            e_pw2 = tk.Entry(frm, width=24, show="●", justify="left")
            e_pw2.grid(row=2, column=1, pady=6)
            tk.Label(win, text="اولین اجرا: نام کاربری و رمز دلخواه را تعیین کنید.", fg="#64748b", font=("Tahoma", 8)).pack()

        def submit():
            user = e_user.get().strip()
            pw = e_pw.get()
            if first_run:
                if len(pw) < 4:
                    messagebox.showerror("خطا", "رمز باید حداقل ۴ کاراکتر باشد.", parent=win)
                    return
                if pw != (e_pw2.get() if e_pw2 else ""):
                    messagebox.showerror("خطا", "تکرار رمز مطابقت ندارد.", parent=win)
                    return
                self.settings["ui_user"] = user or "admin"
                self.settings["ui_pass_hash"] = hash_pw(pw)
                save_json(TRAY_FILE, self.settings)
                win.destroy()
                on_success()
                return
            if self._login_ok(user, pw):
                win.destroy()
                on_success()
            else:
                messagebox.showerror("خطا", "نام کاربری یا رمز اشتباه است.", parent=win)

        tk.Button(win, text="ورود", width=16, command=submit, bg="#0ea5a4", fg="white", font=("Tahoma", 10, "bold")).pack(pady=12)
        win.bind("<Return>", lambda e: submit())
        e_user.focus_set()

    def open_settings(self):
        self.require_login(self._build_settings_window)

    def _build_settings_window(self):
        root = self._ensure_root()
        win = tk.Toplevel(root)
        win.title("داشبورد تنظیمات — " + APP_TITLE)
        win.geometry("640x520")
        win.attributes("-topmost", True)

        cfg = deep_merge(default_config(), load_json(CONFIG_FILE, {}))
        s = self.settings

        nb = ttk.Notebook(win)
        nb.pack(fill="both", expand=True, padx=10, pady=10)

        def tab(title):
            f = tk.Frame(nb, padx=12, pady=12)
            nb.add(f, text=title)
            return f

        def row(frm, r, label, widget):
            tk.Label(frm, text=label, anchor="e", width=26, font=("Tahoma", 9)).grid(row=r, column=0, sticky="e", pady=6, padx=6)
            widget.grid(row=r, column=1, sticky="ew", pady=6)
            frm.columnconfigure(1, weight=1)

        def entry(frm, r, label, value="", show=None, width=34):
            e = tk.Entry(frm, width=width, show=show, justify="left")
            e.insert(0, value)
            row(frm, r, label, e)
            return e

        # --- تب ۱: عمومی
        t1 = tab("عمومی")
        v_startwin = tk.BooleanVar(value=bool(s.get("start_with_windows")))
        v_autorun = tk.BooleanVar(value=bool(s.get("autostart_connector")))
        tk.Checkbutton(t1, text="اجرا همراه با ویندوز (در کنار ساعت)", variable=v_startwin, font=("Tahoma", 9)).grid(row=0, column=0, columnspan=2, sticky="e", pady=8)
        tk.Checkbutton(t1, text="شروع خودکار کانکتور هنگام باز شدن برنامه", variable=v_autorun, font=("Tahoma", 9)).grid(row=1, column=0, columnspan=2, sticky="e", pady=8)
        e_interval = entry(t1, 2, "فاصله‌ی همگام‌سازی (ثانیه)", str(cfg.get("sync_interval_sec", 20)))
        tk.Label(t1, text="وضعیت فعلی کانکتور:", anchor="e", font=("Tahoma", 9)).grid(row=3, column=0, sticky="e", pady=10)
        self.status_var = tk.StringVar(value=self.ctrl.last_status)
        tk.Label(t1, textvariable=self.status_var, anchor="e", fg="#0f766e", font=("Tahoma", 8), wraplength=330, justify="right").grid(row=3, column=1, sticky="e")

        # --- تب ۲: اتصال ابری
        t2 = tab("اتصال ابری")
        e_cloud = entry(t2, 0, "آدرس داشبورد (هاست)", cfg.get("cloud_url", ""))
        e_key = entry(t2, 1, "Device Key", cfg.get("device_key", ""))
        e_secret = entry(t2, 2, "Device Secret", cfg.get("device_secret", ""), show="●")

        # --- تب ۳: ژانیا
        t3 = tab("اتصال به ژانیا")
        v_mode = tk.StringVar(value=cfg.get("mode", "api"))
        row(t3, 0, "نحوه‌ی اتصال", ttk.Combobox(t3, textvariable=v_mode, values=["api", "sqlserver", "demo"], state="readonly", width=31))
        api = cfg.get("api", {})
        e_apiurl = entry(t3, 1, "آدرس ZhaniyaApi (روی سیستم فروشگاه)", api.get("base_url", "http://127.0.0.1/ZhaniyaApi"))
        e_apiuser = entry(t3, 2, "نام کاربری ژانیا", api.get("username", ""))
        e_apipass = entry(t3, 3, "رمز ژانیا", api.get("password", ""), show="●")
        e_agency = entry(t3, 4, "آژانس (معمولاً خالی)", api.get("agency", ""))
        e_sql = entry(t3, 5, "رشته‌ی اتصال SQL (فقط حالت sqlserver)", cfg.get("sql", {}).get("connection_string", ""))

        # --- تب ۴: داشبورد وب
        t4 = tab("داشبورد وب")
        dash = s.get("dashboard", {})
        e_dashurl = entry(t4, 0, "آدرس داشبورد وب", dash.get("url", "") or cfg.get("cloud_url", ""))
        e_dashuser = entry(t4, 1, "نام کاربری داشبورد", dash.get("username", ""))
        e_dashpass = entry(t4, 2, "رمز داشبورد", dash.get("password", ""), show="●")
        tk.Label(t4, text="با دکمه‌ی زیر، داشبورد در مرورگر باز و رمز در کلیپ‌بورد کپی می‌شود.", fg="#64748b", font=("Tahoma", 8), wraplength=400, justify="right").grid(row=3, column=0, columnspan=2, pady=10)

        # ---------------- دکمه‌ها
        bar = tk.Frame(win)
        bar.pack(fill="x", padx=10, pady=(0, 12))

        def save_all():
            cfg["mode"] = v_mode.get()
            cfg["cloud_url"] = e_cloud.get().strip().rstrip("/")
            cfg["device_key"] = e_key.get().strip()
            cfg["device_secret"] = e_secret.get().strip()
            cfg["sync_interval_sec"] = int(e_interval.get() or 20)
            cfg.setdefault("api", {})
            cfg["api"]["base_url"] = e_apiurl.get().strip().rstrip("/")
            cfg["api"]["username"] = e_apiuser.get()
            cfg["api"]["password"] = e_apipass.get()
            cfg["api"]["agency"] = e_agency.get().strip()
            cfg.setdefault("sql", {})
            cfg["sql"]["connection_string"] = e_sql.get().strip()
            save_json(CONFIG_FILE, cfg)

            s["start_with_windows"] = bool(v_startwin.get())
            s["autostart_connector"] = bool(v_autorun.get())
            s["dashboard"] = {
                "url": e_dashurl.get().strip().rstrip("/"),
                "username": e_dashuser.get(),
                "password": e_dashpass.get(),
            }
            save_json(TRAY_FILE, s)

            ok, msg = set_startup(s["start_with_windows"])
            messagebox.showinfo("ذخیره شد", "تنظیمات ذخیره شد ✅\n" + msg, parent=win)

        def test_connection():
            url = e_cloud.get().strip().rstrip("/")
            if not url:
                messagebox.showwarning("هشدار", "ابتدا آدرس داشبورد را وارد کنید.", parent=win)
                return

            def _test():
                try:
                    import urllib.request
                    req = urllib.request.Request(url + "/api/health.php", method="GET")
                    with urllib.request.urlopen(req, timeout=10) as resp:
                        data = json.loads(resp.read().decode("utf-8"))
                    msg = "اتصال برقرار شد ✅\n" + json.dumps(data, ensure_ascii=False)
                except Exception as exc:  # noqa: BLE001
                    msg = "اتصال ناموفق ❌\n" + str(exc)
                win.after(0, lambda: messagebox.showinfo("تست اتصال", msg, parent=win))

            threading.Thread(target=_test, daemon=True).start()

        def open_dashboard():
            url = s_dash_url()
            if not url:
                messagebox.showwarning("هشدار", "آدرس داشبورد وب را در تب «داشبورد وب» وارد کنید.", parent=win)
                return
            webbrowser.open(url)
            pw = e_dashpass.get()
            if pw:
                root = self._ensure_root()
                root.clipboard_clear()
                root.clipboard_append(pw)
                self.notify("داشبورد باز شد", "رمز داشبورد در کلیپ‌بورد کپی شد (Ctrl+V)")

        def s_dash_url():
            u = e_dashurl.get().strip().rstrip("/")
            return u or e_cloud.get().strip().rstrip("/")

        tk.Button(bar, text="💾 ذخیره‌ی تنظیمات", command=save_all, bg="#0ea5a4", fg="white", font=("Tahoma", 9, "bold"), padx=10).pack(side="right", padx=4)
        tk.Button(bar, text="🔌 تست اتصال", command=test_connection, font=("Tahoma", 9), padx=10).pack(side="right", padx=4)
        tk.Button(bar, text="🌐 باز کردن داشبورد وب", command=open_dashboard, font=("Tahoma", 9), padx=10).pack(side="right", padx=4)
        tk.Button(bar, text="بستن", command=win.destroy, font=("Tahoma", 9), padx=10).pack(side="left", padx=4)

        if self.ctrl.running:
            self._schedule_status_refresh()

    def _schedule_status_refresh(self):
        if self.status_var is not None:
            try:
                self.status_var.set(self.ctrl.last_status)
            except Exception:  # noqa: BLE001
                return
        if self.root is not None:
            self.root.after(1500, self._schedule_status_refresh)

    # ---------------- tray
    def notify(self, title: str, msg: str) -> None:
        if self.icon is not None:
            try:
                self.icon.notify(msg, title)
            except Exception:  # noqa: BLE001
                pass

    def _toggle_connector(self, icon=None, item=None):
        if self.ctrl.running:
            self.ctrl.stop()
            self.notify("کانکتور", "کانکتور متوقف شد")
        else:
            if self.ctrl.start():
                self.notify("کانکتور", "کانکتور شروع شد")

    def _toggle_startup(self, icon=None, item=None):
        new = not startup_enabled()
        ok, msg = set_startup(new)
        if ok:
            self.settings["start_with_windows"] = new
            save_json(TRAY_FILE, self.settings)
        self.notify("اجرای خودکار", msg)

    def _open_web(self, icon=None, item=None):
        dash = self.settings.get("dashboard", {})
        url = (dash.get("url") or load_json(CONFIG_FILE, {}).get("cloud_url") or "").rstrip("/")
        if not url:
            self.notify("داشبورد وب", "آدرس داشبورد تنظیم نشده — از «تنظیمات» وارد کنید")
            return
        webbrowser.open(url)
        if dash.get("password"):
            self.copy_to_clipboard(dash["password"])
            self.notify("داشبورد وب", "باز شد — رمز در کلیپ‌بورد کپی شد (Ctrl+V)")
        else:
            self.notify("داشبورد وب", "باز شد")

    def copy_to_clipboard(self, text: str) -> None:
        try:
            root = self._ensure_root()
            root.clipboard_clear()
            root.clipboard_append(text)
        except Exception:  # noqa: BLE001
            pass

    def _quit(self, icon=None, item=None):
        self.ctrl.stop()
        if self.icon is not None:
            self.icon.stop()
        if self.root is not None:
            self.root.after(0, self.root.destroy)

    def build_tray(self):
        status_line = lambda item: f"وضعیت: {self.ctrl.last_status}"  # noqa: E731
        menu = pystray.Menu(
            pystray.MenuItem(status_line, None, enabled=False),
            pystray.MenuItem("─────", None, enabled=False),
            pystray.MenuItem(
                lambda item: "⏸ توقف کانکتور" if self.ctrl.running else "▶ شروع کانکتور",
                self._toggle_connector,
                default=True,
            ),
            pystray.MenuItem("🌐 باز کردن داشبورد وب", self._open_web),
            pystray.MenuItem("⚙ تنظیمات و پیکربندی ...", self.open_settings),
            pystray.MenuItem("─────", None, enabled=False),
            pystray.MenuItem(
                "اجرا همراه با ویندوز",
                self._toggle_startup,
                checked=lambda item: startup_enabled(),
            ),
            pystray.MenuItem("خروج", self._quit),
        )
        self.icon = pystray.Icon("ZhaniyaTray", make_icon_image(), APP_TITLE, menu)

    def run(self):
        if not TRAY_OK:
            print("pystray/Pillow نصب نیست:  pip install pystray pillow")
            return 1
        if not TK_OK:
            print("tkinter در دسترس نیست — نسخه‌ی کامل Python را نصب کنید.")
            return 1

        self.build_tray()

        # شروع خودکار کانکتور
        if self.settings.get("autostart_connector") and CONFIG_FILE.exists():
            self.ctrl.start()

        threading.Thread(target=self.icon.run, daemon=True, name="tray-icon").start()

        # اولین اجرا: ساخت رمز + باز کردن تنظیمات
        if not self.settings.get("ui_pass_hash"):
            self.root.after(400, self.open_settings)

        self.root.mainloop()
        return 0


# ---------------------------------------------------------------- ورودی برنامه
def excepthook(exc_type, exc_value, exc_tb):
    try:
        with open(ERROR_LOG, "a", encoding="utf-8") as f:
            f.write("\n--- " + __import__("datetime").datetime.now().isoformat() + " ---\n")
            traceback.print_exception(exc_type, exc_value, exc_tb, file=f)
    except Exception:  # noqa: BLE001
        pass


def main() -> int:
    sys.excepthook = excepthook
    threading.excepthook = lambda a: excepthook(a.exc_type, a.exc_value, a.exc_traceback)
    app = TrayApp()
    return app.run()


if __name__ == "__main__":
    sys.exit(main())
