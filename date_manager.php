<?php
require_once 'jalali.php';

class DateManager {
    public static function getCurrentJalali() {
        return JalaliDate::now();
    }

    public static function convertToGregorian($jalaliDate) {
        if (empty($jalaliDate)) return null;
        
        list($jy, $jm, $jd) = explode('/', $jalaliDate);
        list($gy, $gm, $gd) = JalaliDate::jalaliToGregorian($jy, $jm, $jd);
        
        return sprintf('%04d-%02d-%02d', $gy, $gm, $gd);
    }

    public static function convertToJalali($gregorianDate) {
        if (empty($gregorianDate) || $gregorianDate == '0000-00-00') return '';
        
        list($gy, $gm, $gd) = explode('-', $gregorianDate);
        return JalaliDate::gregorianToJalaliString($gy, $gm, $gd);
    }

    public static function addDaysToJalali($jalaliDate, $days) {
        return JalaliDate::addDays($jalaliDate, $days);
    }

    public static function validateJalaliDate($date) {
        if (!preg_match('/^(\d{4})\/(\d{1,2})\/(\d{1,2})$/', $date, $matches)) {
            return false;
        }
        
        $year = (int)$matches[1];
        $month = (int)$matches[2];
        $day = (int)$matches[3];
        
        if ($month < 1 || $month > 12 || $day < 1 || $day > 31) {
            return false;
        }
        
        // بررسی صحت تاریخ شمسی
        $days_in_month = [31, 31, 31, 31, 31, 31, 30, 30, 30, 30, 30, 29];
        
        // بررسی سال کبیسه
        if ($month == 12 && self::isLeapYear($year)) {
            $days_in_month[11] = 30;
        }
        
        return $day <= $days_in_month[$month - 1];
    }

    private static function isLeapYear($year) {
        $leap_years = [1, 5, 9, 13, 17, 22, 26, 30];
        return in_array(($year % 33), $leap_years);
    }
}
?>