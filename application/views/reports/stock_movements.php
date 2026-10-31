<div class="reports-stock-movements">
    <div class="page-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; flex-wrap: wrap; gap: 1rem;">
        <div>
            <h1 class="page-title" style="font-size: 1.5rem; font-weight: 700; color: var(--text-dark); margin-bottom: 0.25rem;">
                🔄 تقرير حركات المخزون
            </h1>
            <p style="color: var(--text-muted); font-size: 0.88rem; margin: 0;">
                السجل التاريخي الشامل لحركات التوريد والتسويات والمبيعات والإلغاءات.
            </p>
        </div>
        <div style="display: flex; gap: 0.5rem;">
            <a href="<?php echo site_url('reports'); ?>" class="btn btn-secondary">
                ↩ عودة للمركز
            </a>
            <?php 
                $export_query = $_GET;
                $export_query['export'] = 'csv';
                $export_url = site_url('reports/stock_movements') . '?' . http_build_query($export_query);
            ?>
            <a href="<?php echo $export_url; ?>" class="btn btn-success" style="background-color: #10b981; border-color: #10b981;">
                📥 تصدير CSV
            </a>
        </div>
    </div>

    <!-- Filter Card -->
    <div class="card" style="margin-bottom: 1.5rem; padding: 1.25rem;">
        <form method="get" action="<?php echo site_url('reports/stock_movements'); ?>" style="display: flex; flex-wrap: wrap; gap: 1rem; align-items: flex-end;">
            
            <!-- Date From -->
            <div style="flex: 1 1 150px;">
                <label class="form-label" style="font-size: 0.85rem; font-weight: 600; margin-bottom: 0.35rem; display: block;">من تاريخ</label>
                <input type="date" name="date_from" class="form-control" value="<?php echo html_escape(isset($filters['date_from']) ? $filters['date_from'] : ''); ?>">
            </div>

            <!-- Date To -->
            <div style="flex: 1 1 150px;">
                <label class="form-label" style="font-size: 0.85rem; font-weight: 600; margin-bottom: 0.35rem; display: block;">إلى تاريخ</label>
                <input type="date" name="date_to" class="form-control" value="<?php echo html_escape(isset($filters['date_to']) ? $filters['date_to'] : ''); ?>">
            </div>

            <!-- Warehouse Filter -->
            <div style="flex: 1 1 180px;">
                <label class="form-label" style="font-size: 0.85rem; font-weight: 600; margin-bottom: 0.35rem; display: block;">المستودع</label>
                <select name="warehouse_id" class="form-control" style="width: 100%;">
                    <option value="">جميع المستودعات المصرح بها</option>
                    <?php if (!empty($warehouses)): ?>
                        <?php foreach ($warehouses as $wh): ?>
                            <option value="<?php echo $wh->id; ?>" <?php echo (isset($filters['warehouse_id']) && (int)$filters['warehouse_id'] === (int)$wh->id) ? 'selected' : ''; ?>>
                                <?php echo html_escape($wh->name); ?>
                            </option>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </select>
            </div>

            <!-- Movement Type Filter -->
            <div style="flex: 1 1 160px;">
                <label class="form-label" style="font-size: 0.85rem; font-weight: 600; margin-bottom: 0.35rem; display: block;">نوع الحركة</label>
                <select name="movement_type" class="form-control" style="width: 100%;">
                    <option value="">جميع أنواع الحركات</option>
                    <option value="in" <?php echo (isset($filters['movement_type']) && $filters['movement_type'] === 'in') ? 'selected' : ''; ?>>إدخال مخزون (In)</option>
                    <option value="adjustment" <?php echo (isset($filters['movement_type']) && $filters['movement_type'] === 'adjustment') ? 'selected' : ''; ?>>تسوية مخزنية (Adjustment)</option>
                    <option value="sale" <?php echo (isset($filters['movement_type']) && $filters['movement_type'] === 'sale') ? 'selected' : ''; ?>>بيع (Sale)</option>
                    <option value="sale_reversal" <?php echo (isset($filters['movement_type']) && $filters['movement_type'] === 'sale_reversal') ? 'selected' : ''; ?>>عكس بيع / إلغاء (Reversal)</option>
                </select>
            </div>

            <!-- Search Filter -->
            <div style="flex: 1 1 180px;">
                <label class="form-label" style="font-size: 0.85rem; font-weight: 600; margin-bottom: 0.35rem; display: block;">بحث بالمنتج أو SKU</label>
                <input type="text" name="search" class="form-control" placeholder="اسم أو SKU..." value="<?php echo html_escape(isset($filters['search']) ? $filters['search'] : ''); ?>">
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
                <?php if (!empty($filters['date_from']) || !empty($filters['date_to']) || !empty($filters['warehouse_id']) || !empty($filters['movement_type']) || !empty($filters['search'])): ?>
                    <a href="<?php echo site_url('reports/stock_movements'); ?>" class="btn btn-secondary" style="padding: 0.6rem 1rem;" title="إعادة ضبط">
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
                        <th>تاريخ ووقت الحركة</th>
                        <th>المنتج</th>
                        <th>المستودع</th>
                        <th style="text-align: center;">نوع الحركة</th>
                        <th style="text-align: center;">الكمية المتغيرة</th>
                        <th style="text-align: center;">الرصيد قبل</th>
                        <th style="text-align: center;">الرصيد بعد</th>
                        <th>المستخدم المنفذ</th>
                        <th>السبب / المرجع</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($rows)): ?>
                        <?php 
                            $seq = isset($pagination['offset']) ? $pagination['offset'] + 1 : 1;
                            foreach ($rows as $row): 
                                $is_pos = ((int)$row->quantity_delta > 0);
                        ?>
                            <tr>
                                <td style="text-align: center; color: var(--text-muted); font-size: 0.85rem;"><?php echo $seq++; ?></td>
                                <td style="white-space: nowrap; font-size: 0.85rem; color: var(--text-dark);">
                                    <?php echo html_escape($row->created_at); ?>
                                </td>
                                <td>
                                    <div style="font-weight: 600; color: var(--text-dark);"><?php echo html_escape($row->product_name); ?></div>
                                    <code style="background: #f1f5f9; padding: 0.15rem 0.35rem; border-radius: 4px; font-size: 0.8rem;">
                                        <?php echo html_escape($row->product_sku); ?>
                                    </code>
                                </td>
                                <td><?php echo html_escape($row->warehouse_name); ?></td>
                                <td style="text-align: center;">
                                    <?php if ($row->movement_type === 'in'): ?>
                                        <span class="badge" style="background: rgba(16, 185, 129, 0.15); color: #047857; font-size: 0.78rem;">إدخال مخزون</span>
                                    <?php elseif ($row->movement_type === 'adjustment'): ?>
                                        <span class="badge" style="background: rgba(245, 158, 11, 0.15); color: #b45309; font-size: 0.78rem;">تسوية مخزنية</span>
                                    <?php elseif ($row->movement_type === 'sale'): ?>
                                        <span class="badge" style="background: rgba(239, 68, 68, 0.15); color: #b91c1c; font-size: 0.78rem;">بيع</span>
                                    <?php elseif ($row->movement_type === 'sale_reversal'): ?>
                                        <span class="badge" style="background: rgba(139, 92, 246, 0.15); color: #6d28d9; font-size: 0.78rem;">عكس بيع (إلغاء)</span>
                                    <?php endif; ?>
                                </td>
                                <td style="text-align: center; font-weight: 700; font-size: 0.95rem; direction: ltr; color: <?php echo $is_pos ? '#16a34a' : '#dc2626'; ?>;">
                                    <?php echo ($is_pos ? '+' : '') . (int)$row->quantity_delta; ?>
                                </td>
                                <td style="text-align: center; color: var(--text-muted); font-size: 0.9rem;">
                                    <?php echo (int)$row->before_quantity; ?>
                                </td>
                                <td style="text-align: center; font-weight: 600; color: var(--text-dark); font-size: 0.9rem;">
                                    <?php echo (int)$row->after_quantity; ?>
                                </td>
                                <td style="font-size: 0.88rem;">
                                    <?php echo html_escape($row->user_name ? $row->user_name : $row->username); ?>
                                </td>
                                <td style="font-size: 0.85rem; color: var(--text-muted);">
                                    <?php if ($row->reason): ?>
                                        <div><?php echo html_escape($row->reason); ?></div>
                                    <?php endif; ?>
                                    <?php if ($row->invoice_number): ?>
                                        <span style="display: inline-block; background: #e0f2fe; color: #0369a1; padding: 0.1rem 0.4rem; border-radius: 4px; font-size: 0.78rem; font-weight: 600; margin-top: 0.2rem;">
                                            فاتورة: <?php echo html_escape($row->invoice_number); ?>
                                        </span>
                                    <?php endif; ?>
                                    <?php if (!$row->reason && !$row->invoice_number): ?>
                                        —
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="10" style="text-align: center; padding: 3rem 1rem; color: var(--text-muted);">
                                <div style="font-size: 2.5rem; margin-bottom: 0.5rem;">🔍</div>
                                <div style="font-weight: 600; font-size: 1.05rem;">لا توجد حركات مخزون مطابقة لمعايير التصفية</div>
                                <div style="font-size: 0.85rem; margin-top: 0.25rem;">جرّب تعديل نطاق التاريخ أو المستودع أو نوع الحركة.</div>
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

