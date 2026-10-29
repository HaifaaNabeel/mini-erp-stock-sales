# Mini ERP Stock & Sales

تطبيق ويب داخلي لإدارة المنتجات والمستودعات والمخزون والمبيعات (Sprint 1 MVP محلي).

**المرجع الرسمي للمتطلبات:** [`docs/MASTER_PROJECT_DOCUMENT_AR.md`](docs/MASTER_PROJECT_DOCUMENT_AR.md)

## الحالة الحالية

| البند | الحالة |
| --- | --- |
| وثيقة المشروع الرسمية | مكتملة في المسار المعتمد |
| CodeIgniter **3.1.13** | مثبت ومُتحقق (CLI + HTTP) |
| قاعدة البيانات / الجداول | لم تُنفَّذ بعد (الحزمة B) |
| المصادقة والميزات التجارية | لم تبدأ |

## التقنيات

- PHP 8.2.x (مُتحقق محليًا: 8.2.12)
- CodeIgniter 3.1.13
- MariaDB/MySQL (XAMPP)
- HTML / CSS / JavaScript / AJAX عند الحاجة

## المتطلبات المحلية

- XAMPP (Apache + MariaDB) أو مكافئ
- PHP 8.2+ مع `mysqli`
- Git
- Composer (اختياري حاليًا؛ الإطار من الأرشيف المحلي)

## التشغيل السريع (بعد الحزمة A)

1. شغّل Apache من XAMPP.
2. انسخ قوالب الإعداد إن لزم:

```text
copy application\config\config.php.example application\config\config.php
copy application\config\database.php.example application\config\database.php
```

3. عدّل `application/config/database.php` محليًا فقط (لا ترفع أسرارًا). اسم قاعدة مقترح: `mini_erp_stock_sales` — **إنشاؤها ضمن الحزمة B**.
4. تأكد أن `base_url` في `config.php` يطابق مسارك، الافتراضي:

`http://localhost/codeigniter-projects/mini-erp-stock-sales/`

5. افتح الرابط في المتصفح. الصفحة الافتراضية: Welcome to CodeIgniter.

المنطقة الزمنية المركزية: `Asia/Riyadh` (مضبوطة في `index.php`).

## الأمان

- لا تضع كلمات مرور حقيقية في Git.
- `application/config/config.php` و`database.php` و`email.php` مستثناة عبر `.gitignore`.
- استخدم ملفات `*.example` كنماذج بلا أسرار.

## الوثائق

| ملف | الغرض |
| --- | --- |
| `docs/MASTER_PROJECT_DOCUMENT_AR.md` | مصدر الحقيقة |
| `docs/Mini_ERP_Master_Project_Document_AR_v1.0.md` / `.docx` | نسخ مرجعية v1.0 |
| `docs/decisions.md` | سجل القرارات |
| `docs/implementation-log.md` | سجل التنفيذ |

## الميزات المخططة (لاحقًا)

منتجات وتصنيفات، مستودعات وأرصدة، حركات مخزون، فواتير مبيعات، تقارير، RBAC، سجل تدقيق — حسب الحزم B–H في الوثيقة الرسمية.
