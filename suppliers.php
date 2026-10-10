<?php
session_start();
require_once 'db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

$message = '';

// Auto-generate Supplier Code (e.g., SUP-001, SUP-002)
$auto_sup_query = "SELECT MAX(supplier_id) AS max_id FROM suppliers";
$auto_sup_result = $conn->query($auto_sup_query);
$row = $auto_sup_result->fetch_assoc();
$next_id = ($row['max_id']) ? $row['max_id'] + 1 : 1;
$generated_sup_code = 'SUP-' . str_pad($next_id, 3, '0', STR_PAD_LEFT);

// Handle Delete/Status Toggle Action (Admin Only)
if (isset($_GET['action']) && $_GET['action'] == 'toggle_status' && isset($_GET['id'])) {
    if ($_SESSION['role_name'] === 'Admin') {
        $sup_id = intval($_GET['id']);
        $current_status = intval($_GET['status']);
        $new_status = ($current_status == 1) ? 0 : 1;

        $stmt_toggle = $conn->prepare("UPDATE suppliers SET is_active = ? WHERE supplier_id = ?");
        $stmt_toggle->bind_param("ii", $new_status, $sup_id);
        if ($stmt_toggle->execute()) {
            header("Location: suppliers.php?msg=updated");
            exit();
        }
    }
}

// Form Submission - Add New Supplier
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $supplier_code = trim($_POST['supplier_code']);
    $supplier_name = trim($_POST['supplier_name']);
    $contact_person = trim($_POST['contact_person']);
    $telephone = trim($_POST['telephone']);
    $email = trim($_POST['email']);

    if (!empty($supplier_code) && !empty($supplier_name)) {
        $stmt = $conn->prepare("INSERT INTO suppliers (supplier_code, supplier_name, contact_person, telephone, email, is_active) VALUES (?, ?, ?, ?, ?, 1)");
        $stmt->bind_param("sssss", $supplier_code, $supplier_name, $contact_person, $telephone, $email);

        if ($stmt->execute()) {
            $message = "<div class='alert alert-success fw-bold'>සැපයුම්කරු සාර්ථකව ඇතුළත් කරන ලදී!</div>";
            // Refresh auto-generated code
            $next_id++;
            $generated_sup_code = 'SUP-' . str_pad($next_id, 3, '0', STR_PAD_LEFT);
        } else {
            $message = "<div class='alert alert-danger fw-bold'>ERROR: ඇතුළත් කිරීම අසාර්ථක විය. (Code එක Duplicate විය හැක)</div>";
        }
    }
}

if (isset($_GET['msg']) && $_GET['msg'] == 'updated') {
    $message = "<div class='alert alert-info fw-bold'>Supplier Status එක සාර්ථකව යාවත්කාලීන විය!</div>";
}

// Fetch All Suppliers
$suppliers_list = $conn->query("SELECT * FROM suppliers ORDER BY supplier_id DESC");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>OSIMS - Suppliers Management</title>
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
                <li class="nav-item"><a class="nav-link active" href="suppliers.php">Suppliers</a></li>
                <li class="nav-item"><a class="nav-link" href="grn.php">Goods Receipt (GRN)</a></li>
                <li class="nav-item"><a class="nav-link" href="issue.php">Goods Issue</a></li>
            </ul>
        </div>
    </div>
</nav>

<div class="container mt-4">
    <?= $message; ?>

    <!-- Add Supplier Form -->
    <div class="card shadow-sm mb-4">
        <div class="card-header bg-primary text-white fw-bold">🚚 Register New Supplier</div>
        <div class="card-body">
            <form action="" method="POST" class="row g-3">
                <div class="col-md-2">
                    <label class="form-label fw-semibold">Supplier Code</label>
                    <input type="text" name="supplier_code" class="form-control bg-light" value="<?= $generated_sup_code; ?>" readonly required>
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-semibold">Supplier Name / Company</label>
                    <input type="text" name="supplier_name" class="form-control" required placeholder="e.g. Abans PLC">
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-semibold">Contact Person</label>
                    <input type="text" name="contact_person" class="form-control" placeholder="e.g. Kamal Perera">
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-semibold">Telephone</label>
                    <input type="text" name="telephone" class="form-control" placeholder="0112345678">
                </div>
                <div class="col-md-8">
                    <label class="form-label fw-semibold">Email Address</label>
                    <input type="email" name="email" class="form-control" placeholder="supplier@company.com">
                </div>
                <div class="col-md-4 d-flex align-items-end">
                    <button type="submit" class="btn btn-success fw-bold w-100">Save Supplier</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Suppliers List Table -->
    <div class="card shadow-sm">
        <div class="card-header bg-dark text-white fw-bold">📋 Registered Suppliers List</div>
        <div class="card-body">
            <table class="table table-striped table-hover align-middle">
                <thead class="table-secondary">
                    <tr>
                        <th>Code</th>
                        <th>Supplier Name</th>
                        <th>Contact Person</th>
                        <th>Telephone</th>
                        <th>Email</th>
                        <th>Status</th>
                        <?php if (isset($_SESSION['role_name']) && $_SESSION['role_name'] === 'Admin'): ?>
                            <th class="text-center">Action</th>
                        <?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($suppliers_list->num_rows > 0): ?>
                        <?php while($row = $suppliers_list->fetch_assoc()): ?>
                            <tr class="<?= ($row['is_active'] == 0) ? 'table-secondary text-muted' : ''; ?>">
                                <td><b><?= $row['supplier_code']; ?></b></td>
                                <td><?= $row['supplier_name']; ?></td>
                                <td><?= $row['contact_person'] ? $row['contact_person'] : '-'; ?></td>
                                <td><?= $row['telephone'] ? $row['telephone'] : '-'; ?></td>
                                <td><?= $row['email'] ? $row['email'] : '-'; ?></td>
                                <td>
                                    <?php if($row['is_active'] == 1): ?>
                                        <span class="badge bg-success">Active</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">Inactive</span>
                                    <?php endif; ?>
                                </td>
                                <?php if (isset($_SESSION['role_name']) && $_SESSION['role_name'] === 'Admin'): ?>
                                    <td class="text-center">
                                        <a href="suppliers.php?action=toggle_status&id=<?= $row['supplier_id']; ?>&status=<?= $row['is_active']; ?>" 
                                           class="btn btn-sm <?= ($row['is_active'] == 1) ? 'btn-outline-danger' : 'btn-outline-success'; ?>"
                                           onclick="return confirm('ඔබට මෙම Supplier ගේ Status වෙනස් කිරීමට අවශ්‍යද?');">
                                           <?= ($row['is_active'] == 1) ? 'Deactivate' : 'Activate'; ?>
                                        </a>
                                    </td>
                                <?php endif; ?>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="7" class="text-center text-muted">තවම කිසිම සැපයුම්කරුවෙකු ඇතුළත් කර නොමැත.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

</body>
</html>