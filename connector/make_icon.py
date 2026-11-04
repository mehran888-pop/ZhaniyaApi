#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""ساخت فایل icon.ico برای ZhaniyaTray.exe (اجرا توسط build_exe.bat)"""
import os
import sys

try:
    from PIL import Image, ImageDraw
except ImportError:
    print("ابتدا Pillow را نصب کنید:  pip install pillow")
    sys.exit(1)


def make(size: int = 256) -> Image.Image:
    img = Image.new("RGBA", (size, size), (0, 0, 0, 0))
    d = ImageDraw.Draw(img)
    r = size // 8
    d.rounded_rectangle([4, 4, size - 4, size - 4], radius=r, fill=(14, 165, 164, 255))
    bw = size // 7
    base = size - size // 5
    for i, h in enumerate((size // 3, size // 2, int(size * 0.72))):
        x = size // 5 + i * (bw + size // 14)
        d.rounded_rectangle([x, base - h, x + bw, base], radius=bw // 3, fill=(255, 255, 255, 255))
    return img


def main() -> None:
    out = os.path.join(os.path.dirname(os.path.abspath(__file__)), "icon.ico")
    img = make(256)
    img.save(out, format="ICO", sizes=[(16, 16), (24, 24), (32, 32), (48, 48), (64, 64), (128, 128), (256, 256)])
    print("ساخته شد:", out)


if __name__ == "__main__":
    main()
