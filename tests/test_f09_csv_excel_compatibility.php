<?php
/**
 * Test Suite: F09 CSV Regional Compatibility & Excel Column Display Verification
 *
 * Verifies:
 * 1. Default semicolon delimiter matching Excel Windows regional setting (sList = ;) in Arabic/Saudi Arabia.
 * 2. UTF-8 BOM presence and Arabic text integrity.
 * 3. Proper escaping of text containing commas, semicolons, and double quotes.
 * 4. CSV Formula Injection prevention.
 * 5. Full Excel opening test via COM automation to verify columns are separated properly.
 * 6. Live HTTP export of report CSV and verification in Excel.
 */

error_reporting(E_ALL);
ini_set('display_errors', '1');

$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
$_SERVER['REQUEST_METHOD'] = 'GET';
ob_start();
require_once __DIR__ . '/../index.php';
ob_end_clean();

require_once APPPATH . 'controllers/Reports.php';

$passed = 0;
$failed = 0;

function assert_test($description, $condition) {
    global $passed, $failed;
    if ($condition) {
        $passed++;
        echo "  [PASS] {$description}\n";
    } else {
        $failed++;
        echo "  [FAIL] {$description}\n";
    }
}

echo "====================================================================\n";
echo " Mini ERP Stock & Sales — F09 CSV Excel Compatibility Verification\n";
echo "====================================================================\n\n";

// -------------------------------------------------------------------------
// SECTION 1: IN-MEMORY CSV GENERATION & DELIMITER TESTS
// -------------------------------------------------------------------------
echo "--- SECTION 1: DELIMITER & ENCLOSURE INTEGRITY ---\n";

$headers = array('م', 'اسم المنتج', 'الرمز (SKU)', 'التصنيف', 'المستودع', 'الكمية', 'حالة المخزون');
$sample_rows = array(
    array(1, 'منتج قياسي', 'SKU-001', 'تصنيف عام', 'مستودع الرياض', 50, 'متوفر'),
    array(2, 'شاشة 15" بوصة; ممتازة', 'SKU-002', 'شاشات, وملحقات', 'مستودع جدة', 5, 'منخفض'),
    array(3, 'كابل "HDMI" بطول 2,5م', 'SKU-003', 'كابلات; وأسلاك', 'مستودع الدمام', 0, 'نافد'),
    array(4, '=1+1', 'SKU-004', 'برمجيات', 'مستودع مكة', 10, 'متوفر')
);

// Helper to generate CSV string using Reports class logic
function generate_test_csv($headers, $rows, $delimiter = ';') {
    $fp = fopen('php://memory', 'r+');
    fwrite($fp, "\xEF\xBB\xBF"); // BOM

    $clean_headers = array_map(array('Reports', 'sanitize_csv_cell'), $headers);
    fputcsv($fp, $clean_headers, $delimiter, '"', "\\");

    foreach ($rows as $row) {
        $clean_row = array_map(array('Reports', 'sanitize_csv_cell'), $row);
        fputcsv($fp, $clean_row, $delimiter, '"', "\\");
    }

    rewind($fp);
    $csv = stream_get_contents($fp);
    fclose($fp);
    return $csv;
}

$csv_content = generate_test_csv($headers, $sample_rows, ';');

// 1.1 BOM Check
assert_test("Generated CSV begins with UTF-8 BOM (0xEF 0xBB 0xBF)", substr($csv_content, 0, 3) === "\xEF\xBB\xBF");

// 1.2 Delimiter is semicolon
$raw_body = substr($csv_content, 3);
$lines = explode("\n", trim(str_replace("\r\n", "\n", $raw_body)));

assert_test("Total lines count equals headers (1) + rows (4) = 5", count($lines) === 5);

// 1.3 Each line has expected number of columns (7)
$col_count_ok = true;
foreach ($lines as $idx => $line) {
    $parsed_cols = str_getcsv($line, ';', '"', "\\");
    if (count($parsed_cols) !== 7) {
        $col_count_ok = false;
        echo "    Row {$idx} has " . count($parsed_cols) . " columns instead of 7\n";
    }
}
assert_test("Every single row has exactly 7 columns when split by semicolon", $col_count_ok);

// 1.4 Field with semicolon and quotes correctly enclosed
$row2_cols = str_getcsv($lines[2], ';', '"', "\\");
assert_test("Field with semicolon & quotes preserved: 'شاشة 15\" بوصة; ممتازة'", $row2_cols[1] === 'شاشة 15" بوصة; ممتازة');
assert_test("Field with comma preserved: 'شاشات, وملحقات'", $row2_cols[3] === 'شاشات, وملحقات');

// 1.5 Field with double quotes and comma
$row3_cols = str_getcsv($lines[3], ';', '"', "\\");
assert_test("Field with quotes & comma preserved: 'كابل \"HDMI\" بطول 2,5م'", $row3_cols[1] === 'كابل "HDMI" بطول 2,5م');
assert_test("Field with semicolon in category preserved: 'كابلات; وأسلاك'", $row3_cols[3] === 'كابلات; وأسلاك');

// 1.6 Formula injection prevented
$row4_cols = str_getcsv($lines[4], ';', '"', "\\");
assert_test("Formula cell sanitized with leading quote: ''=1+1'", $row4_cols[1] === "'=1+1");

echo "\n";

// -------------------------------------------------------------------------
// SECTION 2: OPENING AND PARSING IN REAL MICROSOFT EXCEL (COM)
// -------------------------------------------------------------------------
echo "--- SECTION 2: REAL EXCEL OPENING TEST (Target Windows ar-SA Locale) ---\n";

$temp_csv = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'test_excel_f09_export.csv';
file_put_contents($temp_csv, $csv_content);

$ps_script = <<<POWERSHELL
[Console]::OutputEncoding = [System.Text.Encoding]::UTF8
\$OutputEncoding = [System.Text.Encoding]::UTF8
\$csvPath = '$temp_csv'

\$excel = New-Object -ComObject Excel.Application
\$excel.Visible = \$false
\$excel.DisplayAlerts = \$false

try {
    \$wb = \$excel.Workbooks.Open(\$csvPath)
    \$ws = \$wb.Sheets.Item(1)
    
    \$rows = \$ws.UsedRange.Rows.Count
    \$cols = \$ws.UsedRange.Columns.Count
    
    \$col1Header = \$ws.Cells.Item(1, 1).Text
    \$col2Header = \$ws.Cells.Item(1, 2).Text
    \$col7Header = \$ws.Cells.Item(1, 7).Text
    
    \$row2Col1 = \$ws.Cells.Item(2, 1).Text
    \$row2Col2 = \$ws.Cells.Item(2, 2).Text
    
    \$row3Col2 = \$ws.Cells.Item(3, 2).Text
    \$row3Col4 = \$ws.Cells.Item(3, 4).Text
    
    \$row4Col2 = \$ws.Cells.Item(4, 2).Text
    
    \$row5Col2 = \$ws.Cells.Item(5, 2).Text
    
    \$wb.Close(\$false)
    \$excel.Quit()
    [System.Runtime.Interopservices.Marshal]::ReleaseComObject(\$excel) | Out-Null
    
    \$result = [PSCustomObject]@{
        Rows = \$rows
        Cols = \$cols
        Col1Header = \$col1Header
        Col2Header = \$col2Header
        Col7Header = \$col7Header
        Row2Col1 = \$row2Col1
        Row2Col2 = \$row2Col2
        Row3Col2 = \$row3Col2
        Row3Col4 = \$row3Col4
        Row4Col2 = \$row4Col2
        Row5Col2 = \$row5Col2
    }
    \$result | ConvertTo-Json
} catch {
    \$excel.Quit()
    [System.Runtime.Interopservices.Marshal]::ReleaseComObject(\$excel) | Out-Null
    Write-Error \$_
}
POWERSHELL;

$ps_temp = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'test_excel_run.ps1';
file_put_contents($ps_temp, $ps_script);

$output = shell_exec("powershell -ExecutionPolicy Bypass -File \"$ps_temp\"");
@unlink($ps_temp);
@unlink($temp_csv);

$excel_res = json_decode($output, true);

if ($excel_res) {
    assert_test("Excel recognized exactly 5 rows", $excel_res['Rows'] === 5);
    assert_test("CRITICAL: Excel separated columns into exactly 7 distinct columns (NOT 1 column)", $excel_res['Cols'] === 7);
    assert_test("Excel Cell(1,1) is 'م'", $excel_res['Col1Header'] === 'م');
    assert_test("Excel Cell(1,2) is 'اسم المنتج'", $excel_res['Col2Header'] === 'اسم المنتج');
    assert_test("Excel Cell(1,7) is 'حالة المخزون'", $excel_res['Col7Header'] === 'حالة المخزون');
    assert_test("Excel Cell(2,1) is '1'", $excel_res['Row2Col1'] === '1');
    assert_test("Excel Cell(2,2) is 'منتج قياسي'", $excel_res['Row2Col2'] === 'منتج قياسي');
    assert_test("Excel Cell(3,2) preserves quotes and semicolon: 'شاشة 15\" بوصة; ممتازة'", $excel_res['Row3Col2'] === 'شاشة 15" بوصة; ممتازة');
    assert_test("Excel Cell(3,4) preserves comma in cell: 'شاشات, وملحقات'", $excel_res['Row3Col4'] === 'شاشات, وملحقات');
    assert_test("Excel Cell(4,2) preserves quotes: 'كابل \"HDMI\" بطول 2,5م'", $excel_res['Row4Col2'] === 'كابل "HDMI" بطول 2,5م');
    assert_test("Excel Cell(5,2) formula neutralized as plain text", strpos($excel_res['Row5Col2'], '=1+1') !== false);
} else {
    echo "  [FAIL] Failed to execute Excel COM test or parse JSON output: $output\n";
    $failed++;
}

echo "\n";

// -------------------------------------------------------------------------
// SECTION 3: HTTP LIVE EXPORT INTEGRATION
// -------------------------------------------------------------------------
echo "--- SECTION 3: HTTP LIVE EXPORT INTEGRATION ---\n";

class SimpleHttp {
    private $cookie_file;
    private $base_url;

    public function __construct($base_url) {
        $this->base_url = rtrim($base_url, '/');
        $this->cookie_file = tempnam(sys_get_temp_dir(), 'ci_http_cookie_');
    }

    public function __destruct() {
        if (file_exists($this->cookie_file)) {
            @unlink($this->cookie_file);
        }
    }

    public function request($path, $method = 'GET', $data = array()) {
        $url = $this->base_url . '/' . ltrim($path, '/');
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, true);
        curl_setopt($ch, CURLOPT_COOKIEJAR, $this->cookie_file);
        curl_setopt($ch, CURLOPT_COOKIEFILE, $this->cookie_file);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);

        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        }

        $res = curl_exec($ch);
        $header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $header = substr($res, 0, $header_size);
        $body = substr($res, $header_size);

        return array('status' => $status, 'header' => $header, 'body' => $body);
    }

    public function get_csrf_token($path = 'index.php/login') {
        $res = $this->request($path, 'GET');
        if (preg_match('/name="csrf_erp_token"\s+value="([a-f0-9]{32})"/', $res['body'], $m)) {
            return $m[1];
        }
        if (file_exists($this->cookie_file)) {
            $content = file_get_contents($this->cookie_file);
            if (preg_match('/csrf_erp_cookie\s+([a-f0-9]{32})/', $content, $m)) {
                return $m[1];
            }
        }
        return NULL;
    }
}

$http = new SimpleHttp('http://localhost/codeigniter-projects/mini-erp-stock-sales');

// Login as admin
$csrf = $http->get_csrf_token('index.php/login');

$login_res = $http->request('index.php/login', 'POST', array(
    'csrf_erp_token' => $csrf,
    'username'       => 'admin',
    'password'       => 'Admin@123456'
));

assert_test("Admin logs in successfully", $login_res['status'] === 302 || $login_res['status'] === 303 || $login_res['status'] === 200);

// 3.1 Live Stock Balances Export
$stock_csv_res = $http->request('index.php/reports/stock/export');
assert_test("Live /reports/stock/export returns HTTP 200", $stock_csv_res['status'] === 200);
assert_test("Live export starts with UTF-8 BOM", substr($stock_csv_res['body'], 0, 3) === "\xEF\xBB\xBF");

// Parse rows from live export
$stock_lines = explode("\n", trim(str_replace("\r\n", "\n", substr($stock_csv_res['body'], 3))));
$header_cols = str_getcsv($stock_lines[0], ';', '"', "\\");
assert_test("Stock CSV header contains 10 columns separated by semicolon", count($header_cols) === 10);
assert_test("Stock CSV header includes 'اسم المنتج'", in_array('اسم المنتج', $header_cols, true));

// Test in Excel
$live_temp_csv = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'test_live_stock_export.csv';
file_put_contents($live_temp_csv, $stock_csv_res['body']);

$ps_live = <<<POWERSHELL
\$csvPath = '$live_temp_csv'
\$excel = New-Object -ComObject Excel.Application
\$excel.Visible = \$false
\$excel.DisplayAlerts = \$false
\$wb = \$excel.Workbooks.Open(\$csvPath)
\$ws = \$wb.Sheets.Item(1)
\$cols = \$ws.UsedRange.Columns.Count
\$rows = \$ws.UsedRange.Rows.Count
\$wb.Close(\$false)
\$excel.Quit()
[System.Runtime.Interopservices.Marshal]::ReleaseComObject(\$excel) | Out-Null
Write-Output "\$cols,\$rows"
POWERSHELL;

$ps_live_temp = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'test_ps_live.ps1';
file_put_contents($ps_live_temp, $ps_live);
$live_out = trim(shell_exec("powershell -ExecutionPolicy Bypass -File \"$ps_live_temp\""));
@unlink($ps_live_temp);
@unlink($live_temp_csv);

$dims = explode(',', $live_out);
$live_cols = isset($dims[0]) ? (int)$dims[0] : 0;
$live_rows = isset($dims[1]) ? (int)$dims[1] : 0;

assert_test("CRITICAL: Excel opens live stock CSV with 10 distinct columns (NOT 1 column)", $live_cols === 10);
assert_test("Excel opens live stock CSV with rows >= 1", $live_rows >= 1);

// 3.2 Optional comma delimiter parameter support
$comma_res = $http->request('index.php/reports/stock/export?delimiter=comma');
assert_test("Live export with ?delimiter=comma returns HTTP 200", $comma_res['status'] === 200);
$comma_lines = explode("\n", trim(str_replace("\r\n", "\n", substr($comma_res['body'], 3))));
$comma_header = str_getcsv($comma_lines[0], ',', '"', "\\");
assert_test("Live export with ?delimiter=comma parses into 10 columns using comma", count($comma_header) === 10);

echo "\n====================================================================\n";
echo " TEST SUMMARY: Passed: {$passed} | Failed: {$failed}\n";
echo "====================================================================\n";

if ($failed > 0) {
    exit(1);
}
exit(0);
