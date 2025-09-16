<?php
// helper.php

/**
 * تبدیل تاریخ شمسی به میلادی
 */
function jalali_to_gregorian_date($jalali_date) {
    if (empty($jalali_date)) {
        return null;
    }
    
    $date = trim(preg_replace('/[\\-\s]/', '/', $jalali_date));
    
    if (!preg_match('/^(\d{1,4})\/(\d{1,2})\/(\d{1,2})$/', $date, $matches)) {
        return null;
    }
    
    list(, $jy, $jm, $jd) = $matches;
    
    $jy = (int)$jy;
    $jm = (int)$jm;
    $jd = (int)$jd;
    
    if (function_exists('jalali_to_gregorian')) {
        list($gy, $gm, $gd) = jalali_to_gregorian($jy, $jm, $jd);
        return sprintf('%04d-%02d-%02d', $gy, $gm, $gd);
    }
    
    // تبدیل تقریبی اگر تابع اصلی وجود ندارد
    $gy = $jy + 621;
    return sprintf('%04d-%02d-%02d', $gy, $jm, $jd);
}

/**
 * تبدیل تاریخ میلادی به شمسی برای نمایش
 */
function format_jalali_date($date) {
    if (empty($date) || $date == '0000-00-00') return '';
    
    if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $date, $matches)) {
        return 'تاریخ نامعتبر';
    }
    
    list(, $year, $month, $day) = $matches;
    
    if (function_exists('gregorian_to_jalali')) {
        list($jy, $jm, $jd) = gregorian_to_jalali($year, $month, $day);
        return sprintf('%04d/%02d/%02d', $jy, $jm, $jd);
    }
    
    // تبدیل تقریبی اگر تابع اصلی وجود ندارد
    $jy = $year - 621;
    return sprintf('%04d/%02d/%02d', $jy, $month, $day);
}
?>