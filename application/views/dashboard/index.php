<div class="card" style="border-right: 4px solid var(--primary); background: linear-gradient(to left, #ffffff, #f8fafc);">
    <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;">
        <div>
            <h2 style="font-size: 1.4rem; margin-bottom: 0.35rem; color: var(--text-dark);">
                أهلاً بك، <?php echo html_escape($current_user->full_name); ?>!
            </h2>
            <p style="color: var(--text-muted); font-size: 0.92rem;">
                أنت مسجل الدخول بصلاحية: 
                <span class="role-badge role-badge-<?php echo html_escape($current_user->role_name); ?>">
                    <?php echo html_escape($current_user->role_display_name); ?>
                </span>
            </p>
        </div>
        <div style="text-align: left;">
            <div style="font-size: 0.85rem; color: var(--text-muted);">
                آخر تسجيل دخول:
            </div>
            <strong style="font-size: 0.9rem; color: var(--text-dark);">
                <?php echo $current_user->last_login_at ? html_escape($current_user->last_login_at) : 'هذه هي جلستك الأولى في النظام'; ?>
            </strong>
        </div>
    </div>
</div>

<?php if ($current_user->role_name === 'admin'): ?>
<!-- Admin Quick Stats -->
<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap: 1.25rem; margin-bottom: 1.5rem;">
    <!-- Users Card -->
    <div class="card" style="margin-bottom: 0;">
        <div style="display: flex; justify-content: space-between; align-items: flex-start;">
            <div>
                <p style="color: var(--text-muted); font-size: 0.85rem; font-weight: 600;">المستخدمون المسجلون</p>
                <h3 style="font-size: 2rem; font-weight: 700; color: var(--primary); margin: 0.25rem 0;">
                    <?php echo isset($total_users) ? (int)$total_users : 0; ?>
                </h3>
                <p style="font-size: 0.8rem; color: var(--text-muted);">
                    منهم <?php echo isset($active_admins) ? (int)$active_admins : 1; ?> مدير نظام نشط
                </p>
            </div>
            <div style="width: 48px; height: 48px; border-radius: 10px; background-color: var(--primary-light); display: flex; align-items: center; justify-content: center; font-size: 1.5rem;">
                👥
            </div>
        </div>
        <div style="margin-top: 1rem; border-top: 1px solid var(--border); padding-top: 0.75rem; display: flex; gap: 0.5rem;">
            <a href="<?php echo site_url('users'); ?>" class="btn btn-secondary btn-sm" style="flex: 1;">إدارة المستخدمين</a>
            <a href="<?php echo site_url('users/create'); ?>" class="btn btn-primary btn-sm">+ إضافة</a>
        </div>
    </div>

    <!-- Inventory Preview Card -->
    <div class="card" style="margin-bottom: 0;">
        <div style="display: flex; justify-content: space-between; align-items: flex-start;">
            <div>
                <p style="color: var(--text-muted); font-size: 0.85rem; font-weight: 600;">المخزون والمنتجات</p>
                <h3 style="font-size: 1.5rem; font-weight: 700; color: var(--text-dark); margin: 0.5rem 0;">
                    جاهز للمرحلة F04
                </h3>
                <p style="font-size: 0.8rem; color: var(--text-muted);">
                    التصنيفات، المنتجات والمستودعات
                </p>
            </div>
            <div style="width: 48px; height: 48px; border-radius: 10px; background-color: #fef3c7; display: flex; align-items: center; justify-content: center; font-size: 1.5rem;">
                📦
            </div>
        </div>
        <div style="margin-top: 1rem; border-top: 1px solid var(--border); padding-top: 0.75rem;">
            <span style="font-size: 0.8rem; color: var(--text-muted);">حسب ترتيب التنفيذ المعتمد</span>
        </div>
    </div>

    <!-- Sales Invoices Preview Card -->
    <div class="card" style="margin-bottom: 0;">
        <div style="display: flex; justify-content: space-between; align-items: flex-start;">
            <div>
                <p style="color: var(--text-muted); font-size: 0.85rem; font-weight: 600;">المبيعات والفواتير</p>
                <h3 style="font-size: 2rem; font-weight: 700; color: var(--success); margin: 0.25rem 0;">
                    0 <span style="font-size: 1rem; font-weight: 500;">ر.س</span>
                </h3>
                <p style="font-size: 0.8rem; color: var(--text-muted);">
                    العملة الرسمية المعتمدة: SAR
                </p>
            </div>
            <div style="width: 48px; height: 48px; border-radius: 10px; background-color: var(--success-light); display: flex; align-items: center; justify-content: center; font-size: 1.5rem;">
                🧾
            </div>
        </div>
        <div style="margin-top: 1rem; border-top: 1px solid var(--border); padding-top: 0.75rem;">
            <span style="font-size: 0.8rem; color: var(--text-muted);">فواتير نقدية بأسعار ثابتة تاريخياً</span>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <h3 class="card-title">إجراءات سريعة</h3>
    </div>
    <div style="display: flex; gap: 0.75rem; flex-wrap: wrap;">
        <a href="<?php echo site_url('users'); ?>" class="btn btn-primary">
            👥 إدارة المستخدمين والصلاحيات
        </a>
        <a href="<?php echo site_url('users/create'); ?>" class="btn btn-secondary">
            ➕ إضافة مستخدم جديد
        </a>
    </div>
</div>

<?php elseif ($current_user->role_name === 'warehouse'): ?>
<!-- Warehouse Dashboard -->
<div class="card">
    <div class="card-header">
        <h3 class="card-title">لوحة عمليات المستودع (Warehouse Operations)</h3>
    </div>
    <p style="color: var(--text-muted); margin-bottom: 1.25rem;">
        صلاحيات دور المستودع المعتمدة: الاطلاع على المنتجات والمستودعات المفعلة، تسجيل إدخال المخزون، وإجراء التسويات المخزنية الموجبة والسالبة ضمن الأرصدة المتاحة دون إمكانية تجاوز الرصيد أو تسجيل رصيد سالب.
    </p>
    <div class="alert alert-success">
        <span>ℹ</span>
        <div>نظام المستودعات جاهز وسيتم ربط شاشات العمليات المخزنية تباعاً في الميزات F04 و F05 و F06.</div>
    </div>
</div>

<?php elseif ($current_user->role_name === 'sales'): ?>
<!-- Sales Dashboard -->
<div class="card">
    <div class="card-header">
        <h3 class="card-title">لوحة عمليات المبيعات (Sales Operations)</h3>
    </div>
    <p style="color: var(--text-muted); margin-bottom: 1.25rem;">
        صلاحيات دور المبيعات المعتمدة: الاطلاع على المنتجات المفعلة وأسعار البيع، وإنشاء فواتير بيع بالريال السعودي للمستودعات المصرح بها، واستعراض فواتيرك الصادرة.
    </p>
    <div class="alert alert-success">
        <span>ℹ</span>
        <div>نظام المبيعات مهيأ لربط شاشة إنشاء الفواتير في الميزة F07 وفق الترتيب المعتمد.</div>
    </div>
</div>
<?php endif; ?>
