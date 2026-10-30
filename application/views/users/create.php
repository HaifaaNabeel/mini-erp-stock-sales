<div class="card" style="max-width: 650px; margin: 0 auto;">
    <div class="card-header">
        <div>
            <h2 class="card-title">إضافة مستخدم جديد للنظام</h2>
            <p style="color: var(--text-muted); font-size: 0.85rem; margin-top: 0.2rem;">
                إنشاء حساب مستخدم جديد وتحديد دوره الوظيفي وصلاحياته.
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

    <?php echo form_open('users/create'); ?>
        <div class="form-group">
            <label for="username" class="form-label">اسم المستخدم <span style="color: var(--danger);">*</span></label>
            <input type="text" id="username" name="username" class="form-control" 
                   value="<?php echo set_value('username'); ?>" 
                   required placeholder="مثال: ahmed_sales (سيتم تحويله تلقائياً إلى أحرف صغيرة)">
            <p class="form-text">يُستخدم لتسجيل الدخول ويجب أن يكون فريداً في النظام.</p>
        </div>

        <div class="form-group">
            <label for="full_name" class="form-label">الاسم الكامل <span style="color: var(--danger);">*</span></label>
            <input type="text" id="full_name" name="full_name" class="form-control" 
                   value="<?php echo set_value('full_name'); ?>" 
                   required placeholder="مثال: أحمد محمد علي">
        </div>

        <div class="form-group">
            <label for="email" class="form-label">البريد الإلكتروني (اختياري)</label>
            <input type="email" id="email" name="email" class="form-control" 
                   value="<?php echo set_value('email'); ?>" 
                   placeholder="مثال: ahmed@example.com">
            <p class="form-text">اختياري، وفي حال إدخاله يجب أن يكون فريداً في النظام.</p>
        </div>

        <div class="form-group">
            <label for="role_id" class="form-label">الدور الوظيفي والصلاحيات <span style="color: var(--danger);">*</span></label>
            <select id="role_id" name="role_id" class="form-control" required>
                <option value="">-- اختر الدور الوظيفي --</option>
                <?php foreach ($roles as $r): ?>
                    <option value="<?php echo (int)$r->id; ?>" <?php echo set_select('role_id', $r->id); ?>>
                        <?php echo html_escape($r->display_name); ?> (<?php echo html_escape($r->name); ?>)
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
            <div class="form-group">
                <label for="password" class="form-label">كلمة المرور <span style="color: var(--danger);">*</span></label>
                <input type="password" id="password" name="password" class="form-control" 
                       required placeholder="6 أحرف على الأقل">
            </div>

            <div class="form-group">
                <label for="password_confirm" class="form-label">تأكيد كلمة المرور <span style="color: var(--danger);">*</span></label>
                <input type="password" id="password_confirm" name="password_confirm" class="form-control" 
                       required placeholder="أعد إدخال كلمة المرور">
            </div>
        </div>

        <div class="form-group" style="margin-top: 0.5rem; background-color: var(--bg-main); padding: 0.75rem 1rem; border-radius: 8px; border: 1px solid var(--border);">
            <label style="display: flex; align-items: center; gap: 0.6rem; cursor: pointer;">
                <input type="checkbox" name="is_active" value="1" <?php echo set_checkbox('is_active', '1', TRUE); ?>>
                <span class="form-label" style="margin-bottom: 0;">حساب نشط (يمكنه تسجيل الدخول واستخدام النظام فوراً)</span>
            </label>
        </div>

        <div style="display: flex; gap: 0.75rem; margin-top: 1.5rem; border-top: 1px solid var(--border); padding-top: 1.25rem;">
            <button type="submit" class="btn btn-primary">حفظ وإنشاء المستخدم</button>
            <a href="<?php echo site_url('users'); ?>" class="btn btn-secondary">إلغاء</a>
        </div>
    <?php echo form_close(); ?>
</div>
