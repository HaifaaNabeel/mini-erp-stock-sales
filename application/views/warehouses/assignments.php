<div class="card" style="max-width: 750px; margin: 0 auto;">
    <div class="card-header">
        <div>
            <h2 class="card-title">المستخدمون المصرح لهم بالمستودع: <?php echo html_escape($warehouse->name); ?></h2>
            <p style="color: var(--text-muted); font-size: 0.85rem; margin-top: 0.2rem;">
                الرمز: <strong><?php echo html_escape($warehouse->code); ?></strong> | حدد المستخدمين من أدوار المستودع والمبيعات المصرح لهم بالعمل على هذا المستودع.
            </p>
        </div>
        <a href="<?php echo site_url('warehouses'); ?>" class="btn btn-secondary btn-sm">عودة للمستودعات</a>
    </div>

    <div class="alert alert-success" style="margin-bottom: 1.25rem;">
        <span>ℹ</span>
        <div>
            <strong>قاعدة الصلاحيات:</strong> مدير النظام (Admin) لديه صلاحية تلقائية للوصول لجميع المستودعات. مستخدمو المستودع والمبيعات تنحصر رؤيتهم للمستودعات المحددة هنا فقط.
        </div>
    </div>

    <?php echo form_open('warehouses/assignments/' . $warehouse->id); ?>
        <div class="table-responsive" style="margin-bottom: 1.5rem;">
            <table class="table">
                <thead>
                    <tr>
                        <th style="width: 50px; text-align: center;">اختيار</th>
                        <th>اسم المستخدم</th>
                        <th>الاسم الكامل</th>
                        <th>الدور الوظيفي</th>
                        <th>الحالة</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($eligible_users)): ?>
                        <?php foreach ($eligible_users as $u): ?>
                            <?php $is_checked = in_array((int)$u->id, $assigned_user_ids, TRUE); ?>
                            <tr style="<?php echo $is_checked ? 'background-color: var(--primary-light);' : ''; ?>">
                                <td style="text-align: center;">
                                    <input type="checkbox" name="user_ids[]" value="<?php echo (int)$u->id; ?>" 
                                           <?php echo $is_checked ? 'checked' : ''; ?>
                                           style="width: 18px; height: 18px; cursor: pointer;">
                                </td>
                                <td>
                                    <strong><?php echo html_escape($u->username); ?></strong>
                                </td>
                                <td>
                                    <?php echo html_escape($u->full_name); ?>
                                </td>
                                <td>
                                    <span class="role-badge role-badge-<?php echo html_escape($u->role_name); ?>">
                                        <?php echo html_escape($u->role_display_name); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ((int)$u->is_active === 1): ?>
                                        <span class="badge badge-active">نشط</span>
                                    <?php else: ?>
                                        <span class="badge badge-inactive">معطل</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="5" style="text-align: center; padding: 2rem; color: var(--text-muted);">
                                لا يوجد حالياً مستخدمون نشطون بدور "مستودع" أو "مبيعات" في النظام. يمكنك إنشاء مستخدمين من شاشة إدارة المستخدمين.
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <div style="display: flex; gap: 0.75rem; border-top: 1px solid var(--border); padding-top: 1.25rem;">
            <button type="submit" class="btn btn-primary">حفظ وتحديث الارتباطات</button>
            <a href="<?php echo site_url('warehouses'); ?>" class="btn btn-secondary">إلغاء</a>
        </div>
    <?php echo form_close(); ?>
</div>

