<div class="audit-logs-page">
    <div class="page-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; flex-wrap: wrap; gap: 1rem;">
        <div>
            <h1 class="page-title" style="font-size: 1.5rem; font-weight: 700; color: var(--text-dark); margin-bottom: 0.25rem;">
                📜 سجل التدقيق المركزي (Audit Logs)
            </h1>
            <p style="color: var(--text-muted); font-size: 0.88rem; margin: 0;">
                سجل تاريخي تدقيقي غير قابل للتعديل لكافة العمليات الإدارية والمالية والمخزنية المنفذة في النظام.
            </p>
        </div>
        <div>
            <span class="badge" style="background-color: #6366f1; color: #fff; font-size: 0.85rem; padding: 0.5rem 0.85rem; border-radius: 6px;">
                إجمالي السجلات: <?php echo number_format($total_count); ?>
            </span>
        </div>
    </div>

    <!-- Filter Card -->
    <div class="card" style="margin-bottom: 1.5rem; padding: 1.25rem;">
        <form method="get" action="<?php echo site_url('audit_logs'); ?>" style="display: flex; flex-wrap: wrap; gap: 1rem; align-items: flex-end;">
            
            <!-- Date From -->
            <div style="flex: 1 1 130px;">
                <label class="form-label" style="font-size: 0.85rem; font-weight: 600; margin-bottom: 0.35rem; display: block;">من تاريخ</label>
                <input type="date" name="date_from" class="form-control" value="<?php echo html_escape(isset($filters['date_from']) ? $filters['date_from'] : ''); ?>">
            </div>

            <!-- Date To -->
            <div style="flex: 1 1 130px;">
                <label class="form-label" style="font-size: 0.85rem; font-weight: 600; margin-bottom: 0.35rem; display: block;">إلى تاريخ</label>
                <input type="date" name="date_to" class="form-control" value="<?php echo html_escape(isset($filters['date_to']) ? $filters['date_to'] : ''); ?>">
            </div>

            <!-- Action Filter -->
            <div style="flex: 1 1 160px;">
                <label class="form-label" style="font-size: 0.85rem; font-weight: 600; margin-bottom: 0.35rem; display: block;">نوع العملية</label>
                <select name="action" class="form-control" style="width: 100%;">
                    <option value="">جميع العمليات</option>
                    <?php if (!empty($actions)): ?>
                        <?php foreach ($actions as $act): ?>
                            <option value="<?php echo html_escape($act); ?>" <?php echo (isset($filters['action']) && $filters['action'] === $act) ? 'selected' : ''; ?>>
                                <?php echo html_escape($act); ?>
                            </option>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </select>
            </div>

            <!-- Entity Filter -->
            <div style="flex: 1 1 140px;">
                <label class="form-label" style="font-size: 0.85rem; font-weight: 600; margin-bottom: 0.35rem; display: block;">نوع الكيان</label>
                <select name="entity_type" class="form-control" style="width: 100%;">
                    <option value="">جميع الكيانات</option>
                    <?php if (!empty($entity_types)): ?>
                        <?php foreach ($entity_types as $ent): ?>
                            <option value="<?php echo html_escape($ent); ?>" <?php echo (isset($filters['entity_type']) && $filters['entity_type'] === $ent) ? 'selected' : ''; ?>>
                                <?php echo html_escape($ent); ?>
                            </option>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </select>
            </div>

            <!-- User Filter -->
            <div style="flex: 1 1 150px;">
                <label class="form-label" style="font-size: 0.85rem; font-weight: 600; margin-bottom: 0.35rem; display: block;">المستخدم</label>
                <select name="user_id" class="form-control" style="width: 100%;">
                    <option value="">جميع المستخدمين</option>
                    <?php if (!empty($users)): ?>
                        <?php foreach ($users as $u): ?>
                            <option value="<?php echo $u->id; ?>" <?php echo (isset($filters['user_id']) && (int)$filters['user_id'] === (int)$u->id) ? 'selected' : ''; ?>>
                                <?php echo html_escape($u->full_name . ' (' . $u->username . ')'); ?>
                            </option>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </select>
            </div>

            <!-- Text Search -->
            <div style="flex: 1 1 180px;">
                <label class="form-label" style="font-size: 0.85rem; font-weight: 600; margin-bottom: 0.35rem; display: block;">بحث بالوصف أو المعرف</label>
                <input type="text" name="search" class="form-control" placeholder="كلمة بحث أو رقم..." value="<?php echo html_escape(isset($filters['search']) ? $filters['search'] : ''); ?>">
            </div>

            <!-- Buttons -->
            <div style="display: flex; gap: 0.5rem; align-items: flex-end;">
                <button type="submit" class="btn btn-primary">
                    🔍 تصفية
                </button>
                <a href="<?php echo site_url('audit_logs'); ?>" class="btn btn-secondary">
                    إعادة ضبط
                </a>
            </div>
        </form>
    </div>

    <!-- Table Card -->
    <div class="card" style="padding: 0; overflow: hidden;">
        <div class="table-responsive">
            <table class="table" style="margin-bottom: 0; width: 100%; border-collapse: collapse;">
                <thead>
                    <tr style="background-color: var(--bg-main, #f8fafc); border-bottom: 1px solid var(--border-color, #e2e8f0); text-align: right;">
                        <th style="padding: 0.75rem 1rem; width: 70px;">#</th>
                        <th style="padding: 0.75rem 1rem; width: 160px;">التاريخ والوقت</th>
                        <th style="padding: 0.75rem 1rem; width: 150px;">المستخدم المنفذ</th>
                        <th style="padding: 0.75rem 1rem; width: 140px;">نوع العملية</th>
                        <th style="padding: 0.75rem 1rem; width: 130px;">الكيان المتأثر</th>
                        <th style="padding: 0.75rem 1rem;">تفاصيل العملية</th>
                        <th style="padding: 0.75rem 1rem; width: 120px;">عنوان IP</th>
                        <th style="padding: 0.75rem 1rem; width: 90px; text-align: center;">البيانات</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($logs)): ?>
                        <?php foreach ($logs as $log): ?>
                            <tr style="border-bottom: 1px solid var(--border-color, #f1f5f9);">
                                <td style="padding: 0.75rem 1rem; font-weight: 600; color: var(--text-muted);">
                                    <?php echo $log->id; ?>
                                </td>
                                <td style="padding: 0.75rem 1rem; font-size: 0.85rem; color: var(--text-muted); direction: ltr; text-align: right;">
                                    <?php echo html_escape($log->created_at); ?>
                                </td>
                                <td style="padding: 0.75rem 1rem;">
                                    <?php if ($log->user_id): ?>
                                        <div style="font-weight: 600; color: var(--text-dark); font-size: 0.88rem;">
                                            <?php echo html_escape($log->full_name ? $log->full_name : $log->username); ?>
                                        </div>
                                        <small style="color: var(--text-muted);">
                                            @<?php echo html_escape($log->username); ?>
                                            <?php if ($log->role_name): ?>
                                                • <span style="color: #6366f1;"><?php echo html_escape($log->role_name); ?></span>
                                            <?php endif; ?>
                                        </small>
                                    <?php else: ?>
                                        <span class="badge" style="background-color: #94a3b8; color: #fff; font-size: 0.75rem;">نظامي / زائر</span>
                                    <?php endif; ?>
                                </td>
                                <td style="padding: 0.75rem 1rem;">
                                    <?php
                                        $badge_bg = '#64748b';
                                        if (strpos($log->action, 'create') !== false || strpos($log->action, 'in') !== false || strpos($log->action, 'activate') !== false) {
                                            $badge_bg = '#10b981';
                                        } elseif (strpos($log->action, 'cancel') !== false || strpos($log->action, 'deactivate') !== false || strpos($log->action, 'reversal') !== false) {
                                            $badge_bg = '#ef4444';
                                        } elseif (strpos($log->action, 'update') !== false || strpos($log->action, 'adjustment') !== false) {
                                            $badge_bg = '#f59e0b';
                                        }
                                    ?>
                                    <span class="badge" style="background-color: <?php echo $badge_bg; ?>; color: #fff; font-size: 0.78rem; padding: 0.35rem 0.55rem; border-radius: 4px; font-family: monospace;">
                                        <?php echo html_escape($log->action); ?>
                                    </span>
                                </td>
                                <td style="padding: 0.75rem 1rem; font-size: 0.85rem;">
                                    <span style="font-weight: 600; color: var(--text-dark);"><?php echo html_escape($log->entity_type); ?></span>
                                    <?php if ($log->entity_id): ?>
                                        <span style="color: var(--text-muted);">#<?php echo $log->entity_id; ?></span>
                                    <?php endif; ?>
                                </td>
                                <td style="padding: 0.75rem 1rem; font-size: 0.88rem; color: var(--text-dark);">
                                    <?php echo html_escape($log->details); ?>
                                </td>
                                <td style="padding: 0.75rem 1rem; font-size: 0.82rem; color: var(--text-muted); font-family: monospace;">
                                    <?php echo html_escape($log->ip_address ? $log->ip_address : '—'); ?>
                                </td>
                                <td style="padding: 0.75rem 1rem; text-align: center;">
                                    <?php if (!empty($log->metadata) && $log->metadata !== '[]' && $log->metadata !== 'null'): ?>
                                        <button type="button" class="btn btn-sm btn-outline-info" style="font-size: 0.78rem; padding: 0.2rem 0.5rem;" onclick="showAuditModal(<?php echo html_escape(json_encode($log)); ?>)">
                                            عرض
                                        </button>
                                    <?php else: ?>
                                        <span style="color: var(--text-muted); font-size: 0.8rem;">—</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="8" style="padding: 3rem; text-align: center; color: var(--text-muted);">
                                📂 لا توجد سجلات تدقيق مطابقة للشروط المحددة.
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <?php if (!empty($pagination['links'])): ?>
            <div style="padding: 1rem; border-top: 1px solid var(--border-color, #e2e8f0); display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;">
                <div style="font-size: 0.85rem; color: var(--text-muted);">
                    عرض السجلات من <?php echo $pagination['offset'] + 1; ?> إلى <?php echo min($pagination['offset'] + count($logs), $total_count); ?> من أصل <?php echo number_format($total_count); ?>
                </div>
                <div>
                    <?php $this->load->view('partials/pagination', $pagination); ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Audit Metadata Modal -->
<div id="auditModal" style="display: none; position: fixed; z-index: 9999; inset: 0; background: rgba(0,0,0,0.5); align-items: center; justify-content: center; padding: 1rem;">
    <div style="background: #fff; border-radius: 8px; width: 100%; max-width: 600px; max-height: 85vh; display: flex; flex-direction: column; overflow: hidden; box-shadow: 0 10px 25px rgba(0,0,0,0.2);">
        <div style="padding: 1rem 1.25rem; border-bottom: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center;">
            <h5 style="margin: 0; font-size: 1.1rem; font-weight: 700; color: #1e293b;" id="auditModalTitle">تفاصيل البيانات الإضافية</h5>
            <button type="button" style="background: none; border: none; font-size: 1.5rem; cursor: pointer; color: #64748b;" onclick="closeAuditModal()">&times;</button>
        </div>
        <div style="padding: 1.25rem; overflow-y: auto;">
            <div style="margin-bottom: 1rem;">
                <strong>العملية:</strong> <span id="auditModalAction"></span> | 
                <strong>التاريخ:</strong> <span id="auditModalDate" style="direction: ltr; display: inline-block;"></span>
            </div>
            <div style="margin-bottom: 1rem;">
                <strong>الوصف:</strong> <span id="auditModalDetails"></span>
            </div>
            <div>
                <strong>البيانات الإضافية (Metadata JSON):</strong>
                <pre id="auditModalJson" style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px; padding: 0.75rem; font-size: 0.85rem; max-height: 250px; overflow: auto; direction: ltr; text-align: left;"></pre>
            </div>
        </div>
        <div style="padding: 0.75rem 1.25rem; border-top: 1px solid #e2e8f0; text-align: left; background: #f8fafc;">
            <button type="button" class="btn btn-secondary btn-sm" onclick="closeAuditModal()">إغلاق</button>
        </div>
    </div>
</div>

<script>
function showAuditModal(log) {
    document.getElementById('auditModalTitle').innerText = 'تفاصيل سجل التدقيق #' + log.id;
    document.getElementById('auditModalAction').innerText = log.action;
    document.getElementById('auditModalDate').innerText = log.created_at;
    document.getElementById('auditModalDetails').innerText = log.details;
    
    var formattedJson = '';
    try {
        var parsed = JSON.parse(log.metadata);
        formattedJson = JSON.stringify(parsed, null, 2);
    } catch(e) {
        formattedJson = log.metadata;
    }
    document.getElementById('auditModalJson').innerText = formattedJson;
    
    document.getElementById('auditModal').style.display = 'flex';
}

function closeAuditModal() {
    document.getElementById('auditModal').style.display = 'none';
}

// Close modal when clicking background
window.addEventListener('click', function(e) {
    var modal = document.getElementById('auditModal');
    if (e.target === modal) {
        closeAuditModal();
    }
});
</script>

