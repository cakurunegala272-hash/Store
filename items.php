<?php
session_start();
require_once 'db.php';

// User Login වී නැතිනම් Login පිටුවට යවන්න
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

$active_page = 'items';
$user_role   = $_SESSION['role'] ?? 'data_entry';
$message     = '';
$error       = '';

// 1. AUTO GENERATE ITEM CODE (NEW ITEM සඳහා පමණි)
$next_item_code = '';
try {
    $stmt_code = $conn->query("SELECT MAX(item_id) FROM items");
    $max_id = $stmt_code->fetchColumn();
    $next_id = ($max_id ? $max_id + 1 : 1);
    $next_item_code = 'ITEM-' . str_pad($next_id, 3, '0', STR_PAD_LEFT);
} catch (PDOException $e) {
    $next_item_code = 'ITEM-001';
}

// 2. DELETE ITEM (Admin හට පමණක් අවසර ඇත)
if (isset($_GET['delete'])) {
    if ($user_role === 'admin') {
        $delete_id = $_GET['delete'];
        try {
            $stmt = $conn->prepare("DELETE FROM items WHERE item_id = ?");
            $stmt->execute([$delete_id]);
            header("Location: items.php?msg=deleted");
            exit();
        } catch (PDOException $e) {
            $error = "භාණ්ඩය ඉවත් කිරීමට නොහැක: " . $e->getMessage();
        }
    } else {
        $error = "ඔබට භාණ්ඩ ඉවත් කිරීමට (Delete) බලතල නොමැත!";
    }
}

// 3. ADD / UPDATE ITEM
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $item_code     = trim($_POST['item_code']);
    $item_name     = trim($_POST['item_name']);
    $category      = trim($_POST['category']);
    $unit          = trim($_POST['unit']);
    $current_stock = (int)$_POST['opening_stock']; // Database column: current_stock
    $reorder_level = (int)$_POST['reorder_level'];
    $item_id       = $_POST['item_id'] ?? '';

    if (!empty($item_code) && !empty($item_name)) {
        try {
            if (!empty($item_id)) {
                // Update Item
                $stmt = $conn->prepare("UPDATE items SET item_code = ?, item_name = ?, category = ?, unit = ?, current_stock = ?, reorder_level = ? WHERE item_id = ?");
                $stmt->execute([$item_code, $item_name, $category, $unit, $current_stock, $reorder_level, $item_id]);
                $message = "භාණ්ඩයේ විස්තර සාර්ථකව යාවත්කාලීන විය!";
            } else {
                // Insert New Item
                $stmt = $conn->prepare("INSERT INTO items (item_code, item_name, category, unit, current_stock, reorder_level) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->execute([$item_code, $item_name, $category, $unit, $current_stock, $reorder_level]);
                $message = "අලුත් භාණ්ඩය සාර්ථකව එකතු කරන ලදී!";
            }
            // Page Refresh to update code
            header("Location: items.php?msg=success");
            exit();
        } catch (PDOException $e) {
            $error = "දෝෂයක් සිදුවිය: " . $e->getMessage();
        }
    } else {
        $error = "කරුණාකර Item Code සහ Item Name ඇතුලත් කරන්න.";
    }
}

// EDIT සඳහා Item එකක් Select කර ඇත්නම්
$edit_item = null;
if (isset($_GET['edit'])) {
    $edit_id = $_GET['edit'];
    $stmt = $conn->prepare("SELECT * FROM items WHERE item_id = ?");
    $stmt->execute([$edit_id]);
    $edit_item = $stmt->fetch(PDO::FETCH_ASSOC);
}

// සියලුම Items ලැයිස්තුව ලබාගැනීම
$items_list = $conn->query("SELECT * FROM items ORDER BY item_id DESC")->fetchAll(PDO::FETCH_ASSOC);

// predefined Dropdown Lists
$categories_list = ['Stationery', 'Cleaning & Washroom', 'Electronics & IT', 'Furniture', 'Printing & Paper', 'General Stores'];
$units_list      = ['Nos', 'Pack', 'Box', 'Ream', 'Bottle', 'Roll', 'Set', 'Kg', 'Meters'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>OSIMS - Manage Items</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">

<?php include 'navbar.php'; ?>

<div class="container-fluid px-4 mt-4">
    <div class="row">
        <!-- Add / Edit Form -->
        <div class="col-md-4 mb-4">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-primary text-white fw-bold">
                    <?= $edit_item ? '✏️ Edit Item' : '➕ Add New Item'; ?>
                </div>
                <div class="card-body">
                    <?php if (isset($_GET['msg']) && $_GET['msg'] == 'success'): ?>
                        <div class="alert alert-success alert-dismissible fade show">දත්ත සාර්ථකව සුරකින ලදී!<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
                    <?php endif; ?>
                    <?php if ($error): ?>
                        <div class="alert alert-danger alert-dismissible fade show"><?= $error; ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
                    <?php endif; ?>

                    <form method="POST" action="items.php">
                        <input type="hidden" name="item_id" value="<?= $edit_item['item_id'] ?? ''; ?>">

                        <div class="mb-3">
                            <label class="form-label fw-bold">Item Code (කේතය - Auto Generated)</label>
                            <input type="text" name="item_code" class="form-control bg-light" value="<?= htmlspecialchars($edit_item['item_code'] ?? $next_item_code); ?>" readonly required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold">Item Name (නම)</label>
                            <input type="text" name="item_name" class="form-control" placeholder="A4 Paper, Blue Pen..." value="<?= htmlspecialchars($edit_item['item_name'] ?? ''); ?>" required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold">Category (වර්ගය)</label>
                            <select name="category" class="form-select" required>
                                <option value="">-- Select Category --</option>
                                <?php foreach ($categories_list as $cat): ?>
                                    <option value="<?= $cat; ?>" <?= (isset($edit_item['category']) && $edit_item['category'] == $cat) ? 'selected' : ''; ?>>
                                        <?= $cat; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold">Unit (ඒකකය)</label>
                            <select name="unit" class="form-select" required>
                                <option value="">-- Select Unit --</option>
                                <?php foreach ($units_list as $u): ?>
                                    <option value="<?= $u; ?>" <?= (isset($edit_item['unit']) && $edit_item['unit'] == $u) ? 'selected' : ($u == 'Nos' && !$edit_item ? 'selected' : ''); ?>>
                                        <?= $u; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold">Opening Stock (ආරම්භක තොගය)</label>
                            <input type="number" name="opening_stock" class="form-control" value="<?= $edit_item['current_stock'] ?? 0; ?>" min="0" required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold">Reorder Level (අවම සීමාව)</label>
                            <input type="number" name="reorder_level" class="form-control" value="<?= $edit_item['reorder_level'] ?? 10; ?>" min="0" required>
                        </div>

                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-primary fw-bold"><?= $edit_item ? 'Update Item' : 'Save Item'; ?></button>
                            <?php if ($edit_item): ?>
                                <a href="items.php" class="btn btn-secondary">Cancel Edit</a>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Items Table -->
        <div class="col-md-8 mb-4">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-dark text-white fw-bold">
                    📦 Items List (භාණ්ඩ ලැයිස්තුව)
                </div>
                <div class="card-body p-0">
                    <?php if (isset($_GET['msg']) && $_GET['msg'] == 'deleted'): ?>
                        <div class="alert alert-warning m-3">භාණ්ඩය ඉවත් කරන ලදී!</div>
                    <?php endif; ?>

                    <div class="table-responsive">
                        <table class="table table-striped table-hover mb-0">
                            <thead class="table-dark">
                                <tr>
                                    <th>Code</th>
                                    <th>Name</th>
                                    <th>Category</th>
                                    <th>Opening Stock</th>
                                    <th>Unit</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($items_list)): ?>
                                    <?php foreach ($items_list as $row): ?>
                                        <tr>
                                            <td><code><?= htmlspecialchars($row['item_code']); ?></code></td>
                                            <td><?= htmlspecialchars($row['item_name']); ?></td>
                                            <td><?= htmlspecialchars($row['category']); ?></td>
                                            <td>
                                                <span class="badge <?= $row['current_stock'] <= $row['reorder_level'] ? 'bg-danger' : 'bg-success'; ?> fs-6">
                                                    <?= $row['current_stock']; ?>
                                                </span>
                                            </td>
                                            <td><?= htmlspecialchars($row['unit']); ?></td>
                                            <td>
                                                <a href="items.php?edit=<?= $row['item_id']; ?>" class="btn btn-sm btn-warning">Edit</a>
                                                
                                                <!-- Delete Button එක Admin ට පමණක් පෙනේ -->
                                                <?php if ($user_role === 'admin'): ?>
                                                    <a href="items.php?delete=<?= $row['item_id']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('මෙම භාණ්ඩය ඉවත් කිරීමට තහවුරු කරන්න?');">Delete</a>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="6" class="text-center text-muted py-3">දැනට භාණ්ඩ කිසිවක් ඇතුලත් කර නොමැත.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.bundle.min.js"></script>
</body>
</html>