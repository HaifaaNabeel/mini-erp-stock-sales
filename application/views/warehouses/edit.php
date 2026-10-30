<div class="card" style="max-width: 650px; margin: 0 auto;">
    <div class="card-header">
        <div>
            <h2 class="card-title">تعديل المستودع: <?php echo html_escape($warehouse->name); ?></h2>
            <p style="color: var(--text-muted); font-size: 0.85rem; margin-top: 0.2rem;">
                الرمز: <strong><?php echo html_escape($warehouse->code); ?></strong> | المستخدمون المرتبطون: <strong><?php echo (int)$warehouse->assigned_users_count; ?></strong> مستخدم.
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

    <?php echo form_open('warehouses/edit/' . $warehouse->id); ?>
        <div class="form-group">
            <label for="code" class="form-label">رمز المستودع (Code) <span style="color: var(--danger);">*</span></label>
            <input type="text" id="code" name="code" class="form-control" 
                   value="<?php echo set_value('code', $warehouse->code); ?>" 
                   required autofocus>
            <p class="form-text">يجب أن يظل فريداً في النظام، وسيتم تطبيعه تلقائياً بأحرف كبيرة.</p>
        </div>

        <div class="form-group">
            <label for="name" class="form-label">اسم المستودع <span style="color: var(--danger);">*</span></label>
            <input type="text" id="name" name="name" class="form-control" 
                   value="<?php echo set_value('name', $warehouse->name); ?>" 
                   required>
        </div>

        <div class="form-group">
            <label for="location" class="form-label">الموقع / العنوان</label>
            <input type="text" id="location" name="location" class="form-control" 
                   value="<?php echo set_value('location', $warehouse->location); ?>">
        </div>

        <div class="form-group" style="background-color: var(--bg-main); padding: 0.75rem 1rem; border-radius: 8px; border: 1px solid var(--border);">
            <label style="display: flex; align-items: center; gap: 0.6rem; cursor: pointer;">
                <input type="checkbox" name="is_active" value="1" <?php echo set_checkbox('is_active', '1', ((int)$warehouse->is_active === 1)); ?>>
                <span class="form-label" style="margin-bottom: 0;">مستودع نشط (إذا عُطّل، فلن يمكن استخدامه في عمليات جديدة، وتبقى السجلات التاريخية محفوظة)</span>
            </label>
        </div>

        <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 1.5rem; border-top: 1px solid var(--border); padding-top: 1.25rem;">
            <div style="display: flex; gap: 0.75rem;">
                <button type="submit" class="btn btn-primary">حفظ التعديلات</button>
                <a href="<?php echo site_url('warehouses'); ?>" class="btn btn-secondary">إلغاء</a>
            </div>
            <a href="<?php echo site_url('warehouses/assignments/' . $warehouse->id); ?>" class="btn btn-secondary btn-sm">
                👥 ربط المستخدمين
            </a>
        </div>
    <?php echo form_close(); ?>
</div>

