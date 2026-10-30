<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>

<div class="card">
    <div class="card-header">
        <div>
            <h2 class="card-title">📊 أرصدة المخزون بالمستودعات</h2>
            <p style="color: var(--text-muted); font-size: 0.85rem; margin-top: 0.2rem;">
                إجمالي السجلات المطابقة: <strong><?php echo (int)$total_count; ?></strong> سجل مخزني عبر المستودعات المصرح بها.
            </p>
        </div>
    </div>

    <!-- Filter & Search Toolbar -->
    <div style="background-color: var(--bg-main); padding: 1rem; border-radius: 8px; border: 1px solid var(--border); margin-bottom: 1.25rem;">
        <form method="get" action="<?php echo site_url('stock'); ?>" style="display: flex; gap: 0.75rem; flex-wrap: wrap; align-items: flex-end;">
            <div style="flex: 2; min-width: 220px;">
                <label for="search" class="form-label" style="font-size: 0.82rem; margin-bottom: 0.2rem;">بحث بالاسم أو SKU</label>
                <input type="text" id="search" name="search" class="form-control" style="padding: 0.5rem 0.75rem; font-size: 0.9rem;"
                       value="<?php echo html_escape($current_search); ?>" placeholder="أدخل اسم المنتج أو رمز SKU...">
            </div>

            <div style="flex: 1.5; min-width: 200px;">
                <label for="warehouse_id" class="form-label" style="font-size: 0.82rem; margin-bottom: 0.2rem;">المستودع</label>
                <select id="warehouse_id" name="warehouse_id" class="form-control" style="padding: 0.5rem 0.75rem; font-size: 0.9rem;">
                    <option value="">-- جميع المستودعات المصرح بها --</option>
                    <?php if (!empty($warehouses)): ?>
                        <?php foreach ($warehouses as $wh): ?>
                            <option value="<?php echo (int)$wh->id; ?>" <?php echo ($current_warehouse === (int)$wh->id) ? 'selected' : ''; ?>>
                                [<?php echo html_escape($wh->code); ?>] <?php echo html_escape($wh->name); ?>
                            </option>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </select>
            </div>

            <!-- Show entries selector -->
            <div style="flex: 0 0 auto;">
                <?php $this->load->view('partials/show_entries', array('pagination' => $pagination, 'form_id' => '')); ?>
            </div>

            <div style="display: flex; gap: 0.5rem;">
                <button type="submit" class="btn btn-primary" style="padding: 0.5rem 1rem;">
                    🔍 تصفية
                </button>
                <?php if ($current_search !== NULL || $current_warehouse !== NULL): ?>
                    <a href="<?php echo site_url('stock'); ?>" class="btn btn-secondary" style="padding: 0.5rem 1rem;" title="إعادة ضبط الفلاتر">
                        ✕ إعادة ضبط
                    </a>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <!-- Stock Table -->
    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th style="width: 50px;">#</th>
                    <th>رمز المنتج (SKU)</th>
                    <th>اسم المنتج</th>
                    <th>المستودع</th>
                    <th style="text-align: center;">الرصيد الحالي</th>
                    <th style="text-align: center;">حد إعادة الطلب</th>
                    <th style="text-align: center;">كمية النقص</th>
                    <th style="text-align: center;">حالة الرصيد</th>
                    <?php if ($can_edit_reorder): ?>
                        <th style="width: 130px; text-align: center;">الإجراءات</th>
                    <?php endif; ?>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($stock_items)): ?>
                    <?php foreach ($stock_items as $item): ?>
                        <tr>
                            <td><?php echo (int)$item->id; ?></td>
                            <td>
                                <strong style="color: var(--primary); font-family: monospace; font-size: 0.95rem;">
                                    <?php echo html_escape($item->product_sku); ?>
                                </strong>
                            </td>
                            <td>
                                <strong><?php echo html_escape($item->product_name); ?></strong>
                                <?php if ((int)$item->product_is_active !== 1): ?>
                                    <span class="badge badge-inactive" style="font-size: 0.7rem; margin-right: 0.3rem;">منتج معطل</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="badge" style="background-color: #f1f5f9; color: var(--text-dark); border: 1px solid var(--border);">
                                    🏬 <?php echo html_escape($item->warehouse_name); ?>
                                </span>
                            </td>
                            <td style="text-align: center;">
                                <strong style="font-size: 1.1rem; color: var(--text-dark);">
                                    <?php echo (int)$item->quantity; ?>
                                </strong>
                            </td>
                            <td style="text-align: center;">
                                <span style="font-size: 0.95rem; font-weight: 600; color: var(--text-secondary);">
                                    <?php echo (int)$item->reorder_level; ?>
                                </span>
                            </td>
                            <td style="text-align: center;">
                                <?php if ((int)$item->shortage > 0): ?>
                                    <strong style="color: var(--danger); font-size: 1rem;">
                                        ⚠️ <?php echo (int)$item->shortage; ?>
                                    </strong>
                                <?php else: ?>
                                    <span style="color: var(--text-muted); font-size: 0.9rem;">0</span>
                                <?php endif; ?>
                            </td>
                            <td style="text-align: center;">
                                <?php if ((int)$item->reorder_level > 0 && (int)$item->quantity <= (int)$item->reorder_level): ?>
                                    <span class="badge badge-inactive" style="font-size: 0.8rem;">
                                        مخزون منخفض
                                    </span>
                                <?php else: ?>
                                    <span class="badge badge-active" style="font-size: 0.8rem;">
                                        متوفر
                                    </span>
                                <?php endif; ?>
                            </td>
                            <?php if ($can_edit_reorder): ?>
                                <td style="text-align: center;">
                                    <a href="<?php echo site_url('stock/edit_reorder/' . $item->id); ?>" class="btn btn-secondary btn-sm" title="تعديل حد إعادة الطلب">
                                        ⚙️ ضبط الحد
                                    </a>
                                </td>
                            <?php endif; ?>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="<?php echo $can_edit_reorder ? '9' : '8'; ?>" style="text-align: center; padding: 2.5rem; color: var(--text-muted);">
                            لا توجد سجلات مخزون مطابقة لمعايير البحث أو لا تملك صلاحية الوصول لأي مستودع حالياً.
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Pagination -->
    <?php $this->load->view('partials/pagination', array('pagination' => $pagination)); ?>
</div>

