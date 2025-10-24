<?php
declare(strict_types=1);
require_once __DIR__ . '/../auth.php';
requireCustomer();

global $conn;

$userId = $_SESSION['user_id'];
$username = $_SESSION['username'] ?? 'Guest';
$userFullname = $_SESSION['fullname'] ?? $username;

if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

$messages = [];
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add_to_cart') {
        $itemId = (int) ($_POST['item_id'] ?? 0);
        $quantity = max(1, (int) ($_POST['quantity'] ?? 1));

        $stmt = $conn->prepare('SELECT id, name FROM menu_items WHERE id = ?');
        if ($stmt) {
            $stmt->bind_param('i', $itemId);
            $stmt->execute();
            $result = $stmt->get_result();
            $item = $result->fetch_assoc();
            $stmt->close();

            if ($item) {
                $_SESSION['cart'][$itemId] = ($_SESSION['cart'][$itemId] ?? 0) + $quantity;
                $messages[] = $item['name'] . ' added to cart.';
            } else {
                $errors[] = 'Selected item not found.';
            }
        }
    }

    if ($action === 'update_cart') {
        $itemId = (int) ($_POST['item_id'] ?? 0);
        $quantity = max(0, (int) ($_POST['quantity'] ?? 0));

        if (isset($_SESSION['cart'][$itemId])) {
            if ($quantity === 0) {
                unset($_SESSION['cart'][$itemId]);
                $messages[] = 'Item removed from cart.';
            } else {
                $_SESSION['cart'][$itemId] = $quantity;
                $messages[] = 'Cart updated.';
            }
        }
    }

    if ($action === 'clear_cart') {
        $_SESSION['cart'] = [];
        $messages[] = 'Cart cleared.';
    }

    if ($action === 'place_order') {
        if (empty($_SESSION['cart'])) {
            $errors[] = 'Your cart is empty.';
        } else {
            $itemIds = array_keys($_SESSION['cart']);
            $itemIdsInt = array_map('intval', $itemIds);
            $idList = implode(',', $itemIdsInt);

            $itemsQuery = "SELECT id, name, price FROM menu_items WHERE id IN ($idList)";
            $itemsResult = $conn->query($itemsQuery);

            if ($itemsResult && $itemsResult->num_rows > 0) {
                $items = [];
                $total = 0;

                while ($row = $itemsResult->fetch_assoc()) {
                    $qty = $_SESSION['cart'][$row['id']];
                    $subtotal = $qty * (float) $row['price'];
                    $items[] = [
                        'id' => (int) $row['id'],
                        'name' => $row['name'],
                        'price' => (float) $row['price'],
                        'quantity' => $qty,
                        'subtotal' => $subtotal,
                    ];
                    $total += $subtotal;
                }

                $conn->begin_transaction();

                try {
                    $customerName = $username;
                    $status = 'Pending';
                    $stmt = $conn->prepare('INSERT INTO orders (user_id, customer_name, total, status) VALUES (?, ?, ?, ?)');
                    $stmt->bind_param('isds', $userId, $customerName, $total, $status);
                    $stmt->execute();
                    $orderId = $conn->insert_id;
                    $stmt->close();

                    $stmtItem = $conn->prepare(
                        'INSERT INTO order_items (order_id, item_id, quantity, subtotal) VALUES (?, ?, ?, ?)'
                    );

                    foreach ($items as $item) {
                        $stmtItem->bind_param('iiid', $orderId, $item['id'], $item['quantity'], $item['subtotal']);
                        $stmtItem->execute();
                    }

                    $stmtItem->close();

                    $conn->commit();
                    $_SESSION['cart'] = [];
                    $messages[] = 'Order placed successfully!';
                } catch (Throwable $e) {
                    $conn->rollback();
                    $errors[] = 'Could not place order: ' . $e->getMessage();
                }
            } else {
                $errors[] = 'Unable to load items from cart.';
            }
        }
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

$cartItems = [];
$cartTotal = 0.0;
if (!empty($_SESSION['cart'])) {
    $itemIds = array_keys($_SESSION['cart']);
    $idList = implode(',', array_map('intval', $itemIds));
    $query = "SELECT id, name, price FROM menu_items WHERE id IN ($idList)";
    $cartResult = $conn->query($query);
    if ($cartResult) {
        while ($row = $cartResult->fetch_assoc()) {
            $qty = $_SESSION['cart'][$row['id']];
            $subtotal = $qty * (float) $row['price'];
            $cartTotal += $subtotal;
            $cartItems[] = [
                'id' => (int) $row['id'],
                'name' => $row['name'],
                'price' => (float) $row['price'],
                'quantity' => $qty,
                'subtotal' => $subtotal,
            ];
        }
    }
}

$orderHistory = [];
$stmt = $conn->prepare(
    'SELECT o.id, o.total, o.status, o.created_at,
            COALESCE(GROUP_CONCAT(CONCAT(mi.name, " (x", oi.quantity, ")") SEPARATOR ", "), "No items") AS items
     FROM orders o
     LEFT JOIN order_items oi ON o.id = oi.order_id
     LEFT JOIN menu_items mi ON oi.item_id = mi.id
     WHERE o.user_id = ?
     GROUP BY o.id
     ORDER BY o.created_at DESC'
);

if ($stmt) {
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $orderHistory[] = $row;
    }
    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>User Dashboard</title>
    <link rel="stylesheet" href="../style.css">
</head>
<body>
    <div class="container">
        <h1>👋 Welcome, <?= htmlspecialchars($userFullname, ENT_QUOTES); ?>!</h1>
        <div class="nav">
            <a href="#menu">🍽️ Menu</a>
            <a href="#cart">🛒 Cart</a>
            <a href="#orders">📋 My Orders</a>
            <a href="logout.php">🚪 Logout</a>
        </div>

    <?php foreach ($messages as $message): ?>
        <p class="message"><?= htmlspecialchars($message, ENT_QUOTES); ?></p>
    <?php endforeach; ?>

    <?php foreach ($errors as $error): ?>
        <p class="error"><?= htmlspecialchars($error, ENT_QUOTES); ?></p>
    <?php endforeach; ?>

        <section id="menu">
            <h2>🍽️ Menu</h2>
            <?php if (empty($menuItems)): ?>
                <p class="text-center">No menu items available at the moment.</p>
            <?php else: ?>
                <div class="card">
                    <table>
                        <thead>
                            <tr>
                                <th>Item</th>
                                <th>Category</th>
                                <th>Description</th>
                                <th>Price</th>
                                <th>Add to Cart</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($menuItems as $item): ?>
                                <tr>
                                    <td><strong><?= htmlspecialchars($item['name'], ENT_QUOTES); ?></strong></td>
                                    <td><?= htmlspecialchars($item['category'] ?? 'Uncategorized', ENT_QUOTES); ?></td>
                                    <td><?= htmlspecialchars($item['description'] ?? '', ENT_QUOTES); ?></td>
                                    <td><strong>$<?= number_format((float) $item['price'], 2); ?></strong></td>
                                    <td>
                                        <form method="post" class="form-row">
                                            <input type="hidden" name="action" value="add_to_cart">
                                            <input type="hidden" name="item_id" value="<?= (int) $item['id']; ?>">
                                            <input type="number" name="quantity" value="1" min="1" max="10" style="width: 60px;">
                                            <button type="submit">Add</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </section>

        <section id="cart">
            <h2>🛒 Shopping Cart</h2>
            <?php if (empty($cartItems)): ?>
                <p class="text-center">Your cart is empty. Add some delicious items from the menu!</p>
            <?php else: ?>
                <div class="card">
                    <table>
                        <thead>
                            <tr>
                                <th>Item</th>
                                <th>Price</th>
                                <th>Quantity</th>
                                <th>Subtotal</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($cartItems as $item): ?>
                                <tr>
                                    <td><strong><?= htmlspecialchars($item['name'], ENT_QUOTES); ?></strong></td>
                                    <td>$<?= number_format($item['price'], 2); ?></td>
                                    <td>
                                        <form method="post" class="form-row">
                                            <input type="hidden" name="action" value="update_cart">
                                            <input type="hidden" name="item_id" value="<?= (int) $item['id']; ?>">
                                            <input type="number" name="quantity" value="<?= (int) $item['quantity']; ?>" min="0" style="width: 60px;">
                                            <button type="submit">Update</button>
                                        </form>
                                    </td>
                                    <td>$<?= number_format($item['subtotal'], 2); ?></td>
                                    <td>
                                        <form method="post">
                                            <input type="hidden" name="action" value="update_cart">
                                            <input type="hidden" name="item_id" value="<?= (int) $item['id']; ?>">
                                            <input type="hidden" name="quantity" value="0">
                                            <button type="submit">Remove</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot>
                            <tr>
                                <th colspan="3">Total</th>
                                <th>$<?= number_format($cartTotal, 2); ?></th>
                                <th></th>
                            </tr>
                        </tfoot>
                    </table>
                    <div class="form-row mt-20">
                        <form method="post" style="margin-right: 15px;">
                            <input type="hidden" name="action" value="place_order">
                            <button type="submit">Place Order</button>
                        </form>
                        <form method="post">
                            <input type="hidden" name="action" value="clear_cart">
                            <button type="submit">Clear Cart</button>
                        </form>
                    </div>
                </div>
            <?php endif; ?>
        </section>

        <section id="orders">
            <h2>📋 My Orders</h2>
            <?php if (empty($orderHistory)): ?>
                <p class="text-center">No orders yet. Start by adding items to your cart!</p>
            <?php else: ?>
                <div class="card">
                    <table>
                        <thead>
                            <tr>
                                <th>Order #</th>
                                <th>Date</th>
                                <th>Status</th>
                                <th>Total</th>
                                <th>Items</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($orderHistory as $order): ?>
                                <tr>
                                    <td><strong>#<?= (int) $order['id']; ?></strong></td>
                                    <td><?= htmlspecialchars($order['created_at'], ENT_QUOTES); ?></td>
                                    <td><span class="status-<?= strtolower($order['status']); ?>"><?= htmlspecialchars($order['status'], ENT_QUOTES); ?></span></td>
                                    <td><strong>$<?= number_format((float) $order['total'], 2); ?></strong></td>
                                    <td><?= htmlspecialchars($order['items'], ENT_QUOTES); ?></td>
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
