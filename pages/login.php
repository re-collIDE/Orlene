<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
if (session_status() === PHP_SESSION_NONE) session_start();

if (isset($_SESSION['user_id'])) {
    if ($_SESSION['user_role'] === 'admin') {
        header('Location: ' . BASE_URL . '/admin/dashboard.php');
    } else {
        header('Location: ' . BASE_URL . '/pages/my_rentals.php');
    }
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($email && $password) {
        $db = get_db();
        $stmt = $db->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password_hash'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_name'] = $user['name'];
            $_SESSION['user_role'] = $user['role'];

            if ($user['role'] === 'admin') {
                redirect('/admin/dashboard.php', "Welcome back, Admin!");
            } else {
                redirect('/pages/my_rentals.php', "Welcome back, {$user['name']}!");
            }
        } else {
            $_SESSION['flash'] = ['msg' => 'Invalid email or password.', 'type' => 'error'];
        }
    } else {
        $_SESSION['flash'] = ['msg' => 'Please fill in all fields.', 'type' => 'error'];
    }
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="auth-split-wrapper">
    <div class="auth-side-image"></div>
    <div class="auth-content">
        <h2 class="mb-20">Login to Orlene</h2>
        <p class="text-muted mb-30">Welcome back! Please enter your details to access your account.</p>
        
        <form method="POST">
            <div class="form-group">
                <label>Email Address</label>
                <input type="email" name="email" class="form-control" required value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label>Password</label>
                <input type="password" name="password" class="form-control" required>
            </div>
            <button type="submit" class="btn btn-primary" style="width: 100%; padding: 12px;">Login</button>
        </form>
        
        <p class="text-center mt-20">
            Don't have an account? <a href="<?= BASE_URL ?>/pages/register.php">Register here</a>
        </p>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
