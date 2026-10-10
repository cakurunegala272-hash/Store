<?php
session_start();
require_once 'db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

$message = '';

// Auto-generate GRN Number (e.g., GRN-001, GRN-002)
$auto_grn_query = "SELECT MAX(grn_id) AS max_id FROM goods_receipts";
$auto_grn_result = $conn->query($auto_grn_query);
$row = $auto_grn_result->fetch_assoc();
$next_id = ($row['max_id']) ? $row['max_id'] + 1 : 1;
$generated_grn_num = 'GRN-' . str_pad($next_id, 3, '0', STR_PAD_LEFT);

// Handle Form Submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $grn_number = trim($_POST['grn_number']);
    $grn_date = $_POST['grn_date'];
    $supplier_id = intval($_POST['supplier_id']);
    $po_number = trim($_POST['po_number']);
    $item_id = intval($_POST['item_id']);
    $received_qty = floatval($_POST['received_qty']);
    $unit_cost = floatval($_POST['unit_cost']); // Unit Cost Field
    $user_id = $_SESSION['user_id'];

    if (!empty($grn_number) && $received_qty > 0 && $item_id > 0) {
        $conn->begin_transaction();

        try {
            // 1. Insert into goods_receipts
            $stmt1 = $conn->prepare("INSERT INTO goods_receipts (grn_number, grn_date, supplier_id, po_number, received_by) VALUES (?, ?, ?, ?, ?)");
            $stmt1->bind_param("ssisi", $grn_number, $grn_date, $supplier_id, $po_number, $user_id);
            $stmt1->execute();
            $grn_id = $conn->insert_id;

            // 2. Insert into goods_receipt_items
            $stmt2 = $conn->prepare("INSERT INTO goods_receipt_items (grn_id, item_id, received_qty) VALUES (?, ?, ?)");
            $stmt2->bind_param("iid", $grn_id, $item_id, $received_qty);
            $stmt2->execute();

            // 3. Get Current Stock Balance for Item
            $bal_query = $conn->prepare("SELECT balance_qty FROM stock_ledger WHERE item_id = ? ORDER BY ledger_id DESC LIMIT 1");
            $bal_query->bind_param("i", $item_id);
            $bal_query->execute();
            $bal_res = $bal_query->get_result();
            $prev_bal = ($bal_res->num_rows > 0) ? $bal_res->fetch_assoc()['balance_qty'] : 0;
            $new_bal = $prev_bal + $received_qty;

            // 4. Update Stock Ledger (Including Unit Cost)
            $stmt3 = $conn->prepare("INSERT INTO stock_ledger (item_id, transaction_type, reference_number, qty_in, balance_qty, unit_cost, created_by) VALUES (?, 'GRN', ?, ?, ?, ?, ?)");
            $stmt3->bind_param("isdddi", $item_id, $grn_number, $received_qty, $new_bal, $unit_cost, $user_id);
            $stmt3->execute();

            $conn->commit();
            $message = "<div class='alert alert-success fw-bold'>Goods Receipt ($grn_number) සාර්ථකව සටහන් විය! Unit Cost එක සහ Stock එක Update විය.</div>";
            
            // Refresh GRN Number
            $next_id++;
            $generated_grn_num = 'GRN-' . str_pad($next_id, 3, '0', STR_PAD_LEFT);
        } catch (Exception $e) {
            $conn->rollback();
            $message = "<div class='alert alert-danger fw-bold'>ERROR: " . $e->getMessage() . "</div>";
        }
    }
}

// Fetch Active Suppliers & Items
$suppliers = $conn->query("SELECT * FROM suppliers WHERE is_active = 1");
$items = $conn->query("SELECT * FROM items WHERE is_active = 1");

// Fetch GRN History
$grn_list = $conn->query("SELECT g.grn_number, g.grn_date, s.supplier_name, i.item_code, i.item_name, gri.received_qty, sl.unit_cost 
                          FROM goods_receipts g 
                          JOIN suppliers s ON g.supplier_id = s.supplier_id 
                          JOIN goods_receipt_items gri ON g.grn_id = gri.grn_id 
                          JOIN items i ON gri.item_id = i.item_id 
                          LEFT JOIN stock_ledger sl ON sl.reference_number = g.grn_number AND sl.item_id = i.item_id
                          ORDER BY g.grn_id DESC");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>OSIMS - Goods Receipt (GRN)</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">

<nav class="navbar navbar-expand-lg navbar-dark bg-dark shadow-sm">
    <div class="container">
        <a class="navbar-brand fw-bold" href="dashboard.php">🏢 OSIMS Store System</a>
        <div class="collapse navbar-collapse">
            <ul class="navbar-nav me-auto">
                <li class="nav-item"><a class="nav-link" href="dashboard.php">Dashboard</a></li>
                <li class="nav-item"><a class="nav-link" href="items.php">Manage Items</a></li>
                <li class="nav-item"><a class="nav-link" href="suppliers.php">Suppliers</a></li>
                <li class="nav-item"><a class="nav-link active" href="grn.php">Goods Receipt (GRN)</a></li>
                <li class="nav-item"><a class="nav-link" href="issue.php">Goods Issue</a></li>
            </ul>
            <span class="navbar-text text-white me-3">
                👤 <?= $_SESSION['full_name'] ?? 'User'; ?> (<b><?= $_SESSION['role_name'] ?? 'Staff'; ?></b>)
            </span>
            <a href="logout.php" class="btn btn-outline-light btn-sm">Logout</a>
        </div>
    </div>
</nav>

<div class="container mt-4">
    <?= $message; ?>

    <!-- GRN Entry Form -->
    <div class="card shadow-sm mb-4">
        <div class="card-header bg-success text-white fw-bold">📥 Goods Receiving Note (GRN)</div>
        <div class="card-body">
            <form action="" method="POST" class="row g-3">
                <div class="col-md-3">
                    <label class="form-label fw-semibold">GRN Number</label>
                    <input type="text" name="grn_number" class="form-control bg-light" value="<?= $generated_grn_num; ?>" readonly required>
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-semibold">GRN Date</label>
                    <input type="date" name="grn_date" class="form-control" value="<?= date('Y-m-d'); ?>" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-semibold">Supplier</label>
                    <select name="supplier_id" class="form-select" required>
                        <option value="">-- Select Supplier --</option>
                        <?php while($sup = $suppliers->fetch_assoc()): ?>
                            <option value="<?= $sup['supplier_id']; ?>"><?= $sup['supplier_name']; ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-semibold">PO Number (Optional)</label>
                    <input type="text" name="po_number" class="form-control" placeholder="e.g. PO-1002">
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-semibold">Select Item</label>
                    <select name="item_id" class="form-select" required>
                        <option value="">-- Choose Item --</option>
                        <?php while($itm = $items->fetch_assoc()): ?>
                            <option value="<?= $itm['item_id']; ?>"><?= $itm['item_code']; ?> - <?= $itm['item_name']; ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-semibold">Received Quantity</label>
                    <input type="number" step="0.01" name="received_qty" class="form-control" placeholder="0.00" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-semibold">Unit Cost (LKR)</label>
                    <input type="number" step="0.01" name="unit_cost" class="form-control" placeholder="0.00" required>
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <button type="submit" class="btn btn-success fw-bold w-100">Receive Stock</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Recent GRNs Table -->
    <div class="card shadow-sm">
        <div class="card-header bg-dark text-white fw-bold">📋 GRN Receiving History</div>
        <div class="card-body">
            <table class="table table-striped table-hover align-middle">
                <thead class="table-secondary">
                    <tr>
                        <th>GRN No</th>
                        <th>Date</th>
                        <th>Supplier</th>
                        <th>Item Code</th>
                        <th>Item Description</th>
                        <th>Qty Received</th>
                        <th>Unit Cost (LKR)</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($grn_list && $grn_list->num_rows > 0): ?>
                        <?php while($row = $grn_list->fetch_assoc()): ?>
                            <tr>
                                <td><b><?= $row['grn_number']; ?></b></td>
                                <td><?= $row['grn_date']; ?></td>
                                <td><?= $row['supplier_name']; ?></td>
                                <td><?= $row['item_code']; ?></td>
                                <td><?= $row['item_name']; ?></td>
                                <td><span class="badge bg-success">+<?= number_format($row['received_qty'], 2); ?></span></td>
                                <td><b><?= number_format($row['unit_cost'], 2); ?></b></td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="7" class="text-center text-muted">තවම කිසිදු GRN එකක් ඇතුළත් කර නොමැත.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

</body>
</html>