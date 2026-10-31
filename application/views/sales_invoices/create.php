<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>

<div class="card" style="max-width: 1050px; margin: 0 auto;">
    <!-- Page Header -->
    <div class="card-header" style="border-bottom: 2px solid var(--border); padding-bottom: 1.25rem;">
        <div>
            <h1 style="font-size: 1.4rem; font-weight: 700; color: var(--text-dark); margin: 0;">
                ➕ إنشاء فاتورة مبيعات جديدة
            </h1>
            <p style="color: var(--text-muted); font-size: 0.88rem; margin-top: 0.35rem;">
                إصدار فاتورة بيع جديدة وتوثيق حركة البيع وخصم الكميات من المستودع المحدد بشكل آلي وتراكمي.
            </p>
        </div>
        <div>
            <a href="<?php echo site_url('sales/invoices'); ?>" class="btn btn-secondary">
                ← عودة لقائمة الفواتير
            </a>
        </div>
    </div>

    <!-- Flash Messages & Validation Errors -->
    <?php if ($this->session->flashdata('error')): ?>
        <div class="alert alert-error" style="margin-bottom: 1.25rem;">
            <span>⚠️</span>
            <div><?php echo $this->session->flashdata('error'); ?></div>
        </div>
    <?php endif; ?>

    <?php if ($this->session->flashdata('success')): ?>
        <div class="alert alert-success" style="margin-bottom: 1.25rem;">
            <span>✓</span>
            <div><?php echo $this->session->flashdata('success'); ?></div>
        </div>
    <?php endif; ?>

    <!-- Main Invoice Form -->
    <?php echo form_open('sales/invoices/create', array('id' => 'invoiceForm', 'autocomplete' => 'off')); ?>
        
        <!-- Hidden Idempotency Key (32-character hex) -->
        <input type="hidden" name="idempotency_key" value="<?php echo html_escape($idempotency_key); ?>">

        <!-- Section 1: Invoice Header Information -->
        <div style="background-color: var(--bg-main); padding: 1.25rem; border-radius: 8px; border: 1px solid var(--border); margin-bottom: 1.5rem;">
            <h2 style="font-size: 1.05rem; font-weight: 700; color: var(--text-dark); margin-bottom: 1rem;">
                1. بيانات الفاتورة الأساسية
            </h2>

            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 1rem;">
                
                <!-- Customer Selection -->
                <div class="form-group" style="margin-bottom: 0;">
                    <label for="customer_id" class="form-label" style="font-weight: 600;">
                        العميل <span style="color: var(--danger);">*</span>
                    </label>
                    <select id="customer_id" name="customer_id" class="form-control" required autofocus>
                        <option value="">-- اختر العميل --</option>
                        <?php if (!empty($customers)): ?>
                            <?php foreach ($customers as $c): ?>
                                <option value="<?php echo (int)$c->id; ?>">
                                    <?php echo html_escape($c->name); ?> <?php echo $c->phone ? '(' . html_escape($c->phone) . ')' : ''; ?>
                                </option>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </select>
                    <small style="color: var(--text-muted); font-size: 0.8rem; display: block; margin-top: 0.25rem;">
                        العملاء النشطون في النظام فقط.
                    </small>
                </div>

                <!-- Warehouse Selection -->
                <div class="form-group" style="margin-bottom: 0;">
                    <label for="warehouse_id" class="form-label" style="font-weight: 600;">
                        المستودع <span style="color: var(--danger);">*</span>
                    </label>
                    <select id="warehouse_id" name="warehouse_id" class="form-control" required>
                        <option value="">-- اختر المستودع --</option>
                        <?php if (!empty($warehouses)): ?>
                            <?php foreach ($warehouses as $w): ?>
                                <option value="<?php echo (int)$w->id; ?>">
                                    [<?php echo html_escape($w->code); ?>] <?php echo html_escape($w->name); ?>
                                </option>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </select>
                    <small style="color: var(--text-muted); font-size: 0.8rem; display: block; margin-top: 0.25rem;">
                        المستودعات المصرح لك بالبيع منها.
                    </small>
                </div>

                <!-- Invoice Date -->
                <div class="form-group" style="margin-bottom: 0;">
                    <label for="invoice_date" class="form-label" style="font-weight: 600;">
                        تاريخ الفاتورة <span style="color: var(--danger);">*</span>
                    </label>
                    <input type="date" id="invoice_date" name="invoice_date" class="form-control" 
                           value="<?php echo html_escape($default_date); ?>" required>
                    <small style="color: var(--text-muted); font-size: 0.8rem; display: block; margin-top: 0.25rem;">
                        تاريخ تسجيل عملية البيع.
                    </small>
                </div>

            </div>
        </div>

        <!-- Section 2: Product Addition Tool -->
        <div style="background-color: var(--bg-main); padding: 1.25rem; border-radius: 8px; border: 1px solid var(--border); margin-bottom: 1.5rem;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.85rem; flex-wrap: wrap; gap: 0.5rem;">
                <h2 style="font-size: 1.05rem; font-weight: 700; color: var(--text-dark); margin: 0;">
                    2. إضافة الأصناف إلى الفاتورة
                </h2>
                <span id="warehouseNotice" style="color: var(--text-muted); font-size: 0.85rem;">
                    يرجى اختيار المستودع أولاً لتحميل المنتجات المتاحة.
                </span>
            </div>

            <div style="display: grid; grid-template-columns: 3fr 1fr auto; gap: 0.85rem; align-items: flex-end;">
                
                <!-- Product Selector -->
                <div>
                    <label for="product_select" class="form-label" style="font-weight: 600; font-size: 0.88rem;">
                        اختر المنتج
                    </label>
                    <select id="product_select" class="form-control" disabled>
                        <option value="">-- اختر المستودع أولاً --</option>
                    </select>
                </div>

                <!-- Quantity to Add -->
                <div>
                    <label for="add_quantity" class="form-label" style="font-weight: 600; font-size: 0.88rem;">
                        الكمية
                    </label>
                    <input type="number" id="add_quantity" class="form-control" min="1" step="1" value="1" disabled>
                </div>

                <!-- Add Button -->
                <div>
                    <button type="button" id="btnAddItem" class="btn btn-primary" style="padding: 0.55rem 1.15rem; font-weight: 600;" disabled>
                        ➕ إضافة
                    </button>
                </div>

            </div>

            <!-- Product info hint below selector -->
            <div id="productInfoBanner" style="display: none; margin-top: 0.75rem; padding: 0.5rem 0.75rem; background-color: #f1f5f9; border-radius: 6px; font-size: 0.85rem; color: var(--text-secondary);">
                <span>سعر البيع: <strong id="hintSalePrice">0.00</strong> ر.س</span>
                &nbsp;|&nbsp;
                <span>المخزون المتوفر في المستودع: <strong id="hintStock">0</strong> وحدة</span>
            </div>
        </div>

        <!-- Section 3: Invoice Items Table -->
        <div style="margin-bottom: 1.5rem;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.65rem;">
                <h2 style="font-size: 1.05rem; font-weight: 700; color: var(--text-dark); margin: 0;">
                    3. أصناف الفاتورة
                </h2>
                <span style="font-size: 0.85rem; color: var(--text-muted);">
                    عدد الأصناف: <strong id="itemsCountBadge">0</strong>
                </span>
            </div>

            <div class="table-responsive" style="border: 1px solid var(--border); border-radius: 8px; overflow: hidden; background: #fff;">
                <table class="table" style="margin-bottom: 0;" id="invoiceItemsTable">
                    <thead style="background-color: var(--bg-main);">
                        <tr>
                            <th style="width: 50px; text-align: center;">#</th>
                            <th style="width: 140px;">رمز المنتج (SKU)</th>
                            <th>اسم المنتج</th>
                            <th style="width: 130px; text-align: left;">سعر الوحدة (ر.س)</th>
                            <th style="width: 110px; text-align: center;">المتوفر</th>
                            <th style="width: 130px; text-align: center;">الكمية</th>
                            <th style="width: 140px; text-align: left;">الإجمالي (ر.س)</th>
                            <th style="width: 80px; text-align: center;">إجراء</th>
                        </tr>
                    </thead>
                    <tbody id="invoiceItemsBody">
                        <tr id="emptyItemsRow">
                            <td colspan="8" style="text-align: center; padding: 2.5rem; color: var(--text-muted);">
                                لم يتم إضافة أي صنف بعد. اختر منتجاً من الأعلى ثم اضغط على زر "إضافة".
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Section 4: Discount, Summary & Notes Grid -->
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 1.5rem; margin-bottom: 1.75rem;">
            
            <!-- Notes Box -->
            <div style="background-color: var(--bg-main); padding: 1.25rem; border-radius: 8px; border: 1px solid var(--border);">
                <label for="notes" class="form-label" style="font-weight: 600;">
                    ملاحظات الفاتورة (اختياري)
                </label>
                <textarea id="notes" name="notes" class="form-control" rows="5" maxlength="1000"
                          placeholder="أي ملاحظات أو تفاصيل إضافية خاصة بهذه الفاتورة..."></textarea>
                <small style="color: var(--text-muted); font-size: 0.8rem; display: block; margin-top: 0.35rem;">
                    الحد الأقصى 1000 حرف.
                </small>
            </div>

            <!-- Financial Summary Box -->
            <div style="background-color: var(--bg-main); padding: 1.25rem; border-radius: 8px; border: 1px solid var(--border);">
                <h3 style="font-size: 1rem; font-weight: 700; color: var(--text-dark); margin-bottom: 1rem; border-bottom: 1px solid var(--border); padding-bottom: 0.5rem;">
                    الملخص المالي للفاتورة
                </h3>

                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.75rem;">
                    <span style="color: var(--text-secondary); font-size: 0.92rem;">المجموع الفرعي (Subtotal):</span>
                    <strong style="font-size: 1.05rem;" id="summarySubtotal">0.00 ر.س</strong>
                </div>

                <!-- Discount Input Row -->
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.75rem; gap: 0.5rem;">
                    <label for="discount_percentage" style="color: var(--text-secondary); font-size: 0.92rem; margin: 0; white-space: nowrap;">
                        نسبة الخصم (%):
                    </label>
                    <div style="display: flex; align-items: center; gap: 0.35rem; width: 140px;">
                        <input type="number" id="discount_percentage" name="discount_percentage" class="form-control" 
                               min="0" max="100" step="0.01" value="0.00" style="text-align: center; padding: 0.35rem 0.5rem; font-weight: 600;">
                        <span style="font-weight: 600; color: var(--text-secondary);">%</span>
                    </div>
                </div>

                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.85rem;">
                    <span style="color: var(--text-secondary); font-size: 0.92rem;">قيمة الخصم (Discount Amount):</span>
                    <span style="color: var(--danger); font-weight: 600;" id="summaryDiscountAmount">0.00 ر.س</span>
                </div>

                <div style="display: flex; justify-content: space-between; align-items: center; padding-top: 0.85rem; border-top: 2px dashed var(--border); margin-bottom: 1rem;">
                    <strong style="font-size: 1.1rem; color: var(--text-dark);">الإجمالي الصافي النهائي:</strong>
                    <strong style="font-size: 1.35rem; color: var(--primary);" id="summaryGrandTotal">0.00 ر.س</strong>
                </div>

                <div style="background: #eff6ff; border: 1px solid #bfdbfe; border-radius: 6px; padding: 0.65rem 0.85rem; font-size: 0.8rem; color: #1e40af;">
                    ℹ️ الحسابات المالية المعروضة هنا فورية واسترشادية، ويتم التدقيق والاحتساب النهائي الملزم على الخادم وفق القواعد المالية الدقيقة.
                </div>
            </div>

        </div>

        <!-- Form Actions Toolbar -->
        <div style="display: flex; justify-content: flex-end; gap: 0.85rem; align-items: center; padding-top: 1.25rem; border-top: 1px solid var(--border);">
            <a href="<?php echo site_url('sales/invoices'); ?>" class="btn btn-secondary">
                إلغاء وعودة
            </a>
            <button type="submit" id="btnSubmitInvoice" class="btn btn-primary" style="padding: 0.65rem 1.75rem; font-size: 1rem; font-weight: 700;" disabled>
                💾 حفظ وإصدار الفاتورة
            </button>
        </div>

    <?php echo form_close(); ?>
</div>

<!-- Dynamic Client Script for Invoice Creation -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    var warehouseSelect     = document.getElementById('warehouse_id');
    var customerSelect      = document.getElementById('customer_id');
    var productSelect        = document.getElementById('product_select');
    var addQuantityInput     = document.getElementById('add_quantity');
    var btnAddItem          = document.getElementById('btnAddItem');
    var warehouseNotice     = document.getElementById('warehouseNotice');
    var productInfoBanner   = document.getElementById('productInfoBanner');
    var hintSalePrice       = document.getElementById('hintSalePrice');
    var hintStock           = document.getElementById('hintStock');
    var invoiceItemsBody    = document.getElementById('invoiceItemsBody');
    var emptyItemsRow       = document.getElementById('emptyItemsRow');
    var itemsCountBadge     = document.getElementById('itemsCountBadge');
    var discountInput       = document.getElementById('discount_percentage');
    var summarySubtotal     = document.getElementById('summarySubtotal');
    var summaryDiscount     = document.getElementById('summaryDiscountAmount');
    var summaryGrandTotal   = document.getElementById('summaryGrandTotal');
    var btnSubmitInvoice    = document.getElementById('btnSubmitInvoice');
    var invoiceForm         = document.getElementById('invoiceForm');

    // Store products list for currently selected warehouse
    // Map: productId -> { id, sku, name, sale_price, stock_quantity }
    var availableProductsMap = {};

    // Store items currently added in invoice
    // Map: productId -> { product_id, sku, name, unit_price, max_stock, quantity }
    var invoiceItems = {};

    // -----------------------------------------------------------------
    // 1. Warehouse Change Handler -> Fetch Products via AJAX
    // -----------------------------------------------------------------
    warehouseSelect.addEventListener('change', function() {
        var wid = parseInt(this.value, 10);
        
        // If there are existing items and warehouse is changed, warn user
        if (Object.keys(invoiceItems).length > 0) {
            if (!confirm('تغيير المستودع سيؤدي إلى مسح الأصناف الحالية من الفاتورة لإعادة التحقق من الأرصدة. هل ترغب بالمتابعة؟')) {
                // Revert selection
                return;
            }
            clearInvoiceItems();
        }

        if (isNaN(wid) || wid <= 0) {
            resetProductSelector('يرجى اختيار المستودع أولاً لتحميل المنتجات المتاحة.');
            return;
        }

        warehouseNotice.textContent = 'جارٍ تحميل المنتجات وأرصدة المستودع...';
        warehouseNotice.style.color = 'var(--primary)';
        productSelect.disabled = true;
        addQuantityInput.disabled = true;
        btnAddItem.disabled = true;

        var url = '<?php echo site_url("sales/invoices/ajax_get_products"); ?>?warehouse_id=' + encodeURIComponent(wid);

        fetch(url, {
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(function(res) {
            if (!res.ok) {
                throw new Error('HTTP status ' + res.status);
            }
            return res.json();
        })
        .then(function(data) {
            if (data && data.success && Array.isArray(data.products)) {
                populateProductsDropdown(data.products);
                warehouseNotice.textContent = 'تم تحميل ' + data.products.length + ' منتج بنجاح.';
                warehouseNotice.style.color = 'var(--text-muted)';
            } else {
                var err = (data && data.error) ? data.error : 'فشل في تحميل منتجات المستودع.';
                resetProductSelector(err);
            }
        })
        .catch(function(err) {
            resetProductSelector('حدث خطأ أثناء الاتصال بالخادم لتحميل المنتجات.');
        });
    });

    function resetProductSelector(noticeMsg) {
        availableProductsMap = {};
        productSelect.innerHTML = '<option value="">-- اختر المستودع أولاً --</option>';
        productSelect.disabled = true;
        addQuantityInput.disabled = true;
        btnAddItem.disabled = true;
        productInfoBanner.style.display = 'none';
        warehouseNotice.textContent = noticeMsg;
        warehouseNotice.style.color = 'var(--danger)';
    }

    function populateProductsDropdown(products) {
        availableProductsMap = {};
        productSelect.innerHTML = '<option value="">-- اختر المنتج من القائمة --</option>';

        products.forEach(function(p) {
            var pid = parseInt(p.id, 10);
            var stock = parseInt(p.stock_quantity, 10);
            var price = parseFloat(p.sale_price).toFixed(2);

            availableProductsMap[pid] = {
                id: pid,
                sku: p.sku,
                name: p.name,
                sale_price: parseFloat(p.sale_price),
                stock_quantity: stock
            };

            var opt = document.createElement('option');
            opt.value = pid;
            opt.textContent = '[' + p.sku + '] ' + p.name + ' — ' + price + ' ر.س (المتوفر: ' + stock + ')';
            
            // Highlight products with 0 stock
            if (stock <= 0) {
                opt.textContent += ' [نفد المخزون]';
                opt.style.color = '#94a3b8';
            }
            productSelect.appendChild(opt);
        });

        productSelect.disabled = false;
        addQuantityInput.disabled = false;
        btnAddItem.disabled = false;
    }

    // -----------------------------------------------------------------
    // 2. Product Dropdown Change Handler
    // -----------------------------------------------------------------
    productSelect.addEventListener('change', function() {
        var pid = parseInt(this.value, 10);
        if (availableProductsMap[pid]) {
            var prod = availableProductsMap[pid];
            hintSalePrice.textContent = prod.sale_price.toFixed(2);
            hintStock.textContent = prod.stock_quantity;
            productInfoBanner.style.display = 'block';
            addQuantityInput.value = 1;
            addQuantityInput.max = prod.stock_quantity;
        } else {
            productInfoBanner.style.display = 'none';
        }
    });

    // -----------------------------------------------------------------
    // 3. Add Item to Invoice Handler
    // -----------------------------------------------------------------
    btnAddItem.addEventListener('click', function() {
        var pid = parseInt(productSelect.value, 10);
        var qty = parseInt(addQuantityInput.value, 10);

        if (isNaN(pid) || !availableProductsMap[pid]) {
            alert('يرجى اختيار منتج من القائمة أولاً.');
            productSelect.focus();
            return;
        }

        var prod = availableProductsMap[pid];

        if (isNaN(qty) || qty <= 0) {
            alert('يرجى إدخال كمية صحيحة موجبة (1 أو أكثر).');
            addQuantityInput.focus();
            return;
        }

        if (prod.stock_quantity <= 0) {
            alert('عذراً، هذا المنتج غير متوفر في المستودع المختار (الرصيد: 0). لا يمكن إضافته.');
            return;
        }

        // Check if product already added in invoice
        if (invoiceItems[pid]) {
            var newTotalQty = invoiceItems[pid].quantity + qty;
            if (newTotalQty > prod.stock_quantity) {
                alert('الكمية الإجمالية المطلوبة (' + newTotalQty + ') تتجاوز الرصيد المتوفر في المستودع (' + prod.stock_quantity + ').');
                return;
            }
            invoiceItems[pid].quantity = newTotalQty;
            flashRowHighlight(pid);
        } else {
            if (qty > prod.stock_quantity) {
                alert('الكمية المطلوبة (' + qty + ') تتجاوز الرصيد المتوفر في المستودع (' + prod.stock_quantity + ').');
                return;
            }
            invoiceItems[pid] = {
                product_id: pid,
                sku: prod.sku,
                name: prod.name,
                unit_price: prod.sale_price,
                max_stock: prod.stock_quantity,
                quantity: qty
            };
        }

        renderItemsTable();
        calculateTotals();

        // Reset add form
        productSelect.value = '';
        addQuantityInput.value = 1;
        productInfoBanner.style.display = 'none';
    });

    // -----------------------------------------------------------------
    // 4. Render Items Table
    // -----------------------------------------------------------------
    function renderItemsTable() {
        var pids = Object.keys(invoiceItems);
        itemsCountBadge.textContent = pids.length;

        if (pids.length === 0) {
            invoiceItemsBody.innerHTML = '';
            invoiceItemsBody.appendChild(emptyItemsRow);
            btnSubmitInvoice.disabled = true;
            return;
        }

        invoiceItemsBody.innerHTML = '';
        btnSubmitInvoice.disabled = false;

        var rowIdx = 1;
        pids.forEach(function(pidStr) {
            var item = invoiceItems[pidStr];
            var lineTotal = (item.quantity * item.unit_price).toFixed(2);

            var tr = document.createElement('tr');
            tr.id = 'item_row_' + item.product_id;

            tr.innerHTML = 
                '<td style="text-align: center; font-weight: 600;">' + rowIdx + '</td>' +
                '<td><code>' + escapeHtml(item.sku) + '</code></td>' +
                '<td><strong>' + escapeHtml(item.name) + '</strong></td>' +
                '<td style="text-align: left; font-family: monospace;">' + item.unit_price.toFixed(2) + '</td>' +
                '<td style="text-align: center;"><span class="badge" style="background: #e2e8f0; color: #334155;">' + item.max_stock + '</span></td>' +
                '<td style="text-align: center;">' +
                    '<input type="number" class="form-control item-qty-input" ' +
                           'data-pid="' + item.product_id + '" ' +
                           'min="1" max="' + item.max_stock + '" step="1" ' +
                           'value="' + item.quantity + '" ' +
                           'style="width: 85px; text-align: center; margin: 0 auto; padding: 0.3rem;">' +
                '</td>' +
                '<td style="text-align: left; font-weight: 700; font-family: monospace;" id="line_total_' + item.product_id + '">' + lineTotal + '</td>' +
                '<td style="text-align: center;">' +
                    '<button type="button" class="btn btn-secondary btn-sm btn-delete-item" data-pid="' + item.product_id + '" style="color: var(--danger); border-color: #fecaca; background: #fff;" title="حذف">' +
                        '🗑️' +
                    '</button>' +
                    '<input type="hidden" name="items[' + (rowIdx - 1) + '][product_id]" value="' + item.product_id + '">' +
                    '<input type="hidden" name="items[' + (rowIdx - 1) + '][quantity]" id="hidden_qty_' + item.product_id + '" value="' + item.quantity + '">' +
                    '<input type="hidden" name="product_id[]" value="' + item.product_id + '">' +
                    '<input type="hidden" name="quantity[]" id="hidden_arr_qty_' + item.product_id + '" value="' + item.quantity + '">' +
                '</td>';

            invoiceItemsBody.appendChild(tr);
            rowIdx++;
        });

        // Attach event listeners to quantity inputs and delete buttons
        var qtyInputs = invoiceItemsBody.querySelectorAll('.item-qty-input');
        qtyInputs.forEach(function(input) {
            input.addEventListener('change', handleItemQuantityChange);
            input.addEventListener('keyup', handleItemQuantityChange);
        });

        var deleteButtons = invoiceItemsBody.querySelectorAll('.btn-delete-item');
        deleteButtons.forEach(function(btn) {
            btn.addEventListener('click', function() {
                var pid = parseInt(this.getAttribute('data-pid'), 10);
                if (invoiceItems[pid]) {
                    delete invoiceItems[pid];
                    renderItemsTable();
                    calculateTotals();
                }
            });
        });
    }

    function handleItemQuantityChange() {
        var pid = parseInt(this.getAttribute('data-pid'), 10);
        var item = invoiceItems[pid];
        if (!item) return;

        var val = parseInt(this.value, 10);

        if (isNaN(val) || val <= 0) {
            this.value = 1;
            val = 1;
        }

        if (val > item.max_stock) {
            alert('الكمية المدخلة (' + val + ') تتجاوز الرصيد المتوفر في المستودع (' + item.max_stock + ').');
            this.value = item.max_stock;
            val = item.max_stock;
        }

        item.quantity = val;

        // Update hidden inputs
        var hidden1 = document.getElementById('hidden_qty_' + pid);
        if (hidden1) hidden1.value = val;
        var hidden2 = document.getElementById('hidden_arr_qty_' + pid);
        if (hidden2) hidden2.value = val;

        // Update line total
        var lineTotalEl = document.getElementById('line_total_' + pid);
        if (lineTotalEl) {
            lineTotalEl.textContent = (item.quantity * item.unit_price).toFixed(2);
        }

        calculateTotals();
    }

    function flashRowHighlight(pid) {
        var row = document.getElementById('item_row_' + pid);
        if (row) {
            row.style.transition = 'background-color 0.3s ease';
            row.style.backgroundColor = '#fef3c7';
            setTimeout(function() {
                row.style.backgroundColor = '';
            }, 800);
        }
    }

    function clearInvoiceItems() {
        invoiceItems = {};
        renderItemsTable();
        calculateTotals();
    }

    // -----------------------------------------------------------------
    // 5. Calculate Financial Totals
    // -----------------------------------------------------------------
    function calculateTotals() {
        var subtotal = 0;
        Object.keys(invoiceItems).forEach(function(pid) {
            var itm = invoiceItems[pid];
            subtotal += (itm.quantity * itm.unit_price);
        });

        // Round subtotal to 2 decimals
        subtotal = Math.round(subtotal * 100) / 100;

        var discountPerc = parseFloat(discountInput.value);
        if (isNaN(discountPerc) || discountPerc < 0) {
            discountPerc = 0;
        } else if (discountPerc > 100) {
            discountPerc = 100;
            discountInput.value = '100.00';
        }

        var discountAmount = Math.round((subtotal * (discountPerc / 100.0)) * 100) / 100;
        var grandTotal = Math.max(0, Math.round((subtotal - discountAmount) * 100) / 100);

        summarySubtotal.textContent = subtotal.toFixed(2) + ' ر.س';
        summaryDiscount.textContent = discountAmount.toFixed(2) + ' ر.س';
        summaryGrandTotal.textContent = grandTotal.toFixed(2) + ' ر.س';
    }

    discountInput.addEventListener('input', calculateTotals);

    // -----------------------------------------------------------------
    // 6. Form Submission Validation
    // -----------------------------------------------------------------
    invoiceForm.addEventListener('submit', function(e) {
        var customerId = parseInt(customerSelect.value, 10);
        var warehouseId = parseInt(warehouseSelect.value, 10);
        var itemsCount = Object.keys(invoiceItems).length;

        if (isNaN(customerId) || customerId <= 0) {
            e.preventDefault();
            alert('يرجى اختيار العميل أولاً.');
            customerSelect.focus();
            return false;
        }

        if (isNaN(warehouseId) || warehouseId <= 0) {
            e.preventDefault();
            alert('يرجى اختيار المستودع أولاً.');
            warehouseSelect.focus();
            return false;
        }

        if (itemsCount <= 0) {
            e.preventDefault();
            alert('لا يمكن حفظ فاتورة فارغة. يرجى إضافة صنف واحد على الأقل.');
            productSelect.focus();
            return false;
        }

        // Prevent double click on submit button
        btnSubmitInvoice.disabled = true;
        btnSubmitInvoice.textContent = 'جارٍ الحفظ والتوثيق...';
        return true;
    });

    function escapeHtml(text) {
        var map = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        };
        return text ? String(text).replace(/[&<>"']/g, function(m) { return map[m]; }) : '';
    }
});
</script>

