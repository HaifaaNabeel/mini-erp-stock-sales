<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>

<div class="card">
    <div class="card-header">
        <div>
            <h2 class="card-title">فواتير المبيعات</h2>
            <p style="color: var(--text-muted); font-size: 0.85rem; margin-top: 0.2rem;">
                استعراض فواتير المبيعات الصادرة وتفاصيل المبالغ والأرصدة والخصومات.
            </p>
        </div>
        <div style="display: flex; gap: 0.6rem; align-items: center; flex-wrap: wrap;">
            <a href="<?php echo site_url('sales/invoices/create'); ?>" class="btn btn-primary btn-sm">
                ➕ إنشاء فاتورة جديدة
            </a>
            <span class="badge" style="background-color: var(--primary-light); color: var(--primary); font-size: 0.88rem; padding: 0.45rem 0.8rem; border: 1px solid #bfdbfe;">
                العملة: ريال سعودي (SAR)
            </span>
        </div>
    </div>

    <!-- Filter & Search Toolbar -->
    <div style="background-color: var(--bg-main); padding: 1.15rem; border-radius: 8px; border: 1px solid var(--border); margin-bottom: 1.25rem;">
        <form method="get" action="<?php echo site_url('sales/invoices'); ?>">
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 0.75rem; align-items: flex-end; margin-bottom: 0.75rem;">
                
                <!-- Search term -->
                <div>
                    <label for="search" class="form-label" style="font-size: 0.82rem; margin-bottom: 0.2rem;">رقم الفاتورة / العميل</label>
                    <input type="text" id="search" name="search" class="form-control" style="padding: 0.45rem 0.65rem; font-size: 0.88rem;"
                           value="<?php echo html_escape($filters['search']); ?>" placeholder="رقم الفاتورة أو اسم العميل...">
                </div>

                <!-- Customer Filter -->
                <div>
                    <label for="customer_id" class="form-label" style="font-size: 0.82rem; margin-bottom: 0.2rem;">العميل</label>
                    <select id="customer_id" name="customer_id" class="form-control" style="padding: 0.45rem 0.65rem; font-size: 0.88rem;">
                        <option value="">-- جميع العملاء --</option>
                        <?php if (!empty($customers)): ?>
                            <?php foreach ($customers as $c): ?>
                                <option value="<?php echo (int)$c->id; ?>" <?php echo ((int)$filters['customer_id'] === (int)$c->id) ? 'selected' : ''; ?>>
                                    <?php echo html_escape($c->name); ?>
                                </option>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </select>
                </div>

                <!-- Warehouse Filter -->
                <div>
                    <label for="warehouse_id" class="form-label" style="font-size: 0.82rem; margin-bottom: 0.2rem;">المستودع</label>
                    <select id="warehouse_id" name="warehouse_id" class="form-control" style="padding: 0.45rem 0.65rem; font-size: 0.88rem;">
                        <option value="">-- جميع المستودعات --</option>
                        <?php if (!empty($warehouses)): ?>
                            <?php foreach ($warehouses as $w): ?>
                                <option value="<?php echo (int)$w->id; ?>" <?php echo ((int)$filters['warehouse_id'] === (int)$w->id) ? 'selected' : ''; ?>>
                                    <?php echo html_escape($w->name . ' (' . $w->code . ')'); ?>
                                </option>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </select>
                </div>

                <!-- Status Filter -->
                <div>
                    <label for="status" class="form-label" style="font-size: 0.82rem; margin-bottom: 0.2rem;">الحالة</label>
                    <select id="status" name="status" class="form-control" style="padding: 0.45rem 0.65rem; font-size: 0.88rem;">
                        <option value="">-- جميع الحالات --</option>
                        <option value="active" <?php echo ($filters['status'] === 'active') ? 'selected' : ''; ?>>نشطة فقط</option>
                        <option value="cancelled" <?php echo ($filters['status'] === 'cancelled') ? 'selected' : ''; ?>>ملغاة فقط</option>
                    </select>
                </div>

                <!-- Start Date -->
                <div>
                    <label for="start_date" class="form-label" style="font-size: 0.82rem; margin-bottom: 0.2rem;">من تاريخ</label>
                    <input type="date" id="start_date" name="start_date" class="form-control" style="padding: 0.45rem 0.65rem; font-size: 0.88rem;"
                           value="<?php echo html_escape($filters['start_date']); ?>">
                </div>

                <!-- End Date -->
                <div>
                    <label for="end_date" class="form-label" style="font-size: 0.82rem; margin-bottom: 0.2rem;">إلى تاريخ</label>
                    <input type="date" id="end_date" name="end_date" class="form-control" style="padding: 0.45rem 0.65rem; font-size: 0.88rem;"
                           value="<?php echo html_escape($filters['end_date']); ?>">
                </div>
            </div>

            <!-- Action buttons & Limit selector -->
            <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 0.75rem; border-top: 1px solid var(--border); padding-top: 0.75rem;">
                <div style="display: flex; gap: 0.5rem;">
                    <button type="submit" class="btn btn-primary" style="padding: 0.45rem 1rem;">
                        🔍 تصفية النتائج
                    </button>
                    <?php 
                    $has_active_filters = ($filters['search'] !== NULL || $filters['customer_id'] !== NULL || $filters['warehouse_id'] !== NULL || $filters['status'] !== NULL || $filters['start_date'] !== NULL || $filters['end_date'] !== NULL);
                    if ($has_active_filters): 
                    ?>
                        <a href="<?php echo site_url('sales/invoices'); ?>" class="btn btn-secondary" style="padding: 0.45rem 1rem;">
                            ✕ إعادة ضبط
                        </a>
                    <?php endif; ?>
                </div>

                <div>
                    <?php $this->load->view('partials/show_entries', array('pagination' => $pagination, 'form_id' => '')); ?>
                </div>
            </div>
        </form>
    </div>

    <!-- Invoices Table -->
    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th style="width: 150px;">رقم الفاتورة</th>
                    <th>التاريخ</th>
                    <th>العميل</th>
                    <th>المستودع</th>
                    <th style="text-align: left;">المجموع</th>
                    <th style="text-align: left;">الخصم</th>
                    <th style="text-align: left;">الإجمالي الصافي</th>
                    <th style="text-align: center; width: 90px;">الحالة</th>
                    <th>المنشئ</th>
                    <th style="width: 100px; text-align: center;">الإجراءات</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($invoices)): ?>
                    <?php foreach ($invoices as $inv): ?>
                        <tr>
                            <td>
                                <a href="<?php echo site_url('sales/invoices/view/' . $inv->id); ?>" style="font-weight: 700; color: var(--primary); text-decoration: none;">
                                    <?php echo html_escape($inv->invoice_number); ?>
                                </a>
                            </td>
                            <td style="font-size: 0.88rem; color: var(--text-muted);">
                                <?php echo html_escape($inv->invoice_date); ?>
                            </td>
                            <td>
                                <strong><?php echo html_escape($inv->customer_name); ?></strong>
                                <?php if ($inv->customer_phone): ?>
                                    <div style="font-size: 0.78rem; color: var(--text-muted);" dir="ltr"><?php echo html_escape($inv->customer_phone); ?></div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="badge" style="background-color: #f1f5f9; color: var(--text-dark); border: 1px solid var(--border);">
                                    <?php echo html_escape($inv->warehouse_name); ?>
                                </span>
                            </td>
                            <td style="text-align: left; font-family: monospace; font-size: 0.95rem;">
                                <?php echo number_format((float)$inv->subtotal, 2); ?>
                            </td>
                            <td style="text-align: left; font-size: 0.88rem;">
                                <?php if ((float)$inv->discount_amount > 0): ?>
                                    <span style="color: var(--danger); font-family: monospace;">
                                        -<?php echo number_format((float)$inv->discount_amount, 2); ?>
                                    </span>
                                    <span style="font-size: 0.75rem; color: var(--text-muted); display: block;">
                                        (<?php echo (float)$inv->discount_percentage; ?>%)
                                    </span>
                                <?php else: ?>
                                    <span style="color: var(--text-muted);">-</span>
                                <?php endif; ?>
                            </td>
                            <td style="text-align: left; font-weight: 700; font-family: monospace; font-size: 1rem; color: #0f172a;">
                                <?php echo number_format((float)$inv->total_amount, 2); ?> <small style="font-size: 0.75rem; color: var(--text-muted);">ر.س</small>
                            </td>
                            <td style="text-align: center;">
                                <?php if ($inv->status === 'active'): ?>
                                    <span class="badge badge-active">نشطة</span>
                                <?php else: ?>
                                    <span class="badge badge-inactive">ملغاة</span>
                                <?php endif; ?>
                            </td>
                            <td style="font-size: 0.85rem; color: var(--text-muted);">
                                <?php echo html_escape($inv->created_by_name); ?>
                            </td>
                            <td style="text-align: center; white-space: nowrap;">
                                <a href="<?php echo site_url('sales/invoices/view/' . $inv->id); ?>" class="btn btn-secondary btn-sm" title="عرض تفاصيل الفاتورة">
                                    👁️ عرض
                                </a>
                                <a href="<?php echo site_url('sales/invoices/print/' . $inv->id); ?>" class="btn btn-secondary btn-sm" target="_blank" title="طباعة الفاتورة">
                                    🖨️ طباعة
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="10" style="text-align: center; padding: 2.5rem; color: var(--text-muted);">
                            لا توجد فواتير مبيعات مسجلة مطابقة لمعايير البحث.
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Pagination & Meta -->
    <?php if (!empty($pagination['links']) || $pagination['total_rows'] > 0): ?>
        <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 1.25rem; flex-wrap: wrap; gap: 0.75rem;">
            <div style="font-size: 0.88rem; color: var(--text-muted);">
                عرض الفواتير من <strong><?php echo (int)$pagination['from']; ?></strong> إلى <strong><?php echo (int)$pagination['to']; ?></strong> من أصل <strong><?php echo (int)$pagination['total_rows']; ?></strong> فاتورة.
            </div>
            <div>
                <?php echo $pagination['links']; ?>
            </div>
        </div>
    <?php endif; ?>
</div>

