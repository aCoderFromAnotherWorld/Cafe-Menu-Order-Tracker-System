<?php
declare(strict_types=1);
require_once __DIR__ . '/../auth.php';
requireAdmin();

global $conn;

$username = $_SESSION['username'] ?? 'admin';
$messages = [];
$errors = [];
$queryResult = [];
$queryColumns = [];
$queryMessage = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add_category') {
        $name = trim((string) ($_POST['name'] ?? ''));
        $description = trim((string) ($_POST['description'] ?? ''));
        if ($name === '') {
            $errors[] = 'Category name is required.';
        } else {
            $stmt = $conn->prepare('INSERT INTO categories (name, description) VALUES (?, ?)');
            if ($stmt) {
                $stmt->bind_param('ss', $name, $description);
                $stmt->execute();
                $stmt->close();
                $messages[] = 'Category added.';
            } else {
                $errors[] = 'Unable to prepare category insert.';
            }
        }
    }

    if ($action === 'add_item') {
        $name = trim((string) ($_POST['item_name'] ?? ''));
        $description = trim((string) ($_POST['item_description'] ?? ''));
        $price = (float) ($_POST['item_price'] ?? 0);
        $categoryId = (int) ($_POST['category_id'] ?? 0);

        if ($name === '' || $price <= 0 || $categoryId <= 0) {
            $errors[] = 'Provide name, positive price, and category.';
        } else {
            $stmt = $conn->prepare('INSERT INTO menu_items (category_id, name, description, price) VALUES (?, ?, ?, ?)');
            if ($stmt) {
                $stmt->bind_param('issd', $categoryId, $name, $description, $price);
                $stmt->execute();
                $stmt->close();
                $messages[] = 'Menu item added.';
            } else {
                $errors[] = 'Unable to prepare menu item insert.';
            }
        }
    }

    if ($action === 'update_item') {
        $itemId = (int) ($_POST['item_id'] ?? 0);
        $price = (float) ($_POST['price'] ?? 0);
        $description = trim((string) ($_POST['description'] ?? ''));

        if ($itemId <= 0) {
            $errors[] = 'Invalid menu item.';
        } else {
            $stmt = $conn->prepare('UPDATE menu_items SET price = ?, description = ? WHERE id = ?');
            if ($stmt) {
                $stmt->bind_param('dsi', $price, $description, $itemId);
                $stmt->execute();
                $stmt->close();
                $messages[] = 'Menu item updated.';
            } else {
                $errors[] = 'Unable to update menu item.';
            }
        }
    }

    if ($action === 'delete_item') {
        $itemId = (int) ($_POST['item_id'] ?? 0);
        if ($itemId > 0) {
            $stmt = $conn->prepare('DELETE FROM menu_items WHERE id = ?');
            if ($stmt) {
                $stmt->bind_param('i', $itemId);
                $stmt->execute();
                $stmt->close();
                $messages[] = 'Menu item deleted.';
            }
        }
    }

    if ($action === 'update_order') {
        $orderId = (int) ($_POST['order_id'] ?? 0);
        $status = trim((string) ($_POST['status'] ?? 'Pending'));
        if ($orderId > 0) {
            $stmt = $conn->prepare('UPDATE orders SET status = ? WHERE id = ?');
            if ($stmt) {
                $stmt->bind_param('si', $status, $orderId);
                $stmt->execute();
                $stmt->close();
                $messages[] = 'Order updated.';
            }
        }
    }

    if ($action === 'run_query') {
        $sql = trim((string) ($_POST['sql'] ?? ''));
        if ($sql === '') {
            $errors[] = 'Provide a SQL statement.';
        } else {
            $firstWord = strtoupper(strtok($sql, " \n\t"));
            $allowed = ['SELECT', 'SHOW', 'DESCRIBE', 'EXPLAIN'];
            if (!in_array($firstWord, $allowed, true)) {
                $errors[] = 'Only read-only queries are permitted.';
            } else {
                $result = $conn->query($sql);
                if ($result instanceof mysqli_result) {
                    $queryColumns = $result->fetch_fields();
                    while ($row = $result->fetch_assoc()) {
                        $queryResult[] = $row;
                    }
                    $result->free();
                    $queryMessage = count($queryResult) . ' row(s) returned.';
                } elseif ($result === true) {
                    $queryMessage = 'Query executed.';
                } else {
                    $errors[] = 'Query failed: ' . $conn->error;
                }
            }
        }
    }
}

$categories = [];
$result = $conn->query('SELECT id, name FROM categories ORDER BY name');
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $categories[] = $row;
    }
}

$menuItems = [];
$result = $conn->query(
    'SELECT m.id, m.name, m.description, m.price, c.name AS category
     FROM menu_items m
     LEFT JOIN categories c ON m.category_id = c.id
     ORDER BY c.name, m.name'
);
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $menuItems[] = $row;
    }
}

$orders = [];
$result = $conn->query(
    'SELECT o.id, o.customer_name, o.total, o.status, o.created_at, u.fullname, u.phone, u.address,
            COALESCE(GROUP_CONCAT(CONCAT(mi.name, " (x", oi.quantity, ")") SEPARATOR ", "), "No items") AS items
     FROM orders o
     LEFT JOIN order_items oi ON o.id = oi.order_id
     LEFT JOIN menu_items mi ON oi.item_id = mi.id
     LEFT JOIN users u ON o.user_id = u.id
     GROUP BY o.id
     ORDER BY o.created_at DESC'
);
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $orders[] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin Dashboard</title>
    <link rel="stylesheet" href="../style.css">
</head>
<body>
    <div class="container">
        <h1>⚙️ Admin Dashboard</h1>
        <p>Signed in as <strong><?= htmlspecialchars($username, ENT_QUOTES); ?></strong></p>
        <div class="nav">
            <a href="#menu">🍽️ Manage Menu</a>
            <a href="#orders">📋 Manage Orders</a>
            <a href="#queries">🔍 SQL Queries</a>
            <a href="logout.php">🚪 Logout</a>
        </div>

    <?php foreach ($messages as $message): ?>
        <p class="message"><?= htmlspecialchars($message, ENT_QUOTES); ?></p>
    <?php endforeach; ?>

    <?php foreach ($errors as $error): ?>
        <p class="error"><?= htmlspecialchars($error, ENT_QUOTES); ?></p>
    <?php endforeach; ?>

        <section id="menu">
            <h2>🍽️ Manage Menu</h2>
            <div class="form-container">
                <div class="card">
                    <form method="post">
                        <h3>Add Category</h3>
                        <input type="hidden" name="action" value="add_category">
                        <div class="form-group">
                            <label for="category_name">Category Name</label>
                            <input type="text" id="category_name" name="name" placeholder="e.g., Beverages" required>
                        </div>
                        <div class="form-group">
                            <label for="category_desc">Description</label>
                            <textarea id="category_desc" name="description" placeholder="Optional description"></textarea>
                        </div>
                        <button type="submit">Add Category</button>
                    </form>
                </div>

                <div class="card">
                    <form method="post">
                        <h3>Add Menu Item</h3>
                        <input type="hidden" name="action" value="add_item">
                        <div class="form-group">
                            <label for="item_name">Item Name</label>
                            <input type="text" id="item_name" name="item_name" placeholder="e.g., Espresso" required>
                        </div>
                        <div class="form-group">
                            <label for="item_desc">Description</label>
                            <textarea id="item_desc" name="item_description" placeholder="Optional description"></textarea>
                        </div>
                        <div class="form-row">
                            <div class="form-group">
                                <label for="item_price">Price ($)</label>
                                <input type="number" id="item_price" name="item_price" step="0.01" min="0" placeholder="5.99" required>
                            </div>
                            <div class="form-group">
                                <label for="category_id">Category</label>
                                <select id="category_id" name="category_id" required>
                                    <option value="">Choose category</option>
                                    <?php foreach ($categories as $category): ?>
                                        <option value="<?= (int) $category['id']; ?>"><?= htmlspecialchars($category['name'], ENT_QUOTES); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <button type="submit">Add Item</button>
                    </form>
                </div>
            </div>

            <h3>Menu Items</h3>
            <?php if (empty($menuItems)): ?>
                <p class="text-center">No menu items configured yet.</p>
            <?php else: ?>
                <div class="card">
                    <table>
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Category</th>
                                <th>Description</th>
                                <th>Price</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($menuItems as $item): ?>
                            <tr>
                                <td><strong><?= htmlspecialchars($item['name'], ENT_QUOTES); ?></strong></td>
                                <td><?= htmlspecialchars($item['category'] ?? 'Uncategorized', ENT_QUOTES); ?></td>
                                <td><?= htmlspecialchars($item['description'] ?? '', ENT_QUOTES); ?></td>
                                <td>$<?= number_format((float) $item['price'], 2); ?></td>
                                <td>
                                    <form method="post" class="form-row mb-20">
                                        <input type="hidden" name="action" value="update_item">
                                        <input type="hidden" name="item_id" value="<?= (int) $item['id']; ?>">
                                        <input type="number" step="0.01" name="price" value="<?= number_format((float) $item['price'], 2, '.', ''); ?>" required style="width: 80px;">
                                        <textarea name="description" placeholder="Description" style="width: 150px; height: 60px;"><?= htmlspecialchars($item['description'] ?? '', ENT_QUOTES); ?></textarea>
                                        <button type="submit">Update</button>
                                    </form>
                                    <form method="post" onsubmit="return confirm('Delete this item?');">
                                        <input type="hidden" name="action" value="delete_item">
                                        <input type="hidden" name="item_id" value="<?= (int) $item['id']; ?>">
                                        <button type="submit">Delete</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </section>

        <section id="orders">
            <h2>📋 Manage Orders</h2>
            <?php if (empty($orders)): ?>
                <p class="text-center">No orders recorded yet.</p>
            <?php else: ?>
                <div class="card">
                    <table>
                        <thead>
                            <tr>
                                <th>Order #</th>
                                <th>Customer</th>
                                <th>Contact</th>
                                <th>Address</th>
                                <th>Date</th>
                                <th>Status</th>
                                <th>Total</th>
                                <th>Items</th>
                                <th>Update Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($orders as $order): ?>
                                <tr>
                                    <td><strong>#<?= (int) $order['id']; ?></strong></td>
                                    <td><strong><?= htmlspecialchars($order['fullname'] ?? $order['customer_name'] ?? 'Guest', ENT_QUOTES); ?></strong></td>
                                    <td><?= htmlspecialchars($order['phone'] ?? '', ENT_QUOTES); ?></td>
                                    <td><?= htmlspecialchars($order['address'] ?? '', ENT_QUOTES); ?></td>
                                    <td><?= htmlspecialchars($order['created_at'], ENT_QUOTES); ?></td>
                                    <td><span class="status-<?= strtolower($order['status']); ?>"><?= htmlspecialchars($order['status'], ENT_QUOTES); ?></span></td>
                                    <td><strong>$<?= number_format((float) $order['total'], 2); ?></strong></td>
                                    <td><?= htmlspecialchars($order['items'], ENT_QUOTES); ?></td>
                                    <td>
                                        <form method="post">
                                            <input type="hidden" name="action" value="update_order">
                                            <input type="hidden" name="order_id" value="<?= (int) $order['id']; ?>">
                                            <select name="status" style="width: 120px;">
                                                <?php foreach (['Pending', 'Preparing', 'Ready', 'Completed'] as $status): ?>
                                                    <option value="<?= $status; ?>" <?= $status === $order['status'] ? 'selected' : ''; ?>><?= $status; ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                            <button type="submit">Save</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </section>

        <section id="queries">
            <h2>🔍 SQL Queries</h2>
            <div class="card">
                <form method="post">
                    <input type="hidden" name="action" value="run_query">
                    <div class="form-group">
                        <label for="sql_query">SQL Query</label>
                        <textarea id="sql_query" name="sql" rows="6" placeholder="SELECT * FROM orders LIMIT 10;"></textarea>
                    </div>
                    <button type="submit">Run Query</button>
                </form>
            </div>
            <?php if ($queryMessage !== ''): ?>
                <p class="message text-center mt-20"><?= htmlspecialchars($queryMessage, ENT_QUOTES); ?></p>
            <?php endif; ?>
            <?php if (!empty($queryResult)): ?>
                <div class="card mt-20">
                    <table>
                        <thead>
                            <tr>
                                <?php foreach ($queryColumns as $col): ?>
                                    <th><?= htmlspecialchars($col->name, ENT_QUOTES); ?></th>
                                <?php endforeach; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($queryResult as $row): ?>
                                <tr>
                                    <?php foreach ($row as $value): ?>
                                        <td><?= htmlspecialchars((string) $value, ENT_QUOTES); ?></td>
                                    <?php endforeach; ?>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </section>
    </div>
</body>
</html>
