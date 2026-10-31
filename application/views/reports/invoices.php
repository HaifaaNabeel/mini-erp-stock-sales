<div class="reports-invoices">
    <div class="page-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; flex-wrap: wrap; gap: 1rem;">
        <div>
            <h1 class="page-title" style="font-size: 1.5rem; font-weight: 700; color: var(--text-dark); margin-bottom: 0.25rem;">
                🧾 تقرير الفواتير الشامل
            </h1>
            <p style="color: var(--text-muted); font-size: 0.88rem; margin: 0;">
                سجل فواتير المبيعات بحالاتها المختلفة مع تتبع الإلغاءات والبيانات التاريخية.
            </p>
        </div>
        <div style="display: flex; gap: 0.5rem;">
            <a href="<?php echo site_url('reports'); ?>" class="btn btn-secondary">
                ↩ عودة للمركز
            </a>
            <?php 
                $export_query = $_GET;
                $export_query['export'] = 'csv';
                $export_url = site_url('reports/invoices') . '?' . http_build_query($export_query);
            ?>
            <a href="<?php echo $export_url; ?>" class="btn btn-success" style="background-color: #10b981; border-color: #10b981;">
                📥 تصدير CSV
            </a>
        </div>
    </div>

    <!-- Filter Card -->
    <div class="card" style="margin-bottom: 1.5rem; padding: 1.25rem;">
        <form method="get" action="<?php echo site_url('reports/invoices'); ?>" style="display: flex; flex-wrap: wrap; gap: 1rem; align-items: flex-end;">
            
            <!-- Date From -->
            <div style="flex: 1 1 140px;">
                <label class="form-label" style="font-size: 0.85rem; font-weight: 600; margin-bottom: 0.35rem; display: block;">من تاريخ</label>
                <input type="date" name="date_from" class="form-control" value="<?php echo html_escape(isset($filters['date_from']) ? $filters['date_from'] : ''); ?>">
            </div>

            <!-- Date To -->
            <div style="flex: 1 1 140px;">
                <label class="form-label" style="font-size: 0.85rem; font-weight: 600; margin-bottom: 0.35rem; display: block;">إلى تاريخ</label>
                <input type="date" name="date_to" class="form-control" value="<?php echo html_escape(isset($filters['date_to']) ? $filters['date_to'] : ''); ?>">
            </div>

            <!-- Warehouse Filter -->
            <div style="flex: 1 1 160px;">
                <label class="form-label" style="font-size: 0.85rem; font-weight: 600; margin-bottom: 0.35rem; display: block;">المستودع</label>
                <select name="warehouse_id" class="form-control" style="width: 100%;">
                    <option value="">جميع المستودعات</option>
                    <?php if (!empty($warehouses)): ?>
                        <?php foreach ($warehouses as $wh): ?>
                            <option value="<?php echo $wh->id; ?>" <?php echo (isset($filters['warehouse_id']) && (int)$filters['warehouse_id'] === (int)$wh->id) ? 'selected' : ''; ?>>
                                <?php echo html_escape($wh->name); ?>
                            </option>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </select>
            </div>

            <!-- Customer Filter -->
            <div style="flex: 1 1 160px;">
                <label class="form-label" style="font-size: 0.85rem; font-weight: 600; margin-bottom: 0.35rem; display: block;">العميل</label>
                <select name="customer_id" class="form-control" style="width: 100%;">
                    <option value="">جميع العملاء</option>
                    <?php if (!empty($customers)): ?>
                        <?php foreach ($customers as $cust): ?>
                            <option value="<?php echo $cust->id; ?>" <?php echo (isset($filters['customer_id']) && (int)$filters['customer_id'] === (int)$cust->id) ? 'selected' : ''; ?>>
                                <?php echo html_escape($cust->name); ?>
                            </option>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </select>
            </div>

            <!-- Status Filter -->
            <div style="flex: 1 1 130px;">
                <label class="form-label" style="font-size: 0.85rem; font-weight: 600; margin-bottom: 0.35rem; display: block;">حالة الفاتورة</label>
                <select name="status" class="form-control" style="width: 100%;">
                    <option value="">الكل (نشطة وملغاة)</option>
                    <option value="active" <?php echo (isset($filters['status']) && $filters['status'] === 'active') ? 'selected' : ''; ?>>نشطة فقط</option>
                    <option value="cancelled" <?php echo (isset($filters['status']) && $filters['status'] === 'cancelled') ? 'selected' : ''; ?>>ملغاة فقط</option>
                </select>
            </div>

            <?php if (!empty($users)): ?>
            <!-- User Filter (Admin only) -->
            <div style="flex: 1 1 140px;">
                <label class="form-label" style="font-size: 0.85rem; font-weight: 600; margin-bottom: 0.35rem; display: block;">المنشئ</label>
                <select name="created_by" class="form-control" style="width: 100%;">
                    <option value="">جميع المستخدمين</option>
                    <?php foreach ($users as $u): ?>
                        <option value="<?php echo $u->id; ?>" <?php echo (isset($filters['created_by']) && (int)$filters['created_by'] === (int)$u->id) ? 'selected' : ''; ?>>
                            <?php echo html_escape($u->full_name ? $u->full_name : $u->username); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php endif; ?>

            <!-- Search Filter -->
            <div style="flex: 1 1 160px;">
                <label class="form-label" style="font-size: 0.85rem; font-weight: 600; margin-bottom: 0.35rem; display: block;">بحث برقم الفاتورة أو العميل</label>
                <input type="text" name="search" class="form-control" placeholder="رقم أو عميل..." value="<?php echo html_escape(isset($filters['search']) ? $filters['search'] : ''); ?>">
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
                <?php if (!empty($filters['date_from']) || !empty($filters['date_to']) || !empty($filters['warehouse_id']) || !empty($filters['customer_id']) || !empty($filters['status']) || !empty($filters['created_by']) || !empty($filters['search'])): ?>
                    <a href="<?php echo site_url('reports/invoices'); ?>" class="btn btn-secondary" style="padding: 0.6rem 1rem;" title="إعادة ضبط">
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
                        <th>رقم الفاتورة</th>
                        <th>العميل</th>
                        <th>المستودع</th>
                        <th>تاريخ الفاتورة</th>
                        <th style="text-align: center;">الحالة</th>
                        <th style="text-align: center;">المجموع قبل الخصم</th>
                        <th style="text-align: center;">الخصم</th>
                        <th style="text-align: center;">صافي الإجمالي</th>
                        <th>المنشئ</th>
                        <th style="text-align: center; width: 90px;">تفاصيل</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($rows)): ?>
                        <?php 
                            $seq = isset($pagination['offset']) ? $pagination['offset'] + 1 : 1;
                            foreach ($rows as $row): 
                                $is_cancelled = ($row->status === 'cancelled');
                        ?>
                            <tr style="<?php echo $is_cancelled ? 'background-color: #fef2f2;' : ''; ?>">
                                <td style="text-align: center; color: var(--text-muted); font-size: 0.85rem;"><?php echo $seq++; ?></td>
                                <td>
                                    <strong style="color: <?php echo $is_cancelled ? '#dc2626' : 'var(--primary)'; ?>;">
                                        <?php echo html_escape($row->invoice_number); ?>
                                    </strong>
                                </td>
                                <td style="font-weight: 600; color: var(--text-dark);">
                                    <?php echo html_escape($row->customer_name); ?>
                                </td>
                                <td><?php echo html_escape($row->warehouse_name); ?></td>
                                <td style="white-space: nowrap; font-size: 0.88rem; color: var(--text-dark);">
                                    <?php echo html_escape($row->invoice_date); ?>
                                </td>
                                <td style="text-align: center;">
                                    <?php if ($is_cancelled): ?>
                                        <span class="badge" style="background: rgba(220, 38, 38, 0.15); color: #dc2626; font-size: 0.78rem;" title="ألغيت بواسطة <?php echo html_escape($row->cancelled_by_name ? $row->cancelled_by_name : '—'); ?> بتاريخ <?php echo html_escape($row->cancelled_at); ?>">
                                            ملغاة ✕
                                        </span>
                                    <?php else: ?>
                                        <span class="badge badge-active" style="font-size: 0.78rem;">نشطة ✓</span>
                                    <?php endif; ?>
                                </td>
                                <td style="text-align: center; font-size: 0.9rem; color: var(--text-dark);">
                                    <?php echo number_format((float)$row->subtotal, 2); ?> <small>SAR</small>
                                </td>
                                <td style="text-align: center; font-size: 0.9rem; color: #d97706;">
                                    <?php echo number_format((float)$row->discount_amount, 2); ?> <small>SAR</small>
                                </td>
                                <td style="text-align: center; font-weight: 700; font-size: 0.95rem; color: <?php echo $is_cancelled ? '#dc2626' : '#16a34a'; ?>;">
                                    <?php echo number_format((float)$row->total_amount, 2); ?> <small>SAR</small>
                                </td>
                                <td style="font-size: 0.88rem;">
                                    <?php echo html_escape($row->created_by_name ? $row->created_by_name : $row->username); ?>
                                </td>
                                <td style="text-align: center;">
                                    <a href="<?php echo site_url('sales/invoices/view/' . $row->id); ?>" class="btn btn-secondary btn-sm" title="عرض الفاتورة" style="padding: 0.25rem 0.5rem; font-size: 0.8rem;">
                                        👁️ عرض
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="11" style="text-align: center; padding: 3rem 1rem; color: var(--text-muted);">
                                <div style="font-size: 2.5rem; margin-bottom: 0.5rem;">🔍</div>
                                <div style="font-weight: 600; font-size: 1.05rem;">لا توجد فواتير مطابقة لمعايير التصفية</div>
                                <div style="font-size: 0.85rem; margin-top: 0.25rem;">جرّب تعديل نطاق التاريخ أو المستودع أو حالة الفاتورة.</div>
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

