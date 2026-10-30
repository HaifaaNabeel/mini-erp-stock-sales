<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>

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

    <!-- Filter & Search Toolbar -->
    <div style="background-color: var(--bg-main); padding: 1rem; border-radius: 8px; border: 1px solid var(--border); margin-bottom: 1.25rem;">
        <form method="get" action="<?php echo site_url('users'); ?>" style="display: flex; gap: 0.75rem; flex-wrap: wrap; align-items: flex-end;">
            <div style="flex: 2; min-width: 200px;">
                <label for="search" class="form-label" style="font-size: 0.82rem; margin-bottom: 0.2rem;">بحث بالاسم أو البريد</label>
                <input type="text" id="search" name="search" class="form-control" style="padding: 0.5rem 0.75rem; font-size: 0.9rem;"
                       value="<?php echo html_escape($current_search); ?>" placeholder="اسم المستخدم، الاسم الكامل، أو البريد...">
            </div>

            <div style="flex: 1; min-width: 160px;">
                <label for="role" class="form-label" style="font-size: 0.82rem; margin-bottom: 0.2rem;">الدور الوظيفي</label>
                <select id="role" name="role" class="form-control" style="padding: 0.5rem 0.75rem; font-size: 0.9rem;">
                    <option value="">-- جميع الأدوار --</option>
                    <?php if (!empty($roles)): ?>
                        <?php foreach ($roles as $r): ?>
                            <option value="<?php echo html_escape($r->name); ?>" <?php echo ($current_role === $r->name) ? 'selected' : ''; ?>>
                                <?php echo html_escape($r->display_name); ?>
                            </option>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </select>
            </div>

            <div style="flex: 1; min-width: 140px;">
                <label for="status" class="form-label" style="font-size: 0.82rem; margin-bottom: 0.2rem;">الحالة</label>
                <select id="status" name="status" class="form-control" style="padding: 0.5rem 0.75rem; font-size: 0.9rem;">
                    <option value="">-- جميع الحالات --</option>
                    <option value="1" <?php echo ($current_status === 1) ? 'selected' : ''; ?>>نشط فقط</option>
                    <option value="0" <?php echo ($current_status === 0) ? 'selected' : ''; ?>>معطل فقط</option>
                </select>
            </div>

            <!-- Show entries selector -->
            <div style="flex: 0 0 auto;">
                <?php $this->load->view('partials/show_entries', array('pagination' => $pagination, 'form_id' => '')); ?>
            </div>

            <div style="display: flex; gap: 0.5rem;">
                <button type="submit" class="btn btn-primary" style="padding: 0.5rem 1rem;">
                    🔍 تصفية
                </button>
                <?php if ($current_search !== NULL || $current_role !== NULL || $current_status !== NULL): ?>
                    <a href="<?php echo site_url('users'); ?>" class="btn btn-secondary" style="padding: 0.5rem 1rem;" title="إعادة ضبط الفلاتر">
                        ✕ إعادة ضبط
                    </a>
                <?php endif; ?>
            </div>
        </form>
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
                    <th style="width: 290px; text-align: center;">الإجراءات</th>
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
                                <div style="display: inline-flex; gap: 0.35rem; align-items: center; justify-content: center; flex-wrap: wrap;">
                                    <?php if ($u->role_name !== 'admin'): ?>
                                        <a href="<?php echo site_url('users/warehouses/' . $u->id); ?>" class="btn btn-secondary btn-sm" title="المستودعات المصرح بها">
                                            🏬 المستودعات
                                        </a>
                                    <?php endif; ?>

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
                            لا يوجد مستخدمون مطابقون لمعايير البحث أو لا توجد مستخدمون مسجلون بعد.
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <?php $this->load->view('partials/pagination', array('pagination' => $pagination)); ?>
</div>
