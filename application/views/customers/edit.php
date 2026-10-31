<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>

<div class="card" style="max-width: 680px; margin: 0 auto;">
    <div class="card-header">
        <div>
            <h2 class="card-title">تعديل بيانات العميل</h2>
            <p style="color: var(--text-muted); font-size: 0.85rem; margin-top: 0.2rem;">
                تعديل بيانات العميل رقم #<?php echo (int)$customer->id; ?> (<?php echo html_escape($customer->name); ?>)
            </p>
        </div>
        <a href="<?php echo site_url('customers'); ?>" class="btn btn-secondary btn-sm">عودة للقائمة</a>
    </div>

    <?php if (validation_errors()): ?>
        <div class="alert alert-error" style="margin-bottom: 1.25rem;">
            <span>⚠</span>
            <div><?php echo validation_errors('<div>', '</div>'); ?></div>
        </div>
    <?php endif; ?>

    <?php echo form_open('customers/edit/' . $customer->id); ?>
        <div class="form-group" style="margin-bottom: 1.25rem;">
            <label for="name" class="form-label" style="font-weight: 600; margin-bottom: 0.35rem; display: block;">
                اسم العميل أو المؤسسة <span style="color: var(--danger);">*</span>
            </label>
            <input type="text" id="name" name="name" class="form-control" 
                   value="<?php echo set_value('name', $customer->name); ?>" 
                   required autofocus
                   style="width: 100%; padding: 0.6rem 0.85rem; border: 1px solid var(--border); border-radius: 8px;">
        </div>

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1.25rem;">
            <div class="form-group">
                <label for="phone" class="form-label" style="font-weight: 600; margin-bottom: 0.35rem; display: block;">
                    رقم الهاتف للتواصل
                </label>
                <input type="text" id="phone" name="phone" class="form-control" dir="ltr"
                       value="<?php echo set_value('phone', $customer->phone); ?>" 
                       placeholder="05XXXXXXXX"
                       style="width: 100%; padding: 0.6rem 0.85rem; border: 1px solid var(--border); border-radius: 8px; text-align: right;">
            </div>

            <div class="form-group">
                <label for="email" class="form-label" style="font-weight: 600; margin-bottom: 0.35rem; display: block;">
                    البريد الإلكتروني (اختياري)
                </label>
                <input type="email" id="email" name="email" class="form-control" dir="ltr"
                       value="<?php echo set_value('email', $customer->email); ?>" 
                       placeholder="client@example.com"
                       style="width: 100%; padding: 0.6rem 0.85rem; border: 1px solid var(--border); border-radius: 8px; text-align: right;">
            </div>
        </div>

        <div class="form-group" style="margin-bottom: 1.25rem;">
            <label for="address" class="form-label" style="font-weight: 600; margin-bottom: 0.35rem; display: block;">
                العنوان أو الموقع (اختياري)
            </label>
            <textarea id="address" name="address" class="form-control" rows="3" 
                      placeholder="المدينة، الحي، اسم الشارع..."
                      style="width: 100%; padding: 0.6rem 0.85rem; border: 1px solid var(--border); border-radius: 8px;"><?php echo set_value('address', $customer->address); ?></textarea>
        </div>

        <div class="form-group" style="background-color: var(--bg-main); padding: 0.85rem 1rem; border-radius: 8px; border: 1px solid var(--border); margin-bottom: 1.5rem;">
            <label style="display: flex; align-items: center; gap: 0.6rem; cursor: pointer; margin-bottom: 0;">
                <input type="checkbox" name="is_active" value="1" <?php echo set_checkbox('is_active', '1', ((int)$customer->is_active === 1)); ?>>
                <span class="form-label" style="margin-bottom: 0; font-weight: 600;">حساب نشط</span>
            </label>
        </div>

        <div style="display: flex; gap: 0.75rem; border-top: 1px solid var(--border); padding-top: 1.25rem;">
            <button type="submit" class="btn btn-primary">حفظ التعديلات</button>
            <a href="<?php echo site_url('customers'); ?>" class="btn btn-secondary">إلغاء</a>
        </div>
    <?php echo form_close(); ?>
</div>

