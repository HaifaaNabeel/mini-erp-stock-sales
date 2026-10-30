<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>

<div class="card">
    <div class="card-header">
        <div>
            <h2 class="card-title">إدارة تصنيفات المنتجات</h2>
            <p style="color: var(--text-muted); font-size: 0.85rem; margin-top: 0.2rem;">
                تنظيم المنتجات ضمن مجموعات رئيسية وتحديد حالة تفعيل التصنيف.
            </p>
        </div>
        <a href="<?php echo site_url('categories/create'); ?>" class="btn btn-primary">
            ➕ إضافة تصنيف جديد
        </a>
    </div>

    <!-- Filter & Search Toolbar -->
    <div style="background-color: var(--bg-main); padding: 1rem; border-radius: 8px; border: 1px solid var(--border); margin-bottom: 1.25rem;">
        <form method="get" action="<?php echo site_url('categories'); ?>" style="display: flex; gap: 0.75rem; flex-wrap: wrap; align-items: flex-end;">
            <div style="flex: 2; min-width: 200px;">
                <label for="search" class="form-label" style="font-size: 0.82rem; margin-bottom: 0.2rem;">بحث بالاسم أو الوصف</label>
                <input type="text" id="search" name="search" class="form-control" style="padding: 0.5rem 0.75rem; font-size: 0.9rem;"
                       value="<?php echo html_escape($current_search); ?>" placeholder="أدخل اسم التصنيف أو وصفه...">
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
                    🔍 تصفية
                </button>
                <?php if ($current_search !== NULL || $current_status !== NULL): ?>
                    <a href="<?php echo site_url('categories'); ?>" class="btn btn-secondary" style="padding: 0.5rem 1rem;" title="إعادة ضبط الفلاتر">
                        ✕ إعادة ضبط
                    </a>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th style="width: 50px;">#</th>
                    <th>اسم التصنيف</th>
                    <th>الوصف</th>
                    <th>عدد المنتجات</th>
                    <th>الحالة</th>
                    <th>تاريخ الإنشاء</th>
                    <th style="width: 180px; text-align: center;">الإجراءات</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($categories)): ?>
                    <?php foreach ($categories as $cat): ?>
                        <tr>
                            <td><?php echo (int)$cat->id; ?></td>
                            <td>
                                <strong><?php echo html_escape($cat->name); ?></strong>
                            </td>
                            <td>
                                <?php echo $cat->description ? html_escape($cat->description) : '<span style="color: var(--text-muted);">-</span>'; ?>
                            </td>
                            <td>
                                <span class="badge" style="background-color: #f1f5f9; color: var(--text-dark); border: 1px solid var(--border);">
                                    📦 <?php echo (int)$cat->products_count; ?> منتج
                                </span>
                            </td>
                            <td>
                                <?php if ((int)$cat->is_active === 1): ?>
                                    <span class="badge badge-active">نشط</span>
                                <?php else: ?>
                                    <span class="badge badge-inactive">معطل</span>
                                <?php endif; ?>
                            </td>
                            <td style="font-size: 0.85rem; color: var(--text-muted);">
                                <?php echo html_escape(substr($cat->created_at, 0, 10)); ?>
                            </td>
                            <td style="text-align: center;">
                                <div style="display: inline-flex; gap: 0.4rem; align-items: center; justify-content: center;">
                                    <a href="<?php echo site_url('categories/edit/' . $cat->id); ?>" class="btn btn-secondary btn-sm" title="تعديل التصنيف">
                                        تعديل
                                    </a>

                                    <?php echo form_open('categories/toggle_status/' . $cat->id, array('style' => 'display:inline;')); ?>
                                        <?php if ((int)$cat->is_active === 1): ?>
                                            <button type="submit" class="btn btn-danger btn-sm" onclick="return confirm('هل أنت متأكد من تعطيل هذا التصنيف؟ ملاحظة: لن يتم حذف أو نقل المنتجات التابعة له تلقائياً.');">
                                                تعطيل
                                            </button>
                                        <?php else: ?>
                                            <button type="submit" class="btn btn-primary btn-sm" onclick="return confirm('هل تريد تفعيل هذا التصنيف؟');">
                                                تفعيل
                                            </button>
                                        <?php endif; ?>
                                    <?php echo form_close(); ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="7" style="text-align: center; padding: 2rem; color: var(--text-muted);">
                            لا توجد تصنيفات مطابقة لمعايير البحث أو لا توجد تصنيفات مسجلة بعد.
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <?php $this->load->view('partials/pagination', array('pagination' => $pagination)); ?>
</div>
