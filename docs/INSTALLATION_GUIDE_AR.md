# دليل التثبيت والتشغيل الشامل — Mini ERP Stock & Sales
# Comprehensive Installation & Deployment Manual

**المشروع:** Mini ERP Stock & Sales
**الإصدار:** 1.0 (Sprint 1 MVP)
**العملة المعتمدة:** الريال السعودي (SAR)
**اللغة:** العربية (RTL)

---

## 1. المتطلبات البرمجية والبيئة المدعومة (System Requirements)

| المكون | المواصفة المطلوبة | الإصدار المختبر فعلياً |
| :--- | :--- | :--- |
| **نظام التشغيل** | Windows 10/11 أو Linux (Ubuntu/Debian) | Windows 11 Pro 64-bit |
| **حزمة خادم الويب** | XAMPP أو Apache 2.4+ مستقل | XAMPP 8.2.12 |
| **لغة البرمجة (PHP)** | PHP 7.4.x إلى PHP 8.2.x | PHP 8.2.12 (CLI & Module) |
| **امتدادات PHP المطلوبة** | `mysqli`, `mbstring`, `json`, `session`, `ctype` | مفعلة افتراضياً في XAMPP |
| **قواعد البيانات (RDBMS)** | MariaDB 10.4.32+ أو MySQL 8.0+ | MariaDB 10.4.32 (InnoDB) |
| **إطار العمل** | CodeIgniter 3.1.13 | 3.1.13 (مع معالجة توافقية PHP 8.2) |

---

## 2. خطوات تثبيت المشروع وإعداده في بيئة XAMPP

### الخطوة 1: وضع المشروع داخل مجلد الويب `htdocs`
تأكد من وجود مجلد المشروع داخل المسار المعتمد لخادم Apache المحلي في XAMPP:

```text
C:\xampp\htdocs\codeigniter-projects\mini-erp-stock-sales\
```

> [!NOTE]
> إذا كان مسار XAMPP لديك مختلفاً (مثلاً على القرص `D:\` أو مسار مخصص)، انسخ المشروع داخل مجلد `htdocs` وعدّل الروابط تبعاً لذلك.

---

### الخطوة 2: تشغيل خدمات Apache و MySQL
1. افتح لوحة تحكم XAMPP (**XAMPP Control Panel**).
2. انقر على زر **Start** بجوار خدمة **Apache**.
3. انقر على زر **Start** بجوار خدمة **MySQL**.
4. تأكد من إضاءة الخدمتين باللون الأخضر وظهور أرقام المنافذ (80, 443 لـ Apache، و 3306 لـ MySQL).

---

### الخطوة 3: إنشاء قاعدة البيانات
1. افتح المتصفح وانتقل إلى أداة phpMyAdmin:
   `http://localhost/phpmyadmin/`
2. انقر على تبويب **Databases** (قواعد البيانات).
3. أدخل اسم قاعدة البيانات: `mini_erp_stock_sales`.
4. اختر الترميز الموحد: **`utf8mb4_unicode_ci`**.
5. انقر على زر **Create** (إنشاء).

أو عبر موجه الأوامر (Command Prompt):
```sql
CREATE DATABASE `mini_erp_stock_sales` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

---

### الخطوة 4: استيراد المخطط الهيكلي الكامل (`schema_full.sql`)
يجب استيراد المخطط الشامل أولاً لبناء الجداول الـ 13 والعلاقات والفهارس:
1. في phpMyAdmin، اختر قاعدة البيانات `mini_erp_stock_sales`.
2. انقر على تبويب **Import** (استيراد).
3. اختر الملف من داخل المشروع:
   `database/schema_full.sql`
4. انقر على زر **Import / Go** في أسفل الصفحة.

أو عبر موجه الأوامر:
```bash
mysql -u root -p mini_erp_stock_sales < "C:\xampp\htdocs\codeigniter-projects\mini-erp-stock-sales\database\schema_full.sql"
```

---

### الخطوة 5: استيراد البيانات التجريبية الشاملة (`seed_demo_data.sql`)
1. في phpMyAdmin، تأكد من استمرار تحديد قاعدة البيانات `mini_erp_stock_sales`.
2. انقر على تبويب **Import** (استيراد).
3. اختر ملف البيانات التجريبية:
   `database/seed_demo_data.sql`
4. انقر على زر **Import / Go**.

أو عبر موجه الأوامر:
```bash
mysql -u root -p mini_erp_stock_sales < "C:\xampp\htdocs\codeigniter-projects\mini-erp-stock-sales\database\seed_demo_data.sql"
```

> [!TIP]
> يزودك هذا الملف ببيانات تجريبية واقعية جاهزة للاستخدام: 3 مستخدمين (مدير، مستودع، مبيعات)، مستودعان، 4 تصنيفات، 20 منتجاً، 8 عملاء، 8 فواتير، 63 حركة مخزون، وأرصدة كاملة.

---

### الخطوة 6: ضبط إعدادات الاتصال بقاعدة البيانات
افحص الملف `application/config/database.php` وتأكد من مطابقة إعدادات الاتصال لبيئتك المحلية (الإعداد الافتراضي لـ XAMPP لا يتطلب كلمة مرور للمستخدم `root`):

```php
$db['default'] = array(
    'dsn'	       => '',
    'hostname'     => '127.0.0.1',
    'username'     => 'root',
    'password'     => '',
    'database'     => 'mini_erp_stock_sales',
    'dbdriver'     => 'mysqli',
    'dbprefix'     => '',
    'pconnect'     => FALSE,
    'db_debug'     => (ENVIRONMENT !== 'production'),
    'cache_on'     => FALSE,
    'cachedir'     => '',
    'char_set'     => 'utf8mb4',
    'dbcollat'     => 'utf8mb4_unicode_ci',
    'swap_pre'     => '',
    'encrypt'      => FALSE,
    'compress'     => FALSE,
    'stricton'     => FALSE,
    'failover'     => array(),
    'save_queries' => TRUE
);
```

---

### الخطوة 7: ضبط رابط التطبيق الأساسي (Base URL)
في الملف `application/config/config.php`، تأكد من أن الرابط الأساسي يطابق مسار المشروع:

```php
$config['base_url'] = 'http://localhost/codeigniter-projects/mini-erp-stock-sales/';
```

> [!NOTE]
> تم ضبط المنطقة الزمنية المركزية للنظام تلقائياً في `index.php` لتكون:
> `date_default_timezone_set('Asia/Riyadh');`

---

## 3. تسجيل الدخول وتشغيل النظام

1. افتح المتصفح وتوجه إلى الرابط:
   `http://localhost/codeigniter-projects/mini-erp-stock-sales/`
2. سيتم توجيهك تلقائياً إلى صفحة تسجيل الدخول (`index.php/login`).
3. استخدم أحد الحسابات التجريبية المعتمدة التالية لتجربة الأدوار المختلفة:

| الدور (Role) | اسم المستخدم (Username) | كلمة المرور (Password) | النطاق والصلاحيات |
| :--- | :--- | :--- | :--- |
| **مدير النظام** | `admin` | `Admin@123456` | وصول كامل لكافة المستودعات، المنتجات، الفواتير، الإلغاء، التقارير، وسجل التدقيق. |
| **أمين المستودع** | `warehouse_user` | `Warehouse@123456` | محصور بمستودع الرياض وجدة لتوريد وتسوية المخزون وعرض الأرصدة والمخزون المنخفض. |
| **مسؤول المبيعات** | `sales_user` | `Sales@123456` | محصور بمستودع الرياض لإدارة العملاء وإنشاء الفواتير وعرض مبيعاته. |

---

## 4. استكشاف وحل المشكلات الشائعة (Troubleshooting)

### 1. مشكلة ظهور خطأ `Unable to connect to your database server`
- **السبب:** خدمة MySQL غير مشغلة، أو بيانات المستخدم/كلمة المرور في `database.php` غير صحيحة.
- **الحل:**
  1. تأكد من تشغيل MySQL في لوحة تحكم XAMPP.
  2. افتح `application/config/database.php` وتأكد من أن `hostname` هو `127.0.0.1` أو `localhost`، وأن اسم القاعدة هو `mini_erp_stock_sales`.

### 2. مشكلة ظهور صفحات غير موجودة 404 عند التنقل بين الروابط
- **السبب:** عدم تفعيل امتداد `mod_rewrite` في خادم Apache، أو غياب ملف `.htaccess`.
- **الحل:**
  1. تأكد من وجود ملف `.htaccess` في المجلد الرئيسي للمشروع.
  2. افتح ملف `C:\xampp\apache\conf\httpd.conf` وتأكد من إزالة علامة `#` من أمام السطر:
     `LoadModule rewrite_module modules/mod_rewrite.so`
  3. تأكد من ضبط `AllowOverride All` لدليل `htdocs`.
  4. أعد تشغيل خدمة Apache.

### 3. خطأ `The action you have requested is not allowed` (CSRF Error 403)
- **السبب:** انتهاء صلاحية رمز توكن CSRF أو إرسال طلب POST خارجي دون الرمز.
- **الحل:**
  1. حدّث الصفحة لإعادة توليد رمز CSRF جديد.
  2. تأكد من تفعيل ملفات الكوكيز (Cookies) في المتصفح.

### 4. ظهور نصوص أو أسماء عربية مشوهة في Excel عند فتح ملفات CSV
- **السبب:** فتح الملف ببرنامج لا يدعم ترميزات UTF-8 أو فاصل القوائم الإقليمي.
- **الحل:**
  - تم حل هذه المشكلة هندسياً في النظام؛ حيث تُصدر ملفات CSV ببادئة `UTF-8 BOM` وبفاصل منقوط (`;`) ليتعرف عليها Microsoft Excel العربي تلقائياً ويوزع الأعمدة بشكل مباشر.
  - إذا كنت تستخدم إصدار Excel إنجليزي يعتمد الفاصلة، يمكنك التصدير مباشرة عبر إضافة `?delimiter=comma` لرابط التصدير.
