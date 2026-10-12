<?php
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type');
    header('Access-Control-Max-Age: 86400');
    http_response_code(200);
    exit;
}

header('Access-Control-Allow-Origin: *');
header('Content-Type: text/html; charset=utf-8');

$defaultConfig = [
    'host' => 'localhost',
    'dbname' => 'erp',
    'username' => 'root',
    'password' => 'root',
];

$host = $_POST['host'] ?? $_GET['host'] ?? $defaultConfig['host'];
$dbname = $_POST['dbname'] ?? $_GET['dbname'] ?? $defaultConfig['dbname'];
$username = $_POST['username'] ?? $_GET['username'] ?? $defaultConfig['username'];
$password = $_POST['password'] ?? $_GET['password'] ?? $defaultConfig['password'];

$dbConfig = [
    'host' => $host,
    'dbname' => $dbname,
    'username' => $username,
    'password' => $password,
];

$pdo = null;
$connectError = '';

try {
    $pdo = new PDO(
        "mysql:host={$dbConfig['host']};charset=utf8mb4",
        $dbConfig['username'],
        $dbConfig['password']
    );
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    $connectError = $e->getMessage();
}

$action = $_GET['action'] ?? ($_POST['action'] ?? '');

if ($action === 'init' && $pdo) {
    $results = [];
    $sqlDir = __DIR__ . '/sql';

    $sqlFiles = [
        'warehouse/warehouse.sql',
        'warehouse/warehouse_inventory.sql',
        'warehouse/warehouse_io.sql',
        'warehouse/warehouse_io_alter.sql',
        'warehouse/purchase_order.sql',
        'warehouse/purchase_item.sql',
        'customer/customer.sql',
        'customer/customer_price.sql',
        'supplier/supplier_main.sql',
        'supplier/supplier_quote.sql',
        'supplier/supplier_quote_alter.sql',
        'order/order_main.sql',
        'order/order_item.sql',
        'order/order_item_deduction.sql',
        'order/order_payment.sql',
        'finance/finance_flow.sql',
        'finance/finance_receivable.sql',
        'finance/finance_payable.sql',
        'finance/finance_reconciliation.sql',
    ];

    try {
        $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$dbConfig['dbname']}` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        $pdo->exec("USE `{$dbConfig['dbname']}`");
        $dbCreated = true;
    } catch (PDOException $e) {
        $dbCreated = false;
        $dbCreateError = $e->getMessage();
    }

    if ($dbCreated) {
        foreach ($sqlFiles as $file) {
            $filePath = $sqlDir . '/' . $file;
            if (!file_exists($filePath)) {
                $results[] = ['file' => $file, 'status' => 'skip', 'message' => '文件不存在'];
                continue;
            }

            $sql = file_get_contents($filePath);
            $statements = parseSqlStatements($sql);

            $successCount = 0;
            $errorCount = 0;
            $skipCount = 0;
            $errors = [];
            $skipErrors = [];

            $skipCodes = ['1050', '1359', '1060', '1061'];

            foreach ($statements as $stmt) {
                $stmt = trim($stmt);
                if (empty($stmt)) continue;

                try {
                    $pdo->exec($stmt);
                    $successCount++;
                } catch (PDOException $e) {
                    $msg = $e->getMessage();
                    $isSkip = false;
                    foreach ($skipCodes as $code) {
                        if (preg_match("/\b{$code}\b/", $msg)) {
                            $isSkip = true;
                            break;
                        }
                    }
                    if ($isSkip) {
                        $skipCount++;
                        $skipErrors[] = $msg;
                    } else {
                        $errorCount++;
                        $errors[] = $msg;
                    }
                }
            }

            if ($errorCount > 0) {
                $status = 'partial';
            } elseif ($skipCount > 0) {
                $status = 'exists';
            } else {
                $status = 'success';
            }

            $results[] = [
                'file' => $file,
                'status' => $status,
                'success' => $successCount,
                'skip' => $skipCount,
                'error' => $errorCount,
                'errors' => $errors,
                'skipErrors' => $skipErrors,
            ];
        }
    }

    renderResult($results, $dbConfig, $dbCreated, $dbCreateError ?? '');
    exit;
}

function parseSqlStatements($sql) {
    $statements = [];
    $current = '';
    $inDelimiter = false;
    $delimiter = ';';
    $sql = str_replace("\r\n", "\n", $sql);
    $lines = explode("\n", $sql);

    foreach ($lines as $line) {
        $trimmed = trim($line);

        if (stripos($trimmed, 'DELIMITER') === 0) {
            $parts = preg_split('/\s+/', $trimmed);
            if (isset($parts[1])) {
                $delimiter = $parts[1];
                $inDelimiter = ($delimiter !== ';');
            }
            continue;
        }

        $current .= $line . "\n";

        if (!$inDelimiter) {
            if (substr($trimmed, -1) === ';') {
                $statements[] = $current;
                $current = '';
            }
        } else {
            $delimLen = strlen($delimiter);
            if (substr($trimmed, -$delimLen) === $delimiter) {
                $current = rtrim($current);
                $current = substr($current, 0, -$delimLen) . ';';
                $statements[] = $current;
                $current = '';
                $delimiter = ';';
                $inDelimiter = false;
            }
        }
    }

    if (trim($current)) {
        $statements[] = $current;
    }

    return $statements;
}

function renderResult($results, $dbConfig, $dbCreated, $dbCreateError) {
    $totalSuccess = 0;
    $totalError = 0;
    $totalSkip = 0;
    $hasError = !$dbCreated;

    foreach ($results as $r) {
        $totalSuccess += $r['success'] ?? 0;
        $totalError += $r['error'] ?? 0;
        $totalSkip += $r['skip'] ?? 0;
        if ($r['status'] === 'partial') $hasError = true;
    }
    ?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<title>数据库初始化结果</title>
<style>
* { margin: 0; padding: 0; box-sizing: border-box; }
body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; background: #f5f6fa; padding: 40px 20px; }
.container { max-width: 800px; margin: 0 auto; background: #fff; border-radius: 12px; box-shadow: 0 2px 12px rgba(0,0,0,0.08); overflow: hidden; }
.header { padding: 30px 40px; background: linear-gradient(135deg, #6c5ce7, #8e44ad); color: #fff; }
.header h1 { font-size: 24px; margin-bottom: 8px; }
.header p { opacity: 0.9; font-size: 14px; }
.summary { display: flex; gap: 20px; padding: 20px 40px; background: #f8f9fa; border-bottom: 1px solid #eee; }
.summary-item { flex: 1; text-align: center; }
.summary-item .num { font-size: 28px; font-weight: 700; color: #2d3436; }
.summary-item .label { font-size: 12px; color: #636e72; margin-top: 4px; }
.summary-item.success .num { color: #2ecc71; }
.summary-item.error .num { color: #e74c3c; }
.summary-item.skip .num { color: #74b9ff; }
.db-status { padding: 16px 40px; background: <?php echo $dbCreated ? '#d4edda' : '#f8d7da'; ?>; color: <?php echo $dbCreated ? '#155724' : '#721c24'; ?>; font-size: 14px; border-bottom: 1px solid #eee; }
.result-list { padding: 20px 40px; }
.result-item { display: flex; justify-content: space-between; align-items: flex-start; padding: 12px 16px; border-bottom: 1px solid #eee; }
.result-item:last-child { border-bottom: none; }
.result-item .file { font-size: 14px; color: #2d3436; font-family: Consolas, monospace; }
.result-item .status-badge { padding: 4px 12px; border-radius: 12px; font-size: 12px; font-weight: 600; flex-shrink: 0; }
.status-badge.success { background: #d4edda; color: #155724; }
.status-badge.exists { background: #d1ecf1; color: #0c5460; }
.status-badge.partial { background: #fff3cd; color: #856404; }
.status-badge.skip { background: #e2e3e5; color: #383d41; }
.errors { margin-top: 6px; }
.errors div { font-size: 12px; color: #e74c3c; font-family: Consolas, monospace; }
.skip-info { margin-top: 4px; font-size: 12px; color: #74b9ff; font-family: Consolas, monospace; }
.back-btn { display: inline-block; margin-top: 20px; padding: 10px 24px; background: #6c5ce7; color: #fff; text-decoration: none; border-radius: 6px; font-size: 14px; border: none; cursor: pointer; }
.back-btn:hover { background: #5b4cdb; }
.footer { padding: 20px 40px; text-align: center; color: #b2bec3; font-size: 12px; }
</style>
</head>
<body>
<div class="container">
    <div class="header">
        <h1>🗄️ 数据库初始化</h1>
        <p>数据库：<?php echo htmlspecialchars($dbConfig['dbname']); ?> @ <?php echo htmlspecialchars($dbConfig['host']); ?></p>
    </div>
    <div class="db-status">
        <?php if ($dbCreated): ?>
        ✅ 数据库 <code><?php echo htmlspecialchars($dbConfig['dbname']); ?></code> 创建成功（或已存在）
        <?php else: ?>
        ❌ 数据库创建失败：<?php echo htmlspecialchars($dbCreateError); ?>
        <?php endif; ?>
    </div>
    <?php if ($dbCreated): ?>
    <div class="summary">
        <div class="summary-item success">
            <div class="num"><?php echo $totalSuccess; ?></div>
            <div class="label">新建成功</div>
        </div>
        <div class="summary-item skip">
            <div class="num"><?php echo $totalSkip; ?></div>
            <div class="label">已存在</div>
        </div>
        <div class="summary-item error">
            <div class="num"><?php echo $totalError; ?></div>
            <div class="label">执行失败</div>
        </div>
        <div class="summary-item">
            <div class="num"><?php echo count($results); ?></div>
            <div class="label">SQL文件</div>
        </div>
    </div>
    <div class="result-list">
        <?php foreach ($results as $r): ?>
        <div class="result-item">
            <div>
                <div class="file"><?php echo htmlspecialchars($r['file']); ?></div>
                <?php if (!empty($r['errors'])): ?>
                <div class="errors">
                    <?php foreach (array_slice($r['errors'], 0, 3) as $err): ?>
                    <div>• <?php echo htmlspecialchars($err); ?></div>
                    <?php endforeach; ?>
                    <?php if (count($r['errors']) > 3): ?>
                    <div>• ... 还有 <?php echo count($r['errors']) - 3; ?> 条错误</div>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
                <?php if (!empty($r['skipErrors']) && $r['status'] === 'exists'): ?>
                <div class="skip-info">
                    共 <?php echo count($r['skipErrors']); ?> 项已存在（表/触发器/字段/索引）
                </div>
                <?php endif; ?>
            </div>
            <span class="status-badge <?php echo $r['status']; ?>">
                <?php
                $map = ['success' => '成功', 'exists' => '已存在', 'partial' => '部分成功', 'skip' => '跳过'];
                echo $map[$r['status']] ?? $r['status'];
                ?>
            </span>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
    <div style="padding: 0 40px 30px; text-align: center;">
        <a href="api.php" class="back-btn">返回首页</a>
    </div>
    <div class="footer">TCOS 数据库初始化工具</div>
</div>
</body>
</html>
    <?php
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<title>数据库初始化 - TCOS</title>
<style>
* { margin: 0; padding: 0; box-sizing: border-box; }
body {
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
    background: linear-gradient(135deg, #6c5ce7, #8e44ad);
    min-height: 100vh;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 20px;
}
.card {
    background: #fff;
    border-radius: 16px;
    box-shadow: 0 20px 60px rgba(0,0,0,0.2);
    padding: 48px 56px;
    max-width: 520px;
    width: 100%;
}
.icon {
    font-size: 64px;
    text-align: center;
    margin-bottom: 20px;
}
h1 {
    font-size: 28px;
    color: #2d3436;
    margin-bottom: 8px;
    text-align: center;
}
.subtitle {
    color: #636e72;
    font-size: 14px;
    margin-bottom: 32px;
    text-align: center;
}
.form-group {
    margin-bottom: 18px;
}
.form-group label {
    display: block;
    font-size: 13px;
    color: #2d3436;
    font-weight: 600;
    margin-bottom: 6px;
}
.form-group input {
    width: 100%;
    padding: 10px 14px;
    border: 1px solid #dfe6e9;
    border-radius: 6px;
    font-size: 14px;
    font-family: Consolas, monospace;
    transition: border-color 0.2s;
}
.form-group input:focus {
    outline: none;
    border-color: #6c5ce7;
}
.form-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 12px;
}
.tables-list {
    margin: 24px 0;
}
.tables-list h3 {
    font-size: 14px;
    color: #2d3436;
    margin-bottom: 10px;
}
.tables-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 6px;
}
.table-tag {
    background: #f0f1f3;
    color: #636e72;
    padding: 4px 8px;
    border-radius: 4px;
    font-size: 12px;
    font-family: Consolas, monospace;
    text-align: center;
}
.btn {
    width: 100%;
    padding: 14px;
    border: none;
    border-radius: 8px;
    font-size: 16px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s;
}
.btn-primary {
    background: linear-gradient(135deg, #6c5ce7, #8e44ad);
    color: #fff;
}
.btn-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(108, 92, 231, 0.4);
}
.btn-primary:active {
    transform: translateY(0);
}
.warning {
    background: #fff3cd;
    color: #856404;
    padding: 12px 16px;
    border-radius: 8px;
    font-size: 13px;
    margin-bottom: 20px;
}
.error-box {
    background: #f8d7da;
    color: #721c24;
    padding: 12px 16px;
    border-radius: 8px;
    font-size: 13px;
    margin-bottom: 20px;
    font-family: Consolas, monospace;
    word-break: break-all;
}
.footer {
    margin-top: 24px;
    color: #b2bec3;
    font-size: 12px;
    text-align: center;
}
</style>
</head>
<body>
<div class="card">
    <div class="icon">🗄️</div>
    <h1>数据库初始化</h1>
    <p class="subtitle">TCOS 进销存管理系统数据库安装工具</p>

    <?php if ($connectError): ?>
    <div class="error-box">
        ⚠️ 数据库连接失败：<?php echo htmlspecialchars($connectError); ?>
    </div>
    <?php endif; ?>

    <form action="api.php" method="post">
        <input type="hidden" name="action" value="init">

        <div class="form-row">
            <div class="form-group">
                <label>数据库地址</label>
                <input type="text" name="host" value="<?php echo htmlspecialchars($dbConfig['host']); ?>" placeholder="localhost">
            </div>
            <div class="form-group">
                <label>数据库名</label>
                <input type="text" name="dbname" value="<?php echo htmlspecialchars($dbConfig['dbname']); ?>" placeholder="erp">
            </div>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label>用户名</label>
                <input type="text" name="username" value="<?php echo htmlspecialchars($dbConfig['username']); ?>" placeholder="root">
            </div>
            <div class="form-group">
                <label>密码</label>
                <input type="password" name="password" value="<?php echo htmlspecialchars($dbConfig['password']); ?>" placeholder="password">
            </div>
        </div>

        <div class="tables-list">
            <h3>将创建以下数据表（16张）：</h3>
            <div class="tables-grid">
                <div class="table-tag">warehouse</div>
                <div class="table-tag">inventory</div>
                <div class="table-tag">warehouse_io</div>
                <div class="table-tag">purchase</div>
                <div class="table-tag">purchase_item</div>
                <div class="table-tag">customer</div>
                <div class="table-tag">customer_price</div>
                <div class="table-tag">supplier</div>
                <div class="table-tag">supplier_quote</div>
                <div class="table-tag">order_main</div>
                <div class="table-tag">order_item</div>
                <div class="table-tag">order_pay</div>
                <div class="table-tag">finance_flow</div>
                <div class="table-tag">receivable</div>
                <div class="table-tag">payable</div>
                <div class="table-tag">reconciliation</div>
            </div>
        </div>

        <button type="submit" class="btn btn-primary">🚀 开始初始化</button>
    </form>

    <p class="footer">TCOS © 2026 — 数据库初始化工具 v1.0</p>
</div>
</body>
</html>