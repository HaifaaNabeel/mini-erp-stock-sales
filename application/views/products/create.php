<div class="card" style="max-width: 700px; margin: 0 auto;">
    <div class="card-header">
        <div>
            <h2 class="card-title">إضافة منتج جديد</h2>
            <p style="color: var(--text-muted); font-size: 0.85rem; margin-top: 0.2rem;">
                إدخال رمز المنتج الفريد (SKU)، الاسم، التصنيف النشط، وسعر البيع بالريال السعودي.
            </p>
        </div>
        <a href="<?php echo site_url('products'); ?>" class="btn btn-secondary btn-sm">عودة للقائمة</a>
    </div>

    <?php if (validation_errors()): ?>
        <div class="alert alert-error">
            <span>⚠</span>
            <div><?php echo validation_errors('<div>', '</div>'); ?></div>
        </div>
    <?php endif; ?>

    <?php echo form_open('products/create'); ?>
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
            <div class="form-group">
                <label for="sku" class="form-label">رمز المنتج (SKU) <span style="color: var(--danger);">*</span></label>
                <input type="text" id="sku" name="sku" class="form-control" 
                       value="<?php echo set_value('sku'); ?>" 
                       required autofocus placeholder="مثال: PROD-1001, LAP-DELL-G15">
                <p class="form-text">رمز فريد غير حساس لحالة الأحرف (يُحوّل لأحرف كبيرة).</p>
            </div>

            <div class="form-group">
                <label for="category_id" class="form-label">التصنيف <span style="color: var(--danger);">*</span></label>
                <select id="category_id" name="category_id" class="form-control" required>
                    <option value="">-- اختر التصنيف --</option>
                    <?php if (!empty($categories)): ?>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?php echo (int)$cat->id; ?>" <?php echo set_select('category_id', $cat->id); ?>>
                                <?php echo html_escape($cat->name); ?>
                            </option>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </select>
                <p class="form-text">تظهر فقط التصنيفات النشطة للربط الجديد.</p>
            </div>
        </div>

        <div class="form-group">
            <label for="name" class="form-label">اسم المنتج <span style="color: var(--danger);">*</span></label>
            <input type="text" id="name" name="name" class="form-control" 
                   value="<?php echo set_value('name'); ?>" 
                   required placeholder="مثال: لابتوب ديل انسبيرون 15">
        </div>

        <div class="form-group">
            <label for="description" class="form-label">الوصف التفصيلي (اختياري)</label>
            <textarea id="description" name="description" class="form-control" rows="3" 
                      placeholder="وصف مواصفات المنتج أو خصائصه..."><?php echo set_value('description'); ?></textarea>
        </div>

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
            <div class="form-group">
                <label for="sale_price" class="form-label">سعر البيع (ر.س) <span style="color: var(--danger);">*</span></label>
                <input type="number" step="0.01" min="0" id="sale_price" name="sale_price" class="form-control" 
                       value="<?php echo set_value('sale_price', '0.00'); ?>" 
                       required placeholder="0.00">
                <p class="form-text">العملة: ريال سعودي (SAR). لا يُقبل سعر سالب.</p>
            </div>

            <div class="form-group">
                <label for="reorder_level" class="form-label">حد إعادة الطلب <span style="color: var(--danger);">*</span></label>
                <input type="number" step="1" min="0" id="reorder_level" name="reorder_level" class="form-control" 
                       value="<?php echo set_value('reorder_level', '0'); ?>" 
                       required placeholder="0">
                <p class="form-text">عدد صحيح يحدد مستوى التنبيه لانخفاض المخزون (0 أو أكبر).</p>
            </div>
        </div>

        <div class="form-group" style="background-color: var(--bg-main); padding: 0.75rem 1rem; border-radius: 8px; border: 1px solid var(--border);">
            <label style="display: flex; align-items: center; gap: 0.6rem; cursor: pointer;">
                <input type="checkbox" name="is_active" value="1" <?php echo set_checkbox('is_active', '1', TRUE); ?>>
                <span class="form-label" style="margin-bottom: 0;">منتج نشط (يمكن استخدامه في عمليات المخزون والمبيعات)</span>
            </label>
        </div>

        <div style="display: flex; gap: 0.75rem; margin-top: 1.5rem; border-top: 1px solid var(--border); padding-top: 1.25rem;">
            <button type="submit" class="btn btn-primary">حفظ وإنشاء المنتج</button>
            <a href="<?php echo site_url('products'); ?>" class="btn btn-secondary">إلغاء</a>
        </div>
    <?php echo form_close(); ?>
</div>

