<div class="card" style="max-width: 700px; margin: 0 auto;">
    <div class="card-header">
        <div>
            <h2 class="card-title">المستودعات المصرح بها للمستخدم: <?php echo html_escape($user->full_name); ?></h2>
            <p style="color: var(--text-muted); font-size: 0.85rem; margin-top: 0.2rem;">
                اسم المستخدم: <strong><?php echo html_escape($user->username); ?></strong> | الدور: <strong><?php echo html_escape($user->role_display_name); ?></strong>
            </p>
        </div>
        <a href="<?php echo site_url('users'); ?>" class="btn btn-secondary btn-sm">عودة للمستخدمين</a>
    </div>

    <?php if ($user->role_name === 'admin'): ?>
        <div class="alert alert-success" style="margin-bottom: 1.25rem;">
            <span>ℹ</span>
            <div>
                <strong>تنبيه إداري:</strong> هذا المستخدم يمتلك دور "مدير النظام (Admin)"، ولديه تلقائياً صلاحية الوصول والاطلاع على جميع المستودعات دون الحاجة إلى تحديد قيود.
            </div>
        </div>
    <?php endif; ?>

    <?php echo form_open('users/warehouses/' . $user->id); ?>
        <div class="table-responsive" style="margin-bottom: 1.5rem;">
            <table class="table">
                <thead>
                    <tr>
                        <th style="width: 50px; text-align: center;">تصريح</th>
                        <th>رمز المستودع (Code)</th>
                        <th>اسم المستودع</th>
                        <th>الموقع</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($warehouses)): ?>
                        <?php foreach ($warehouses as $wh): ?>
                            <?php $is_checked = in_array((int)$wh->id, $assigned_warehouse_ids, TRUE); ?>
                            <tr style="<?php echo $is_checked ? 'background-color: var(--primary-light);' : ''; ?>">
                                <td style="text-align: center;">
                                    <input type="checkbox" name="warehouse_ids[]" value="<?php echo (int)$wh->id; ?>" 
                                           <?php echo $is_checked ? 'checked' : ''; ?>
                                           style="width: 18px; height: 18px; cursor: pointer;">
                                </td>
                                <td>
                                    <strong style="color: var(--primary); font-family: monospace;">
                                        <?php echo html_escape($wh->code); ?>
                                    </strong>
                                </td>
                                <td>
                                    <strong><?php echo html_escape($wh->name); ?></strong>
                                </td>
                                <td>
                                    <?php echo $wh->location ? html_escape($wh->location) : '<span style="color: var(--text-muted);">-</span>'; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="4" style="text-align: center; padding: 2rem; color: var(--text-muted);">
                                لا توجد مستودعات نشطة مسجلة حالياً.
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <div style="display: flex; gap: 0.75rem; border-top: 1px solid var(--border); padding-top: 1.25rem;">
            <button type="submit" class="btn btn-primary">حفظ وتحديث المستودعات المصرح بها</button>
            <a href="<?php echo site_url('users'); ?>" class="btn btn-secondary">إلغاء</a>
        </div>
    <?php echo form_close(); ?>
</div>

