<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>

<div class="card" style="max-width: 900px; margin: 0 auto;">
    <!-- Invoice Header -->
    <div class="card-header" style="border-bottom: 2px solid var(--border); padding-bottom: 1.25rem;">
        <div>
            <div style="display: flex; align-items: center; gap: 0.75rem; flex-wrap: wrap;">
                <h1 style="font-size: 1.4rem; font-weight: 700; color: var(--text-dark); margin: 0;">
                    فاتورة مبيعات #<?php echo html_escape($invoice->invoice_number); ?>
                </h1>
                <?php if ($invoice->status === 'active'): ?>
                    <span class="badge badge-active" style="font-size: 0.85rem; padding: 0.35rem 0.75rem;">فاتورة نشطة</span>
                <?php else: ?>
                    <span class="badge badge-inactive" style="font-size: 0.85rem; padding: 0.35rem 0.75rem;">فاتورة ملغاة</span>
                <?php endif; ?>
            </div>
            <p style="color: var(--text-muted); font-size: 0.88rem; margin-top: 0.35rem;">
                تاريخ الإصدار: <strong><?php echo html_escape($invoice->invoice_date); ?></strong>
                &nbsp;|&nbsp;
                العملة: <strong>ريال سعودي (SAR)</strong>
            </p>
        </div>

        <div style="display: flex; gap: 0.5rem; align-items: center; flex-wrap: wrap;">
            <a href="<?php echo site_url('sales/invoices/print/' . $invoice->id); ?>" class="btn btn-primary" target="_blank">
                🖨️ طباعة الفاتورة
            </a>
            <a href="<?php echo site_url('sales/invoices'); ?>" class="btn btn-secondary">
                ← عودة لقائمة الفواتير
            </a>
        </div>
    </div>

    <!-- Cancellation Notice (if cancelled) -->
    <?php if ($invoice->status === 'cancelled'): ?>
        <div class="alert alert-error" style="margin-bottom: 1.5rem; background-color: #fef2f2; border: 1px solid #fecaca; border-radius: 8px; padding: 1rem 1.25rem;">
            <div style="display: flex; gap: 0.75rem; align-items: flex-start;">
                <span style="font-size: 1.5rem; line-height: 1;">🚫</span>
                <div>
                    <h3 style="font-size: 1rem; font-weight: 700; color: #991b1b; margin-bottom: 0.25rem;">تم إلغاء هذه الفاتورة</h3>
                    <p style="font-size: 0.88rem; color: #7f1d1d; margin-bottom: 0.2rem;">
                        <strong>سبب الإلغاء:</strong> <?php echo html_escape($invoice->cancellation_reason); ?>
                    </p>
                    <p style="font-size: 0.82rem; color: #991b1b;">
                        ألغيت بواسطة: <strong><?php echo html_escape($invoice->cancelled_by_name ? $invoice->cancelled_by_name : 'مدير النظام'); ?></strong>
                        بتاريخ: <?php echo html_escape($invoice->cancelled_at); ?>
                    </p>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- Invoice Parties & Metadata Grid -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 1.25rem; margin-bottom: 1.75rem;">
        
        <!-- Customer Box -->
        <div style="background-color: var(--bg-main); padding: 1rem 1.25rem; border-radius: 8px; border: 1px solid var(--border);">
            <div style="font-size: 0.82rem; color: var(--text-muted); font-weight: 600; margin-bottom: 0.5rem; text-transform: uppercase;">
                بيانات العميل
            </div>
            <div style="font-size: 1.05rem; font-weight: 700; color: var(--text-dark); margin-bottom: 0.25rem;">
                <?php echo html_escape($invoice->customer_name); ?>
            </div>
            <?php if ($invoice->customer_phone): ?>
                <div style="font-size: 0.88rem; color: var(--text-secondary); margin-bottom: 0.2rem;" dir="ltr">
                    📞 <?php echo html_escape($invoice->customer_phone); ?>
                </div>
            <?php endif; ?>
            <?php if ($invoice->customer_email): ?>
                <div style="font-size: 0.85rem; color: var(--text-muted);">
                    ✉️ <?php echo html_escape($invoice->customer_email); ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Warehouse Box -->
        <div style="background-color: var(--bg-main); padding: 1rem 1.25rem; border-radius: 8px; border: 1px solid var(--border);">
            <div style="font-size: 0.82rem; color: var(--text-muted); font-weight: 600; margin-bottom: 0.5rem; text-transform: uppercase;">
                مستودع الصرف
            </div>
            <div style="font-size: 1.05rem; font-weight: 700; color: var(--text-dark); margin-bottom: 0.25rem;">
                <?php echo html_escape($invoice->warehouse_name); ?>
            </div>
            <div style="font-size: 0.88rem; color: var(--text-secondary);">
                رمز المستودع: <strong><?php echo html_escape($invoice->warehouse_code); ?></strong>
            </div>
        </div>

        <!-- Creator & Meta Box -->
        <div style="background-color: var(--bg-main); padding: 1rem 1.25rem; border-radius: 8px; border: 1px solid var(--border);">
            <div style="font-size: 0.82rem; color: var(--text-muted); font-weight: 600; margin-bottom: 0.5rem; text-transform: uppercase;">
                بيانات الإنشاء والنظام
            </div>
            <div style="font-size: 0.92rem; color: var(--text-dark); margin-bottom: 0.25rem;">
                أنشئت بواسطة: <strong><?php echo html_escape($invoice->created_by_name); ?></strong>
                <span style="font-size: 0.8rem; color: var(--text-muted);">(<?php echo html_escape($invoice->created_by_username); ?>)</span>
            </div>
            <div style="font-size: 0.82rem; color: var(--text-muted);">
                توقيت النظام: <?php echo html_escape($invoice->created_at); ?>
            </div>
        </div>
    </div>

    <!-- Line Items Table -->
    <h3 style="font-size: 1.1rem; font-weight: 700; color: var(--text-dark); margin-bottom: 0.75rem;">
        بنود الفاتورة
    </h3>

    <div class="table-responsive" style="margin-bottom: 1.5rem;">
        <table class="table">
            <thead>
                <tr>
                    <th style="width: 50px;">#</th>
                    <th style="width: 140px;">رمز المنتج (SKU)</th>
                    <th>اسم المنتج المباع</th>
                    <th style="text-align: left; width: 120px;">سعر الوحدة</th>
                    <th style="text-align: center; width: 90px;">الكمية</th>
                    <th style="text-align: left; width: 140px;">إجمالي البند</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($items)): ?>
                    <?php $row_num = 1; foreach ($items as $item): ?>
                        <tr>
                            <td><?php echo $row_num++; ?></td>
                            <td>
                                <code style="font-family: monospace; font-size: 0.9rem; font-weight: 600; color: #1e3a8a;">
                                    <?php echo html_escape($item->sku_snapshot); ?>
                                </code>
                            </td>
                            <td>
                                <strong><?php echo html_escape($item->product_name_snapshot); ?></strong>
                            </td>
                            <td style="text-align: left; font-family: monospace; font-size: 0.92rem;">
                                <?php echo number_format((float)$item->unit_price, 2); ?> <small>ر.س</small>
                            </td>
                            <td style="text-align: center; font-weight: 700; font-size: 0.95rem;">
                                <?php echo (int)$item->quantity; ?>
                            </td>
                            <td style="text-align: left; font-family: monospace; font-weight: 700; font-size: 0.98rem; color: #0f172a;">
                                <?php echo number_format((float)$item->line_total, 2); ?> <small>ر.س</small>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="6" style="text-align: center; padding: 1.5rem; color: var(--text-muted);">
                            لا توجد بنود مسجلة في هذه الفاتورة.
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Financial Totals Section -->
    <div style="display: flex; justify-content: space-between; flex-wrap: wrap; gap: 1.5rem; margin-top: 1rem; align-items: flex-start;">
        
        <!-- Notes box -->
        <div style="flex: 1; min-width: 280px;">
            <?php if (!empty($invoice->notes)): ?>
                <div style="background-color: #f8fafc; border: 1px solid var(--border); border-radius: 8px; padding: 0.85rem 1rem;">
                    <div style="font-weight: 600; font-size: 0.85rem; color: var(--text-secondary); margin-bottom: 0.35rem;">ملاحظات الفاتورة:</div>
                    <p style="font-size: 0.9rem; color: var(--text-dark); margin: 0; line-height: 1.5;">
                        <?php echo nl2br(html_escape($invoice->notes)); ?>
                    </p>
                </div>
            <?php endif; ?>
        </div>

        <!-- Summary box -->
        <div style="width: 320px; background-color: var(--bg-main); border: 1px solid var(--border); border-radius: 10px; padding: 1.25rem;">
            
            <!-- Subtotal -->
            <div style="display: flex; justify-content: space-between; font-size: 0.95rem; margin-bottom: 0.65rem; color: var(--text-secondary);">
                <span>المجموع قبل الخصم:</span>
                <span style="font-family: monospace; font-weight: 600; color: var(--text-dark);">
                    <?php echo number_format((float)$invoice->subtotal, 2); ?> ر.س
                </span>
            </div>

            <!-- Discount -->
            <div style="display: flex; justify-content: space-between; font-size: 0.95rem; margin-bottom: 0.65rem; color: var(--danger);">
                <span>
                    الخصم 
                    <?php if ((float)$invoice->discount_percentage > 0): ?>
                        (<?php echo (float)$invoice->discount_percentage; ?>%):
                    <?php else: ?>
                        :
                    <?php endif; ?>
                </span>
                <span style="font-family: monospace; font-weight: 600;">
                    -<?php echo number_format((float)$invoice->discount_amount, 2); ?> ر.س
                </span>
            </div>

            <div style="border-top: 2px dashed var(--border); margin: 0.75rem 0;"></div>

            <!-- Grand Total -->
            <div style="display: flex; justify-content: space-between; align-items: baseline; font-size: 1.15rem; font-weight: 700; color: var(--primary);">
                <span>الإجمالي النهائي:</span>
                <span style="font-family: monospace; font-size: 1.35rem;">
                    <?php echo number_format((float)$invoice->total_amount, 2); ?> <small style="font-size: 0.85rem;">ر.س</small>
                </span>
            </div>
            
            <div style="text-align: left; font-size: 0.75rem; color: var(--text-muted); margin-top: 0.35rem;">
                العملة المعتمدة: ريال سعودي (SAR)
            </div>
        </div>
    </div>
</div>

