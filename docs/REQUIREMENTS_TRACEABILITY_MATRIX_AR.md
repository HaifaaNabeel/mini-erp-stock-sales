# مصفوفة تتبع المتطلبات — Mini ERP Stock & Sales
# Requirements Traceability Matrix (RTM)

**المشروع:** Mini ERP Stock & Sales  
**المرجع الأساسي:** `docs/MASTER_PROJECT_DOCUMENT_AR.md`  
**تاريخ الإعداد:** 2026-10-31  
**حالة المشروع:** مكتمل وجاهز للتسليم النهائي (100%)  

---

## 1. جدول تتبع المتطلبات الوظيفية (Functional Requirements)

| رمز المتطلب | وصف المتطلب الأصلي | حالة التنفيذ | ملفات التنفيذ البرمجية (Implementation) | ملفات الاختبار المؤتمت (Automated Tests) | طريقة التحقق اليدوي (Manual Verification) |
| :---: | :--- | :---: | :--- | :--- | :--- |
| **F01** | **تأسيس التطبيق**<br>- تثبيت CodeIgniter 3.1.13<br>- إعدادات التطبيق والأمان<br>- عدم تخزين أسرار في Git | **مكتمل بنجاح** | `index.php`<br>`application/core/MY_Controller.php`<br>`application/config/config.php`<br>`application/config/database.php` | `tests/test_f10_audit_and_system_integration.php`<br>(Domain 1) | فتح المتصفح على رابط النظام المحلي والتأكد من تحميل الصفحة دون أخطاء PHP أو كشف أسرار. |
| **F02** | **بنية قاعدة البيانات**<br>- محرك InnoDB وترميز utf8mb4<br>- القيود والفهارس والعلاقات<br>- مبالغ `DECIMAL` وكميات صحيحة | **مكتمل بنجاح** | `database/schema_full.sql`<br>`database/schema_f03_auth.sql` إلى `schema_f10_audit_logs.sql` | `tests/test_f10_audit_and_system_integration.php`<br>(Domain 9) | فحص الجداول عبر phpMyAdmin والتأكد من المحرك `InnoDB`، الترميز `utf8mb4_unicode_ci`، والمفاتيح الأجنبية. |
| **F03** | **المصادقة والصلاحيات**<br>- 3 أدوار: admin, warehouse, sales<br>- BCRYPT لتجزئة كلمات المرور<br>- حماية CSRF ومنع وصول المعطلين | **مكتمل بنجاح** | `application/controllers/Auth.php`<br>`application/models/User_model.php`<br>`application/models/Role_model.php`<br>`application/views/auth/login.php` | `tests/test_f10_audit_and_system_integration.php`<br>(Domains 1, 2) | محاولة تسجيل الدخول بحساب غير نشط (يُرفض)، تسجيل الدخول كـ sales ومحاولة فتح رابط `/users` (يُعاد توجيهه برمز 302). |
| **F04** | **التصنيفات والمنتجات**<br>- تفرد رمز SKU (غير حساس للحالة)<br>- تفعيل وتعطيل الأصناف بدل الحذف<br>- تعيين المستودعات للمستخدمين | **مكتمل بنجاح** | `application/controllers/Products.php`<br>`application/models/Product_model.php`<br>`application/models/Category_model.php`<br>`application/views/products/` | `tests/test_f10_audit_and_system_integration.php`<br>(Domain 3) | الدخول كمدير، إنشاء تصنيف ومنتج جديد، محاولة إضافة منتج آخر بنفس الرمز SKU للتأكد من رفض التكرار. |
| **F05** | **المستودعات والأرصدة**<br>- رصيد مستقل لكل منتج بكل مستودع<br>- أقفال ذرية لمنع التزامن<br>- حد إعادة الطلب وتنبيهات النقص | **مكتمل بنجاح** | `application/controllers/Stock.php`<br>`application/models/Warehouse_model.php`<br>`application/models/Stock_model.php`<br>`application/views/stock/` | `tests/test_f10_audit_and_system_integration.php`<br>(Domain 4) | تصفح شاشة أرصدة المخزون، والتحقق من ظهور الرصيد لكل مستودع، وتمييز الأصناف التي بلغت حد إعادة الطلب. |
| **F06** | **حركات المخزون**<br>- توريد وتسوية موجبة وسالبة<br>- دفتر أستاذ تراكمي غير قابل للحذف<br>- منع نزول الرصيد عن الصفر ذرياً | **مكتمل بنجاح** | `application/controllers/Stock_movements.php`<br>`application/models/Stock_movement_model.php`<br>`application/views/stock_movements/` | `tests/test_f10_audit_and_system_integration.php`<br>(Domain 4) | إجراء حركة إدخال مخزون (توريد)، ثم إجراء تسوية بنقص أكبر من الرصيد المتاح؛ والتأكد من رفض العملية وظهور رسالة خطأ. |
| **F07** | **فواتير المبيعات**<br>- فواتير متعددة البنود مع عملاء<br>- حساب المبالغ بالسيرفر حصراً بالريال<br>- خصم المخزون الذري وترقيم الفاتورة | **مكتمل بنجاح** | `application/controllers/Sales_invoices.php`<br>`application/models/Sales_invoice_model.php`<br>`application/models/Customer_model.php`<br>`application/views/sales_invoices/` | `tests/test_f10_audit_and_system_integration.php`<br>(Domain 5)<br>`tests/test_f07_customer_explicit_status.php` | إنشاء فاتورة جديدة كمسؤول مبيعات، التحقق من ظهور رقم تسلسلي مثل `INV-YYYYMMDD-0001` ونقص المخزون فورياً. |
| **F08** | **إلغاء فواتير المبيعات**<br>- حصر الإلغاء بالمدير admin فقط<br>- استعادة المخزون بحركات عكسية ذرية<br>- منع الإلغاء المتكرر وحفظ التاريخ | **مكتمل بنجاح** | `application/controllers/Sales_invoices.php`<br>`application/models/Sales_invoice_model.php`<br>`application/views/sales_invoices/view.php` | `tests/test_f08_invoice_cancellation.php`<br>`tests/test_f10_audit_and_system_integration.php`<br>(Domain 6) | الدخول كمدير واستعراض فاتورة نشطة والضغط على "إلغاء الفاتورة" مع كتابة السبب؛ التأكد من تحولها إلى "ملغاة" واستعادة الرصيد. |
| **F09** | **التقارير وتصدير CSV**<br>- 5 تقارير تشغيلية ولوحة مؤشرات<br>- استبعاد الفواتير الملغاة من المبيعات<br>- تصدير CSV متوافق مع Excel العربي | **مكتمل بنجاح** | `application/controllers/Reports.php`<br>`application/models/Report_model.php`<br>`application/views/reports/` | `tests/test_f09_reports.php`<br>`tests/test_f09_csv_excel_compatibility.php`<br>`tests/test_f10_audit_and_system_integration.php`<br>(Domain 7) | تنزيل تقرير أرصدة المخزون بصيغة CSV وفتحه في Microsoft Excel والتأكد من انفصال الأعمدة وظهور الحروف العربية بوضوح. |
| **F10** | **سجل التدقيق والتحقق النهائي**<br>- تسجيل تدقيق مركزي للأحداث الحساسة<br>- تراجع ذري شامل عند فشل التدقيق<br>- حجب الأسرار وكلمات المرور | **مكتمل بنجاح** | `application/controllers/Audit_logs.php`<br>`application/models/Audit_model.php`<br>`application/libraries/Audit_service.php`<br>`application/views/audit_logs/` | `tests/test_f10_audit_and_system_integration.php`<br>(Domains 8, 9, 10) | الدخول كمدير إلى شاشة `/audit_logs` والتحقق من تسجيل العمليات السابقة مع تفاصيلها واستبعاد أي كلمات مرور أو رموز سرية. |

---

## 2. جدول تتبع المتطلبات غير الوظيفية والأمنية (Non-Functional & Security Requirements)

| المتطلب | المواصفة والقاعدة المعتمدة | حالة التطبيق | دليل وآلية التحقق البرمجية |
| :--- | :--- | :---: | :--- |
| **العملة المعتمدة** | الريال السعودي (SAR) حصراً | **مطبق بالكامل** | قيد قاعدة البيانات `chk_si_currency_sar` وتنسيق العرض الثابت "ر.س" في كافة الفواتير والتقارير. |
| **طبيعة الكميات** | أعداد صحيحة موجبة فقط (Integers) | **مطبق بالكامل** | حقول `INT` في الجداول، والتحقق عبر `FILTER_VALIDATE_INT` في كافة النماذج. |
| **منع الرصيد السالب** | عدم السماح بنزول رصيد أي منتج عن الصفر | **مطبق بالكامل** | قيود `CHECK (quantity >= 0)`، وأقفال `FOR UPDATE` في المعاملات الذرية. |
| **الحماية من CSRF** | تفعيل حماية توكن CSRF لجميع نماذج POST | **مطبق بالكامل** | تفعيل `$config['csrf_protection'] = TRUE;` ورفض أي طلب بدون رمز صالح برمز 403. |
| **الحماية من XSS** | هروب كافة المخرجات النصية الديناميكية | **مطبق بالكامل** | استخدام دالة `html_escape()` المعيارية في كافة واجهات العرض (Views). |
| **الحماية من SQLi** | استعلامات مجهزة خالية من الدمج النصي | **مطبق بالكامل** | الاعتماد الحصري على `CodeIgniter Query Builder` وتمرير المتغيرات كمعاملات (Parameters). |
| **حماية CSV Injection** | منع تنفيذ المعادلات عند فتح الملفات في Excel | **مطبق بالكامل** | دالة `sanitize_csv_cell()` تبدأ الخلايا النصية المبتدئة بـ `=`, `+`, `-`, `@` بعلامة اقتباس `'`. |
| **التوافقية العكسية** | التوافق التام مع PHP 7.4 و PHP 8.2 | **مطبق بالكامل** | خلو الكود من صياغات PHP 8 الحصرية، واجتياز الفحص الصارم `php -l` بنسبة 100%. |
| **اللغة والاتجاه** | واجهة عربية بالكامل من اليمين لليسار (RTL) | **مطبق بالكامل** | هيكلية HTML5 مع `dir="rtl"` و `lang="ar"` ومكتبة Bootstrap 5 RTL وخطوط عربية واضحة. |

---

## 3. ملخص نتائج الاختبارات المؤتمتة المرتبطة بالمصفوفة

| اسم حزمة الاختبار | المراحل المغطاة | عدد الحالات | النتيجة المؤتمتة |
| :--- | :--- | :---: | :---: |
| `tests/test_f10_audit_and_system_integration.php` | F01, F02, F03, F04, F05, F06, F07, F08, F09, F10 | 76 | **76/76 PASS (100%)** |
| `tests/test_f09_reports.php` | F09 (تقارير المخزون والمبيعات والحركات وتنبيهات النقص) | 71 | **71/71 PASS (100%)** |
| `tests/test_f09_csv_excel_compatibility.php` | F09 (توافق CSV الإقليمي مع Excel العربي وعبر COM) | 28 | **28/28 PASS (100%)** |
| `tests/test_f08_invoice_cancellation.php` | F08 (إلغاء الفواتير والحركات العكسية ومنع السباق) | 68 | **68/68 PASS (100%)** |
| `tests/test_f07_customer_explicit_status.php` | F07 (عمليات تفعيل وتعطيل العملاء الصريحة Idempotent) | 37 | **37/37 PASS (100%)** |
| **المجموع الإجمالي** | **تغطية شاملة لجميع متطلبات وثيقة الأساس** | **280** | **280/280 PASS (100%)** |

