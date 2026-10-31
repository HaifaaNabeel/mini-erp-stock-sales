<a id="arabic"></a>

# نظام إدارة المخزون والمبيعات — Mini ERP Stock & Sales

**Mini ERP Stock & Sales** — Internal Web-based Inventory and Sales Management System (PHP / CodeIgniter 3 / MariaDB).

---

### التنقل السريع بين اللغات / Language Navigation
**[العربية](#arabic) | [English](#english)**

---

> [!WARNING]
> **تنبيه هام:** البيانات المرفقة في ملفات التأسيس هي **بيانات تجريبية لأغراض الفحص والاعتماد (Demo Data)**، وليست بيانات إنتاجية. لا تُنفذ ملفات التأسيس على قواعد بيانات تحوي بيانات حية.

---

## 1. نظرة عامة على المشروع (Project Overview)

مشروع **Mini ERP Stock & Sales** هو نظام ويب داخلي متكامل ومحكم لإدارة المنتجات، المستودعات المتعددة، أرصدة المخزون، سجل حركات المخزون التراكمي بالإضافة فقط (Append-only)، فواتير المبيعات، حركات الإلغاء العكسية، التقارير التشغيلية والمالية مع تصدير CSV المتوافق مع Excel العربي، وسجل التدقيق المركزي.

- **العملة المعتمدة:** الريال السعودي (**SAR**) حصراً في كافة السجلات والواجهات والتقارير.
- **طبيعة الكميات:** أعداد صحيحة موجبة (**Integers Only**) دون كسور عشرية.
- **قاعدة منع الرصيد السالب:** قيود تحقق خادمية وقواعد تكامل بيانات تمنع نزول رصيد أي منتج في أي مستودع دون الصفر أثناء العمليات التشغيلية والمبيعات.
- **اللغة والواجهة:** واجهات عربية كاملة تدعم اتجاه اليمين إلى اليسار (**RTL**).

---

## 2. الميزات الرئيسية المنفذة (Key Features: F01 — F10)

1. **F01 — تأسيس التطبيق (Project Foundation):** تثبيت وإعداد CodeIgniter 3.1.13، ضبط المنطقة الزمنية `Asia/Riyadh`، تهيئة الأمان، والمتحكم الأساسي `MY_Controller`.
2. **F02 — بنية قاعدة البيانات (Database Architecture):** مخطط تبادلي موحد (InnoDB) بترميز `utf8mb4_unicode_ci` مع علاقات وقيود تكامل صارمة.
3. **F03 — المصادقة والصلاحيات (Authentication & RBAC):** تشفير BCRYPT لكلمات المرور، حماية CSRF، وصلاحيات خادمية صارمة لثلاثة أدوار (`admin`, `warehouse`, `sales`).
4. **F04 — البيانات الأساسية (Master Data):** إدارة التصنيفات، المستودعات، بطاقات المنتجات مع تفرد رمز SKU، والتعطيل الناعم (`is_active = 0`) بدل الحذف المادي.
5. **F05 — أرصدة المخزون (Warehouse Stock):** رصيد مستقل لكل منتج بكل مستودع، أقفال الصفوف الحصرية (`SELECT ... FOR UPDATE`) داخل المعاملات لحماية الرصيد أثناء العمليات، وتنبيهات حد إعادة الطلب الفعال.
6. **F06 — حركات المخزون (Stock Movements):** سجل حركات تراكمي بالإضافة فقط (Append-only) للتوريد (`in`) والتسويات (`adjustment`) مع التوازن الحسابي الصارم وإلزامية السبب.
7. **F07 — فواتير المبيعات (Sales Invoices):** فواتير متعددة البنود، إدارة العملاء، حساب الإجماليات بالسيرفر، لقطات الأسعار التاريخية، وترقيم تسلسلي يومي ذري.
8. **F08 — إلغاء الفواتير (Invoice Cancellation):** حصر الإلغاء بالمدير، توثيق السبب، استرجاع المخزون بحركات عكسية ذرية (`sale_reversal`)، ومنع الإلغاء المزدوج.
9. **F09 — التقارير وتصدير CSV (Reports & CSV):** 5 تقارير مع مؤشرات الأداء، استبعاد الفواتير الملغاة، وتصدير CSV متوافق مع Excel الإقليمي العربي (فاصل منقوط و UTF-8 BOM).
10. **F10 — سجل التدقيق المركزي (Audit Logs):** توثيق العمليات الحساسة، حجب كلمات المرور والأسرار، تراجع ذري شامل عند الفشل، وواجهة إدارية للقراءة فقط.

---

## 3. حزمة التقنيات المستخدمة (Technology Stack)

- **إطار العمل:** CodeIgniter 3.1.13 (معمارية MVC الصارمة وخدمات الأعمال المركزية).
- **لغة البرمجة:** PHP 7.4+ متوافق ومختبر على بيئة PHP 8.2.12.
- **قواعد البيانات:** MariaDB 10.4.32+ / MySQL 8.0+ بمحرك `InnoDB` وترميز `utf8mb4_unicode_ci`.
- **الواجهة الأمامية:** HTML5, CSS3, JavaScript, jQuery, Bootstrap 5 RTL مع دعم كامل للغة العربية.
- **الخادم المحلي المعتمد:** Apache 2.4+ عبر حزمة XAMPP على نظام Windows.

---

## 4. متطلبات التشغيل (System Requirements)

| المكون | المواصفة المطلوبة | البيئة المختبرة فعلياً |
| :--- | :--- | :--- |
| **نظام التشغيل** | Windows 10/11 أو Linux | Windows 11 Pro 64-bit |
| **خادم الويب** | Apache 2.4+ (XAMPP) | Apache 2.4.58 |
| **إصدار PHP** | PHP 7.4.x إلى PHP 8.2.x | PHP 8.2.12 |
| **امتدادات PHP المطلوبة** | `mysqli`, `mbstring`, `json`, `session`, `ctype` | امتدادات مطلوبة يجب التحقق من تفعيلها في ملف `php.ini` |
| **قواعد البيانات** | MariaDB 10.4.32+ أو MySQL 8.0+ | MariaDB 10.4.32 (InnoDB) |

---

## 5. خطوات التثبيت والإعداد (Installation & Setup)

1. **وضع المشروع داخل مجلد الويب:**
   انسخ المشروع داخل مسار خادم Apache المحلي:
   ```text
   C:\xampp\htdocs\codeigniter-projects\mini-erp-stock-sales\
   ```
2. **تشغيل الخدمات:**
   افتح لوحة تحكم XAMPP وشغّل خدمتي **Apache** و **MySQL**.
3. **إنشاء قاعدة البيانات:**
   أنشئ قاعدة بيانات جديدة باسم `mini_erp_stock_sales` بترميز `utf8mb4_unicode_ci` عبر phpMyAdmin أو موجه الأوامر:
   ```sql
   CREATE DATABASE `mini_erp_stock_sales` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
   ```
4. **استيراد المخطط الهيكلي الكامل:**
   استورد الملف: [`database/schema_full.sql`](database/schema_full.sql)
5. **استيراد البيانات التجريبية الشاملة:**
   استورد الملف: [`database/seed_demo_data.sql`](database/seed_demo_data.sql)
6. **فحص إعدادات الاتصال والـ Base URL:**
   - في `application/config/database.php`: تأكد من ضبط اسم القاعدة `mini_erp_stock_sales` والمستخدم `root`.
   - في `application/config/config.php`: تأكد من مطابقة الرابط: [http://localhost/codeigniter-projects/mini-erp-stock-sales/](http://localhost/codeigniter-projects/mini-erp-stock-sales/)
7. **فتح التطبيق:**
   توجه إلى الرابط: [http://localhost/codeigniter-projects/mini-erp-stock-sales/](http://localhost/codeigniter-projects/mini-erp-stock-sales/)

---

## 6. تعليمات المخطط والبيانات التجريبية (Database Schema & Demo Data)

- **ملف المخطط الموحد:** [`database/schema_full.sql`](database/schema_full.sql) يحتوي على الهيكلية الكاملة لـ **13 جدولاً** مع كافة القيود المرجعية والفهارس وقيود التحقق الرياضية.
- **ملف البيانات التجريبية:** [`database/seed_demo_data.sql`](database/seed_demo_data.sql) مخصص صراحة لقاعدة بيانات جديدة وفارغة، ولا يحتوي على أي أوامر حذف (`DROP`, `TRUNCATE`, `DELETE`).
- **دليل البيانات:** راجع [`database/DEMO_DATA_GUIDE_AR.md`](database/DEMO_DATA_GUIDE_AR.md) للاطلاع على تفاصيل المنتجات والمستودعات وسيناريوهات فحص نقص المخزون.

---

## 7. الحسابات التجريبية وبيانات الدخول (Demo Credentials)

| الدور (Role) | اسم المستخدم (Username) | كلمة المرور (Password) | النطاق والصلاحيات |
| :--- | :--- | :--- | :--- |
| **مدير النظام** | `admin` | `Admin@123456` | وصول شامل لكافة الأقسام، إدارة المستخدمين، التوريد، التسويات، الفواتير، الإلغاء، وسجل التدقيق. |
| **أمين المستودع** | `warehouse_user` | `Warehouse@123456` | محصور بمستودع الرياض وجدة؛ توريد وتسوية المخزون وعرض الأرصدة والمخزون المنخفض. محظور من الفواتير والتدقيق. |
| **مسؤول المبيعات** | `sales_user` | `Sales@123456` | محصور بمستودع الرياض؛ إدارة وتفعيل العملاء، إنشاء فواتير المبيعات وعرض مبيعاته. محظور من التوريد والإلغاء والتدقيق. |

---

## 8. هيكلية مجلدات المشروع (Project Directory Structure)

```text
mini-erp-stock-sales/
├── application/                  # شيفرة التطبيق البرمجية وفق نمط MVC
│   ├── config/                   # ملفات الإعدادات (قواعد البيانات، المسارات، الجلسات)
│   ├── controllers/              # المتحكمات النحيفة (Auth, Products, Stock, Sales, Reports, Audit)
│   ├── core/                     # فئات التوسعة الأساسية (MY_Controller, MY_Loader, إلخ)
│   ├── libraries/                # مكتبات الأعمال المركزية (Audit_service)
│   ├── models/                   # نماذج قواعد البيانات واستعلامات المعاملات الذرية
│   └── views/                    # واجهات العرض العربية RTL ومكونات الترقيم
├── database/                     # ملفات ومخططات قواعد البيانات
│   ├── schema_full.sql           # المخطط الشامل الموحد (13 جدولاً)
│   ├── seed_demo_data.sql        # سكربت البيانات التجريبية الشامل
│   ├── DEMO_DATA_GUIDE_AR.md     # دليل تفاصيل وفحص البيانات التجريبية
│   └── README.md                 # فهرس المخططات والترقيات المرحلية
├── docs/                         # الوثائق الرسمية والتقارير الهندسية
│   ├── MASTER_PROJECT_DOCUMENT_AR.md # وثيقة الأساس المعتمدة (مصدر الحقيقة)
│   ├── INSTALLATION_GUIDE_AR.md  # دليل التثبيت والتشغيل الشامل
│   ├── IMPLEMENTATION_AND_DECISIONS_AR.md # وثيقة التنفيذ والقرارات المعمارية
│   ├── FINAL_PROJECT_REPORT_AR.md # التقرير الختامي الشامل لنتائج المشروع
│   ├── REQUIREMENTS_TRACEABILITY_MATRIX_AR.md # مصفوفة تتبع المتطلبات
│   ├── MANUAL_TEST_PLAN_AR.md    # خطة الاختبار اليدوي للمختبرين
│   ├── decisions.md              # سجل القرارات المعمارية
│   └── implementation-log.md     # سجل التنفيذ المرحلي من F01 إلى F10
├── system/                       # نواة إطار العمل CodeIgniter 3.1.13 (غير معدلة)
├── tests/                        # حزم الاختبارات المؤتمتة والتكاملية
├── index.php                     # نقطة الدخول الرئيسية للنظام وضبط المنطقة الزمنية
└── README.md                     # الدليل التعريفي المزدوج (عربي + إنجليزي)
```

---

## 9. فهرس الوثائق الرسمية (Documentation Index)

- [وثيقة الأساس المعتمدة v1.0 (مصدر الحقيقة)](docs/MASTER_PROJECT_DOCUMENT_AR.md) — مواصفة المتطلبات الرسمية وقواعد العمل المعتمدة.
- [دليل التثبيت والتشغيل الشامل](docs/INSTALLATION_GUIDE_AR.md) — خطوات إعداد الخادم وقاعدة البيانات وحل المشكلات الشائعة.
- [وثيقة التنفيذ والقرارات المعمارية](docs/IMPLEMENTATION_AND_DECISIONS_AR.md) — شرح المعمارية، القرارات التقنية، التزامن، والقيود.
- [التقرير الختامي الشامل للمشروع](docs/FINAL_PROJECT_REPORT_AR.md) — التقرير الرسمي الشامل لإنجاز المراحل F01–F10 ونتائج الاختبارات وحالة التسليم.
- [مصفوفة تتبع المتطلبات](docs/REQUIREMENTS_TRACEABILITY_MATRIX_AR.md) — ربط كل متطلب أصلي بملفات التنفيذ وحزم الاختبارات.
- [خطة الاختبار اليدوي](docs/MANUAL_TEST_PLAN_AR.md) — 27 سيناريو فحص يدوي منظم للمختبرين والمراجعين.
- [دليل البيانات التجريبية](database/DEMO_DATA_GUIDE_AR.md) — تفاصيل محتويات الحزمة التجريبية، الحسابات، وحالات نقص المخزون.
- [سجل القرارات المعمارية](docs/decisions.md) — سجل القرارات التقنية المعتمدة.
- [سجل التنفيذ المرحلي](docs/implementation-log.md) — السجل التفصيلي لتنفيذ المراحل البرمجية من F01 إلى F10.

---

## 10. معلومات ونتائج الاختبارات (Testing Information)

### أ. نتائج الاختبارات المؤتمتة الموثقة (Automated Regression & Integration: 100% Pass)
تم التحقق البرمجي التام من كافة مراحل ووظائف النظام عبر حزم اختبارات مؤتمتة موثقة حققت نسبة نجاح **100% (280 / 280 اختباراً)** دون أي فشل:
- `tests/test_f10_audit_and_system_integration.php`: **76 / 76 ناجح** (الفحص التكاملي الشامل للمشروع عبر 10 مجالات).
- `tests/test_f09_reports.php`: **71 / 71 ناجح** (التقارير التشغيلية والمالية ومؤشرات الأداء).
- `tests/test_f09_csv_excel_compatibility.php`: **28 / 28 ناجح** (توافق تصدير CSV مع إعدادات Excel العربية و COM).
- `tests/test_f08_invoice_cancellation.php`: **68 / 68 ناجح** (إلغاء فواتير المبيعات والحركات العكسية ومنع السباق).
- `tests/test_f07_customer_explicit_status.php`: **37 / 37 ناجح** (عمليات العملاء الصريحة Idempotent).

### ب. خطة الاختبار اليدوي (Manual Test Plan)
تتضمن وثيقة [`docs/MANUAL_TEST_PLAN_AR.md`](docs/MANUAL_TEST_PLAN_AR.md) **27 حالة اختبار عملي** مخصصة لفرق الفحص والمراجعة؛ وتُركت حقول «النتيجة الفعلية» و«حالة الاختبار» فيها فارغة عمداً لتوثيقها أثناء الاختبار البشري المباشر.

---

## 11. نطاق العمل والقيود المعروفة (Scope & Limitations)

وفق وثيقة الأساس المعتمدة `docs/MASTER_PROJECT_DOCUMENT_AR.md`، تظل البنود التالية خارج نطاق هذا الإصدار (Sprint 1 MVP):
1. نظام المشتريات والموردين وأوامر الشراء (PO).
2. الحسابات العامة وقيود اليومية والفوترة الإلكترونية (ZATCA المرحلة الثانية).
3. الحسابات الآجلة والدفع الجزئي وسندات القبض والصرف.
4. المرتجعات الجزئية لبنود الفاتورة (يدعم النظام حالياً إلغاء الفاتورة بالكامل فقط).
5. تعدد العملات؛ النظام يعتمد الريال السعودي (SAR) حصراً.
6. استيراد البيانات عبر Excel/CSV (يتوفر حالياً تصدير التقارير فقط).
7. قارئات الباركود والطباعة الحرارية وتطبيقات الجوال.

---

<a id="english"></a>

# Mini ERP Stock & Sales Management System — English

**Mini ERP Stock & Sales** — Robust Web-based Inventory and Sales Management System built with PHP, CodeIgniter 3.1.13, and MariaDB/MySQL.

---

### Language Navigation / التنقل السريع بين اللغات
**[العربية](#arabic) | [English](#english)**

---

> [!WARNING]
> **Important Notice:** The data provided in the database seed file is **demonstration data for testing and evaluation purposes (Demo Data)**, not production data. Never execute seed scripts on databases containing live business data.

---

## 1. Project Overview

**Mini ERP Stock & Sales** is an integrated, tightly controlled internal web application designed to manage products, multiple warehouses, stock balances, an append-only stock movement ledger, sales invoices, atomic cancellation reversals, operational and sales reports with regional CSV export for Excel, and centralized audit logging.

- **System Currency:** Saudi Riyal (**SAR**) strictly across all database records, user interfaces, and exported reports.
- **Quantity Type:** Positive integers only (**Integers Only**); fractional quantities are strictly disallowed.
- **Non-Negative Stock Rule:** Server-side validation and database constraints prevent product stock in any warehouse from falling below zero during operational and sales workflows.
- **User Interface:** Full Arabic Right-to-Left (**RTL**) layout powered by responsive Bootstrap 5 RTL.

---

## 2. Key Features (F01 — F10 Implementation Roadmap)

1. **F01 — Project Foundation:** Setup and verification of CodeIgniter 3.1.13, centralized timezone `Asia/Riyadh`, security baseline, and base controller `MY_Controller`.
2. **F02 — Database Architecture:** Unified transactional schema using `InnoDB` and `utf8mb4_unicode_ci` with strict foreign key constraints and mathematical CHECK rules.
3. **F03 — Authentication & RBAC Authorization:** BCRYPT password hashing, CSRF protection, and strict server-side role enforcement across 3 roles (`admin`, `warehouse`, `sales`).
4. **F04 — Master Data Management:** Management of categories, warehouses with unique codes, product catalog with unique SKU enforcement, and soft disabling (`is_active = 0`) instead of hard deletion.
5. **F05 — Warehouse Stock Management:** Independent stock balances per product and warehouse, row-level locking (`SELECT ... FOR UPDATE`) during transactions to protect balances during operations, and effective reorder level alerts.
6. **F06 — Stock Movement Ledger:** Append-only movement ledger for stock inward (`in`) and adjustments (`adjustment`) with strict mathematical balance enforcement and mandatory reasons.
7. **F07 — Sales Invoices:** Multi-item sales invoices, customer management, server-calculated totals, historical pricing snapshots, atomic stock deduction, and daily sequential numbering (`INV-YYYYMMDD-XXXX`).
8. **F08 — Invoice Cancellation:** Admin-only cancellation workflow preserving historical records, documenting reasons, restoring stock through atomic reversal movements (`sale_reversal`), and preventing duplicate cancellation.
9. **F09 — Reports & Regional CSV Export:** 5 operational reports with KPI metric cards, exclusion of cancelled invoices, and regional CSV export optimized for Arabic Excel (semicolon delimiter and UTF-8 BOM).
10. **F10 — Centralized Audit Logging:** Automated audit trail for sensitive operations, strict metadata sanitization (excluding passwords and tokens), full transaction rollback on failure, and a read-only admin viewer.

---

## 3. Technology Stack

- **Framework:** CodeIgniter 3.1.13 (Strict MVC architecture with thin controllers, dedicated models, and central business services).
- **Backend Language:** PHP 7.4+ compatible, fully tested on PHP 8.2.12 without PHP 8-only syntax.
- **Database:** MariaDB 10.4.32+ / MySQL 8.0+ running the `InnoDB` storage engine with `utf8mb4_unicode_ci` collation.
- **Frontend:** HTML5, CSS3, JavaScript, jQuery, AJAX, and Bootstrap 5 RTL.
- **Development Environment:** Apache 2.4+ on Windows via XAMPP.

---

## 4. System Requirements

| Component | Minimum Requirement | Verified Test Environment |
| :--- | :--- | :--- |
| **Operating System** | Windows 10/11 or Linux | Windows 11 Pro 64-bit |
| **Web Server** | Apache 2.4+ (XAMPP) | Apache 2.4.58 |
| **PHP Version** | PHP 7.4.x to PHP 8.2.x | PHP 8.2.12 |
| **Required PHP Extensions** | `mysqli`, `mbstring`, `json`, `session`, `ctype` | Required extensions; must be verified as enabled in `php.ini` |
| **Database Engine** | MariaDB 10.4.32+ or MySQL 8.0+ | MariaDB 10.4.32 (InnoDB) |

---

## 5. Installation and Setup Instructions

1. **Place Project in Web Server Directory:**
   Copy the project folder into your local Apache root:
   ```text
   C:\xampp\htdocs\codeigniter-projects\mini-erp-stock-sales\
   ```
2. **Start Services:**
   Open the **XAMPP Control Panel** and start both **Apache** and **MySQL**.
3. **Create Database:**
   Create a new database named `mini_erp_stock_sales` with `utf8mb4_unicode_ci` collation via phpMyAdmin or the MySQL CLI:
   ```sql
   CREATE DATABASE `mini_erp_stock_sales` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
   ```
4. **Import Full Schema:**
   Import the unified schema script: [`database/schema_full.sql`](database/schema_full.sql)
5. **Import Demo Seed Data:**
   Import the comprehensive demo dataset: [`database/seed_demo_data.sql`](database/seed_demo_data.sql)
6. **Verify Database Configuration & Base URL:**
   - In `application/config/database.php`: Ensure database is set to `mini_erp_stock_sales` and user is `root`.
   - In `application/config/config.php`: Verify base URL matches: [http://localhost/codeigniter-projects/mini-erp-stock-sales/](http://localhost/codeigniter-projects/mini-erp-stock-sales/)
7. **Launch the Application:**
   Open your browser and navigate to: [http://localhost/codeigniter-projects/mini-erp-stock-sales/](http://localhost/codeigniter-projects/mini-erp-stock-sales/)

---

## 6. Database Schema & Demo Data Instructions

- **Unified Schema File:** [`database/schema_full.sql`](database/schema_full.sql) creates all **13 tables** in correct dependency order with all primary keys, foreign keys, compound indexes, and mathematical CHECK constraints.
- **Demo Seed Data File:** [`database/seed_demo_data.sql`](database/seed_demo_data.sql) is designed strictly for a fresh, empty database. It contains zero destructive statements (`DROP`, `TRUNCATE`, `DELETE`).
- **Demo Data Guide:** Refer to [`database/DEMO_DATA_GUIDE_AR.md`](database/DEMO_DATA_GUIDE_AR.md) for full details on seeded products, warehouses, low stock thresholds, and test scenarios.

---

## 7. Demo Accounts & Credentials

| Role | Username | Password | Scope & Responsibilities |
| :--- | :--- | :--- | :--- |
| **System Administrator** | `admin` | `Admin@123456` | Full access across all warehouses, user management, stock operations, sales invoices, cancellation, and audit logs. |
| **Warehouse User** | `warehouse_user` | `Warehouse@123456` | Restricted to assigned warehouses (Riyadh & Jeddah); stock inward, adjustments, stock balances, and low stock reports. Invoices and audit logs are blocked. |
| **Sales User** | `sales_user` | `Sales@123456` | Restricted to assigned warehouse (Riyadh); customer management, sales invoice creation, and own sales reports. Inward, adjustments, cancellation, and audit logs are blocked. |

---

## 8. Project Directory Structure

```text
mini-erp-stock-sales/
├── application/                  # CodeIgniter MVC application source code
│   ├── config/                   # Configuration files (database, routes, session, etc.)
│   ├── controllers/              # Thin controllers (Auth, Products, Stock, Sales, Reports, Audit)
│   ├── core/                     # Core extension classes (MY_Controller, MY_Loader, etc.)
│   ├── libraries/                # Central business service libraries (Audit_service)
│   ├── models/                   # Database models with transactional Query Builder methods
│   └── views/                    # Arabic RTL view templates and shared partials
├── database/                     # Database scripts and migration documentation
│   ├── schema_full.sql           # Complete unified schema definition (13 tables)
│   ├── seed_demo_data.sql        # Comprehensive demo seed data script
│   ├── DEMO_DATA_GUIDE_AR.md     # Detailed demo dataset guide and verification manual
│   └── README.md                 # Incremental schema evolution notes
├── docs/                         # Formal project documentation and engineering reports
│   ├── MASTER_PROJECT_DOCUMENT_AR.md # Authoritative v1.0 specification (Source of Truth)
│   ├── INSTALLATION_GUIDE_AR.md  # Detailed installation and troubleshooting manual
│   ├── IMPLEMENTATION_AND_DECISIONS_AR.md # Implementation and architectural decisions document
│   ├── FINAL_PROJECT_REPORT_AR.md # Comprehensive final project report
│   ├── REQUIREMENTS_TRACEABILITY_MATRIX_AR.md # Requirements Traceability Matrix (RTM)
│   ├── MANUAL_TEST_PLAN_AR.md    # Structured manual test plan for QA testers
│   ├── decisions.md              # Architectural decisions log
│   └── implementation-log.md     # Engineering implementation log (F01–F10)
├── system/                       # CodeIgniter 3.1.13 framework core (Untouched)
├── tests/                        # Automated regression and integration test suites
├── index.php                     # Application entry point and timezone configuration
└── README.md                     # Bilingual project documentation (Arabic & English)
```

---

## 9. Official Documentation Index

- [Master Project Specification v1.0 (Source of Truth)](docs/MASTER_PROJECT_DOCUMENT_AR.md) — The official functional requirements and business rules specification.
- [Comprehensive Installation Guide](docs/INSTALLATION_GUIDE_AR.md) — Complete setup instructions, web server configuration, and troubleshooting guide.
- [Implementation & Decisions Document](docs/IMPLEMENTATION_AND_DECISIONS_AR.md) — Detailed technical architecture, design decisions, concurrency handling, and scope boundaries.
- [Final Project Report](docs/FINAL_PROJECT_REPORT_AR.md) — Formal project completion report covering goals, testing results, and delivery status.
- [Requirements Traceability Matrix (RTM)](docs/REQUIREMENTS_TRACEABILITY_MATRIX_AR.md) — Bidirectional matrix mapping original requirements to code and test suites.
- [Manual Test Plan](docs/MANUAL_TEST_PLAN_AR.md) — 27 practical manual test scenarios prepared for QA testers.
- [Demo Data Guide](database/DEMO_DATA_GUIDE_AR.md) — Complete guide to seeded entities, credentials, and stock test cases.
- [Decisions Log](docs/decisions.md) — Record of approved architectural and engineering decisions.
- [Implementation Log](docs/implementation-log.md) — Comprehensive engineering trajectory log from F01 to F10.

---

## 10. Testing Information & Verification Results

### A. Automated Regression & Integration Suites (100% Pass)
The system has been verified through automated test suites that achieved a **100% pass rate (280 / 280 tests passing, 0 failures)**:
- `tests/test_f10_audit_and_system_integration.php`: **76 / 76 Passed** (Master end-to-end integration across all 10 project domains).
- `tests/test_f09_reports.php`: **71 / 71 Passed** (Operational reports, sales summaries, and KPI calculations).
- `tests/test_f09_csv_excel_compatibility.php`: **28 / 28 Passed** (Regional CSV export compatibility with Arabic Excel via COM).
- `tests/test_f08_invoice_cancellation.php`: **68 / 68 Passed** (Atomic invoice cancellation, stock reversal movements, and race conditions).
- `tests/test_f07_customer_explicit_status.php`: **37 / 37 Passed** (Explicit idempotent customer activation/deactivation).

### B. Manual Test Plan
The document [`docs/MANUAL_TEST_PLAN_AR.md`](docs/MANUAL_TEST_PLAN_AR.md) contains **27 structured manual test cases** covering authentication, master data, inventory, sales, cancellation, reports, and audit logs. The fields "Actual Result" and "Test Status" are deliberately left blank for QA testers to complete during physical verification.

---

## 11. Scope and Known Limitations

As defined in the authoritative project specification `docs/MASTER_PROJECT_DOCUMENT_AR.md`, the following areas are strictly out of scope for the v1.0 release (Sprint 1 MVP):
1. Purchasing, vendors, and purchase orders (PO).
2. General ledger accounting, journal entries, and ZATCA Phase 2 electronic invoicing.
3. Credit sales accounts, partial payments, and payment vouchers.
4. Partial line item returns (the system supports full invoice cancellation only).
5. Multi-currency support; the system operates exclusively in Saudi Riyals (SAR).
6. Excel/CSV data import (operational CSV export is provided for reports).
7. Barcode scanning hardware, thermal printers, and mobile applications.
