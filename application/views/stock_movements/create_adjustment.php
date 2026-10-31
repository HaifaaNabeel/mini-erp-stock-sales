<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>

<div class="card" style="max-width: 720px; margin: 0 auto;">
    <div class="card-header">
        <div>
            <h2 class="card-title">⚖ تسوية مخزون (Stock Adjustment)</h2>
            <p style="color: var(--text-muted); font-size: 0.85rem; margin-top: 0.2rem;">
                تعديل رصيد المخزون بالزيادة أو الخصم لمعالجة نتائج الجرد أو التلف مع إلزامية توثيق السبب المبرر.
            </p>
        </div>
        <a href="<?php echo site_url('stock/movements'); ?>" class="btn btn-secondary btn-sm">عودة للسجل</a>
    </div>

    <?php if (validation_errors()): ?>
        <div class="alert alert-error">
            <span>⚠</span>
            <div><?php echo validation_errors('<div>', '</div>'); ?></div>
        </div>
    <?php endif; ?>

    <?php echo form_open('stock/movements/adjust', array('id' => 'stockAdjustmentForm')); ?>
        <!-- Hidden Idempotency Key -->
        <input type="hidden" name="idempotency_key" value="<?php echo html_escape($idempotency_key); ?>">

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
            <!-- Warehouse -->
            <div class="form-group">
                <label for="warehouse_id" class="form-label">المستودع <span style="color: var(--danger);">*</span></label>
                <select id="warehouse_id" name="warehouse_id" class="form-control" required autofocus>
                    <option value="">-- اختر المستودع --</option>
                    <?php if (!empty($warehouses)): ?>
                        <?php foreach ($warehouses as $wh): ?>
                            <option value="<?php echo (int)$wh->id; ?>" <?php echo set_select('warehouse_id', $wh->id); ?>>
                                [<?php echo html_escape($wh->code); ?>] <?php echo html_escape($wh->name); ?>
                            </option>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </select>
                <p class="form-text">المستودعات النشطة والمصرح لك بالعمل عليها.</p>
            </div>

            <!-- Product -->
            <div class="form-group">
                <label for="product_id" class="form-label">المنتج <span style="color: var(--danger);">*</span></label>
                <select id="product_id" name="product_id" class="form-control" required>
                    <option value="">-- اختر المنتج --</option>
                    <?php if (!empty($products)): ?>
                        <?php foreach ($products as $prod): ?>
                            <option value="<?php echo (int)$prod->id; ?>" <?php echo set_select('product_id', $prod->id); ?>>
                                [<?php echo html_escape($prod->sku); ?>] <?php echo html_escape($prod->name); ?>
                            </option>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </select>
                <p class="form-text">المنتجات النشطة المتاحة في النظام.</p>
            </div>
        </div>

        <!-- Live Stock Preview Card -->
        <div id="stockPreviewBox" style="display: none; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 0.85rem 1rem; margin-bottom: 1.25rem;">
            <div style="display: flex; justify-content: space-between; align-items: center; font-size: 0.9rem;">
                <span style="color: var(--text-secondary);">الرصيد الحالي بالمستودع:</span>
                <span id="currentStockVal" style="font-weight: 700; font-size: 1.1rem; color: var(--primary);">0</span>
            </div>
            <div id="projectedRow" style="display: none; justify-content: space-between; align-items: center; font-size: 0.9rem; margin-top: 0.4rem; padding-top: 0.4rem; border-top: 1px dashed #cbd5e1;">
                <span style="color: var(--text-secondary);">الرصيد المتوقع بعد التسوية:</span>
                <span id="projectedStockVal" style="font-weight: 700; font-size: 1.1rem;">0</span>
            </div>
            <div id="negativeStockWarning" style="display: none; margin-top: 0.6rem; padding: 0.5rem 0.75rem; background: #fee2e2; border: 1px solid #fca5a5; border-radius: 6px; color: #991b1b; font-size: 0.85rem; font-weight: 600;">
                ⚠ تنبيه: كمية الخصم تتجاوز الرصيد المتاح. لا يمكن أن يصبح رصيد المخزون سالباً!
            </div>
        </div>

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
            <!-- Adjustment Type -->
            <div class="form-group">
                <label for="adjustment_type" class="form-label">نوع التسوية <span style="color: var(--danger);">*</span></label>
                <select id="adjustment_type" name="adjustment_type" class="form-control" required>
                    <option value="add" <?php echo set_select('adjustment_type', 'add', TRUE); ?>>➕ إضافة إلى الرصيد (فائض جرد)</option>
                    <option value="subtract" <?php echo set_select('adjustment_type', 'subtract'); ?>>➖ خصم من الرصيد (عجز جرد أو تالف)</option>
                </select>
                <p class="form-text">حدد ما إذا كانت العملية زيادة أم إنقاصاً للرصيد.</p>
            </div>

            <!-- Quantity -->
            <div class="form-group">
                <label for="quantity" class="form-label">كمية التسوية <span style="color: var(--danger);">*</span></label>
                <input type="number" id="quantity" name="quantity" class="form-control"
                       value="<?php echo set_value('quantity'); ?>"
                       required min="1" step="1" max="2147483647" placeholder="أدخل الكمية الصحيحة (مثال: 5)">
                <p class="form-text">عدد صحيح موجب أكبر من الصفر.</p>
            </div>
        </div>

        <!-- Reason (Mandatory) -->
        <div class="form-group">
            <label for="reason" class="form-label">سبب التسوية المبرر <span style="color: var(--danger);">*</span></label>
            <textarea id="reason" name="reason" class="form-control" rows="3" required minlength="5" maxlength="255"
                      placeholder="اذكر سبب التسوية بدقة (مثال: تسوية عجز جرد سنوي، أو إتلاف بضاعة منتهية الصلاحية محضر رقم 12)..."><?php echo set_value('reason'); ?></textarea>
            <div style="display: flex; justify-content: space-between; margin-top: 0.25rem;">
                <p class="form-text" style="margin-top: 0;">السبب إلزامي شرعاً ونظاماً (5 أحرف على الأقل، حد أقصى 255 حرفاً).</p>
                <span id="reasonCounter" style="font-size: 0.78rem; color: var(--text-muted);">0 / 255</span>
            </div>
        </div>

        <div style="display: flex; gap: 0.75rem; justify-content: flex-end; margin-top: 1.5rem; padding-top: 1rem; border-top: 1px solid var(--border);">
            <a href="<?php echo site_url('stock/movements'); ?>" class="btn btn-secondary">إلغاء</a>
            <button type="submit" class="btn btn-primary" id="btnSubmit" style="background-color: #0284c7; border-color: #0284c7;">
                ⚖ حفظ وتأكيد تسوية المخزون
            </button>
        </div>
    <?php echo form_close(); ?>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    var warehouseSelect = document.getElementById('warehouse_id');
    var productSelect   = document.getElementById('product_id');
    var adjustTypeSelect= document.getElementById('adjustment_type');
    var quantityInput   = document.getElementById('quantity');
    var previewBox      = document.getElementById('stockPreviewBox');
    var currentValSpan  = document.getElementById('currentStockVal');
    var projectedRow    = document.getElementById('projectedRow');
    var projectedValSpan= document.getElementById('projectedStockVal');
    var negativeWarning = document.getElementById('negativeStockWarning');
    var btnSubmit       = document.getElementById('btnSubmit');
    var reasonInput     = document.getElementById('reason');
    var reasonCounter   = document.getElementById('reasonCounter');

    var currentStock = null;

    function updateReasonCounter() {
        var len = reasonInput.value.length;
        reasonCounter.textContent = len + ' / 255';
        if (len < 5 && len > 0) {
            reasonCounter.style.color = '#dc2626';
        } else {
            reasonCounter.style.color = 'var(--text-muted)';
        }
    }

    function fetchCurrentStock() {
        var wid = warehouseSelect.value;
        var pid = productSelect.value;

        if (!wid || !pid) {
            previewBox.style.display = 'none';
            projectedRow.style.display = 'none';
            negativeWarning.style.display = 'none';
            currentStock = null;
            return;
        }

        var url = '<?php echo site_url("stock/movements/ajax_get_stock"); ?>?warehouse_id=' + encodeURIComponent(wid) + '&product_id=' + encodeURIComponent(pid);

        fetch(url, {
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(function(res) { return res.json(); })
        .then(function(data) {
            if (data && data.success) {
                currentStock = parseInt(data.quantity, 10);
                currentValSpan.textContent = currentStock;
                previewBox.style.display = 'block';
                updateProjected();
            } else {
                previewBox.style.display = 'none';
                currentStock = null;
            }
        })
        .catch(function() {
            previewBox.style.display = 'none';
            currentStock = null;
        });
    }

    function updateProjected() {
        if (currentStock === null) {
            projectedRow.style.display = 'none';
            negativeWarning.style.display = 'none';
            btnSubmit.disabled = false;
            return;
        }

        var qty = parseInt(quantityInput.value, 10);
        var type = adjustTypeSelect.value;

        if (!isNaN(qty) && qty > 0) {
            var projected = (type === 'add') ? (currentStock + qty) : (currentStock - qty);
            projectedValSpan.textContent = projected;

            if (projected < 0) {
                projectedValSpan.style.color = '#dc2626';
                negativeWarning.style.display = 'block';
                btnSubmit.disabled = true;
            } else {
                projectedValSpan.style.color = (type === 'add') ? '#16a34a' : '#0284c7';
                negativeWarning.style.display = 'none';
                btnSubmit.disabled = false;
            }

            projectedRow.style.display = 'flex';
        } else {
            projectedRow.style.display = 'none';
            negativeWarning.style.display = 'none';
            btnSubmit.disabled = false;
        }
    }

    warehouseSelect.addEventListener('change', fetchCurrentStock);
    productSelect.addEventListener('change', fetchCurrentStock);
    adjustTypeSelect.addEventListener('change', updateProjected);
    quantityInput.addEventListener('input', updateProjected);
    reasonInput.addEventListener('input', updateReasonCounter);

    updateReasonCounter();

    if (warehouseSelect.value && productSelect.value) {
        fetchCurrentStock();
    }
});
</script>

