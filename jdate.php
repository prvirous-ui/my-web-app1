<?php
// jdate.php - توابع تاریخ شمسی (نسخه بهبود یافته)

if (!function_exists('gregorian_to_jalali')) {
    /**
     * تبدیل تاریخ میلادی به شمسی
     * 
     * @param int $g_y سال میلادی
     * @param int $g_m ماه میلادی
     * @param int $g_d روز میلادی
     * @return array [سال شمسی, ماه شمسی, روز شمسی]
     */
    function gregorian_to_jalali($g_y, $g_m, $g_d) {
        $g_days_in_month = [31, 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31];
        $j_days_in_month = [31, 31, 31, 31, 31, 31, 30, 30, 30, 30, 30, 29];
        
        $gy = $g_y - 1600;
        $gm = $g_m - 1;
        $gd = $g_d - 1;
        
        // محاسبه تعداد روزهای گذشته از سال 1600
        $g_day_no = 365 * $gy + (int)(($gy + 3) / 4) - (int)(($gy + 99) / 100) + (int)(($gy + 399) / 400);
        
        // اضافه کردن روزهای ماه‌های گذشته
        for ($i = 0; $i < $gm; ++$i) {
            $g_day_no += $g_days_in_month[$i];
        }
        
        // اضافه کردن روز اضافه برای سال کبیسه
        if ($gm > 1 && (($gy % 4 == 0 && $gy % 100 != 0) || ($gy % 400 == 0))) {
            $g_day_no++;
        }
        
        $g_day_no += $gd;
        
        // تبدیل به تاریخ شمسی
        $j_day_no = $g_day_no - 79;
        $j_np = (int)($j_day_no / 12053);
        $j_day_no %= 12053;
        
        $jy = 979 + 33 * $j_np + 4 * (int)($j_day_no / 1461);
        $j_day_no %= 1461;
        
        if ($j_day_no >= 366) {
            $jy += (int)(($j_day_no - 1) / 365);
            $j_day_no = ($j_day_no - 1) % 365;
        }
        
        // پیدا کردن ماه و روز شمسی
        for ($i = 0; $i < 11 && $j_day_no >= $j_days_in_month[$i]; ++$i) {
            $j_day_no -= $j_days_in_month[$i];
        }
        
        $jm = $i + 1;
        $jd = $j_day_no + 1;
        
        return [$jy, $jm, $jd];
    }
}

if (!function_exists('jalali_to_gregorian')) {
    /**
     * تبدیل تاریخ شمسی به میلادی
     * 
     * @param int $j_y سال شمسی
     * @param int $j_m ماه شمسی
     * @param int $j_d روز شمسی
     * @return array [سال میلادی, ماه میلادی, روز میلادی]
     */
    function jalali_to_gregorian($j_y, $j_m, $j_d) {
        $g_days_in_month = [31, 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31];
        $j_days_in_month = [31, 31, 31, 31, 31, 31, 30, 30, 30, 30, 30, 29];
        
        $jy = $j_y - 979;
        $jm = $j_m - 1;
        $jd = $j_d - 1;
        
        // محاسبه تعداد روزهای گذشته از سال 979 شمسی
        $j_day_no = 365 * $jy + (int)($jy / 33) * 8 + (int)(($jy % 33 + 3) / 4);
        
        for ($i = 0; $i < $jm; ++$i) {
            $j_day_no += $j_days_in_month[$i];
        }
        
        $j_day_no += $jd;
        
        // تبدیل به تاریخ میلادی
        $g_day_no = $j_day_no + 79;
        $gy = 1600 + 400 * (int)($g_day_no / 146097);
        $g_day_no %= 146097;
        
        $leap = true;
        if ($g_day_no >= 36525) {
            $g_day_no--;
            $gy += 100 * (int)($g_day_no / 36524);
            $g_day_no %= 36524;
            
            if ($g_day_no >= 365) {
                $g_day_no++;
            } else {
                $leap = false;
            }
        }
        
        $gy += 4 * (int)($g_day_no / 1461);
        $g_day_no %= 1461;
        
        if ($g_day_no >= 366) {
            $leap = false;
            $g_day_no--;
            $gy += (int)($g_day_no / 365);
            $g_day_no %= 365;
        }
        
        // پیدا کردن ماه و روز میلادی
        for ($i = 0; $g_day_no >= $g_days_in_month[$i] + ($i == 1 && $leap); $i++) {
            $g_day_no -= $g_days_in_month[$i] + ($i == 1 && $leap);
        }
        
        $gm = $i + 1;
        $gd = $g_day_no + 1;
        
        return [$gy, $gm, $gd];
    }
}

if (!function_exists('jdate')) {
    /**
     * فرمت‌بندی تاریخ شمسی
     * 
     * @param string $format فرمت خروجی
     * @param int $timestamp تایم‌استامپ (پیش‌فرض: زمان حال)
     * @param string $none مقدار پیش‌فرض برای تاریخ نامعتبر
     * @param string $time_zone منطقه زمانی
     * @param string $tr_num نوع اعداد ('fa' برای فارسی، 'en' برای انگلیسی)
     * @return string تاریخ فرمت‌بندی شده
     */
    function jdate($format, $timestamp = '', $none = '', $time_zone = 'Asia/Tehran', $tr_num = 'fa') {
        $timestamp = ($timestamp === '') ? time() : $timestamp;
        
        // تنظیم منطقه زمانی
        $original_timezone = date_default_timezone_get();
        date_default_timezone_set($time_zone);
        
        // آرایه‌های فارسی
        $persian_days = ['یکشنبه', 'دوشنبه', 'سه‌شنبه', 'چهارشنبه', 'پنجشنبه', 'جمعه', 'شنبه'];
        $persian_months = [
            'فروردین', 'اردیبهشت', 'خرداد', 'تیر', 'مرداد', 'شهریور',
            'مهر', 'آبان', 'آذر', 'دی', 'بهمن', 'اسفند'
        ];
        
        // اگر فرمت فقط نام روز باشد
        if ($format === 'l') {
            $day_of_week = (int)date('w', $timestamp);
            $result = $persian_days[$day_of_week];
            date_default_timezone_set($original_timezone);
            return $result;
        }
        
        // تبدیل تاریخ میلادی به شمسی
        $date = date('Y-m-d', $timestamp);
        list($year, $month, $day) = explode('-', $date);
        list($jyear, $jmonth, $jday) = gregorian_to_jalali((int)$year, (int)$month, (int)$day);
        
        // فرمت‌های مختلف
        switch ($format) {
            case 'Y/m/d':
                $result = sprintf('%04d/%02d/%02d', $jyear, $jmonth, $jday);
                break;
            case 'Y-m-d':
                $result = sprintf('%04d-%02d-%02d', $jyear, $jmonth, $jday);
                break;
            case 'd F Y':
                $month_name = ($jmonth >= 1 && $jmonth <= 12) ? $persian_months[$jmonth - 1] : 'نامشخص';
                $result = tr_num($jday, $tr_num) . ' ' . $month_name . ' ' . tr_num($jyear, $tr_num);
                break;
            case 'Y':
                $result = tr_num($jyear, $tr_num);
                break;
            case 'm':
                $result = tr_num($jmonth, $tr_num);
                break;
            case 'd':
                $result = tr_num($jday, $tr_num);
                break;
            default:
                $result = tr_num("$jyear/$jmonth/$jday", $tr_num);
                break;
        }
        
        // بازگرداندن منطقه زمانی اصلی
        date_default_timezone_set($original_timezone);
        
        return $result;
    }
}

if (!function_exists('format_jalali_date')) {
    /**
     * فرمت‌بندی تاریخ شمسی از رشته تاریخ میلادی
     * 
     * @param string $date تاریخ میلادی (YYYY-MM-DD)
     * @param string $format فرمت خروجی
     * @return string تاریخ شمسی فرمت‌بندی شده
     */
    function format_jalali_date($date, $format = 'd F Y') {
        if (empty($date) || $date === '0000-00-00' || $date === '0000-00-00 00:00:00') {
            return '';
        }
        
        if (!preg_match('/^(\d{4})-(\d{1,2})-(\d{1,2})(?:\s+\d{1,2}:\d{1,2}:\d{1,2})?$/', $date, $matches)) {
            return 'تاریخ نامعتبر';
        }
        
        $year = (int)$matches[1];
        $month = (int)$matches[2];
        $day = (int)$matches[3];
        
        list($jy, $jm, $jd) = gregorian_to_jalali($year, $month, $day);
        
        // آرایه نام ماه‌های شمسی
        $persian_months = [
            'فروردین', 'اردیبهشت', 'خرداد', 'تیر', 'مرداد', 'شهریور',
            'مهر', 'آبان', 'آذر', 'دی', 'بهمن', 'اسفند'
        ];
        
        switch ($format) {
            case 'd F Y':
                $month_name = ($jm >= 1 && $jm <= 12) ? $persian_months[$jm - 1] : 'نامشخص';
                return tr_num($jd) . ' ' . $month_name . ' ' . tr_num($jy);
            
            case 'Y/m/d':
                return sprintf('%04d/%02d/%02d', $jy, $jm, $jd);
            
            case 'Y-m-d':
                return sprintf('%04d-%02d-%02d', $jy, $jm, $jd);
            
            default:
                $month_name = ($jm >= 1 && $jm <= 12) ? $persian_months[$jm - 1] : 'نامشخص';
                return tr_num($jd) . ' ' . $month_name . ' ' . tr_num($jy);
        }
    }
}

if (!function_exists('convert_jalali_to_gregorian')) {
    /**
     * تبدیل تاریخ شمسی به میلادی
     * 
     * @param string $date تاریخ شمسی (YYYY/MM/DD)
     * @return string تاریخ میلادی (YYYY-MM-DD) یا رشته خالی در صورت خطا
     */
    function convert_jalali_to_gregorian($date) {
        if (empty($date)) {
            return '';
        }
        
        if (!preg_match('/^(\d{4})\/(\d{1,2})\/(\d{1,2})$/', $date, $matches)) {
            return '';
        }
        
        $jy = (int)$matches[1];
        $jm = (int)$matches[2];
        $jd = (int)$matches[3];
        
        // اعتبارسنجی تاریخ شمسی
        if ($jm < 1 || $jm > 12 || $jd < 1 || $jd > 31) {
            return '';
        }
        
        list($gy, $gm, $gd) = jalali_to_gregorian($jy, $jm, $jd);
        
        // اعتبارسنجی تاریخ میلادی تبدیل شده
        if (!checkdate($gm, $gd, $gy)) {
            return '';
        }
        
        return sprintf('%04d-%02d-%02d', $gy, $gm, $gd);
    }
}

if (!function_exists('tr_num')) {
    /**
     * تبدیل اعداد بین فارسی و انگلیسی
     * 
     * @param string $str رشته حاوی اعداد
     * @param string $mod نوع تبدیل ('en' یا 'fa')
     * @param string $mf جداکننده اعشار فارسی
     * @return string رشته تبدیل شده
     */
    function tr_num($str, $mod = 'en', $mf = '٫') {
        $num_a = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9', '.'];
        $key_a = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹', $mf];
        
        return ($mod === 'fa') 
            ? str_replace($num_a, $key_a, $str) 
            : str_replace($key_a, $num_a, $str);
    }
}

if (!function_exists('get_current_jalali_date')) {
    /**
     * دریافت تاریخ امروز به شمسی
     * 
     * @param string $format فرمت خروجی
     * @return string تاریخ شمسی امروز
     */
    function get_current_jalali_date($format = 'Y/m/d') {
        $current_gregorian = date('Y-m-d');
        $parts = explode('-', $current_gregorian);
        $jalali = gregorian_to_jalali((int)$parts[0], (int)$parts[1], (int)$parts[2]);
        
        switch ($format) {
            case 'Y/m/d':
                return sprintf("%04d/%02d/%02d", $jalali[0], $jalali[1], $jalali[2]);
            case 'Y-m-d':
                return sprintf("%04d-%02d-%02d", $jalali[0], $jalali[1], $jalali[2]);
            case 'd F Y':
                $persian_months = [
                    'فروردین', 'اردیبهشت', 'خرداد', 'تیر', 'مرداد', 'شهریور',
                    'مهر', 'آبان', 'آذر', 'دی', 'بهمن', 'اسفند'
                ];
                $month_name = ($jalali[1] >= 1 && $jalali[1] <= 12) 
                    ? $persian_months[$jalali[1] - 1] 
                    : 'نامشخص';
                return tr_num($jalali[2]) . ' ' . $month_name . ' ' . tr_num($jalali[0]);
            default:
                return sprintf("%04d/%02d/%02d", $jalali[0], $jalali[1], $jalali[2]);
        }
    }
}

if (!function_exists('is_jalali_leap_year')) {
    /**
     * بررسی سال کبیسه شمسی
     * 
     * @param int $year سال شمسی
     * @return bool true اگر کبیسه باشد
     */
    function is_jalali_leap_year($year) {
        // در تقویم هجری شمسی، سال کبیسه سالی است که باقیمانده تقسیم آن بر 33
        // برابر با 1، 5، 9، 13، 17، 22، 26 یا 30 باشد
        $remainder = $year % 33;
        $leap_remainders = [1, 5, 9, 13, 17, 22, 26, 30];
        return in_array($remainder, $leap_remainders);
    }
}

if (!function_exists('validate_jalali_date')) {
    /**
     * اعتبارسنجی تاریخ شمسی
     * 
     * @param int $year سال شمسی
     * @param int $month ماه شمسی
     * @param int $day روز شمسی
     * @return bool true اگر تاریخ معتبر باشد
     */
    function validate_jalali_date($year, $month, $day) {
        if ($year < 1 || $month < 1 || $month > 12 || $day < 1) {
            return false;
        }
        
        $days_in_month = [31, 31, 31, 31, 31, 31, 30, 30, 30, 30, 30, 29];
        
        // اگر سال کبیسه باشد، اسفند 30 روز دارد
        if ($month === 12 && is_jalali_leap_year($year)) {
            $days_in_month[11] = 30;
        }
        
        return $day <= $days_in_month[$month - 1];
    }
}
?>