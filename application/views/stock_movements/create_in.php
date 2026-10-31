<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>

<div class="card" style="max-width: 720px; margin: 0 auto;">
    <div class="card-header">
        <div>
            <h2 class="card-title">📥 إدخال مخزون جديد (Stock In)</h2>
            <p style="color: var(--text-muted); font-size: 0.85rem; margin-top: 0.2rem;">
                تسجيل حركة توريد أو استلام مخزني لمنتج في مستودع مصرح به وتحديث الرصيد تراكمياً.
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

    <?php echo form_open('stock/movements/in', array('id' => 'stockInForm')); ?>
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
                <span style="color: var(--text-secondary);">الرصيد المتوقع بعد الإدخال:</span>
                <span id="projectedStockVal" style="font-weight: 700; font-size: 1.1rem; color: #16a34a;">0</span>
            </div>
        </div>

        <!-- Quantity -->
        <div class="form-group">
            <label for="quantity" class="form-label">الكمية المدخلة <span style="color: var(--danger);">*</span></label>
            <input type="number" id="quantity" name="quantity" class="form-control"
                   value="<?php echo set_value('quantity'); ?>"
                   required min="1" step="1" max="2147483647" placeholder="أدخل الكمية الصحيحة (مثال: 50)">
            <p class="form-text">يجب أن تكون عدداً صحيحاً موجباً أكبر من الصفر (لا يسمح بالكسور أو الصفر).</p>
        </div>

        <!-- Reason -->
        <div class="form-group">
            <label for="reason" class="form-label">البيان / ملاحظات الإدخال (اختياري)</label>
            <textarea id="reason" name="reason" class="form-control" rows="2" maxlength="255"
                      placeholder="مثال: استلام شحنة بضاعة رقم PO-4412 من المورد..."><?php echo set_value('reason'); ?></textarea>
            <p class="form-text">الحد الأقصى 255 حرفاً.</p>
        </div>

        <div style="display: flex; gap: 0.75rem; justify-content: flex-end; margin-top: 1.5rem; padding-top: 1rem; border-top: 1px solid var(--border);">
            <a href="<?php echo site_url('stock/movements'); ?>" class="btn btn-secondary">إلغاء</a>
            <button type="submit" class="btn btn-primary" id="btnSubmit">
                📥 حفظ وتأكيد إدخال المخزون
            </button>
        </div>
    <?php echo form_close(); ?>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    var warehouseSelect = document.getElementById('warehouse_id');
    var productSelect   = document.getElementById('product_id');
    var quantityInput   = document.getElementById('quantity');
    var previewBox      = document.getElementById('stockPreviewBox');
    var currentValSpan  = document.getElementById('currentStockVal');
    var projectedRow    = document.getElementById('projectedRow');
    var projectedValSpan= document.getElementById('projectedStockVal');

    var currentStock = null;

    function fetchCurrentStock() {
        var wid = warehouseSelect.value;
        var pid = productSelect.value;

        if (!wid || !pid) {
            previewBox.style.display = 'none';
            projectedRow.style.display = 'none';
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
            return;
        }

        var qty = parseInt(quantityInput.value, 10);
        if (!isNaN(qty) && qty > 0) {
            var projected = currentStock + qty;
            projectedValSpan.textContent = projected;
            projectedRow.style.display = 'flex';
        } else {
            projectedRow.style.display = 'none';
        }
    }

    warehouseSelect.addEventListener('change', fetchCurrentStock);
    productSelect.addEventListener('change', fetchCurrentStock);
    quantityInput.addEventListener('input', updateProjected);

    // Initial trigger if values present (e.g. form validation repopulation)
    if (warehouseSelect.value && productSelect.value) {
        fetchCurrentStock();
    }
});
</script>

