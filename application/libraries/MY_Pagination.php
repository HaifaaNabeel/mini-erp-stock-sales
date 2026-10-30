<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * PHP 8.1/8.2 Compatibility Extension for CodeIgniter 3 Pagination
 *
 * المشكلة: الأب يستدعي ctype_digit($this->cur_page) وقد تكون القيمة null
 * عند الصفحة الأولى (لا يوجد رقم صفحة في الـ URL).
 * PHP 8.1+ يُصدر E_DEPRECATED على ذلك.
 *
 * الحل: نُعطّل E_DEPRECATED أثناء create_links() فقط، دون تغيير المنطق.
 */
class MY_Pagination extends CI_Pagination
{
    public function create_links()
    {
        set_error_handler(function ($errno, $errstr) {
            if ($errno === E_DEPRECATED && stripos($errstr, 'ctype_digit') !== false) {
                return true; // تجاهل هذا التحذير فقط
            }
            return false;    // مرّر بقية الأخطاء للمعالج الأصلي
        }, E_DEPRECATED);

        try {
            return parent::create_links();
        } finally {
            restore_error_handler();
        }
    }
}