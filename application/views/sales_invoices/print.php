<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>فاتورة مبيعات #<?php echo html_escape($invoice->invoice_number); ?> — Mini ERP</title>
    <style>
        /* Base Screen & Typography Styles */
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f1f5f9;
            color: #0f172a;
            direction: rtl;
            text-align: right;
            padding: 2rem 1rem;
            line-height: 1.5;
        }

        .print-container {
            max-width: 820px;
            margin: 0 auto;
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 2.5rem 2rem;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
        }

        /* Action Toolbar */
        .toolbar {
            max-width: 820px;
            margin: 0 auto 1.5rem auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.55rem 1.25rem;
            border-radius: 6px;
            font-size: 0.95rem;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            transition: all 0.2s ease;
        }

        .btn-primary {
            background-color: #2563eb;
            color: #ffffff;
            border: 1px solid #1d4ed8;
        }

        .btn-primary:hover {
            background-color: #1d4ed8;
        }

        .btn-secondary {
            background-color: #ffffff;
            color: #475569;
            border: 1px solid #cbd5e1;
        }

        .btn-secondary:hover {
            background-color: #f8fafc;
            color: #1e293b;
        }

        /* Header Grid */
        .header-section {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            border-bottom: 2px solid #0f172a;
            padding-bottom: 1.5rem;
            margin-bottom: 1.5rem;
        }

        .company-brand h1 {
            font-size: 1.5rem;
            font-weight: 800;
            color: #0f172a;
            margin-bottom: 0.2rem;
        }

        .company-brand p {
            font-size: 0.9rem;
            color: #64748b;
        }

        .invoice-title-box {
            text-align: left;
            direction: ltr;
        }

        .invoice-title-box h2 {
            font-size: 1.4rem;
            font-weight: 800;
            color: #2563eb;
            margin-bottom: 0.25rem;
        }

        .invoice-meta {
            font-size: 0.9rem;
            color: #334155;
            line-height: 1.6;
        }

        /* Status Badge */
        .badge {
            display: inline-block;
            padding: 0.25rem 0.65rem;
            font-size: 0.8rem;
            font-weight: 700;
            border-radius: 4px;
        }

        .badge-active {
            background-color: #dcfce7;
            color: #15803d;
            border: 1px solid #86efac;
        }

        .badge-cancelled {
            background-color: #fee2e2;
            color: #b91c1c;
            border: 1px solid #fca5a5;
        }

        /* Cancellation Banner */
        .cancellation-banner {
            background-color: #fef2f2;
            border: 1px solid #fecaca;
            border-radius: 6px;
            padding: 0.85rem 1rem;
            margin-bottom: 1.5rem;
            color: #991b1b;
            font-size: 0.9rem;
        }

        /* Info Grid (Customer, Warehouse, Creator) */
        .info-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .info-box {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            padding: 1rem 1.15rem;
        }

        .info-box-title {
            font-size: 0.8rem;
            font-weight: 700;
            color: #64748b;
            text-transform: uppercase;
            margin-bottom: 0.5rem;
            border-bottom: 1px solid #e2e8f0;
            padding-bottom: 0.35rem;
        }

        .info-box-content p {
            font-size: 0.9rem;
            color: #1e293b;
            margin-bottom: 0.25rem;
        }

        /* Items Table */
        .table-container {
            margin-bottom: 1.5rem;
        }

        table.items-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.9rem;
        }

        table.items-table th {
            background-color: #f1f5f9;
            color: #0f172a;
            font-weight: 700;
            padding: 0.75rem 0.65rem;
            border: 1px solid #cbd5e1;
            text-align: right;
        }

        table.items-table td {
            padding: 0.65rem;
            border: 1px solid #e2e8f0;
            color: #1e293b;
        }

        table.items-table tbody tr:nth-child(even) {
            background-color: #f8fafc;
        }

        /* Financial Summary Box */
        .summary-wrapper {
            display: flex;
            justify-content: flex-end;
            margin-bottom: 1.5rem;
        }

        .summary-box {
            width: 320px;
            border: 1px solid #cbd5e1;
            border-radius: 6px;
            overflow: hidden;
        }

        .summary-row {
            display: flex;
            justify-content: space-between;
            padding: 0.6rem 0.85rem;
            font-size: 0.92rem;
            border-bottom: 1px solid #e2e8f0;
        }

        .summary-row:last-child {
            border-bottom: none;
        }

        .summary-row.grand-total {
            background-color: #f1f5f9;
            font-size: 1.05rem;
            font-weight: 800;
            color: #0f172a;
            border-top: 2px solid #0f172a;
        }

        /* Notes Box */
        .notes-section {
            background-color: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            padding: 1rem;
            margin-bottom: 1.5rem;
        }

        .notes-section h4 {
            font-size: 0.85rem;
            color: #475569;
            font-weight: 700;
            margin-bottom: 0.35rem;
        }

        .notes-section p {
            font-size: 0.88rem;
            color: #1e293b;
            white-space: pre-wrap;
        }

        /* Footer */
        .invoice-footer {
            text-align: center;
            font-size: 0.82rem;
            color: #94a3b8;
            border-top: 1px solid #e2e8f0;
            padding-top: 1rem;
            margin-top: 2rem;
        }

        /* Print Specific Rules (@media print) */
        @media print {
            @page {
                size: A4 portrait;
                margin: 12mm 15mm;
            }

            body {
                background: #ffffff !important;
                color: #000000 !important;
                padding: 0 !important;
                font-size: 10.5pt;
            }

            .print-container {
                max-width: 100% !important;
                border: none !important;
                box-shadow: none !important;
                padding: 0 !important;
                border-radius: 0 !important;
            }

            .no-print {
                display: none !important;
            }

            .info-box {
                background: #ffffff !important;
                border: 1px solid #94a3b8 !important;
            }

            table.items-table th {
                background-color: #f1f5f9 !important;
                color: #000000 !important;
                border: 1px solid #000000 !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }

            table.items-table td {
                border: 1px solid #94a3b8 !important;
            }

            table.items-table tr {
                page-break-inside: avoid;
            }

            .summary-box {
                border: 1px solid #000000 !important;
            }

            .summary-row.grand-total {
                background-color: #f1f5f9 !important;
                border-top: 2px solid #000000 !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
        }
    </style>
</head>
<body>

    <!-- On-screen Action Toolbar (Hidden in Print) -->
    <div class="toolbar no-print">
        <a href="<?php echo site_url('sales/invoices/view/' . $invoice->id); ?>" class="btn btn-secondary">
            ← عودة لتفاصيل الفاتورة
        </a>
        <button type="button" onclick="window.print();" class="btn btn-primary">
            🖨️ طباعة الفاتورة (A4)
        </button>
    </div>

    <!-- Main Printable Document Container -->
    <div class="print-container">
        
        <!-- Document Header -->
        <div class="header-section">
            <div class="company-brand">
                <h1>ميني إي آر بي (Mini ERP)</h1>
                <p>نظام إدارة المخزون وفواتير المبيعات</p>
                <div style="margin-top: 0.5rem; font-size: 0.88rem; color: #475569;">
                    المملكة العربية السعودية &nbsp;|&nbsp; العملة: ريال سعودي (SAR)
                </div>
            </div>

            <div class="invoice-title-box">
                <h2>فاتورة مبيعات</h2>
                <div class="invoice-meta">
                    <div><strong>رقم الفاتورة:</strong> <?php echo html_escape($invoice->invoice_number); ?></div>
                    <div><strong>تاريخ الإصدار:</strong> <?php echo html_escape($invoice->invoice_date); ?></div>
                    <div style="margin-top: 0.25rem;">
                        <strong>الحالة:</strong> 
                        <?php if ($invoice->status === 'active'): ?>
                            <span class="badge badge-active">نشطة وموثقة</span>
                        <?php else: ?>
                            <span class="badge badge-cancelled">ملغاة</span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Cancellation Notice (if cancelled) -->
        <?php if ($invoice->status === 'cancelled'): ?>
            <div class="cancellation-banner">
                <strong>⚠️ تنبيه: هذه الفاتورة ملغاة</strong>
                <div><strong>سبب الإلغاء:</strong> <?php echo html_escape($invoice->cancellation_reason); ?></div>
                <div style="font-size: 0.82rem; margin-top: 0.2rem;">
                    ألغيت بواسطة: <?php echo html_escape($invoice->cancelled_by_name ? $invoice->cancelled_by_name : 'مدير النظام'); ?>
                    بتاريخ: <?php echo html_escape($invoice->cancelled_at); ?>
                </div>
            </div>
        <?php endif; ?>

        <!-- Info Grid (Customer & Warehouse) -->
        <div class="info-grid">
            
            <!-- Customer Box -->
            <div class="info-box">
                <div class="info-box-title">بيانات العميل (Bill To)</div>
                <div class="info-box-content">
                    <p style="font-weight: 700; font-size: 1rem; color: #0f172a;">
                        <?php echo html_escape($invoice->customer_name); ?>
                    </p>
                    <?php if ($invoice->customer_phone): ?>
                        <p dir="ltr" style="text-align: right;">📞 <?php echo html_escape($invoice->customer_phone); ?></p>
                    <?php endif; ?>
                    <?php if ($invoice->customer_email): ?>
                        <p>✉️ <?php echo html_escape($invoice->customer_email); ?></p>
                    <?php endif; ?>
                    <?php if (!empty($invoice->customer_address)): ?>
                        <p>📍 <?php echo html_escape($invoice->customer_address); ?></p>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Warehouse & Issuer Box -->
            <div class="info-box">
                <div class="info-box-title">بيانات المستودع ومصدر الفاتورة</div>
                <div class="info-box-content">
                    <p><strong>المستودع:</strong> [<?php echo html_escape($invoice->warehouse_code); ?>] <?php echo html_escape($invoice->warehouse_name); ?></p>
                    <p><strong>أنشئت بواسطة:</strong> <?php echo html_escape($invoice->created_by_name); ?></p>
                    <p><strong>وقت التوثيق:</strong> <?php echo html_escape($invoice->created_at); ?></p>
                </div>
            </div>

        </div>

        <!-- Itemized Table -->
        <div class="table-container">
            <table class="items-table">
                <thead>
                    <tr>
                        <th style="width: 45px; text-align: center;">#</th>
                        <th style="width: 140px;">رمز المنتج (SKU)</th>
                        <th>اسم المنتج والصنف</th>
                        <th style="width: 120px; text-align: left;">سعر الوحدة (ر.س)</th>
                        <th style="width: 90px; text-align: center;">الكمية</th>
                        <th style="width: 130px; text-align: left;">الإجمالي (ر.س)</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($items)): ?>
                        <?php $row_num = 1; ?>
                        <?php foreach ($items as $item): ?>
                            <tr>
                                <td style="text-align: center;"><?php echo $row_num++; ?></td>
                                <td><code><?php echo html_escape($item->sku_snapshot); ?></code></td>
                                <td><strong><?php echo html_escape($item->product_name_snapshot); ?></strong></td>
                                <td style="text-align: left; font-family: monospace;"><?php echo number_format($item->unit_price, 2); ?></td>
                                <td style="text-align: center; font-weight: 600;"><?php echo (int)$item->quantity; ?></td>
                                <td style="text-align: left; font-weight: 700; font-family: monospace;"><?php echo number_format($item->line_total, 2); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6" style="text-align: center; padding: 1.5rem; color: #64748b;">
                                لا توجد بنود مسجلة في هذه الفاتورة.
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Financial Summary Wrapper -->
        <div class="summary-wrapper">
            <div class="summary-box">
                <div class="summary-row">
                    <span>المجموع الفرعي (Subtotal):</span>
                    <strong style="font-family: monospace;"><?php echo number_format($invoice->subtotal, 2); ?> ر.س</strong>
                </div>
                <div class="summary-row">
                    <span>الخصم (<?php echo number_format($invoice->discount_percentage, 2); ?>%):</span>
                    <span style="color: #b91c1c; font-weight: 600; font-family: monospace;">-<?php echo number_format($invoice->discount_amount, 2); ?> ر.س</span>
                </div>
                <div class="summary-row grand-total">
                    <span>الإجمالي النهائي (Total):</span>
                    <span style="font-family: monospace;"><?php echo number_format($invoice->total_amount, 2); ?> ر.س</span>
                </div>
            </div>
        </div>

        <!-- Notes (if any) -->
        <?php if (!empty($invoice->notes)): ?>
            <div class="notes-section">
                <h4>ملاحظات الفاتورة:</h4>
                <p><?php echo nl2br(html_escape($invoice->notes)); ?></p>
            </div>
        <?php endif; ?>

        <!-- Footer -->
        <div class="invoice-footer">
            <p>تم استخراج هذه الفاتورة آلياً من نظام Mini ERP Stock & Sales — جميع الأسعار بالريال السعودي (SAR).</p>
        </div>

    </div>

</body>
</html>

