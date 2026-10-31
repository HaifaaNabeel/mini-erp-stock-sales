<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>

<div class="card">
    <div class="card-header">
        <div>
            <h2 class="card-title">🔄 سجل حركات المخزون</h2>
            <p style="color: var(--text-muted); font-size: 0.85rem; margin-top: 0.2rem;">
                إجمالي الحركات المطابقة: <strong><?php echo (int)$total_count; ?></strong> حركة مخزنية عبر المستودعات المصرح بها.
            </p>
        </div>
        <?php if ($can_manage_stock): ?>
            <div style="display: flex; gap: 0.5rem; flex-wrap: wrap;">
                <a href="<?php echo site_url('stock/movements/in'); ?>" class="btn btn-primary btn-sm">
                    📥 إدخال مخزون جديد
                </a>
                <a href="<?php echo site_url('stock/movements/adjust'); ?>" class="btn btn-secondary btn-sm" style="background-color: #0284c7; color: #fff; border-color: #0284c7;">
                    ⚖ تسوية مخزون
                </a>
            </div>
        <?php endif; ?>
    </div>

    <!-- Filter & Search Toolbar -->
    <div style="background-color: var(--bg-main); padding: 1rem; border-radius: 8px; border: 1px solid var(--border); margin-bottom: 1.25rem;">
        <form method="get" action="<?php echo site_url('stock/movements'); ?>" style="display: flex; gap: 0.75rem; flex-wrap: wrap; align-items: flex-end;">
            <div style="flex: 2; min-width: 200px;">
                <label for="search" class="form-label" style="font-size: 0.82rem; margin-bottom: 0.2rem;">بحث بالمنتج أو SKU أو البيان</label>
                <input type="text" id="search" name="search" class="form-control" style="padding: 0.45rem 0.75rem; font-size: 0.9rem;"
                       value="<?php echo html_escape($current_search); ?>" placeholder="اسم المنتج أو SKU أو سبب الحركة...">
            </div>

            <div style="flex: 1.5; min-width: 180px;">
                <label for="warehouse_id" class="form-label" style="font-size: 0.82rem; margin-bottom: 0.2rem;">المستودع</label>
                <select id="warehouse_id" name="warehouse_id" class="form-control" style="padding: 0.45rem 0.75rem; font-size: 0.9rem;">
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

            <div style="flex: 1; min-width: 140px;">
                <label for="movement_type" class="form-label" style="font-size: 0.82rem; margin-bottom: 0.2rem;">نوع الحركة</label>
                <select id="movement_type" name="movement_type" class="form-control" style="padding: 0.45rem 0.75rem; font-size: 0.9rem;">
                    <option value="">-- الكل --</option>
                    <option value="in" <?php echo ($current_movement_type === 'in') ? 'selected' : ''; ?>>إدخال مخزون (In)</option>
                    <option value="adjustment" <?php echo ($current_movement_type === 'adjustment') ? 'selected' : ''; ?>>تسوية مخزون (Adjustment)</option>
                </select>
            </div>

            <div style="flex: 1; min-width: 130px;">
                <label for="start_date" class="form-label" style="font-size: 0.82rem; margin-bottom: 0.2rem;">من تاريخ</label>
                <input type="date" id="start_date" name="start_date" class="form-control" style="padding: 0.45rem 0.65rem; font-size: 0.9rem;"
                       value="<?php echo html_escape($current_start_date); ?>">
            </div>

            <div style="flex: 1; min-width: 130px;">
                <label for="end_date" class="form-label" style="font-size: 0.82rem; margin-bottom: 0.2rem;">إلى تاريخ</label>
                <input type="date" id="end_date" name="end_date" class="form-control" style="padding: 0.45rem 0.65rem; font-size: 0.9rem;"
                       value="<?php echo html_escape($current_end_date); ?>">
            </div>

            <!-- Show entries selector -->
            <div style="flex: 0 0 auto;">
                <?php $this->load->view('partials/show_entries', array('pagination' => $pagination, 'form_id' => '')); ?>
            </div>

            <div style="display: flex; gap: 0.5rem;">
                <button type="submit" class="btn btn-primary" style="padding: 0.45rem 1rem;">
                    🔍 تصفية
                </button>
                <?php if ($current_search !== NULL || $current_warehouse !== NULL || $current_movement_type !== NULL || $current_start_date !== NULL || $current_end_date !== NULL): ?>
                    <a href="<?php echo site_url('stock/movements'); ?>" class="btn btn-secondary" style="padding: 0.45rem 0.85rem;" title="إعادة ضبط الفلاتر">
                        ✕ إلغاء
                    </a>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <!-- Movements Table -->
    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th style="width: 50px;">#</th>
                    <th style="width: 140px;">التاريخ والوقت</th>
                    <th>المستودع</th>
                    <th>المنتج</th>
                    <th style="text-align: center; width: 120px;">نوع الحركة</th>
                    <th style="text-align: center; width: 110px;">التغيير</th>
                    <th style="text-align: center; width: 90px;">قبل</th>
                    <th style="text-align: center; width: 90px;">بعد</th>
                    <th>البيان / السبب</th>
                    <th style="width: 120px;">المنفّذ</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($movements)): ?>
                    <?php foreach ($movements as $m): ?>
                        <tr>
                            <td><?php echo (int)$m->id; ?></td>
                            <td style="font-size: 0.85rem; color: var(--text-muted); white-space: nowrap;">
                                <?php echo html_escape(date('Y-m-d H:i', strtotime($m->created_at))); ?>
                            </td>
                            <td>
                                <span class="badge" style="background-color: #f1f5f9; color: var(--text-dark); border: 1px solid var(--border);">
                                    🏬 [<?php echo html_escape($m->warehouse_code); ?>] <?php echo html_escape($m->warehouse_name); ?>
                                </span>
                            </td>
                            <td>
                                <strong><?php echo html_escape($m->product_name); ?></strong>
                                <div style="font-family: monospace; font-size: 0.82rem; color: var(--primary);">
                                    <?php echo html_escape($m->product_sku); ?>
                                </div>
                            </td>
                            <td style="text-align: center;">
                                <?php if ($m->movement_type === 'in'): ?>
                                    <span class="badge" style="background-color: #dcfce7; color: #166534; border: 1px solid #bbf7d0;">
                                        📥 إدخال مخزون
                                    </span>
                                <?php elseif ($m->movement_type === 'adjustment'): ?>
                                    <span class="badge" style="background-color: #e0f2fe; color: #0369a1; border: 1px solid #bae6fd;">
                                        ⚖ تسوية مخزون
                                    </span>
                                <?php else: ?>
                                    <span class="badge badge-secondary">
                                        <?php echo html_escape($m->movement_type); ?>
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td style="text-align: center;">
                                <?php if ((int)$m->quantity_delta > 0): ?>
                                    <strong style="color: #16a34a; font-size: 1rem; direction: ltr; display: inline-block;">
                                        +<?php echo (int)$m->quantity_delta; ?>
                                    </strong>
                                <?php else: ?>
                                    <strong style="color: #dc2626; font-size: 1rem; direction: ltr; display: inline-block;">
                                        <?php echo (int)$m->quantity_delta; ?>
                                    </strong>
                                <?php endif; ?>
                            </td>
                            <td style="text-align: center; color: var(--text-muted); font-size: 0.95rem;">
                                <?php echo (int)$m->before_quantity; ?>
                            </td>
                            <td style="text-align: center; font-weight: 700; font-size: 0.95rem; color: var(--text-dark);">
                                <?php echo (int)$m->after_quantity; ?>
                            </td>
                            <td style="font-size: 0.88rem; max-width: 250px; word-break: break-word;">
                                <?php if (!empty($m->reason)): ?>
                                    <?php echo html_escape($m->reason); ?>
                                <?php else: ?>
                                    <span style="color: var(--text-muted); font-style: italic;">—</span>
                                <?php endif; ?>
                            </td>
                            <td style="font-size: 0.85rem;">
                                👤 <?php echo html_escape($m->created_by_name ? $m->created_by_name : $m->created_by_username); ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="10" style="text-align: center; padding: 2.5rem; color: var(--text-muted);">
                            <div style="font-size: 2.2rem; margin-bottom: 0.5rem;">🔄</div>
                            <div style="font-weight: 600; font-size: 1.05rem;">لا توجد حركات مخزون مطابقة للبحث</div>
                            <p style="margin-top: 0.3rem; font-size: 0.88rem;">لم يتم تسجيل أي حركات إدخال أو تسوية مخزنية بعد وفق الشروط المحددة.</p>
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Pagination -->
    <?php $this->load->view('partials/pagination', array('pagination' => $pagination)); ?>
</div>

