<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>

<?php if (isset($pagination)): ?>
<div class="datatable-footer">
    <div class="datatable-summary">
        <?php if ($pagination['total_rows'] > 0): ?>
            عرض <?php echo (int)$pagination['from']; ?>–<?php echo (int)$pagination['to']; ?> من <?php echo (int)$pagination['total_rows']; ?> سجلًا
        <?php else: ?>
            لا توجد سجلات لعرضها
        <?php endif; ?>
    </div>

    <?php if (!empty($pagination['links'])): ?>
        <nav aria-label="تنقل الصفحات">
            <?php echo $pagination['links']; ?>
        </nav>
    <?php endif; ?>
</div>
<?php endif; ?>

