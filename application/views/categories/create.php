<div class="card" style="max-width: 650px; margin: 0 auto;">
    <div class="card-header">
        <div>
            <h2 class="card-title">إضافة تصنيف جديد</h2>
            <p style="color: var(--text-muted); font-size: 0.85rem; margin-top: 0.2rem;">
                إدخال اسم التصنيف ووصفه وحالة تفعيله.
            </p>
        </div>
        <a href="<?php echo site_url('categories'); ?>" class="btn btn-secondary btn-sm">عودة للقائمة</a>
    </div>

    <?php if (validation_errors()): ?>
        <div class="alert alert-error">
            <span>⚠</span>
            <div><?php echo validation_errors('<div>', '</div>'); ?></div>
        </div>
    <?php endif; ?>

    <?php echo form_open('categories/create'); ?>
        <div class="form-group">
            <label for="name" class="form-label">اسم التصنيف <span style="color: var(--danger);">*</span></label>
            <input type="text" id="name" name="name" class="form-control" 
                   value="<?php echo set_value('name'); ?>" 
                   required autofocus placeholder="مثال: إلكترونيات، مواد غذائية، أجهزة">
        </div>

        <div class="form-group">
            <label for="description" class="form-label">الوصف (اختياري)</label>
            <textarea id="description" name="description" class="form-control" rows="3" 
                      placeholder="وصف مختصر لمجموعة المنتجات التابعة لهذا التصنيف"><?php echo set_value('description'); ?></textarea>
        </div>

        <div class="form-group" style="background-color: var(--bg-main); padding: 0.75rem 1rem; border-radius: 8px; border: 1px solid var(--border);">
            <label style="display: flex; align-items: center; gap: 0.6rem; cursor: pointer;">
                <input type="checkbox" name="is_active" value="1" <?php echo set_checkbox('is_active', '1', TRUE); ?>>
                <span class="form-label" style="margin-bottom: 0;">تصنيف نشط (يمكن ربط منتجات جديدة به فوراً)</span>
            </label>
        </div>

        <div style="display: flex; gap: 0.75rem; margin-top: 1.5rem; border-top: 1px solid var(--border); padding-top: 1.25rem;">
            <button type="submit" class="btn btn-primary">حفظ وإنشاء التصنيف</button>
            <a href="<?php echo site_url('categories'); ?>" class="btn btn-secondary">إلغاء</a>
        </div>
    <?php echo form_close(); ?>
</div>

