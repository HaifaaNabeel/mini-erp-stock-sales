<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>

<div class="show-entries-box">
    <label style="display: inline-flex; align-items: center; gap: 0.4rem; font-size: 0.88rem; color: var(--text-secondary); margin-bottom: 0;">
        <span>عرض</span>
        <select name="limit" class="show-entries-select" onchange="changeEntriesLimit(this)">
            <?php
            $current_limit = isset($pagination['per_page']) ? (int)$pagination['per_page'] : 10;
            foreach (array(10, 20, 50, 100) as $opt):
            ?>
                <option value="<?php echo $opt; ?>" <?php echo ($current_limit === $opt) ? 'selected' : ''; ?>>
                    <?php echo $opt; ?>
                </option>
            <?php endforeach; ?>
        </select>
        <span>سجلات</span>
    </label>
</div>
