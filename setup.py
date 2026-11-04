#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
Zhaniya Cloud — Setup یکپارچه (ویندوز/لینوکس/مک)
=================================================

  python setup.py              منوی تعاملی (نصب آسان)
  python setup.py --demo       نصب سریع دمو + اجرای داشبورد
  python setup.py --check      بررسی پیش‌نیازها

برای نصب تحت وب (روی هاست PHP): کافی است فایل‌ها را آپلود کنید و
setup.php را در مرورگر باز کنید — بقیه‌ی مراحل در ویزارد انجام می‌شود.
"""

from __future__ import annotations

import argparse
import json
import os
import shutil
import subprocess
import sys

HERE = os.path.dirname(os.path.abspath(__file__))
CLOUD = os.path.join(HERE, "cloud")
CONNECTOR = os.path.join(HERE, "connector")


# ------------------------------------------------------------------ utils
def say(msg: str = "") -> None:
    print(msg, flush=True)


def banner() -> None:
    say("=" * 62)
    say("   Zhaniya Cloud — Setup | نصب آسان داشبورد مدیریت ژانیا")
    say("=" * 62)


def find_php(explicit: str | None = None) -> str | None:
    if explicit and os.path.exists(explicit):
        return explicit
    env = os.environ.get("ZH_PHP")
    if env and os.path.exists(env):
        return env
    return shutil.which("php")


def php_version(php: str) -> str:
    try:
        out = subprocess.run([php, "-v"], capture_output=True, text=True, timeout=20)
        return out.stdout.splitlines()[0] if out.stdout else "ناشناخته"
    except Exception as exc:  # noqa: BLE001
        return f"خطا: {exc}"


# ------------------------------------------------------------------ check
def cmd_check(args) -> int:
    banner()
    say("\n[بررسی پیش‌نیازها]")
    php = find_php(args.php)
    if php:
        say(f"  ✓ PHP پیدا شد: {php}")
        say(f"    {php_version(php)}")
        mods = subprocess.run([php, "-m"], capture_output=True, text=True, timeout=20).stdout.lower()
        for m, label in [("pdo", "PDO"), ("sqlite", "SQLite"), ("mysql", "MySQL"), ("mbstring", "mbstring")]:
            say(f"  {'✓' if m in mods else '✗'} اکستنشن {label}")
    else:
        say("  ✗ PHP در PATH پیدا نشد.")
        say("    → نصب: https://windows.php.net  |  sudo apt install php-cli php-sqlite3")
        say("    → یا مسیر php را با --php یا متغیر ZH_PHP بدهید.")
    say(f"  ✓ پایتون {sys.version.split()[0]}")
    say(f"  {'✓' if os.path.isdir(CLOUD) else '✗'} پوشه cloud/")
    say(f"  {'✓' if os.path.isdir(CONNECTOR) else '✗'} پوشه connector/")
    say("\n[نصب تحت وب] کافی است فایل‌های cloud/ را روی هاست آپلود و setup.php را باز کنید.")
    return 0


# ------------------------------------------------------------------ demo
def cmd_demo(args) -> int:
    banner()
    php = find_php(args.php)
    if not php:
        say("✗ PHP پیدا نشد. ابتدا PHP نصب کنید (به --check نگاه کنید) یا از نصب تحت وب استفاده کنید.")
        return 1

    say(f"\n[۱/۳] PHP: {php_version(php)}")
    say("[۲/۳] ساخت دیتابیس و داده‌ی نمونه ...")
    r = subprocess.run([php, os.path.join(CLOUD, "seed_demo.php")], cwd=CLOUD)
    if r.returncode != 0:
        say("✗ seed ناموفق بود.")
        return 1

    say("\n[۳/۳] راه‌اندازی داشبورد ...")
    say("  دمو آماده است:")
    say("    URL      : http://localhost:8080")
    say("    کاربر    : admin")
    say("    رمز      : admin123")
    say("    (در استقرار واقعی حتماً رمز را عوض کنید)")
    if args.no_start:
        say(f"\nبرای اجرا: php -S 0.0.0.0:8080 -t {os.path.join('cloud', 'public')}")
        return 0
    say("\nدر حال اجرا ... (Ctrl+C برای توقف)\n")
    os.chdir(CLOUD)
    return subprocess.call([php, "-S", "0.0.0.0:8080", "-t", "public"])


# ------------------------------------------------------------------ connector config
def ask(prompt: str, default: str = "") -> str:
    suffix = f" [{default}]" if default else ""
    val = input(f"{prompt}{suffix}: ").strip()
    return val or default


def cmd_connector(args) -> int:
    banner()
    say("\n[ساخت config.json برای کانکتور فروشگاه]")
    say("(این فایل را در کنار zhaniya_connector.py روی سیستم فروشگاه قرار دهید)\n")

    cloud_url = args.cloud_url or ask("آدرس سرور ابری (مثال https://pos.example.com)", "http://localhost:8080")
    mode = args.mode or ask("حالت اتصال (api / sqlserver / demo)", "demo")
    device_key = args.device_key or ask("Device Key (از داشبورد/نصب‌کننده)", "demo-device-key")
    device_secret = args.device_secret or ask("Device Secret", "demo-device-secret")

    tpl_path = os.path.join(CONNECTOR, "config.example.json")
    with open(tpl_path, encoding="utf-8") as f:
        cfg = json.load(f)

    cfg.update({
        "mode": mode,
        "cloud_url": cloud_url.rstrip("/"),
        "device_key": device_key,
        "device_secret": device_secret,
    })
    out = os.path.join(CONNECTOR, "config.json")
    with open(out, "w", encoding="utf-8") as f:
        json.dump(cfg, f, ensure_ascii=False, indent=2)
    say(f"\n✓ ساخته شد: {out}")

    if mode == "api":
        say("  → نام کاربری/رمز ژانیا را در بخش api وارد کنید.")
    elif mode == "sqlserver":
        say("  → رشته‌ی اتصال SQL را در بخش sql وارد کنید (pip install pyodbc).")

    say("\n[اجرای کانکتور]")
    say(f"  python {os.path.join('connector', 'zhaniya_connector.py')} --config {os.path.join('connector', 'config.json')}")
    say("[سرویس ویندوز] فایل connector\\install_service.bat را یک‌بار اجرا کنید (Run as Administrator).")
    return 0


# ------------------------------------------------------------------ service helper
def cmd_service(args) -> int:
    banner()
    bat = os.path.join(CONNECTOR, "install_service.bat")
    say("\n[نصب کانکتور به‌عنوان سرویس ویندوز]")
    if os.name != "nt":
        say("  این بخش فقط روی ویندوز قابل اجراست.")
        say(f"  فایل آماده: {bat}")
        say("  آن را روی سیستم فروشگاه (Run as Administrator) اجرا کنید.")
        return 0
    nssm = shutil.which("nssm")
    if not nssm:
        say("  ✗ nssm در PATH نیست. از https://nssm.cc دانلود و کنار فایل‌ها بگذارید.")
        say(f"  سپس {bat} را اجرا کنید.")
        return 1
    say(f"  ✓ nssm: {nssm}")
    say(f"  در حال اجرای {bat} ...")
    return subprocess.call([bat], shell=True)


# ------------------------------------------------------------------ menu
def interactive(args) -> int:
    banner()
    say("""
  1) نصب سریع دمو (SQLite + داده‌ی نمونه + داشبورد)
  2) بررسی پیش‌نیازها
  3) ساخت config.json کانکتور فروشگاه
  4) نصب کانکتور به‌عنوان سرویس ویندوز
  5) راهنمای نصب تحت وب (ویزارد setup.php)
  0) خروج
""")
    choice = input("انتخاب شما: ").strip()
    if choice == "1":
        return cmd_demo(args)
    if choice == "2":
        return cmd_check(args)
    if choice == "3":
        return cmd_connector(args)
    if choice == "4":
        return cmd_service(args)
    if choice == "5":
        say("\n  ۱. پوشه cloud/ را روی هاست PHP آپلود کنید (زیرپوشه public در داک‌روت وب‌سرور).")
        say("  ۲. در مرورگر باز کنید: http://YOUR-SERVER/setup.php")
        say("  ۳. ویزارد ۴ مرحله‌ای را دنبال کنید (بررسی، دیتابیس، مدیر/دستگاه، پایان).")
        say("  ۴. در پایان فایل config.json کانکتور را دانلود و روی سیستم فروشگاه اجرا کنید.")
        return 0
    return 0


def main() -> int:
    ap = argparse.ArgumentParser(description="Zhaniya Cloud Setup")
    ap.add_argument("--demo", action="store_true", help="نصب سریع دمو و اجرای داشبورد")
    ap.add_argument("--check", action="store_true", help="بررسی پیش‌نیازها")
    ap.add_argument("--connector-config", action="store_true", help="ساخت config.json کانکتور")
    ap.add_argument("--service", action="store_true", help="نصب سرویس ویندوز کانکتور")
    ap.add_argument("--no-start", action="store_true", help="(دمو) فقط نصب کن، سرور را اجرا نکن")
    ap.add_argument("--php", help="مسیر فایل اجرایی php")
    ap.add_argument("--cloud-url", help="آدرس سرور ابری برای کانکتور")
    ap.add_argument("--mode", choices=["api", "sqlserver", "demo"], help="حالت کانکتور")
    ap.add_argument("--device-key", help="Device Key")
    ap.add_argument("--device-secret", help="Device Secret")
    args = ap.parse_args()

    try:
        if args.demo:
            return cmd_demo(args)
        if args.check:
            return cmd_check(args)
        if args.connector_config:
            return cmd_connector(args)
        if args.service:
            return cmd_service(args)
        return interactive(args)
    except KeyboardInterrupt:
        say("\nلغو شد.")
        return 130


if __name__ == "__main__":
    sys.exit(main())
