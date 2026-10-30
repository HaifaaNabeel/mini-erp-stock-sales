<div class="card" style="max-width: 650px; margin: 0 auto;">
    <div class="card-header">
        <div>
            <h2 class="card-title">تعديل بيانات المستخدم: <?php echo html_escape($user->username); ?></h2>
            <p style="color: var(--text-muted); font-size: 0.85rem; margin-top: 0.2rem;">
                تعديل الاسم والبريد الإلكتروني والدور وحالة تفعيل الحساب.
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

    <?php echo form_open('users/edit/' . $user->id); ?>
        <div class="form-group">
            <label class="form-label">اسم المستخدم</label>
            <input type="text" class="form-control" value="<?php echo html_escape($user->username); ?>" disabled style="background-color: var(--bg-main); cursor: not-allowed; font-weight: 600;">
            <p class="form-text">اسم المستخدم معرّف ثابت لا يمكن تغييره في النظام.</p>
        </div>

        <div class="form-group">
            <label for="full_name" class="form-label">الاسم الكامل <span style="color: var(--danger);">*</span></label>
            <input type="text" id="full_name" name="full_name" class="form-control" 
                   value="<?php echo set_value('full_name', $user->full_name); ?>" 
                   required>
        </div>

        <div class="form-group">
            <label for="email" class="form-label">البريد الإلكتروني</label>
            <input type="email" id="email" name="email" class="form-control" 
                   value="<?php echo set_value('email', $user->email); ?>" 
                   placeholder="مثال: user@example.com">
        </div>

        <div class="form-group">
            <label for="role_id" class="form-label">الدور الوظيفي <span style="color: var(--danger);">*</span></label>
            <?php if ((int)$user->id === (int)$current_user->id): ?>
                <input type="hidden" name="role_id" value="<?php echo (int)$user->role_id; ?>">
                <input type="text" class="form-control" value="<?php echo html_escape($user->role_display_name); ?> (دورك الحالي)" disabled style="background-color: var(--bg-main); cursor: not-allowed;">
                <p class="form-text" style="color: var(--warning); font-weight: 600;">حماية النظام: لا يمكن لمدير النظام تغيير دوره بنفسه.</p>
            <?php else: ?>
                <select id="role_id" name="role_id" class="form-control" required>
                    <?php foreach ($roles as $r): ?>
                        <option value="<?php echo (int)$r->id; ?>" <?php echo set_select('role_id', $r->id, ((int)$user->role_id === (int)$r->id)); ?>>
                            <?php echo html_escape($r->display_name); ?> (<?php echo html_escape($r->name); ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            <?php endif; ?>
        </div>

        <div class="form-group" style="margin-top: 1rem; background-color: var(--bg-main); padding: 0.75rem 1rem; border-radius: 8px; border: 1px solid var(--border);">
            <?php if ((int)$user->id === (int)$current_user->id): ?>
                <input type="hidden" name="is_active" value="1">
                <label style="display: flex; align-items: center; gap: 0.6rem; opacity: 0.85;">
                    <input type="checkbox" checked disabled>
                    <span class="form-label" style="margin-bottom: 0;">حساب نشط (حسابك الحالي — لا يمكنك تعطيل حسابك الخاص)</span>
                </label>
            <?php else: ?>
                <label style="display: flex; align-items: center; gap: 0.6rem; cursor: pointer;">
                    <input type="checkbox" name="is_active" value="1" <?php echo set_checkbox('is_active', '1', ((int)$user->is_active === 1)); ?>>
                    <span class="form-label" style="margin-bottom: 0;">حساب نشط (يمكنه تسجيل الدخول واستخدام النظام)</span>
                </label>
            <?php endif; ?>
        </div>

        <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 1.5rem; border-top: 1px solid var(--border); padding-top: 1.25rem;">
            <div style="display: flex; gap: 0.75rem;">
                <button type="submit" class="btn btn-primary">حفظ التعديلات</button>
                <a href="<?php echo site_url('users'); ?>" class="btn btn-secondary">إلغاء</a>
            </div>
            <a href="<?php echo site_url('users/change_password/' . $user->id); ?>" class="btn btn-secondary btn-sm">
                🔑 تغيير كلمة المرور
            </a>
        </div>
    <?php echo form_close(); ?>
</div>
