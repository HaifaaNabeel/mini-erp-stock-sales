<div class="card" style="max-width: 700px; margin: 0 auto;">
    <div class="card-header">
        <div>
            <h2 class="card-title">تعديل المنتج: <?php echo html_escape($product->name); ?></h2>
            <p style="color: var(--text-muted); font-size: 0.85rem; margin-top: 0.2rem;">
                الرمز (SKU): <strong><?php echo html_escape($product->sku); ?></strong> | التصنيف الحالي: <strong><?php echo html_escape($product->category_name); ?></strong>
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

    <?php echo form_open('products/edit/' . $product->id); ?>
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
            <div class="form-group">
                <label for="sku" class="form-label">رمز المنتج (SKU) <span style="color: var(--danger);">*</span></label>
                <input type="text" id="sku" name="sku" class="form-control" 
                       value="<?php echo set_value('sku', $product->sku); ?>" 
                       required autofocus>
                <p class="form-text">يجب أن يظل فريداً في النظام (يتم تطبيعه بأحرف كبيرة).</p>
            </div>

            <div class="form-group">
                <label for="category_id" class="form-label">التصنيف <span style="color: var(--danger);">*</span></label>
                <select id="category_id" name="category_id" class="form-control" required>
                    <option value="">-- اختر التصنيف --</option>
                    <?php if (!empty($categories)): ?>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?php echo (int)$cat->id; ?>" <?php echo set_select('category_id', $cat->id, ((int)$product->category_id === (int)$cat->id)); ?>>
                                <?php echo html_escape($cat->name); ?>
                            </option>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </select>
                <p class="form-text">تظهر فقط التصنيفات النشطة للربط.</p>
            </div>
        </div>

        <div class="form-group">
            <label for="name" class="form-label">اسم المنتج <span style="color: var(--danger);">*</span></label>
            <input type="text" id="name" name="name" class="form-control" 
                   value="<?php echo set_value('name', $product->name); ?>" 
                   required>
        </div>

        <div class="form-group">
            <label for="description" class="form-label">الوصف التفصيلي</label>
            <textarea id="description" name="description" class="form-control" rows="3"><?php echo set_value('description', $product->description); ?></textarea>
        </div>

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
            <div class="form-group">
                <label for="sale_price" class="form-label">سعر البيع (ر.س) <span style="color: var(--danger);">*</span></label>
                <input type="number" step="0.01" min="0" id="sale_price" name="sale_price" class="form-control" 
                       value="<?php echo set_value('sale_price', $product->sale_price); ?>" 
                       required>
                <p class="form-text">العملة: ريال سعودي (SAR). تعديل السعر لا يؤثر على الفواتير السابقة.</p>
            </div>

            <div class="form-group">
                <label for="reorder_level" class="form-label">حد إعادة الطلب <span style="color: var(--danger);">*</span></label>
                <input type="number" step="1" min="0" id="reorder_level" name="reorder_level" class="form-control" 
                       value="<?php echo set_value('reorder_level', $product->reorder_level); ?>" 
                       required>
                <p class="form-text">عدد صحيح (0 أو أكبر).</p>
            </div>
        </div>

        <div class="form-group" style="background-color: var(--bg-main); padding: 0.75rem 1rem; border-radius: 8px; border: 1px solid var(--border);">
            <label style="display: flex; align-items: center; gap: 0.6rem; cursor: pointer;">
                <input type="checkbox" name="is_active" value="1" <?php echo set_checkbox('is_active', '1', ((int)$product->is_active === 1)); ?>>
                <span class="form-label" style="margin-bottom: 0;">منتج نشط (إذا عُطّل، فلن يمكن استخدامه في فواتير بيع أو حركات جديدة)</span>
            </label>
        </div>

        <div style="display: flex; gap: 0.75rem; margin-top: 1.5rem; border-top: 1px solid var(--border); padding-top: 1.25rem;">
            <button type="submit" class="btn btn-primary">حفظ التعديلات</button>
            <a href="<?php echo site_url('products'); ?>" class="btn btn-secondary">إلغاء</a>
        </div>
    <?php echo form_close(); ?>
</div>

