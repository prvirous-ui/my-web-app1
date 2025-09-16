<?php
// اضافه کردن کتابخانه jdf
include 'jdf.php';

// تابع تبدیل تاریخ شمسی به میلادی با استفاده از jdf
function jalali_to_gregorian($date) {
    if (empty($date)) {
        return date('Y-m-d');
    }
    
    // جدا کردن بخش‌های تاریخ
    list($jy, $jm, $jd) = explode('/', $date);
    
    // استفاده از تابع jdf برای تبدیل
    list($gy, $gm, $gd) = jdf::jalali_to_gregorian($jy, $jm, $jd);
    
    return sprintf('%04d-%02d-%02d', $gy, $gm, $gd);
}

// تابع تبدیل تاریخ میلادی به شمسی با استفاده از jdf
function format_jalali_date($date) {
    if (empty($date) || $date == '0000-00-00') return '';
    
    if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $date, $matches)) {
        return 'تاریخ نامعتبر';
    }
    
    list(, $year, $month, $day) = $matches;
    return jdf::jdate('Y/m/d', strtotime("$year-$month-$day"));
}