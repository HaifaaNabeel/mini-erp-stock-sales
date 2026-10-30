<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>

<div class="card">
    <div class="card-header">
        <div>
            <h2 class="card-title">🏬 مستودعاتي</h2>
            <p style="color: var(--text-muted); font-size: 0.85rem; margin-top: 0.2rem;">
                المستودعات المصرح لك بالعمل فيها.
            </p>
        </div>
    </div>

    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th style="width: 50px;">#</th>
                    <th>رمز المستودع</th>
                    <th>اسم المستودع</th>
                    <th>الموقع / العنوان</th>
                    <th>الحالة</th>
                    <th>تاريخ التعيين</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($warehouses)): ?>
                    <?php foreach ($warehouses as $wh): ?>
                        <tr>
                            <td><?php echo (int)$wh->id; ?></td>
                            <td>
                                <strong style="color: var(--primary); font-family: monospace; font-size: 1rem;">
                                    <?php echo html_escape($wh->code); ?>
                                </strong>
                            </td>
                            <td>
                                <strong><?php echo html_escape($wh->name); ?></strong>
                            </td>
                            <td>
                                <?php echo $wh->location ? html_escape($wh->location) : '<span style="color: var(--text-muted);">-</span>'; ?>
                            </td>
                            <td>
                                <?php if ((int)$wh->is_active === 1): ?>
                                    <span class="badge badge-active">نشط</span>
                                <?php else: ?>
                                    <span class="badge badge-inactive">معطل</span>
                                <?php endif; ?>
                            </td>
                            <td style="font-size: 0.85rem; color: var(--text-muted);">
                                <?php echo $wh->assigned_at ? html_escape(substr($wh->assigned_at, 0, 10)) : '-'; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="6" style="text-align: center; padding: 2.5rem; color: var(--text-muted);">
                            لا توجد مستودعات مصرح لك بها حالياً. يرجى التواصل مع مدير النظام.
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

