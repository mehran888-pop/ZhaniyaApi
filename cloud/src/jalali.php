<?php
/**
 * تبدیل تقویم جلالی ↔ میلادی — پیاده‌سازی دقیق الگوریتم jalaali-js
 */

function jmod(int $n, int $m): int
{
    return $n - intdiv($n, $m) * $m;
}

function jdiv(int $a, int $b): int
{
    return intdiv($a, $b);
}

/** jalCal: محاسبه سال میلادی معادل و روز «مارس» شروع سال جلالی */
function jal_cal(int $jy, bool $withoutLeap = false): array
{
    $breaks = [-61, 9, 38, 199, 426, 686, 756, 818, 1111, 1181, 1210, 1635, 2060, 2097, 2192, 2262, 2324, 2394, 2456, 3178];
    $bl = count($breaks);
    $gy = $jy + 621;
    $leapJ = -14;
    $jp = $breaks[0];
    $jump = 0;

    if ($jy < $jp || $jy >= $breaks[$bl - 1]) {
        throw new InvalidArgumentException('سال جلالی خارج از بازه پشتیبانی');
    }
    for ($i = 1; $i < $bl; $i++) {
        $jm = $breaks[$i];
        $jump = $jm - $jp;
        if ($jy < $jm) {
            break;
        }
        $leapJ += jdiv($jump, 33) * 8 + jdiv(jmod($jump, 33), 4);
        $jp = $jm;
    }
    $n = $jy - $jp;
    $leapJ += jdiv($n, 33) * 8 + jdiv(jmod($n, 33) + 3, 4);
    if (jmod($jump, 33) === 4 && $jump - $n === 4) {
        $leapJ += 1;
    }
    $leapG = jdiv($gy, 4) - jdiv((jdiv($gy, 100) + 1) * 3, 4) - 150;
    $march = 20 + $leapJ - $leapG;
    $leap = 0;
    if (!$withoutLeap) {
        if ($jump - $n < 6) {
            $n = $n - $jump + jdiv($jump + 4, 33) * 33;
        }
        $n = jmod(jmod($n + 1, 33) - 1, 4);
        if ($n === -1) {
            $n = 4;
        }
        $leap = $n;
    }
    return ['gy' => $gy, 'march' => $march, 'leap' => $leap];
}

function g2d(int $gy, int $gm, int $gd): int
{
    $d = jdiv(($gy + jdiv($gm - 8, 6) + 100100) * 1461, 4)
        + jdiv((153 * jmod($gm + 9, 12) + 2), 5)
        + $gd - 34840408;
    $d = $d - jdiv(jdiv($gy + 100100 + jdiv($gm - 8, 6), 100) * 3, 4) + 752;
    return $d;
}

function d2g(int $jdn): array
{
    $j = 4 * $jdn + 139361631;
    $j = $j + jdiv(jdiv(4 * $jdn + 183187720, 146097) * 3, 4) * 4 - 3908;
    $i = jdiv(jmod($j, 1461), 4) * 5 + 308;
    $gd = jdiv(jmod($i, 153), 5) + 1;
    $gm = jmod(jdiv($i, 153), 12) + 1;
    $gy = jdiv($j, 1461) - 100100 + jdiv(8 - $gm, 6);
    return ['gy' => $gy, 'gm' => $gm, 'gd' => $gd];
}

function j2d(int $jy, int $jm, int $jd): int
{
    $r = jal_cal($jy, true);
    return g2d($r['gy'], 3, $r['march']) + ($jm - 1) * 31 - jdiv($jm, 7) * ($jm - 7) + $jd - 1;
}

function d2j(int $jdn): array
{
    $g = d2g($jdn);
    $gy = $g['gy'];
    $jy = $gy - 621;
    $r = jal_cal($jy, false);
    $jdn1f = g2d($gy, 3, $r['march']);
    $k = $jdn - $jdn1f;
    if ($k >= 0) {
        if ($k <= 185) {
            return ['jy' => $jy, 'jm' => 1 + jdiv($k, 31), 'jd' => jmod($k, 31) + 1];
        }
        $k -= 186;
    } else {
        $jy -= 1;
        $k += 179;
        if ($r['leap'] === 1) {
            $k += 1;
        }
    }
    return ['jy' => $jy, 'jm' => 7 + jdiv($k, 30), 'jd' => jmod($k, 30) + 1];
}

/** YYYY-MM-DD میلادی → YYYY/MM/DD جلالی */
function jalali_date(string $gdate, string $sep = '/'): string
{
    $p = explode('-', $gdate);
    if (count($p) !== 3) {
        return $gdate;
    }
    try {
        $j = d2j(g2d((int)$p[0], (int)$p[1], (int)$p[2]));
    } catch (Throwable $ex) {
        return $gdate;
    }
    return sprintf('%04d%s%02d%s%02d', $j['jy'], $sep, $j['jm'], $sep, $j['jd']);
}

/** YYYY/MM/DD جلالی → YYYY-MM-DD میلادی */
function jalali_to_gregorian(int $jy, int $jm, int $jd): array
{
    $g = d2g(j2d($jy, $jm, $jd));
    return [$g['gy'], $g['gm'], $g['gd']];
}
