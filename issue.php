<?php
session_start();
require_once 'db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

$active_page = 'issue';
$error_msg = "";
$success_msg = "";

// Dynamic Column Checker Helper
function columnExists($conn, $table, $column) {
    $table_check = $conn->query("SHOW TABLES LIKE '$table'");
    if (!$table_check || $table_check->num_rows == 0) return false;
    $res = $conn->query("SHOW COLUMNS FROM `$table` LIKE '$column'");
    return ($res && $res->num_rows > 0);
}

// Dynamic Opening Stock Column Detection
$opening_stock_sql = "0";
if (columnExists($conn, 'items', 'opening_stock')) {
    $opening_stock_sql = "IFNULL(i.opening_stock, 0)";
} elseif (columnExists($conn, 'items', 'opening_balance')) {
    $opening_stock_sql = "IFNULL(i.opening_balance, 0)";
} elseif (columnExists($conn, 'items', 'initial_stock')) {
    $opening_stock_sql = "IFNULL(i.initial_stock, 0)";
} elseif (columnExists($conn, 'items', 'quantity')) {
    $opening_stock_sql = "IFNULL(i.quantity, 0)";
} elseif (columnExists($conn, 'items', 'qty')) {
    $opening_stock_sql = "IFNULL(i.qty, 0)";
}

// Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['issue_stock'])) {
    $issue_number  = trim($_POST['issue_number']);
    $issue_date    = $_POST['issue_date'];
    $department_id = $_POST['department_id'];
    $requested_by  = trim($_POST['requested_by']);
    $item_id       = $_POST['item_id'];
    $issued_qty    = floatval($_POST['issued_qty']);

    if (empty($issue_number) || empty($department_id) || empty($item_id) || $issued_qty <= 0) {
        $error_msg = "කරුණාකර සියලුම අවශ්‍ය දත්ත නිවැරදිව ඇතුළත් කරන්න!";
    } else {
        // Calculate Available Stock (Opening Stock + GRN - Issues)
        $stock_sql = "SELECT 
            (
                $opening_stock_sql 
                + 
                IFNULL((SELECT SUM(gri.received_qty) FROM goods_receipt_items gri WHERE gri.item_id = i.item_id), 0) 
                - 
                IFNULL((SELECT SUM(ivi.issued_qty) FROM issue_voucher_items ivi WHERE ivi.item_id = i.item_id), 0)
            ) AS current_stock
            FROM items i 
            WHERE i.item_id = '$item_id'";

        $stock_res = $conn->query($stock_sql);
        $stock_data = $stock_res->fetch_assoc();
        $current_stock = $stock_data['current_stock'] ?? 0;

        if ($issued_qty > $current_stock) {
            $error_msg = "ERROR: Stock එකේ ප්‍රමාණවත් තරම් භාණ්ඩ නොමැත! වර්තමාන Stock එක: " . number_format($current_stock, 2);
        } else {
            // Start Transaction
            $conn->begin_transaction();
            try {
                // Insert into issue_vouchers with Column Check
                if (columnExists($conn, 'issue_vouchers', 'created_by')) {
                    $stmt1 = $conn->prepare("INSERT INTO issue_vouchers (issue_number, issue_date, department_id, requested_by, created_by) VALUES (?, ?, ?, ?, ?)");
                    $stmt1->bind_param("ssisi", $issue_number, $issue_date, $department_id, $requested_by, $_SESSION['user_id']);
                } else {
                    $stmt1 = $conn->prepare("INSERT INTO issue_vouchers (issue_number, issue_date, department_id, requested_by) VALUES (?, ?, ?, ?)");
                    $stmt1->bind_param("ssis", $issue_number, $issue_date, $department_id, $requested_by);
                }
                $stmt1->execute();
                $issue_id = $conn->insert_id;

                // Insert into issue_voucher_items
                $stmt2 = $conn->prepare("INSERT INTO issue_voucher_items (issue_id, item_id, issued_qty) VALUES (?, ?, ?)");
                $stmt2->bind_param("iid", $issue_id, $item_id, $issued_qty);
                $stmt2->execute();

                $conn->commit();
                $success_msg = "Goods Issue Voucher එක සාර්ථකව ඇතුළත් කරන ලදී!";
            } catch (Exception $e) {
                $conn->rollback();
                $error_msg = "දත්ත ඇතුළත් කිරීම අසාර්ථක විය: " . $e->getMessage();
            }
        }
    }
}

// Generate Next Issue Voucher Number
$next_iss_query = $conn->query("SELECT MAX(issue_id) AS max_id FROM issue_vouchers");
$max_id_row = $next_iss_query->fetch_assoc();
$next_id = ($max_id_row['max_id'] ?? 0) + 1;
$auto_issue_no = "ISS-" . str_pad($next_id, 3, "0", STR_PAD_LEFT);

// Fetch Dropdown Data
$departments_list = $conn->query("SELECT * FROM departments ORDER BY department_name ASC");
$items_list       = $conn->query("SELECT * FROM items ORDER BY item_name ASC");

// Fetch Issue History
$history_sql = "SELECT iv.issue_number, iv.issue_date, d.department_name, iv.requested_by, 
                       i.item_code, i.item_name, ivi.issued_qty 
                FROM issue_voucher_items ivi
                JOIN issue_vouchers iv ON ivi.issue_id = iv.issue_id
                LEFT JOIN departments d ON iv.department_id = d.department_id
                LEFT JOIN items i ON ivi.item_id = i.item_id
                ORDER BY iv.issue_id DESC LIMIT 10";
$history_res = $conn->query($history_sql);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>OSIMS - Goods Issue</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">

<nav class="navbar navbar-expand-lg navbar-dark bg-dark shadow-sm py-2">
    <div class="container-fluid px-4">
        <a class="navbar-brand fw-bold me-4" href="dashboard.php">🏢 OSIMS Store System</a>
        <div class="collapse navbar-collapse">
            <ul class="navbar-nav me-auto gap-2">
                <li class="nav-item"><a class="btn btn-outline-light text-start" href="dashboard.php">📊 Dashboard</a></li>
                <li class="nav-item"><a class="btn btn-outline-light text-start" href="items.php">📦 Manage Items</a></li>
                <li class="nav-item"><a class="btn btn-outline-light text-start" href="suppliers.php">🚚 Suppliers</a></li>
                <li class="nav-item"><a class="btn btn-outline-light text-start" href="sections.php">🏛️ Sections / Branches</a></li>
                <li class="nav-item"><a class="btn btn-outline-light text-start" href="grn.php">📥 GRN</a></li>
                <li class="nav-item"><a class="btn btn-warning active fw-bold text-dark text-start" href="issue.php">📤 Issue</a></li>
                <li class="nav-item"><a class="btn btn-outline-light text-start" href="reports.php">📑 Reports</a></li>
            </ul>
        </div>
    </div>
</nav>

<div class="container-fluid px-4 mt-4">

    <?php if (!empty($error_msg)): ?>
        <div class="alert alert-danger fw-bold shadow-sm"><?= $error_msg; ?></div>
    <?php endif; ?>

    <?php if (!empty($success_msg)): ?>
        <div class="alert alert-success fw-bold shadow-sm"><?= $success_msg; ?></div>
    <?php endif; ?>

    <!-- Issue Form -->
    <div class="card shadow-sm mb-4 border-0">
        <div class="card-header bg-warning text-dark fw-bold">📤 Goods Issue Voucher</div>
        <div class="card-body">
            <form method="POST" action="issue.php">
                <div class="row g-3">
                    <div class="col-md-3">
                        <label class="form-label fw-semibold">Issue Voucher No</label>
                        <input type="text" name="issue_number" class="form-control fw-bold" value="<?= $auto_issue_no; ?>" readonly>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-semibold">Issue Date</label>
                        <input type="date" name="issue_date" class="form-control" value="<?= date('Y-m-d'); ?>" required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-semibold">Issuing Section / Branch</label>
                        <select name="department_id" class="form-select" required>
                            <option value="">-- Select Section --</option>
                            <?php if ($departments_list): while($d = $departments_list->fetch_assoc()): ?>
                                <option value="<?= $d['department_id']; ?>"><?= $d['department_name']; ?></option>
                            <?php endwhile; endif; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-semibold">Requested By</label>
                        <input type="text" name="requested_by" class="form-control" placeholder="Officer Name / Designation" required>
                    </div>
                    <div class="col-md-5">
                        <label class="form-label fw-semibold">Select Item</label>
                        <select name="item_id" class="form-select" required>
                            <option value="">-- Choose Item --</option>
                            <?php if ($items_list): while($i = $items_list->fetch_assoc()): ?>
                                <option value="<?= $i['item_id']; ?>"><?= $i['item_code'] . ' - ' . $i['item_name']; ?></option>
                            <?php endwhile; endif; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-semibold">Issued Quantity</label>
                        <input type="number" step="0.01" name="issued_qty" class="form-control" placeholder="0.00" required>
                    </div>
                    <div class="col-md-4 d-flex align-items-end">
                        <button type="submit" name="issue_stock" class="btn btn-warning fw-bold w-100 shadow-sm">Issue Stock</button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- History Table -->
    <div class="card shadow-sm border-0 mb-5">
        <div class="card-header bg-dark text-white fw-bold">📋 Issued Vouchers History</div>
        <div class="card-body p-0">
            <table class="table table-striped align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Voucher No</th>
                        <th>Date</th>
                        <th>Section / Branch</th>
                        <th>Requested By</th>
                        <th>Item Code</th>
                        <th>Item Description</th>
                        <th class="text-end">Qty Issued</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($history_res && $history_res->num_rows > 0): 
                        while($h = $history_res->fetch_assoc()): ?>
                        <tr>
                            <td><b><?= $h['issue_number']; ?></b></td>
                            <td><?= $h['issue_date']; ?></td>
                            <td><?= $h['department_name']; ?></td>
                            <td><?= $h['requested_by']; ?></td>
                            <td><?= $h['item_code']; ?></td>
                            <td><?= $h['item_name']; ?></td>
                            <td class="text-end"><span class="badge bg-danger fs-6">-<?= number_format($h['issued_qty'], 2); ?></span></td>
                        </tr>
                    <?php endwhile; else: ?>
                        <tr><td colspan="7" class="text-center text-muted py-3">තවමත් නිකුත් කිරීම් සිදු කර නොමැත.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>