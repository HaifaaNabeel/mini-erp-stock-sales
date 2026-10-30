<div class="card" style="max-width: 650px; margin: 0 auto;">
    <div class="card-header">
        <div>
            <h2 class="card-title">إضافة مستودع جديد</h2>
            <p style="color: var(--text-muted); font-size: 0.85rem; margin-top: 0.2rem;">
                إدخال اسم المستودع، الرمز الفريد (Code)، والموقع.
            </p>
        </div>
        <a href="<?php echo site_url('warehouses'); ?>" class="btn btn-secondary btn-sm">عودة للقائمة</a>
    </div>

    <?php if (validation_errors()): ?>
        <div class="alert alert-error">
            <span>⚠</span>
            <div><?php echo validation_errors('<div>', '</div>'); ?></div>
        </div>
    <?php endif; ?>

    <?php echo form_open('warehouses/create'); ?>
        <div class="form-group">
            <label for="code" class="form-label">رمز المستودع (Code) <span style="color: var(--danger);">*</span></label>
            <input type="text" id="code" name="code" class="form-control" 
                   value="<?php echo set_value('code'); ?>" 
                   required autofocus placeholder="مثال: WH-MAIN, WH-NORTH (سيتم تحويله تلقائياً إلى أحرف كبيرة بدون مسافات)">
            <p class="form-text">رمز فريد للمستودع في النظام ولا يمكن تكراره.</p>
        </div>

        <div class="form-group">
            <label for="name" class="form-label">اسم المستودع <span style="color: var(--danger);">*</span></label>
            <input type="text" id="name" name="name" class="form-control" 
                   value="<?php echo set_value('name'); ?>" 
                   required placeholder="مثال: مستودع الرياض الرئيسي، مستودع جدة">
        </div>

        <div class="form-group">
            <label for="location" class="form-label">الموقع / العنوان</label>
            <input type="text" id="location" name="location" class="form-control" 
                   value="<?php echo set_value('location'); ?>" 
                   placeholder="مثال: الرياض - المنطقة الصناعية الثانية">
        </div>

        <div class="form-group" style="background-color: var(--bg-main); padding: 0.75rem 1rem; border-radius: 8px; border: 1px solid var(--border);">
            <label style="display: flex; align-items: center; gap: 0.6rem; cursor: pointer;">
                <input type="checkbox" name="is_active" value="1" <?php echo set_checkbox('is_active', '1', TRUE); ?>>
                <span class="form-label" style="margin-bottom: 0;">مستودع نشط (يمكن استخدامه في العمليات والربط فوراً)</span>
            </label>
        </div>

        <div style="display: flex; gap: 0.75rem; margin-top: 1.5rem; border-top: 1px solid var(--border); padding-top: 1.25rem;">
            <button type="submit" class="btn btn-primary">حفظ وإنشاء المستودع</button>
            <a href="<?php echo site_url('warehouses'); ?>" class="btn btn-secondary">إلغاء</a>
        </div>
    <?php echo form_close(); ?>
</div>

