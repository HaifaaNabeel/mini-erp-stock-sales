<div class="reports-hub">
    <div class="page-header" style="margin-bottom: 2rem;">
        <div>
            <h1 class="page-title" style="font-size: 1.6rem; font-weight: 700; color: var(--text-dark); margin-bottom: 0.25rem;">
                📊 مركز التقار رؤى الأعمال
            </h1>
            <p style="color: var(--text-muted); font-size: 0.95rem; margin: 0;">
                استعراض التقارير التشغيلية والمالية الشاملة وتصديرها بصيغة CSV وفق نطاق الصلاحيات.
            </p>
        </div>
    </div>

    <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); gap: 1.5rem;">
        
        <!-- 1. تقرير أرصدة المخزون -->
        <div class="card" style="display: flex; flex-direction: column; justify-content: space-between; border-top: 4px solid #2563eb; transition: transform 0.2s, box-shadow 0.2s;">
            <div>
                <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 1rem;">
                    <div style="width: 48px; height: 48px; border-radius: 12px; background: rgba(37, 99, 235, 0.1); color: #2563eb; display: flex; align-items: center; justify-content: center; font-size: 1.5rem;">
                        📦
                    </div>
                    <span class="badge badge-active" style="font-size: 0.75rem;">تشغيلي</span>
                </div>
                <h3 style="font-size: 1.2rem; font-weight: 700; color: var(--text-dark); margin-bottom: 0.5rem;">
                    تقرير أرصدة المخزون
                </h3>
                <p style="color: var(--text-muted); font-size: 0.88rem; line-height: 1.5; margin-bottom: 1.5rem;">
                    عرض أرصدة المنتجات الحالية في المستودعات مع مؤشرات النقص وحدود إعادة الطلب، والفلترة حسب المستودع والتصنيف والبحث.
                </p>
            </div>
            <div style="display: flex; gap: 0.5rem;">
                <a href="<?php echo site_url('reports/stock'); ?>" class="btn btn-primary" style="flex: 1; text-align: center; text-decoration: none;">
                    عرض التقرير 🔍
                </a>
                <a href="<?php echo site_url('reports/stock/export'); ?>" class="btn btn-secondary" style="text-decoration: none;" title="تصدير CSV">
                    📥 CSV
                </a>
            </div>
        </div>

        <!-- 2. تقرير المخزون المنخفض -->
        <div class="card" style="display: flex; flex-direction: column; justify-content: space-between; border-top: 4px solid #f59e0b; transition: transform 0.2s, box-shadow 0.2s;">
            <div>
                <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 1rem;">
                    <div style="width: 48px; height: 48px; border-radius: 12px; background: rgba(245, 158, 11, 0.1); color: #f59e0b; display: flex; align-items: center; justify-content: center; font-size: 1.5rem;">
                        ⚠️
                    </div>
                    <span class="badge" style="background: rgba(245, 158, 11, 0.15); color: #b45309; font-size: 0.75rem;">تنبيهات حرجة</span>
                </div>
                <h3 style="font-size: 1.2rem; font-weight: 700; color: var(--text-dark); margin-bottom: 0.5rem;">
                    تقرير المخزون المنخفض
                </h3>
                <p style="color: var(--text-muted); font-size: 0.88rem; line-height: 1.5; margin-bottom: 1.5rem;">
                    رصد المنتجات التي وصلت كميتها الحالية إلى حد إعادة الطلب أو أقل منه، مع حساب دقيق لمقدار النقص المطلوب توريده.
                </p>
            </div>
            <div style="display: flex; gap: 0.5rem;">
                <a href="<?php echo site_url('reports/low_stock'); ?>" class="btn btn-primary" style="flex: 1; text-align: center; text-decoration: none; background: #d97706; border-color: #d97706;">
                    عرض التقرير 🔍
                </a>
                <a href="<?php echo site_url('reports/low_stock/export'); ?>" class="btn btn-secondary" style="text-decoration: none;" title="تصدير CSV">
                    📥 CSV
                </a>
            </div>
        </div>

        <!-- 3. تقرير حركات المخزون -->
        <div class="card" style="display: flex; flex-direction: column; justify-content: space-between; border-top: 4px solid #10b981; transition: transform 0.2s, box-shadow 0.2s;">
            <div>
                <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 1rem;">
                    <div style="width: 48px; height: 48px; border-radius: 12px; background: rgba(16, 185, 129, 0.1); color: #10b981; display: flex; align-items: center; justify-content: center; font-size: 1.5rem;">
                        🔄
                    </div>
                    <span class="badge badge-active" style="font-size: 0.75rem;">تاريخ العمليات</span>
                </div>
                <h3 style="font-size: 1.2rem; font-weight: 700; color: var(--text-dark); margin-bottom: 0.5rem;">
                    تقرير حركات المخزون
                </h3>
                <p style="color: var(--text-muted); font-size: 0.88rem; line-height: 1.5; margin-bottom: 1.5rem;">
                    سجل تاريخي تدقيقي لكافة الحركات (إدخال، تسوية، بيع، وعكس بيع) مع الأرصدة السابقة واللاحقة والمستخدم والسبب.
                </p>
            </div>
            <div style="display: flex; gap: 0.5rem;">
                <a href="<?php echo site_url('reports/stock_movements'); ?>" class="btn btn-primary" style="flex: 1; text-align: center; text-decoration: none; background: #059669; border-color: #059669;">
                    عرض التقرير 🔍
                </a>
                <a href="<?php echo site_url('reports/stock_movements/export'); ?>" class="btn btn-secondary" style="text-decoration: none;" title="تصدير CSV">
                    📥 CSV
                </a>
            </div>
        </div>

        <?php if ($can_view_sales): ?>
        <!-- 4. تقرير ملخص المبيعات -->
        <div class="card" style="display: flex; flex-direction: column; justify-content: space-between; border-top: 4px solid #8b5cf6; transition: transform 0.2s, box-shadow 0.2s;">
            <div>
                <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 1rem;">
                    <div style="width: 48px; height: 48px; border-radius: 12px; background: rgba(139, 92, 246, 0.1); color: #8b5cf6; display: flex; align-items: center; justify-content: center; font-size: 1.5rem;">
                        💰
                    </div>
                    <span class="badge" style="background: rgba(139, 92, 246, 0.15); color: #6d28d9; font-size: 0.75rem;">مالي معتمد</span>
                </div>
                <h3 style="font-size: 1.2rem; font-weight: 700; color: var(--text-dark); margin-bottom: 0.5rem;">
                    تقرير ملخص المبيعات الفعلية
                </h3>
                <p style="color: var(--text-muted); font-size: 0.88rem; line-height: 1.5; margin-bottom: 1.5rem;">
                    إجماليات المبيعات النشطة والخصومات وصافي الإيرادات بالريال السعودي، مع استبعاد الفواتير الملغاة بدقة تامة.
                </p>
            </div>
            <div style="display: flex; gap: 0.5rem;">
                <a href="<?php echo site_url('reports/sales'); ?>" class="btn btn-primary" style="flex: 1; text-align: center; text-decoration: none; background: #7c3aed; border-color: #7c3aed;">
                    عرض التقرير 🔍
                </a>
                <a href="<?php echo site_url('reports/sales/export'); ?>" class="btn btn-secondary" style="text-decoration: none;" title="تصدير CSV">
                    📥 CSV
                </a>
            </div>
        </div>

        <!-- 5. تقرير الفواتير -->
        <div class="card" style="display: flex; flex-direction: column; justify-content: space-between; border-top: 4px solid #0284c7; transition: transform 0.2s, box-shadow 0.2s;">
            <div>
                <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 1rem;">
                    <div style="width: 48px; height: 48px; border-radius: 12px; background: rgba(2, 132, 199, 0.1); color: #0284c7; display: flex; align-items: center; justify-content: center; font-size: 1.5rem;">
                        🧾
                    </div>
                    <span class="badge" style="background: rgba(2, 132, 199, 0.15); color: #0369a1; font-size: 0.75rem;">فواتير وسجلات</span>
                </div>
                <h3 style="font-size: 1.2rem; font-weight: 700; color: var(--text-dark); margin-bottom: 0.5rem;">
                    تقرير الفواتير الشامل
                </h3>
                <p style="color: var(--text-muted); font-size: 0.88rem; line-height: 1.5; margin-bottom: 1.5rem;">
                    استعراض وتتبع كافة فواتير المبيعات بحالاتها المختلفة (النشطة والملغاة) وتفاصيل الإلغاء والعملاء والمستودعات.
                </p>
            </div>
            <div style="display: flex; gap: 0.5rem;">
                <a href="<?php echo site_url('reports/invoices'); ?>" class="btn btn-primary" style="flex: 1; text-align: center; text-decoration: none; background: #0284c7; border-color: #0284c7;">
                    عرض التقرير 🔍
                </a>
                <a href="<?php echo site_url('reports/invoices/export'); ?>" class="btn btn-secondary" style="text-decoration: none;" title="تصدير CSV">
                    📥 CSV
                </a>
            </div>
        </div>
        <?php endif; ?>

    </div>
</div>

