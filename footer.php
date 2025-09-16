<?php
// footer.php - بدون هیچ فضای خالی قبل از این خط
?>
        </div> <!-- بستن container-fluid از header -->
    </div> <!-- بستن container-fluid اصلی -->

    <!-- Footer -->
    <footer class="sticky-footer bg-white mt-5">
        <div class="container my-auto">
            <div class="copyright text-center my-auto">
                <span>کلیه حقوق محفوظ است &copy; برنامه نویسان عمادی و بهرامی; سیستم برنامه‌ریزی شخصی <?php echo date('Y'); ?></span>
            </div>
        </div>
    </footer>

    <!-- Scroll to Top Button-->
    <a class="scroll-to-top rounded" href="#page-top">
        <i class="bi bi-arrow-up"></i>
    </a>

    <!-- Bootstrap & JavaScript Libraries -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link rel="stylesheet" href="https://unpkg.com/persian-datepicker@1.2.0/dist/css/persian-datepicker.min.css">
    <script src="https://unpkg.com/persian-date@1.1.0/dist/persian-date.min.js"></script>
    <script src="https://unpkg.com/persian-datepicker@1.2.0/dist/js/persian-datepicker.min.js"></script>

    <!-- Custom Scripts -->
    <script>
    // Sidebar Toggle - اگر سایدباری وجود دارد
    $(document).ready(function() {
        // فعال کردن تقویم شمسی
        $('.j-datepicker').pDatepicker({
            format: 'YYYY/MM/DD',
            initialValue: false,
            autoClose: true,
            position: 'auto',
            observer: true,
            calendarType: 'persian'
        });
        
        // تنظیم تاریخ امروز به صورت پیش‌فرض
        const today = new persianDate().format('YYYY/MM/DD');
        $('.j-datepicker').each(function() {
            if (!$(this).val()) {
                $(this).val(today);
            }
        });

        // اسکرول به بالا
        $('.scroll-to-top').click(function(e) {
            e.preventDefault();
            $('html, body').animate({scrollTop:0}, '300');
        });
    });
    </script>
</body>
</html>
<?php
// فقط در صورتی که بافر خروجی فعال است، آن را ببندید
if (ob_get_length() > 0) {
    ob_end_flush();
}
// بدون فضای خالی یا خط جدید بعد از این خط