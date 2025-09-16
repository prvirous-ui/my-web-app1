<?php
// jdate_tester.php - ابزار تست و عیب‌یابی توابع تاریخ شمسی

// تابع اصلی jdate.php
function gregorian_to_jalali($g_y, $g_m, $g_d) {
    $g_days_in_month = array(31, 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31);
    $j_days_in_month = array(31, 31, 31, 31, 31, 31, 30, 30, 30, 30, 30, 29);
    
    $gy = $g_y - 1600;
    $gm = $g_m - 1;
    $gd = $g_d - 1;
    
    $g_day_no = 365 * $gy + floor(($gy + 3) / 4) - floor(($gy + 99) / 100) + floor(($gy + 399) / 400);
    
    for ($i = 0; $i < $gm; ++$i)
        $g_day_no += $g_days_in_month[$i];
    if ($gm > 1 && (($gy % 4 == 0 && $gy % 100 != 0) || ($gy % 400 == 0)))
        $g_day_no++;
    $g_day_no += $gd;
    
    $j_day_no = $g_day_no - 79;
    
    $j_np = floor($j_day_no / 12053);
    $j_day_no = $j_day_no % 12053;
    
    $jy = 979 + 33 * $j_np + 4 * floor($j_day_no / 1461);
    $j_day_no %= 1461;
    
    if ($j_day_no >= 366) {
        $jy += floor(($j_day_no - 1) / 365);
        $j_day_no = ($j_day_no - 1) % 365;
    }
    
    for ($i = 0; $i < 11 && $j_day_no >= $j_days_in_month[$i]; ++$i)
        $j_day_no -= $j_days_in_month[$i];
    $jm = $i + 1;
    $jd = $j_day_no + 1;
    
    return array($jy, $jm, $jd);
}

function jdate($format, $timestamp = '', $none = '', $time_zone = 'Asia/Tehran', $tr_num = 'fa') {
    $timestamp = $timestamp === '' ? time() : $timestamp;
    
    date_default_timezone_set($time_zone);
    
    $persian_days = array('یکشنبه', 'دوشنبه', 'سه‌شنبه', 'چهارشنبه', 'پنجشنبه', 'جمعه', 'شنبه');
    $persian_months = array(
        'فروردین', 'اردیبهشت', 'خرداد', 'تیر', 'مرداد', 'شهریور',
        'مهر', 'آبان', 'آذر', 'دی', 'بهمن', 'اسفند'
    );
    
    if ($format == 'l') {
        $day_of_week = date('w', $timestamp);
        return $persian_days[$day_of_week];
    }
    
    $date = date('Y-m-d', $timestamp);
    list($year, $month, $day) = explode('-', $date);
    list($jyear, $jmonth, $jday) = gregorian_to_jalali($year, $month, $day);
    
    if ($format == 'Y/m/d') {
        return $jyear . '/' . ($jmonth < 10 ? '0' . $jmonth : $jmonth) . '/' . ($jday < 10 ? '0' . $jday : $jday);
    }
    
    return $jyear . '/' . $jmonth . '/' . $jday;
}

// توابع کمکی جدید
function get_current_gregorian_date() {
    return date('Y-m-d');
}

function get_current_jalali_date_improved() {
    $current_gregorian = date('Y-m-d');
    $parts = explode('-', $current_gregorian);
    $jalali = gregorian_to_jalali((int)$parts[0], (int)$parts[1], (int)$parts[2]);
    return sprintf("%04d/%02d/%02d", $jalali[0], $jalali[1], $jalali[2]);
}

// تست توابع
$current_gregorian = get_current_gregorian_date();
$current_jalali_old = jdate('Y/m/d');
$current_jalali_new = get_current_jalali_date_improved();

// بررسی مشکل
$is_same = ($current_jalali_old == $current_jalali_new);
$problem = $is_same ? "مشکل: هر دو تابع نتیجه یکسان می‌دهند" : "مشکل: نتایج متفاوت هستند";

// نمایش نتایج
echo "<!DOCTYPE html>
<html dir='rtl'>
<head>
    <meta charset='UTF-8'>
    <title>ابزار تست تاریخ شمسی</title>
    <style>
        body { font-family: Tahoma, Arial, sans-serif; background-color: #f5f5f5; margin: 0; padding: 20px; }
        .container { max-width: 800px; margin: 0 auto; background: white; padding: 20px; border-radius: 10px; box-shadow: 0 0 10px rgba(0,0,0,0.1); }
        h1 { color: #3c3c3c; text-align: center; margin-bottom: 30px; }
        .card { background: #f9f9f9; border-radius: 8px; padding: 15px; margin-bottom: 15px; box-shadow: 0 2px 4px rgba(0,0,0,0.05); }
        .card-title { font-weight: bold; color: #4a4a4a; margin-bottom: 5px; }
        .card-value { font-size: 18px; color: #2c3e50; }
        .problem { background: #ffebee; color: #c62828; padding: 15px; border-radius: 8px; margin: 20px 0; }
        .solution { background: #e8f5e9; color: #2e7d32; padding: 15px; border-radius: 8px; margin: 20px 0; }
        .test-form { margin-top: 30px; }
        .form-group { margin-bottom: 15px; }
        label { display: block; margin-bottom: 5px; font-weight: bold; }
        input[type='date'] { padding: 8px; width: 200px; border: 1px solid #ddd; border-radius: 4px; }
        button { background: #4285f4; color: white; border: none; padding: 10px 15px; border-radius: 4px; cursor: pointer; }
        button:hover { background: #3367d6; }
        .result { margin-top: 15px; padding: 10px; background: #e3f2fd; border-radius: 4px; }
    </style>
</head>
<body>
    <div class='container'>
        <h1>ابزار تست و عیب‌یابی تاریخ شمسی</h1>
        
        <div class='card'>
            <div class='card-title'>تاریخ میلادی امروز:</div>
            <div class='card-value'>$current_gregorian</div>
        </div>
        
        <div class='card'>
            <div class='card-title'>تاریخ شمسی (تابع jdate قدیمی):</div>
            <div class='card-value'>$current_jalali_old</div>
        </div>
        
        <div class='card'>
            <div class='card-title'>تاریخ شمسی (تابع جدید بهبود یافته):</div>
            <div class='card-value'>$current_jalali_new</div>
        </div>
        
        <div class='problem'>
            <strong>تشخیص مشکل:</strong> $problem
        </div>
        
        <div class='solution'>
            <strong>راه‌حل پیشنهادی:</strong>
            <p>1. از تابع جدید get_current_jalali_date_improved() به جای jdate() استفاده کنید.</p>
            <p>2. مطمئن شوید که سرور شما از منطقه زمانی صحیح (Asia/Tehran) استفاده می‌کند.</p>
            <p>3. از صحت فرمت تاریخ‌های ورودی اطمینان حاصل کنید.</p>
        </div>
        
        <div class='test-form'>
            <h3>تست تبدیل تاریخ</h3>
            <form method='POST'>
                <div class='form-group'>
                    <label>تاریخ میلادی را وارد کنید (YYYY-MM-DD):</label>
                    <input type='date' name='test_date' required>
                </div>
                <button type='submit'>تبدیل به تاریخ شمسی</button>
            </form>";

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['test_date'])) {
    $test_date = $_POST['test_date'];
    if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $test_date, $matches)) {
        $year = (int)$matches[1];
        $month = (int)$matches[2];
        $day = (int)$matches[3];
        
        $jalali = gregorian_to_jalali($year, $month, $day);
        $jalali_formatted = sprintf("%04d/%02d/%02d", $jalali[0], $jalali[1], $jalali[2]);
        
        echo "<div class='result'>
                <strong>نتایج تبدیل:</strong><br>
                تاریخ میلادی: $test_date<br>
                تاریخ شمسی: $jalali_formatted
              </div>";
    } else {
        echo "<div class='result' style='background:#ffebee;'>فرت تاریخ نامعتبر است. لطفاً تاریخ را به فرمت YYYY-MM-DD وارد کنید.</div>";
    }
}

echo "
        </div>
    </div>
</body>
</html>";
?>