<div class="card">
    <div class="card-header">
        <div>
            <h2 class="card-title">قائمة مستخدمي النظام والصلاحيات</h2>
            <p style="color: var(--text-muted); font-size: 0.85rem; margin-top: 0.2rem;">
                إدارة الحسابات النشطة، تعيين الأدوار الوظيفية، والتحكم بحالة الوصول للنظام.
            </p>
        </div>
        <a href="<?php echo site_url('users/create'); ?>" class="btn btn-primary">
            ➕ إضافة مستخدم جديد
        </a>
    </div>

    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th style="width: 50px;">#</th>
                    <th>اسم المستخدم</th>
                    <th>الاسم الكامل</th>
                    <th>البريد الإلكتروني</th>
                    <th>الدور الوظيفي</th>
                    <th>حالة الحساب</th>
                    <th>آخر تسجيل دخول</th>
                    <th>تاريخ التسجيل</th>
                    <th style="width: 220px; text-align: center;">الإجراءات</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($users)): ?>
                    <?php foreach ($users as $u): ?>
                        <tr>
                            <td><?php echo (int)$u->id; ?></td>
                            <td>
                                <strong><?php echo html_escape($u->username); ?></strong>
                                <?php if ((int)$u->id === (int)$current_user->id): ?>
                                    <span style="font-size: 0.75rem; color: var(--primary); font-weight: 600;">(حسابك)</span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo html_escape($u->full_name); ?></td>
                            <td><?php echo $u->email ? html_escape($u->email) : '<span style="color: var(--text-muted);">-</span>'; ?></td>
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
                            <td style="font-size: 0.85rem; color: var(--text-muted);">
                                <?php echo $u->last_login_at ? html_escape($u->last_login_at) : 'لم يسجل دخول بعد'; ?>
                            </td>
                            <td style="font-size: 0.85rem; color: var(--text-muted);">
                                <?php echo html_escape(substr($u->created_at, 0, 10)); ?>
                            </td>
                            <td style="text-align: center;">
                                <div style="display: inline-flex; gap: 0.4rem; align-items: center; justify-content: center;">
                                    <a href="<?php echo site_url('users/edit/' . $u->id); ?>" class="btn btn-secondary btn-sm" title="تعديل البيانات والصلاحية">
                                        تعديل
                                    </a>

                                    <a href="<?php echo site_url('users/change_password/' . $u->id); ?>" class="btn btn-secondary btn-sm" title="تغيير كلمة المرور">
                                        كلمة المرور
                                    </a>

                                    <?php if ((int)$u->id !== (int)$current_user->id): ?>
                                        <?php echo form_open('users/toggle_status/' . $u->id, array('style' => 'display:inline;')); ?>
                                            <?php if ((int)$u->is_active === 1): ?>
                                                <button type="submit" class="btn btn-danger btn-sm" onclick="return confirm('هل أنت متأكد من رغبتك في تعطيل هذا المستخدم؟');">
                                                    تعطيل
                                                </button>
                                            <?php else: ?>
                                                <button type="submit" class="btn btn-primary btn-sm" onclick="return confirm('هل تريد تفعيل هذا المستخدم؟');">
                                                    تفعيل
                                                </button>
                                            <?php endif; ?>
                                        <?php echo form_close(); ?>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="9" style="text-align: center; padding: 2rem; color: var(--text-muted);">
                            لا يوجد مستخدمون مسجلون حالياً.
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
