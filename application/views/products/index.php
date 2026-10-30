<div class="card">
    <div class="card-header">
        <div>
            <h2 class="card-title">إدارة المنتجات</h2>
            <p style="color: var(--text-muted); font-size: 0.85rem; margin-top: 0.2rem;">
                إجمالي المنتجات المطابقة: <strong><?php echo (int)$total_count; ?></strong> منتج | العملة الرسمية: <strong>ريال سعودي (SAR)</strong>.
            </p>
        </div>
        <a href="<?php echo site_url('products/create'); ?>" class="btn btn-primary">
            ➕ إضافة منتج جديد
        </a>
    </div>

    <!-- Filter & Search Toolbar -->
    <div style="background-color: var(--bg-main); padding: 1rem; border-radius: 8px; border: 1px solid var(--border); margin-bottom: 1.25rem;">
        <form method="get" action="<?php echo site_url('products'); ?>" style="display: flex; gap: 0.75rem; flex-wrap: wrap; align-items: flex-end;">
            <div style="flex: 2; min-width: 200px;">
                <label for="search" class="form-label" style="font-size: 0.82rem; margin-bottom: 0.2rem;">بحث بالاسم أو SKU</label>
                <input type="text" id="search" name="search" class="form-control" style="padding: 0.5rem 0.75rem; font-size: 0.9rem;"
                       value="<?php echo html_escape($current_search); ?>" placeholder="أدخل اسم المنتج أو رمز SKU...">
            </div>

            <div style="flex: 1; min-width: 160px;">
                <label for="category_id" class="form-label" style="font-size: 0.82rem; margin-bottom: 0.2rem;">التصنيف</label>
                <select id="category_id" name="category_id" class="form-control" style="padding: 0.5rem 0.75rem; font-size: 0.9rem;">
                    <option value="">-- جميع التصنيفات --</option>
                    <?php if (!empty($categories)): ?>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?php echo (int)$cat->id; ?>" <?php echo ($current_category === (int)$cat->id) ? 'selected' : ''; ?>>
                                <?php echo html_escape($cat->name); ?> <?php echo ((int)$cat->is_active !== 1) ? '(معطل)' : ''; ?>
                            </option>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </select>
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
                <?php if ($current_search !== NULL || $current_category !== NULL || $current_status !== NULL): ?>
                    <a href="<?php echo site_url('products'); ?>" class="btn btn-secondary" style="padding: 0.5rem 1rem;" title="إعادة ضبط الفلاتر">
                        ✕ إعادة ضبط
                    </a>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <!-- Products Table -->
    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th style="width: 50px;">#</th>
                    <th>رمز المنتج (SKU)</th>
                    <th>اسم المنتج</th>
                    <th>التصنيف</th>
                    <th>سعر البيع</th>
                    <th>حد إعادة الطلب</th>
                    <th>الحالة</th>
                    <th>تاريخ الإضافة</th>
                    <th style="width: 180px; text-align: center;">الإجراءات</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($products)): ?>
                    <?php foreach ($products as $p): ?>
                        <tr>
                            <td><?php echo (int)$p->id; ?></td>
                            <td>
                                <strong style="color: var(--primary); font-family: monospace; font-size: 0.95rem;">
                                    <?php echo html_escape($p->sku); ?>
                                </strong>
                            </td>
                            <td>
                                <strong><?php echo html_escape($p->name); ?></strong>
                                <?php if (!empty($p->description)): ?>
                                    <p style="color: var(--text-muted); font-size: 0.78rem; margin-top: 0.15rem; max-width: 250px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                                        <?php echo html_escape($p->description); ?>
                                    </p>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="badge" style="background-color: #f1f5f9; color: var(--text-dark); border: 1px solid var(--border);">
                                    <?php echo html_escape($p->category_name); ?>
                                </span>
                                <?php if ((int)$p->category_is_active !== 1): ?>
                                    <span class="badge badge-inactive" style="font-size: 0.7rem;" title="تصنيف هذا المنتج معطل">
                                        تصنيف معطل
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <strong style="color: var(--success); font-size: 0.95rem;">
                                    <?php echo number_format($p->sale_price, 2); ?>
                                </strong>
                                <span style="font-size: 0.78rem; color: var(--text-muted);">ر.س</span>
                            </td>
                            <td>
                                <span><?php echo (int)$p->reorder_level; ?></span>
                            </td>
                            <td>
                                <?php if ((int)$p->is_active === 1): ?>
                                    <span class="badge badge-active">نشط</span>
                                <?php else: ?>
                                    <span class="badge badge-inactive">معطل</span>
                                <?php endif; ?>
                            </td>
                            <td style="font-size: 0.85rem; color: var(--text-muted);">
                                <?php echo html_escape(substr($p->created_at, 0, 10)); ?>
                            </td>
                            <td style="text-align: center;">
                                <div style="display: inline-flex; gap: 0.4rem; align-items: center; justify-content: center;">
                                    <a href="<?php echo site_url('products/edit/' . $p->id); ?>" class="btn btn-secondary btn-sm" title="تعديل المنتج">
                                        تعديل
                                    </a>

                                    <?php echo form_open('products/toggle_status/' . $p->id, array('style' => 'display:inline;')); ?>
                                        <?php if ((int)$p->is_active === 1): ?>
                                            <button type="submit" class="btn btn-danger btn-sm" onclick="return confirm('هل أنت متأكد من رغبتك في تعطيل هذا المنتج؟');">
                                                تعطيل
                                            </button>
                                        <?php else: ?>
                                            <button type="submit" class="btn btn-primary btn-sm" onclick="return confirm('هل تريد تفعيل هذا المنتج؟');">
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
                        <td colspan="9" style="text-align: center; padding: 2.5rem; color: var(--text-muted);">
                            لا توجد منتجات مطابقة لمعايير البحث أو لا توجد منتجات مسجلة بعد.
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Pagination -->
    <?php $this->load->view('partials/pagination', array('pagination' => $pagination)); ?>
</div>

