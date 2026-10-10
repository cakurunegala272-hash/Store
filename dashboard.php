<?php
session_start();
require_once 'db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

$active_page = 'dashboard';

// Helper function to check column existence safely
function columnExists($conn, $table, $column) {
    $table_check = $conn->query("SHOW TABLES LIKE '$table'");
    if (!$table_check || $table_check->num_rows == 0) {
        return false;
    }
    $res = $conn->query("SHOW COLUMNS FROM `$table` LIKE '$column'");
    return ($res && $res->num_rows > 0);
}

// Active status & stock column checks
$has_item_is_active = columnExists($conn, 'items', 'is_active');
$has_supp_is_active = columnExists($conn, 'suppliers', 'is_active');
$has_dept_is_active = columnExists($conn, 'departments', 'is_active');
$has_opening_stock  = columnExists($conn, 'items', 'opening_stock');

// Dynamic column checks for GRN & Issue tables
$grn_date_col   = columnExists($conn, 'goods_receipts', 'received_date') ? 'received_date' : 'grn_date';
$issue_date_col = columnExists($conn, 'issue_vouchers', 'issued_date') ? 'issued_date' : 'issue_date';
$issue_num_col  = columnExists($conn, 'issue_vouchers', 'voucher_number') ? 'voucher_number' : (columnExists($conn, 'issue_vouchers', 'issue_number') ? 'issue_number' : 'voucher_no');
$issue_id_col   = columnExists($conn, 'issue_vouchers', 'issue_id') ? 'issue_id' : 'voucher_id';

// WHERE clauses
$item_where = $has_item_is_active ? "WHERE is_active = 1" : "";
$supp_where = $has_supp_is_active ? "WHERE is_active = 1" : "";
$dept_where = $has_dept_is_active ? "WHERE is_active = 1" : "";

// Overview Card Counts
$total_items       = $conn->query("SELECT COUNT(*) AS total FROM items $item_where")->fetch_assoc()['total'] ?? 0;
$total_suppliers   = $conn->query("SELECT COUNT(*) AS total FROM suppliers $supp_where")->fetch_assoc()['total'] ?? 0;
$total_departments = $conn->query("SELECT COUNT(*) AS total FROM departments $dept_where")->fetch_assoc()['total'] ?? 0;

// Low Stock Items Query
$op_sql = $has_opening_stock ? "IFNULL(i.opening_stock, 0)" : "0";
$item_where_low = $has_item_is_active ? "WHERE i.is_active = 1" : "";

$low_stock_query = "
    SELECT i.item_id, i.item_code, i.item_name, i.reorder_level,
           ($op_sql 
            + IFNULL((SELECT SUM(gri.received_qty) FROM goods_receipt_items gri WHERE gri.item_id = i.item_id), 0)
            - IFNULL((SELECT SUM(ivi.issued_qty) FROM issue_voucher_items ivi WHERE ivi.item_id = i.item_id), 0)
           ) AS current_stock
    FROM items i
    $item_where_low
    HAVING current_stock <= i.reorder_level
    ORDER BY current_stock ASC
";

$low_stock_result = $conn->query($low_stock_query);
$low_stock_count  = $low_stock_result ? $low_stock_result->num_rows : 0;

// Recent Stock Transactions
$recent_grn = $conn->query("
    SELECT gr.grn_number AS ref_no, gr.$grn_date_col AS trans_date, 
           IFNULL(s.supplier_name, 'N/A') AS party_name
    FROM goods_receipts gr
    LEFT JOIN suppliers s ON gr.supplier_id = s.supplier_id
    ORDER BY gr.$grn_date_col DESC, gr.grn_id DESC LIMIT 5
");

$recent_issues = $conn->query("
    SELECT iv.$issue_num_col AS ref_no, iv.$issue_date_col AS trans_date, 
           IFNULL(d.department_name, 'N/A') AS party_name
    FROM issue_vouchers iv
    LEFT JOIN departments d ON iv.department_id = d.department_id
    ORDER BY iv.$issue_date_col DESC, iv.$issue_id_col DESC LIMIT 5
");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>OSIMS - Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">

<nav class="navbar navbar-expand-lg navbar-dark bg-dark shadow-sm py-2">
    <div class="container-fluid px-4">
        <a class="navbar-brand fw-bold me-4" href="dashboard.php">🏢 OSIMS Store System</a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav me-auto gap-2">
                <li class="nav-item"><a class="btn btn-primary active fw-bold shadow text-start" href="dashboard.php">📊 Dashboard</a></li>
                <li class="nav-item"><a class="btn btn-outline-light text-start" href="items.php">📦 Manage Items</a></li>
                <li class="nav-item"><a class="btn btn-outline-light text-start" href="suppliers.php">🚚 Suppliers</a></li>
                <li class="nav-item"><a class="btn btn-outline-light text-start" href="sections.php">🏛️ Sections / Branches</a></li>
                <li class="nav-item"><a class="btn btn-outline-light text-start" href="grn.php">📥 Goods Receipt (GRN)</a></li>
                <li class="nav-item"><a class="btn btn-outline-light text-start" href="issue.php">📤 Goods Issue</a></li>
                <li class="nav-item"><a class="btn btn-outline-light text-start" href="reports.php">📑 Reports</a></li>
            </ul>
            <div class="d-flex align-items-center gap-3">
                <span class="badge bg-secondary fs-6 px-3 py-2">👤 <?= $_SESSION['full_name'] ?? 'System Admin'; ?> (<b><?= $_SESSION['role_name'] ?? 'Admin'; ?></b>)</span>
                <a href="logout.php" class="btn btn-danger btn-sm fw-bold px-3 py-2">Logout</a>
            </div>
        </div>
    </div>
</nav>

<div class="container mt-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h3 class="fw-bold mb-0">📊 Store Dashboard Summary</h3>
        <span class="text-muted fw-bold">Date: <?= date('Y-m-d'); ?></span>
    </div>

    <!-- Overview Cards -->
    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="card bg-primary text-white shadow-sm h-100">
                <div class="card-body">
                    <h6 class="text-uppercase fw-bold">Total Items</h6>
                    <h2 class="fw-bold mb-0"><?= number_format($total_items); ?></h2>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-success text-white shadow-sm h-100">
                <div class="card-body">
                    <h6 class="text-uppercase fw-bold">Suppliers</h6>
                    <h2 class="fw-bold mb-0"><?= number_format($total_suppliers); ?></h2>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-info text-dark shadow-sm h-100">
                <div class="card-body">
                    <h6 class="text-uppercase fw-bold">Sections / Branches</h6>
                    <h2 class="fw-bold mb-0"><?= number_format($total_departments); ?></h2>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-danger text-white shadow-sm h-100">
                <div class="card-body">
                    <h6 class="text-uppercase fw-bold">Low Stock Items</h6>
                    <h2 class="fw-bold mb-0"><?= number_format($low_stock_count); ?></h2>
                </div>
            </div>
        </div>
    </div>

    <!-- Low Stock Alert Table -->
    <div class="card shadow-sm mb-4">
        <div class="card-header bg-danger text-white fw-bold d-flex justify-content-between align-items-center">
            <span>⚠️ Low Stock Alerts (Reorder Level Reached)</span>
            <span class="badge bg-white text-danger fw-bold"><?= $low_stock_count; ?> Items Need Reorder</span>
        </div>
        <div class="card-body p-0">
            <table class="table table-bordered table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Item Code</th>
                        <th>Item Description</th>
                        <th class="text-end">Current Stock</th>
                        <th class="text-end">Reorder Level</th>
                        <th class="text-center">Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($low_stock_result && $low_stock_result->num_rows > 0): 
                        while($row = $low_stock_result->fetch_assoc()):
                    ?>
                        <tr>
                            <td><b><?= $row['item_code']; ?></b></td>
                            <td><?= $row['item_name']; ?></td>
                            <td class="text-end fw-bold text-danger"><?= number_format($row['current_stock'], 2); ?></td>
                            <td class="text-end fw-bold"><?= number_format($row['reorder_level'], 2); ?></td>
                            <td class="text-center"><span class="badge bg-danger">Reorder Needed</span></td>
                        </tr>
                    <?php endwhile; else: ?>
                        <tr>
                            <td colspan="5" class="text-center text-success fw-bold py-3">✅ All items are currently well stocked!</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Recent Activity Tables -->
    <div class="row g-3">
        <div class="col-md-6">
            <div class="card shadow-sm">
                <div class="card-header bg-dark text-white fw-bold">📥 Recent Goods Receipts (GRN)</div>
                <div class="card-body p-0">
                    <table class="table table-sm table-hover mb-0">
                        <thead class="table-secondary">
                            <tr>
                                <th>GRN No</th>
                                <th>Date</th>
                                <th>Supplier</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($recent_grn && $recent_grn->num_rows > 0): 
                                while($g = $recent_grn->fetch_assoc()): ?>
                                <tr>
                                    <td><b><?= $g['ref_no']; ?></b></td>
                                    <td><?= $g['trans_date']; ?></td>
                                    <td><?= $g['party_name']; ?></td>
                                </tr>
                            <?php endwhile; else: ?>
                                <tr><td colspan="3" class="text-center text-muted py-2">No recent receipts.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-md-6">
            <div class="card shadow-sm">
                <div class="card-header bg-dark text-white fw-bold">📤 Recent Issues (Stock Out)</div>
                <div class="card-body p-0">
                    <table class="table table-sm table-hover mb-0">
                        <thead class="table-secondary">
                            <tr>
                                <th>Voucher No</th>
                                <th>Date</th>
                                <th>Section / Branch</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($recent_issues && $recent_issues->num_rows > 0): 
                                while($i = $recent_issues->fetch_assoc()): ?>
                                <tr>
                                    <td><b><?= $i['ref_no']; ?></b></td>
                                    <td><?= $i['trans_date']; ?></td>
                                    <td><?= $i['party_name']; ?></td>
                                </tr>
                            <?php endwhile; else: ?>
                                <tr><td colspan="3" class="text-center text-muted py-2">No recent issues.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>