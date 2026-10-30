<div class="card" style="max-width: 550px; margin: 0 auto;">
    <div class="card-header">
        <div>
            <h2 class="card-title">تغيير كلمة المرور</h2>
            <p style="color: var(--text-muted); font-size: 0.85rem; margin-top: 0.2rem;">
                للمستخدم: <strong><?php echo html_escape($user->username); ?></strong> (<?php echo html_escape($user->full_name); ?>)
            </p>
        </div>
        <a href="<?php echo site_url('users'); ?>" class="btn btn-secondary btn-sm">عودة للقائمة</a>
    </div>

    <?php if (validation_errors()): ?>
        <div class="alert alert-error">
            <span>⚠</span>
            <div><?php echo validation_errors('<div>', '</div>'); ?></div>
        </div>
    <?php endif; ?>

    <?php echo form_open('users/change_password/' . $user->id); ?>
        <div class="form-group">
            <label for="new_password" class="form-label">كلمة المرور الجديدة <span style="color: var(--danger);">*</span></label>
            <input type="password" id="new_password" name="new_password" class="form-control" 
                   required autofocus placeholder="6 أحرف على الأقل">
            <p class="form-text">لا يتم إظهار أو تداول كلمة المرور السابقة أو الهاش لضمان أمان النظام.</p>
        </div>

        <div class="form-group">
            <label for="confirm_password" class="form-label">تأكيد كلمة المرور الجديدة <span style="color: var(--danger);">*</span></label>
            <input type="password" id="confirm_password" name="confirm_password" class="form-control" 
                   required placeholder="أعد إدخال كلمة المرور الجديدة للتطابق">
        </div>

        <div style="display: flex; gap: 0.75rem; margin-top: 1.5rem; border-top: 1px solid var(--border); padding-top: 1.25rem;">
            <button type="submit" class="btn btn-primary">حفظ كلمة المرور الجديدة</button>
            <a href="<?php echo site_url('users'); ?>" class="btn btn-secondary">إلغاء</a>
        </div>
    <?php echo form_close(); ?>
</div>
