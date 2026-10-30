<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>

<div class="card" style="max-width: 600px; margin: 0 auto;">
    <div class="card-header">
        <div>
            <h2 class="card-title">⚙️ ضبط حد إعادة الطلب</h2>
            <p style="color: var(--text-muted); font-size: 0.85rem; margin-top: 0.2rem;">
                تحديد الحد الأدنى للمخزون لهذا المنتج داخل المستودع المحدد.
            </p>
        </div>
        <a href="<?php echo site_url('stock'); ?>" class="btn btn-secondary btn-sm">
            ← عودة للأرصدة
        </a>
    </div>

    <div style="background-color: var(--bg-main); padding: 1rem; border-radius: 8px; border: 1px solid var(--border); margin-bottom: 1.5rem;">
        <div style="margin-bottom: 0.5rem;">
            <span style="color: var(--text-muted); font-size: 0.85rem;">اسم المنتج:</span>
            <strong style="color: var(--text-dark); font-size: 1rem; margin-right: 0.5rem;">
                <?php echo html_escape($stock->product_name); ?>
            </strong>
        </div>
        <div style="margin-bottom: 0.5rem;">
            <span style="color: var(--text-muted); font-size: 0.85rem;">رمز SKU:</span>
            <strong style="color: var(--primary); font-family: monospace; font-size: 0.95rem; margin-right: 0.5rem;">
                <?php echo html_escape($stock->product_sku); ?>
            </strong>
        </div>
        <div style="margin-bottom: 0.5rem;">
            <span style="color: var(--text-muted); font-size: 0.85rem;">المستودع:</span>
            <strong style="color: var(--text-dark); font-size: 0.95rem; margin-right: 0.5rem;">
                🏬 <?php echo html_escape($stock->warehouse_name); ?>
            </strong>
        </div>
        <div>
            <span style="color: var(--text-muted); font-size: 0.85rem;">الرصيد الحالي:</span>
            <strong style="color: var(--text-dark); font-size: 1rem; margin-right: 0.5rem;">
                <?php echo (int)$stock->quantity; ?> وحدة
            </strong>
        </div>
    </div>

    <?php echo form_open('stock/edit_reorder/' . $stock->id); ?>
        <div class="form-group">
            <label for="reorder_level" class="form-label">
                حد إعادة الطلب (Reorder Level) <span style="color: var(--danger);">*</span>
            </label>
            <input type="number" id="reorder_level" name="reorder_level" class="form-control"
                   min="0" step="1" required
                   value="<?php echo set_value('reorder_level', (int)$stock->reorder_level); ?>"
                   placeholder="أدخل رقماً صحيحاً غير سالب (0 أو أكبر)">
            <?php echo form_error('reorder_level', '<div class="form-error">', '</div>'); ?>
            <p style="color: var(--text-muted); font-size: 0.8rem; margin-top: 0.35rem;">
                عندما يصل الرصيد إلى هذا الحد أو يقل عنه، يُصنف المنتج ضمن المخزون المنخفض وتُحسب كمية النقص تلقائياً.
            </p>
        </div>

        <div style="display: flex; gap: 0.75rem; margin-top: 1.5rem; justify-content: flex-end;">
            <a href="<?php echo site_url('stock'); ?>" class="btn btn-secondary">
                إلغاء
            </a>
            <button type="submit" class="btn btn-primary">
                💾 حفظ التعديل
            </button>
        </div>
    <?php echo form_close(); ?>
</div>

