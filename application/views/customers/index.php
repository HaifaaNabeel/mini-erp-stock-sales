<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>

<div class="card">
    <div class="card-header">
        <div>
            <h2 class="card-title">إدارة العملاء</h2>
            <p style="color: var(--text-muted); font-size: 0.85rem; margin-top: 0.2rem;">
                دليل العملاء الأساسي لإصدار فواتير المبيعات وتتبع الحسابات.
            </p>
        </div>
        <a href="<?php echo site_url('customers/create'); ?>" class="btn btn-primary">
            ➕ إضافة عميل جديد
        </a>
    </div>

    <!-- Filter & Search Toolbar -->
    <div style="background-color: var(--bg-main); padding: 1rem; border-radius: 8px; border: 1px solid var(--border); margin-bottom: 1.25rem;">
        <form method="get" action="<?php echo site_url('customers'); ?>" style="display: flex; gap: 0.75rem; flex-wrap: wrap; align-items: flex-end;">
            <div style="flex: 2; min-width: 220px;">
                <label for="search" class="form-label" style="font-size: 0.82rem; margin-bottom: 0.2rem;">بحث بالاسم أو رقم الهاتف</label>
                <input type="text" id="search" name="search" class="form-control" style="padding: 0.5rem 0.75rem; font-size: 0.9rem;"
                       value="<?php echo html_escape($current_search); ?>" placeholder="أدخل اسم العميل أو رقم الهاتف...">
            </div>

            <div style="flex: 1; min-width: 140px;">
                <label for="status" class="form-label" style="font-size: 0.82rem; margin-bottom: 0.2rem;">الحالة</label>
                <select id="status" name="status" class="form-control" style="padding: 0.5rem 0.75rem; font-size: 0.9rem;">
                    <option value="">-- جميع الحالات --</option>
                    <option value="1" <?php echo ($current_status === 1) ? 'selected' : ''; ?>>نشط فقط</option>
                    <option value="0" <?php echo ($current_status === 0) ? 'selected' : ''; ?>>معطل فقط</option>
                </select>
            </div>

            <!-- Show entries selector -->
            <div style="flex: 0 0 auto;">
                <?php $this->load->view('partials/show_entries', array('pagination' => $pagination, 'form_id' => '')); ?>
            </div>

            <div style="display: flex; gap: 0.5rem;">
                <button type="submit" class="btn btn-primary" style="padding: 0.5rem 1rem;">
                    🔍 بحث وتصفية
                </button>
                <?php if ($current_search !== NULL || $current_status !== NULL): ?>
                    <a href="<?php echo site_url('customers'); ?>" class="btn btn-secondary" style="padding: 0.5rem 1rem;" title="إعادة ضبط الفلاتر">
                        ✕ إلغاء
                    </a>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <!-- Customers Table -->
    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th style="width: 60px;">#</th>
                    <th>اسم العميل</th>
                    <th>رقم الهاتف</th>
                    <th>البريد الإلكتروني</th>
                    <th>العنوان</th>
                    <th style="width: 100px;">الحالة</th>
                    <th>تاريخ التسجيل</th>
                    <th style="width: 160px; text-align: center;">الإجراءات</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($customers)): ?>
                    <?php foreach ($customers as $cust): ?>
                        <tr>
                            <td><?php echo (int)$cust->id; ?></td>
                            <td>
                                <strong><?php echo html_escape($cust->name); ?></strong>
                            </td>
                            <td dir="ltr" style="text-align: right;">
                                <?php echo $cust->phone ? html_escape($cust->phone) : '<span style="color: var(--text-muted);">-</span>'; ?>
                            </td>
                            <td>
                                <?php echo $cust->email ? html_escape($cust->email) : '<span style="color: var(--text-muted);">-</span>'; ?>
                            </td>
                            <td>
                                <?php echo $cust->address ? html_escape($cust->address) : '<span style="color: var(--text-muted);">-</span>'; ?>
                            </td>
                            <td>
                                <?php if ((int)$cust->is_active === 1): ?>
                                    <span class="badge badge-active">نشط</span>
                                <?php else: ?>
                                    <span class="badge badge-inactive">معطل</span>
                                <?php endif; ?>
                            </td>
                            <td style="font-size: 0.85rem; color: var(--text-muted);">
                                <?php echo html_escape(substr($cust->created_at, 0, 10)); ?>
                            </td>
                            <td style="text-align: center;">
                                <div style="display: inline-flex; gap: 0.4rem; align-items: center; justify-content: center;">
                                    <a href="<?php echo site_url('customers/edit/' . $cust->id); ?>" class="btn btn-secondary btn-sm" title="تعديل بيانات العميل">
                                        تعديل
                                    </a>

                                    <?php if ((int)$cust->is_active === 1): ?>
                                        <?php echo form_open('customers/deactivate/' . $cust->id, array('style' => 'display:inline;')); ?>
                                            <button type="submit" class="btn btn-danger btn-sm" onclick="return confirm('هل أنت متأكد من تعطيل هذا العميل؟ لن يتمكن المستخدمون من إصدار فواتير جديدة له.');">
                                                تعطيل
                                            </button>
                                        <?php echo form_close(); ?>
                                    <?php else: ?>
                                        <?php echo form_open('customers/activate/' . $cust->id, array('style' => 'display:inline;')); ?>
                                            <button type="submit" class="btn btn-primary btn-sm" onclick="return confirm('هل تريد إعادة تفعيل هذا العميل؟');">
                                                تفعيل
                                            </button>
                                        <?php echo form_close(); ?>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="8" style="text-align: center; padding: 2.5rem; color: var(--text-muted);">
                            لا يوجد عملاء مطابقون لمعايير البحث، أو لم يتم تسجيل أي عملاء بعد.
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
                عرض السجلات من <strong><?php echo (int)$pagination['from']; ?></strong> إلى <strong><?php echo (int)$pagination['to']; ?></strong> من أصل <strong><?php echo (int)$pagination['total_rows']; ?></strong> عميل.
            </div>
            <div>
                <?php echo $pagination['links']; ?>
            </div>
        </div>
    <?php endif; ?>
</div>

