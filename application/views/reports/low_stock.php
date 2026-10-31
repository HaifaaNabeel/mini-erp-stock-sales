<div class="reports-low-stock">
    <div class="page-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; flex-wrap: wrap; gap: 1rem;">
        <div>
            <h1 class="page-title" style="font-size: 1.5rem; font-weight: 700; color: var(--text-dark); margin-bottom: 0.25rem;">
                ⚠️ تقرير المخزون المنخفض وتنبيهات إعادة الطلب
            </h1>
            <p style="color: var(--text-muted); font-size: 0.88rem; margin: 0;">
                المنتجات التي بلغ رصيدها حد إعادة الطلب أو أقل منه مع حساب دقيق لمقدار النقص (Shortage).
            </p>
        </div>
        <div style="display: flex; gap: 0.5rem;">
            <a href="<?php echo site_url('reports'); ?>" class="btn btn-secondary">
                ↩ عودة للمركز
            </a>
            <?php 
                $export_query = $_GET;
                $export_query['export'] = 'csv';
                $export_url = site_url('reports/low_stock') . '?' . http_build_query($export_query);
            ?>
            <a href="<?php echo $export_url; ?>" class="btn btn-success" style="background-color: #10b981; border-color: #10b981;">
                📥 تصدير CSV
            </a>
        </div>
    </div>

    <!-- Filter Card -->
    <div class="card" style="margin-bottom: 1.5rem; padding: 1.25rem;">
        <form method="get" action="<?php echo site_url('reports/low_stock'); ?>" style="display: flex; flex-wrap: wrap; gap: 1rem; align-items: flex-end;">
            
            <!-- Warehouse Filter -->
            <div style="flex: 1 1 220px;">
                <label class="form-label" style="font-size: 0.85rem; font-weight: 600; margin-bottom: 0.35rem; display: block;">المستودع</label>
                <select name="warehouse_id" class="form-control" style="width: 100%;">
                    <option value="">جميع المستودعات المصرح بها</option>
                    <?php if (!empty($warehouses)): ?>
                        <?php foreach ($warehouses as $wh): ?>
                            <option value="<?php echo $wh->id; ?>" <?php echo (isset($filters['warehouse_id']) && (int)$filters['warehouse_id'] === (int)$wh->id) ? 'selected' : ''; ?>>
                                <?php echo html_escape($wh->name . ' (' . $wh->code . ')'); ?>
                            </option>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </select>
            </div>

            <!-- Search Filter -->
            <div style="flex: 1 1 250px;">
                <label class="form-label" style="font-size: 0.85rem; font-weight: 600; margin-bottom: 0.35rem; display: block;">البحث بالمنتج أو SKU</label>
                <input type="text" name="search" class="form-control" placeholder="اسم المنتج أو رمز SKU..." value="<?php echo html_escape(isset($filters['search']) ? $filters['search'] : ''); ?>">
            </div>

            <!-- Show entries selector -->
            <div style="flex: 0 0 auto;">
                <?php $this->load->view('partials/show_entries', array('pagination' => $pagination, 'form_id' => '')); ?>
            </div>

            <!-- Action Buttons -->
            <div style="display: flex; gap: 0.5rem; flex: 0 0 auto;">
                <button type="submit" class="btn btn-primary" style="padding: 0.6rem 1.25rem;">
                    🔍 تصفية
                </button>
                <?php if (!empty($filters['warehouse_id']) || !empty($filters['search'])): ?>
                    <a href="<?php echo site_url('reports/low_stock'); ?>" class="btn btn-secondary" style="padding: 0.6rem 1rem;" title="إعادة ضبط">
                        ✕ إلغاء
                    </a>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <!-- Data Table -->
    <div class="card" style="padding: 0; overflow: hidden;">
        <div class="table-responsive">
            <table class="table" style="margin-bottom: 0;">
                <thead>
                    <tr>
                        <th style="width: 50px; text-align: center;">#</th>
                        <th>اسم المنتج</th>
                        <th>رمز SKU</th>
                        <th>التصنيف</th>
                        <th>المستودع</th>
                        <th style="text-align: center;">الكمية الحالية</th>
                        <th style="text-align: center;">حد التنبيه</th>
                        <th style="text-align: center;">مقدار النقص</th>
                        <th style="text-align: center; width: 120px;">الحالة</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($rows)): ?>
                        <?php 
                            $seq = isset($pagination['offset']) ? $pagination['offset'] + 1 : 1;
                            foreach ($rows as $row): 
                                $is_empty = ((int)$row->quantity === 0);
                        ?>
                            <tr>
                                <td style="text-align: center; color: var(--text-muted); font-size: 0.85rem;"><?php echo $seq++; ?></td>
                                <td style="font-weight: 600; color: var(--text-dark);">
                                    <?php echo html_escape($row->product_name); ?>
                                </td>
                                <td>
                                    <code style="background: #f1f5f9; padding: 0.2rem 0.4rem; border-radius: 4px; font-size: 0.85rem;">
                                        <?php echo html_escape($row->product_sku); ?>
                                    </code>
                                </td>
                                <td><?php echo html_escape($row->category_name ? $row->category_name : '—'); ?></td>
                                <td>
                                    <?php echo html_escape($row->warehouse_name); ?>
                                    <small style="color: var(--text-muted); display: block;"><?php echo html_escape($row->warehouse_code); ?></small>
                                </td>
                                <td style="text-align: center; font-weight: 700; font-size: 1rem; color: <?php echo $is_empty ? '#dc2626' : '#d97706'; ?>;">
                                    <?php echo (int)$row->quantity; ?>
                                </td>
                                <td style="text-align: center; color: var(--text-muted); font-weight: 600;">
                                    <?php echo (int)$row->reorder_level; ?>
                                </td>
                                <td style="text-align: center; font-weight: 700; font-size: 1rem; color: #dc2626;">
                                    <?php echo (int)$row->shortage; ?>
                                </td>
                                <td style="text-align: center;">
                                    <?php if ($is_empty): ?>
                                        <span class="badge" style="background: rgba(220, 38, 38, 0.15); color: #dc2626; font-size: 0.78rem;">نافد تماماً</span>
                                    <?php else: ?>
                                        <span class="badge" style="background: rgba(245, 158, 11, 0.15); color: #b45309; font-size: 0.78rem;">مخزون منخفض</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="9" style="text-align: center; padding: 3rem 1rem; color: var(--text-muted);">
                                <div style="font-size: 2.5rem; margin-bottom: 0.5rem;">🎉</div>
                                <div style="font-weight: 600; font-size: 1.05rem; color: #16a34a;">لا يوجد منتجات بمخزون منخفض حالياً</div>
                                <div style="font-size: 0.85rem; margin-top: 0.25rem;">جميع أرصدة المستودعات أعلى من حدود إعادة الطلب.</div>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Pagination -->
    <?php $this->load->view('partials/pagination', array('pagination' => $pagination)); ?>
</div>

