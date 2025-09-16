// ایجاد فایل jalali.php با توابع بهبود یافته
<?php
class JalaliDate {
    public static function gregorianToJalali($gy, $gm, $gd) {
        $g_d_m = [0,31,59,90,120,151,181,212,243,273,304,334];
        $gy2 = ($gm > 2) ? ($gy + 1) : $gy;
        $days = 355666 + (365 * $gy) + ((int)(($gy2 + 3) / 4)) - ((int)(($gy2 + 99) / 100)) + ((int)(($gy2 + 399) / 400)) + $gd + $g_d_m[$gm-1];
        $jy = -1595 + (33 * ((int)($days / 12053)));
        $days %= 12053;
        $jy += 4 * ((int)($days / 1461));
        $days %= 1461;
        
        if ($days > 365) {
            $jy += (int)(($days - 1) / 365);
            $days = ($days - 1) % 365;
        }
        
        if ($days < 186) {
            $jm = 1 + (int)($days / 31);
            $jd = 1 + ($days % 31);
        } else {
            $jm = 7 + (int)(($days - 186) / 30);
            $jd = 1 + (($days - 186) % 30);
        }
        
        return [$jy, $jm, $jd];
    }

    public static function jalaliToGregorian($jy, $jm, $jd) {
        $jy += 1595;
        $days = -355668 + (365 * $jy) + (((int)($jy / 33)) * 8) + ((int)((($jy % 33) + 3) / 4)) + $jd + (($jm < 7) ? ($jm - 1) * 31 : (($jm - 7) * 30) + 186);
        $gy = 400 * ((int)($days / 146097));
        $days %= 146097;
        
        if ($days > 36524) {
            $gy += 100 * ((int)(--$days / 36524));
            $days %= 36524;
            if ($days >= 365) $days++;
        }
        
        $gy += 4 * ((int)($days / 1461));
        $days %= 1461;
        
        if ($days > 365) {
            $gy += (int)(($days - 1) / 365);
            $days = ($days - 1) % 365;
        }
        
        $gd = $days + 1;
        $sal_a = [0,31,(($gy % 4 == 0 && $gy % 100 != 0) || ($gy % 400 == 0) ? 29 : 28,31,30,31,30,31,31,30,31,30,31];
        
        for ($gm = 0; $gm < 13 && $gd > $sal_a[$gm]; $gm++) {
            $gd -= $sal_a[$gm];
        }
        
        return [$gy, $gm, $gd];
    }

    public static function now($format = 'Y/m/d') {
        $date = new DateTime();
        return self::gregorianToJalaliString($date->format('Y'), $date->format('m'), $date->format('d'), $format);
    }

    public static function gregorianToJalaliString($gy, $gm, $gd, $format = 'Y/m/d') {
        list($jy, $jm, $jd) = self::gregorianToJalali($gy, $gm, $gd);
        
        $map = [
            'Y' => $jy,
            'm' => str_pad($jm, 2, '0', STR_PAD_LEFT),
            'd' => str_pad($jd, 2, '0', STR_PAD_LEFT),
            'H' => date('H'),
            'i' => date('i'),
            's' => date('s')
        ];
        
        return str_replace(array_keys($map), array_values($map), $format);
    }

    public static function addDays($jalaliDate, $days, $format = 'Y/m/d') {
        list($jy, $jm, $jd) = explode('/', $jalaliDate);
        list($gy, $gm, $gd) = self::jalaliToGregorian($jy, $jm, $jd);
        
        $date = new DateTime("$gy-$gm-$gd");
        $date->modify("+$days days");
        
        return self::gregorianToJalaliString(
            $date->format('Y'),
            $date->format('m'),
            $date->format('d'),
            $format
        );
    }
}
?>