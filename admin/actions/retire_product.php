<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/auth.php';
if (session_status() === PHP_SESSION_NONE) session_start();
require_admin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('/admin/products.php');
}

$product_id = (int)($_POST['product_id'] ?? 0);
if (!$product_id) {
    redirect('/admin/products.php', 'Invalid request.', 'error');
}

try {
    $db = get_db();
    $stmt = $db->prepare("UPDATE products SET status = 'RETIRED' WHERE id = ?");
    $stmt->execute([$product_id]);
    
    redirect('/admin/products.php', 'Product has been retired from the catalog.');
} catch (PDOException $e) {
    redirect('/admin/products.php', 'Error: ' . $e->getMessage(), 'error');
}
