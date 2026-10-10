<?php
session_start();
require_once 'db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

$message = '';

// Auto-generate Section Code (e.g., SEC-001, SEC-002)
$auto_sec_query = "SELECT MAX(department_id) AS max_id FROM departments";
$auto_sec_result = $conn->query($auto_sec_query);
$row = $auto_sec_result->fetch_assoc();
$next_id = ($row['max_id']) ? $row['max_id'] + 1 : 1;
$generated_sec_code = 'SEC-' . str_pad($next_id, 3, '0', STR_PAD_LEFT);

// Toggle Active/Inactive Status (Admin Only)
if (isset($_GET['action']) && $_GET['action'] == 'toggle_status' && isset($_GET['id'])) {
    if (isset($_SESSION['role_name']) && $_SESSION['role_name'] === 'Admin') {
        $sec_id = intval($_GET['id']);
        $current_status = intval($_GET['status']);
        $new_status = ($current_status == 1) ? 0 : 1;

        $stmt_toggle = $conn->prepare("UPDATE departments SET is_active = ? WHERE department_id = ?");
        $stmt_toggle->bind_param("ii", $new_status, $sec_id);
        if ($stmt_toggle->execute()) {
            header("Location: sections.php?msg=updated");
            exit();
        }
    }
}

// Form Submission - Add New Section
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $sec_code = trim($_POST['department_code']);
    $sec_name = trim($_POST['department_name']);

    if (!empty($sec_code) && !empty($sec_name)) {
        $stmt = $conn->prepare("INSERT INTO departments (department_code, department_name, is_active) VALUES (?, ?, 1)");
        $stmt->bind_param("ss", $sec_code, $sec_name);

        if ($stmt->execute()) {
            $message = "<div class='alert alert-success fw-bold'>Section / Branch එක සාර්ථකව ඇතුළත් කරන ලදී!</div>";
            $next_id++;
            $generated_sec_code = 'SEC-' . str_pad($next_id, 3, '0', STR_PAD_LEFT);
        } else {
            $message = "<div class='alert alert-danger fw-bold'>ERROR: Code එක Duplicate විය හැක.</div>";
        }
    }
}

if (isset($_GET['msg']) && $_GET['msg'] == 'updated') {
    $message = "<div class='alert alert-info fw-bold'>Status එක සාර්ථකව යාවත්කාලීන විය!</div>";
}

// Fetch All Sections
$sections_list = $conn->query("SELECT * FROM departments ORDER BY department_id DESC");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>OSIMS - Section Management</title>
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
                <li class="nav-item"><a class="nav-link active" href="sections.php">Sections / Branches</a></li>
                <li class="nav-item"><a class="nav-link" href="grn.php">Goods Receipt (GRN)</a></li>
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

    <!-- Add Section Form -->
    <div class="card shadow-sm mb-4">
        <div class="card-header bg-primary text-white fw-bold">🏛️ Register New Section / Branch</div>
        <div class="card-body">
            <form action="" method="POST" class="row g-3">
                <div class="col-md-3">
                    <label class="form-label fw-semibold">Section Code</label>
                    <input type="text" name="department_code" class="form-control bg-light" value="<?= $generated_sec_code; ?>" readonly required>
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Section / Branch Name</label>
                    <input type="text" name="department_name" class="form-control" required placeholder="e.g. Accounts Section / Kandy Branch">
                </div>
                <div class="col-md-3 d-flex align-items-end">
                    <button type="submit" class="btn btn-success fw-bold w-100">Save Section</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Sections List Table -->
    <div class="card shadow-sm">
        <div class="card-header bg-dark text-white fw-bold">📋 Registered Sections List</div>
        <div class="card-body">
            <table class="table table-striped table-hover align-middle">
                <thead class="table-secondary">
                    <tr>
                        <th>Code</th>
                        <th>Section / Branch Name</th>
                        <th>Status</th>
                        <?php if (isset($_SESSION['role_name']) && $_SESSION['role_name'] === 'Admin'): ?>
                            <th class="text-center">Action</th>
                        <?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($sections_list && $sections_list->num_rows > 0): ?>
                        <?php while($row = $sections_list->fetch_assoc()): ?>
                            <tr class="<?= ($row['is_active'] == 0) ? 'table-secondary text-muted' : ''; ?>">
                                <td><b><?= $row['department_code']; ?></b></td>
                                <td><?= $row['department_name']; ?></td>
                                <td>
                                    <?php if($row['is_active'] == 1): ?>
                                        <span class="badge bg-success">Active</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">Inactive</span>
                                    <?php endif; ?>
                                </td>
                                <?php if (isset($_SESSION['role_name']) && $_SESSION['role_name'] === 'Admin'): ?>
                                    <td class="text-center">
                                        <a href="sections.php?action=toggle_status&id=<?= $row['department_id']; ?>&status=<?= $row['is_active']; ?>" 
                                           class="btn btn-sm <?= ($row['is_active'] == 1) ? 'btn-outline-danger' : 'btn-outline-success'; ?>"
                                           onclick="return confirm('ඔබට මෙම Section එකේ Status වෙනස් කිරීමට අවශ්‍යද?');">
                                           <?= ($row['is_active'] == 1) ? 'Deactivate' : 'Activate'; ?>
                                        </a>
                                    </td>
                                <?php endif; ?>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="4" class="text-center text-muted">තවම කිසිම Section එකක් ඇතුළත් කර නොමැත.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

</body>
</html>