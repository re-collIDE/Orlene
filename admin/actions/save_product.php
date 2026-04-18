<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/auth.php';
if (session_status() === PHP_SESSION_NONE) session_start();
require_admin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('/admin/products.php');
}

$db = get_db();
$id = (int)($_POST['id'] ?? 0);
$name = trim($_POST['name'] ?? '');
$subcategory_id = (int)($_POST['subcategory_id'] ?? 0);
$description = trim($_POST['description'] ?? '');
$daily_rate = (float)($_POST['daily_rate'] ?? 0);
$stock_qty = (int)($_POST['stock_qty'] ?? 1);
$threshold = (int)($_POST['maintenance_threshold'] ?? 5);

$errors = [];

if (!$name || !$subcategory_id || !$daily_rate) {
    $errors[] = "Please fill in all required fields.";
}

// Handle Image Upload
$image_path = $_POST['current_image'] ?? 'assets/img/products/default.jpg';

if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
    $file = $_FILES['image'];
    $allowed = ['image/jpeg', 'image/jpg', 'image/png', 'image/webp'];
    
    if (!in_array($file['type'], $allowed)) {
        $errors[] = "Invalid image type. Only JPG, PNG, WebP allowed.";
    } elseif ($file['size'] > 2 * 1024 * 1024) {
        $errors[] = "Image size exceeds 2MB.";
    } else {
        $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
        $filename = uniqid('prod_') . '.' . $ext;
        $target = __DIR__ . '/../../assets/img/products/' . $filename;
        
        if (move_uploaded_file($file['tmp_name'], $target)) {
            $image_path = 'assets/img/products/' . $filename;
        } else {
            $errors[] = "Failed to upload image.";
        }
    }
}

if (empty($errors)) {
    try {
        if ($id) {
            // Update
            $sql = "UPDATE products SET name = ?, subcategory_id = ?, description = ?, daily_rate = ?, stock_qty = ?, maintenance_threshold = ?";
            $params = [$name, $subcategory_id, $description, $daily_rate, $stock_qty, $threshold];
            
            if (isset($filename)) {
                $sql .= ", image_path = ?";
                $params[] = $image_path;
            }
            
            $sql .= " WHERE id = ?";
            $params[] = $id;
            
            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            redirect('/admin/products.php', 'Product updated successfully.');
        } else {
            // Insert
            $stmt = $db->prepare("INSERT INTO products (name, subcategory_id, description, daily_rate, stock_qty, maintenance_threshold, image_path, status) VALUES (?, ?, ?, ?, ?, ?, ?, 'AVAILABLE')");
            $stmt->execute([$name, $subcategory_id, $description, $daily_rate, $stock_qty, $threshold, $image_path]);
            redirect('/admin/products.php', 'Product created successfully.');
        }
    } catch (PDOException $e) {
        $errors[] = "Database error: " . $e->getMessage();
    }
}

if (!empty($errors)) {
    $_SESSION['flash'] = ['msg' => implode('<br>', $errors), 'type' => 'error'];
    header('Location: ' . $_SERVER['HTTP_REFERER']);
    exit;
}
