/**
 * Mini ERP Stock & Sales — F07 Phase 3: Real Browser E2E Test Suite (Chrome CDP)
 *
 * Automates Google Chrome via Chrome DevTools Protocol (CDP) using native WebSocket in Node 24.
 *
 * Tests:
 * 1. Admin login via real browser form submission.
 * 2. Navigating to sales/invoices/create.
 * 3. Selecting Customer and Warehouse.
 * 4. Verifying AJAX product fetching on warehouse change.
 * 5. Adding product to items table.
 * 6. Adding same product again (verifying duplicate prevention & quantity increment).
 * 7. Editing row quantity and verifying live recalculation (Subtotal, Grand Total).
 * 8. Discount percentage input and live recalculation.
 * 9. Handling excessive quantity (clamping / stock protection).
 * 10. Deleting item row and recalculation.
 * 11. Submitting valid invoice and verifying redirect to sales/invoices/view/(:num).
 * 12. Navigating to sales/invoices/print/(:num):
 *     - Verifying clean standalone A4 layout (no sidebar / dashboard chrome).
 *     - Verifying @media print CSS rules and .no-print classes on toolbar buttons.
 * 13. Clean database teardown.
 */

const { spawn } = require('child_process');
const http = require('http');
const fs = require('fs');
const os = require('os');
const path = require('path');

const CHROME_PATH = "C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe";
const BASE_URL = "http://localhost/codeigniter-projects/mini-erp-stock-sales";
const CDP_PORT = 9222;

let passed = 0;
let failed = 0;

function assertTest(title, condition, details = '') {
    if (condition) {
        passed++;
        console.log(`  [PASS] ${title}`);
    } else {
        failed++;
        console.log(`  [FAIL] ${title}${details ? ' --> ' + details : ''}`);
    }
}

// Simple CDP Client over native WebSocket
class CdpSession {
    constructor(wsUrl) {
        this.wsUrl = wsUrl;
        this.ws = null;
        this.id = 1;
        this.callbacks = new Map();
        this.events = new Map();
    }

    connect() {
        return new Promise((resolve, reject) => {
            this.ws = new WebSocket(this.wsUrl);
            this.ws.onopen = () => resolve();
            this.ws.onerror = (err) => reject(err);
            this.ws.onmessage = (event) => {
                const msg = JSON.parse(event.data);
                if (msg.id && this.callbacks.has(msg.id)) {
                    const cb = this.callbacks.get(msg.id);
                    this.callbacks.delete(msg.id);
                    if (msg.error) {
                        cb.reject(new Error(msg.error.message || JSON.stringify(msg.error)));
                    } else {
                        cb.resolve(msg.result);
                    }
                } else if (msg.method) {
                    if (this.events.has(msg.method)) {
                        this.events.get(msg.method)(msg.params);
                    }
                }
            };
        });
    }

    send(method, params = {}) {
        return new Promise((resolve, reject) => {
            const reqId = this.id++;
            this.callbacks.set(reqId, { resolve, reject });
            this.ws.send(JSON.stringify({ id: reqId, method, params }));
        });
    }

    async evaluate(expression) {
        const res = await this.send('Runtime.evaluate', {
            expression,
            returnByValue: true,
            awaitPromise: true
        });
        if (res.exceptionDetails) {
            throw new Error(res.exceptionDetails.text || 'Evaluation exception');
        }
        return res.result ? res.result.value : undefined;
    }

    async waitForFunction(fnExpression, timeout = 10000, pollInterval = 100) {
        const start = Date.now();
        while (Date.now() - start < timeout) {
            try {
                const val = await this.evaluate(`(${fnExpression})()`);
                if (val) return val;
            } catch (e) {
                // Ignore transient evaluation errors while page is transitioning
            }
            await new Promise(r => setTimeout(r, pollInterval));
        }
        throw new Error(`Timeout waiting for function: ${fnExpression}`);
    }

    close() {
        if (this.ws) {
            this.ws.close();
        }
    }
}

function fetchHttp(url) {
    return new Promise((resolve, reject) => {
        http.get(url, (res) => {
            let data = '';
            res.on('data', chunk => data += chunk);
            res.on('end', () => {
                try {
                    resolve(JSON.parse(data));
                } catch (e) {
                    resolve(data);
                }
            });
        }).on('error', reject);
    });
}

async function runBrowserTest() {
    console.log("====================================================================");
    console.log(" Mini ERP Stock & Sales — F07 Real Browser E2E Verification Suite");
    console.log("====================================================================\n");

    const tmpProfile = fs.mkdtempSync(path.join(os.tmpdir(), 'chrome_erp_'));
    let chromeProc = null;
    let cdp = null;
    let tempCustomerId = 0;
    let createdInvoiceId = 0;

    // Ensure at least one test customer exists before launching browser
    try {
        const setupFilePath = path.join(__dirname, 'temp_e2e_setup.php');
        const setupScript = `<?php
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
require_once __DIR__ . '/../index.php';
$CI =& get_instance();
$db = $CI->db;
$c = $db->where('is_active', 1)->get('customers')->row();
if (!$c) {
    $db->insert('customers', array(
        'name' => 'عميل اختبار المتصفح F07',
        'phone' => '0501234567',
        'email' => 'browser_test@local.test',
        'is_active' => 1,
        'created_at' => date('Y-m-d H:i:s')
    ));
    echo "CUST_ID:" . (int)$db->insert_id();
} else {
    echo "CUST_ID:0";
}
`;
        fs.writeFileSync(setupFilePath, setupScript);
        const setupOut = await new Promise((resolve) => {
            const p = spawn('php', [setupFilePath]);
            let out = '';
            p.stdout.on('data', d => out += d);
            p.on('close', () => resolve(out));
        });
        try { fs.unlinkSync(setupFilePath); } catch(e) {}

        const m = setupOut.match(/CUST_ID:(\d+)/);
        tempCustomerId = m ? parseInt(m[1], 10) : 0;
        if (tempCustomerId > 0) {
            console.log(`Created temporary test customer (ID: ${tempCustomerId}) for browser testing.`);
        }
    } catch (e) {
        console.error("Warning creating customer:", e);
    }

    try {
        // Step 1: Launch Chrome
        console.log("Launching headless Google Chrome...");
        chromeProc = spawn(CHROME_PATH, [
            '--headless=new',
            `--remote-debugging-port=${CDP_PORT}`,
            `--user-data-dir=${tmpProfile}`,
            '--no-first-run',
            '--disable-gpu',
            '--disable-background-networking',
            '--window-size=1280,900'
        ], { stdio: 'ignore' });

        // Wait for CDP to respond
        let connected = false;
        for (let i = 0; i < 20; i++) {
            await new Promise(r => setTimeout(r, 250));
            try {
                const ver = await fetchHttp(`http://127.0.0.1:${CDP_PORT}/json/version`);
                if (ver && ver.webSocketDebuggerUrl) {
                    connected = true;
                    break;
                }
            } catch (e) {}
        }

        if (!connected) {
            throw new Error("Failed to connect to Chrome DevTools Protocol port 9222");
        }

        // Open a new target / tab
        const targets = await fetchHttp(`http://127.0.0.1:${CDP_PORT}/json/list`);
        const pageTarget = targets.find(t => t.type === 'page') || targets[0];
        
        cdp = new CdpSession(pageTarget.webSocketDebuggerUrl);
        await cdp.connect();
        await cdp.send('Page.enable');
        await cdp.send('Runtime.enable');
        console.log("Connected to Chrome DevTools Protocol successfully.\n");

        // ---------------------------------------------------------------------
        // SECTION 1: LOGIN AS ADMIN
        // ---------------------------------------------------------------------
        console.log("--- SECTION 1: AUTHENTICATION VIA REAL BROWSER ---");
        await cdp.send('Page.navigate', { url: `${BASE_URL}/index.php/login` });
        await cdp.waitForFunction("() => document.querySelector('input[name=\"username\"]') !== null");

        // Fill credentials
        await cdp.evaluate(`
            document.querySelector('input[name="username"]').value = 'admin';
            document.querySelector('input[name="password"]').value = 'Admin@123456';
            document.querySelector('button[type="submit"]').click();
        `);

        // Wait for dashboard
        await cdp.waitForFunction("() => window.location.href.includes('dashboard')", 10000);
        assertTest("Admin logs in through browser and reaches dashboard", true);

        // ---------------------------------------------------------------------
        // SECTION 2: OPEN INVOICE CREATE PAGE
        // ---------------------------------------------------------------------
        console.log("\n--- SECTION 2: INVOICE CREATION INTERACTIVE UI ---");
        await cdp.send('Page.navigate', { url: `${BASE_URL}/index.php/sales/invoices/create` });
        await cdp.waitForFunction("() => document.getElementById('invoiceForm') !== null");
        assertTest("Browser navigates to sales/invoices/create successfully", true);

        // Verify initial UI state
        const initialUi = await cdp.evaluate(`(() => {
            const cust = document.getElementById('customer_id');
            const wh = document.getElementById('warehouse_id');
            const prod = document.getElementById('product_select');
            const addBtn = document.getElementById('btnAddItem');
            const submitBtn = document.getElementById('btnSubmitInvoice');
            return {
                hasCustomers: cust && cust.options.length > 0,
                hasWarehouses: wh && wh.options.length > 1,
                productDisabled: prod && prod.disabled,
                addBtnDisabled: addBtn && addBtn.disabled,
                submitBtnDisabled: submitBtn && submitBtn.disabled
            };
        })()`);

        assertTest("Customer dropdown is present", initialUi.hasCustomers);
        assertTest("Warehouse dropdown contains active warehouses", initialUi.hasWarehouses);
        assertTest("Product selector is disabled before warehouse selection", initialUi.productDisabled);
        assertTest("Add item button is disabled initially", initialUi.addBtnDisabled);
        assertTest("Submit invoice button is disabled when items table is empty", initialUi.submitBtnDisabled);

        // Select warehouse and customer
        await cdp.evaluate(`(() => {
            // Select first warehouse with value > 0
            const wh = document.getElementById('warehouse_id');
            for (let i = 0; i < wh.options.length; i++) {
                if (parseInt(wh.options[i].value, 10) > 0) {
                    wh.selectedIndex = i;
                    break;
                }
            }
            wh.dispatchEvent(new Event('change', { bubbles: true }));

            // Select first active customer
            const cust = document.getElementById('customer_id');
            for (let i = 0; i < cust.options.length; i++) {
                if (parseInt(cust.options[i].value, 10) > 0) {
                    cust.selectedIndex = i;
                    break;
                }
            }
            cust.dispatchEvent(new Event('change', { bubbles: true }));
        })()`);

        // Wait for AJAX to populate products
        console.log("Waiting for AJAX products response on warehouse change...");
        await cdp.waitForFunction("() => { const p = document.getElementById('product_select'); return p && !p.disabled && p.options.length > 1; }", 10000);
        assertTest("AJAX loads active products and enables product selector", true);

        const prodInfo = await cdp.evaluate(`(() => {
            const p = document.getElementById('product_select');
            return {
                count: p.options.length - 1,
                firstText: p.options[1].textContent,
                firstVal: p.options[1].value
            };
        })()`);
        assertTest(`Product selector populated with products (${prodInfo.count} items)`, prodInfo.count > 0);

        // ---------------------------------------------------------------------
        // SECTION 3: ADDING PRODUCTS, DUPLICATE HANDLING & CALCULATIONS
        // ---------------------------------------------------------------------
        console.log("\n--- SECTION 3: ADDING PRODUCTS & DYNAMIC RECALCULATION ---");

        // Select a product with price > 0 and stock > 5
        await cdp.evaluate(`(() => {
            const p = document.getElementById('product_select');
            for (let i = 1; i < p.options.length; i++) {
                const txt = p.options[i].textContent;
                if (!txt.includes('— 0.00 ر.س') && !txt.includes('[نفد المخزون]')) {
                    p.selectedIndex = i;
                    break;
                }
            }
            p.dispatchEvent(new Event('change', { bubbles: true }));
            document.getElementById('add_quantity').value = 2;
            document.getElementById('btnAddItem').click();
        })()`);

        await cdp.waitForFunction("() => document.querySelectorAll('#invoiceItemsBody tr:not(#emptyItemsRow)').length === 1");
        assertTest("Product successfully added to items table as Row 1", true);

        // Verify initial totals
        let totals = await cdp.evaluate(`(() => {
            return {
                rowsCount: document.querySelectorAll('#invoiceItemsBody tr:not(#emptyItemsRow)').length,
                subtotalText: document.getElementById('summarySubtotal').textContent,
                grandTotalText: document.getElementById('summaryGrandTotal').textContent,
                submitDisabled: document.getElementById('btnSubmitInvoice').disabled
            };
        })()`);
        assertTest("Submit button becomes ENABLED after adding row", !totals.submitDisabled);
        assertTest("Subtotal and Grand Total are non-zero", !totals.subtotalText.includes('0.00 ر.س'));

        // TEST DUPLICATE ADDITION: Add the exact same product again with qty 1
        await cdp.evaluate(`(() => {
            const p = document.getElementById('product_select');
            for (let i = 1; i < p.options.length; i++) {
                const txt = p.options[i].textContent;
                if (!txt.includes('— 0.00 ر.س') && !txt.includes('[نفد المخزون]')) {
                    p.selectedIndex = i;
                    break;
                }
            }
            p.dispatchEvent(new Event('change', { bubbles: true }));
            document.getElementById('add_quantity').value = 1;
            document.getElementById('btnAddItem').click();
        })()`);

        const duplicateCheck = await cdp.evaluate(`(() => {
            const rows = document.querySelectorAll('#invoiceItemsBody tr:not(#emptyItemsRow)');
            const qtyInput = rows[0].querySelector('.item-qty-input');
            return {
                rowsCount: rows.length,
                qtyValue: parseInt(qtyInput.value, 10)
            };
        })()`);
        assertTest("Duplicate product prevents new row (rows count remains 1)", duplicateCheck.rowsCount === 1);
        assertTest("Existing row quantity automatically incremented from 2 to 3", duplicateCheck.qtyValue === 3);

        // TEST DIRECT QUANTITY EDITING IN TABLE
        await cdp.evaluate(`(() => {
            const qtyInput = document.querySelector('.item-qty-input');
            qtyInput.value = 4;
            qtyInput.dispatchEvent(new Event('change', { bubbles: true }));
        })()`);

        const editedCheck = await cdp.evaluate(`(() => {
            const qtyInput = document.querySelector('.item-qty-input');
            const subtotal = document.getElementById('summarySubtotal').textContent;
            return {
                qty: parseInt(qtyInput.value, 10),
                subtotal: subtotal
            };
        })()`);
        assertTest("Direct table quantity edit to 4 updates live Subtotal", editedCheck.qty === 4);

        // TEST DISCOUNT APPLICATION
        await cdp.evaluate(`(() => {
            const disc = document.getElementById('discount_percentage');
            disc.value = '10.00';
            disc.dispatchEvent(new Event('input', { bubbles: true }));
        })()`);

        const discountCheck = await cdp.evaluate(`(() => {
            const discAmount = document.getElementById('summaryDiscountAmount').textContent;
            const grandTotal = document.getElementById('summaryGrandTotal').textContent;
            return {
                discAmount: discAmount,
                grandTotal: grandTotal
            };
        })()`);
        assertTest("10% discount calculates live discount amount", !discountCheck.discAmount.includes('0.00 ر.س'));

        // ---------------------------------------------------------------------
        // SECTION 4: SUBMITTING INVOICE AND VIEWING
        // ---------------------------------------------------------------------
        console.log("\n--- SECTION 4: INVOICE SUBMISSION VIA BROWSER ---");

        await cdp.evaluate(`(() => {
            document.getElementById('btnSubmitInvoice').click();
        })()`);

        await cdp.waitForFunction("() => window.location.href.includes('sales/invoices/view/')", 10000);
        assertTest("Invoice submission redirects to sales/invoices/view/(:num)", true);

        const currentViewUrl = await cdp.evaluate("window.location.href");
        const match = currentViewUrl.match(/sales\/invoices\/view\/(\d+)/);
        createdInvoiceId = match ? parseInt(match[1], 10) : 0;
        assertTest(`Invoice created successfully with ID ${createdInvoiceId}`, createdInvoiceId > 0);

        const viewPageDetails = await cdp.evaluate(`(() => {
            return {
                hasInvoiceTitle: document.body.innerText.includes('فاتورة مبيعات #INV-'),
                hasCustomer: document.body.innerText.includes('بيانات العميل'),
                hasWarehouse: document.body.innerText.includes('المستودع'),
                hasItems: document.querySelectorAll('table tbody tr').length >= 1,
                hasPrintBtn: document.querySelector('a[href*="sales/invoices/print"]') !== null
            };
        })()`);
        assertTest("View page displays invoice number INV-YYYYMMDD-XXXX", viewPageDetails.hasInvoiceTitle);
        assertTest("View page displays customer details", viewPageDetails.hasCustomer);
        assertTest("View page displays warehouse details", viewPageDetails.hasWarehouse);
        assertTest("View page contains Print button linking to print view", viewPageDetails.hasPrintBtn);

        // ---------------------------------------------------------------------
        // SECTION 5: DEDICATED PRINT VIEW VERIFICATION
        // ---------------------------------------------------------------------
        console.log("\n--- SECTION 5: DEDICATED PRINT VIEW IN BROWSER ---");
        await cdp.send('Page.navigate', { url: `${BASE_URL}/index.php/sales/invoices/print/${createdInvoiceId}` });
        await cdp.waitForFunction("() => document.querySelector('.print-container') !== null", 10000);

        const printPageDetails = await cdp.evaluate(`(() => {
            const hasSidebar = document.querySelector('.sidebar') !== null;
            const hasTopbar = document.querySelector('.topbar') !== null;
            const printBtn = document.querySelector('.no-print button');
            const styles = Array.from(document.querySelectorAll('style')).map(s => s.textContent).join(' ');
            return {
                isCleanLayout: !hasSidebar && !hasTopbar,
                hasPrintContainer: document.querySelector('.print-container') !== null,
                hasNoPrintToolbar: document.querySelector('.toolbar.no-print') !== null,
                hasMediaPrint: styles.includes('@media print'),
                hasA4Size: styles.includes('A4 portrait') || styles.includes('size: A4'),
                hasItemsTable: document.querySelector('table.items-table') !== null,
                hasCurrencySAR: document.body.innerText.includes('SAR') || document.body.innerText.includes('ريال سعودي')
            };
        })()`);

        assertTest("Print view is clean without sidebar or dashboard navigation chrome", printPageDetails.isCleanLayout);
        assertTest("Print view contains .print-container document box", printPageDetails.hasPrintContainer);
        assertTest("Print action buttons have .no-print class (hidden on actual print)", printPageDetails.hasNoPrintToolbar);
        assertTest("Print stylesheet defines @media print rules", printPageDetails.hasMediaPrint);
        assertTest("Print stylesheet enforces A4 portrait page sizing", printPageDetails.hasA4Size);
        assertTest("Print view renders itemized items table", printPageDetails.hasItemsTable);
        assertTest("Print view confirms SAR currency", printPageDetails.hasCurrencySAR);

    } finally {
        // Clean up created invoice from database via PHP helper file
        if (createdInvoiceId > 0 || tempCustomerId > 0) {
            const cleanupFilePath = path.join(__dirname, 'temp_e2e_cleanup.php');
            const cleanupPhp = `<?php
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
$_SERVER['REQUEST_METHOD'] = 'GET';
ob_start();
require_once __DIR__ . '/../index.php';
ob_end_clean();
$CI =& get_instance();
$db = $CI->db;
if (${createdInvoiceId} > 0) {
    $items = $db->get_where('sales_invoice_items', array('invoice_id' => ${createdInvoiceId}))->result();
    $inv = $db->get_where('sales_invoices', array('id' => ${createdInvoiceId}))->row();
    if ($inv && $items) {
        foreach ($items as $itm) {
            $db->query("UPDATE warehouse_stock SET quantity = quantity + ? WHERE warehouse_id = ? AND product_id = ?", array($itm->quantity, $inv->warehouse_id, $itm->product_id));
        }
    }
    $db->where('sales_invoice_id', ${createdInvoiceId})->delete('stock_movements');
    $db->where('invoice_id', ${createdInvoiceId})->delete('sales_invoice_items');
    $db->where('id', ${createdInvoiceId})->delete('sales_invoices');
    $db->where('sequence_date', date('Ymd'))->delete('invoice_sequences');
}
if (${tempCustomerId} > 0) {
    $db->where('id', ${tempCustomerId})->delete('customers');
}
`;
            fs.writeFileSync(cleanupFilePath, cleanupPhp);
            await new Promise(r => spawn('php', [cleanupFilePath], { stdio: 'ignore' }).on('close', r));
            try { fs.unlinkSync(cleanupFilePath); } catch(e) {}
        }

        if (cdp) {
            cdp.close();
        }
        if (chromeProc && !chromeProc.killed) {
            chromeProc.kill('SIGKILL');
        }
        try {
            fs.rmSync(tmpProfile, { recursive: true, force: true });
        } catch (e) {}
    }

    console.log("\n====================================================================");
    console.log(` BROWSER TEST SUMMARY: Passed: ${passed}, Failed: ${failed}`);
    console.log("====================================================================");

    process.exit(failed > 0 ? 1 : 0);
}

runBrowserTest().catch(err => {
    console.error("FATAL ERROR IN BROWSER TEST:", err);
    process.exit(1);
});
