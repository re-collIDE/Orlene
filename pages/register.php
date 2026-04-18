<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
if (session_status() === PHP_SESSION_NONE) session_start();

if (isset($_SESSION['user_id'])) {
    header('Location: ' . BASE_URL . '/index.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $phone = trim($_POST['phone'] ?? '');
    $nid = trim($_POST['nid'] ?? '');

    $errors = [];

    if (!$name || !$email || !$password || !$phone || !$nid) {
        $errors[] = "All fields are required.";
    }

    if ($password !== $confirm_password) {
        $errors[] = "Passwords do not match.";
    }

    if (strlen($password) < 6) {
        $errors[] = "Password must be at least 6 characters.";
    }

    $db = get_db();
    $stmt = $db->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->execute([$email]);
    if ($stmt->fetch()) {
        $errors[] = "Email already registered.";
    }

    if (empty($errors)) {
        try {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $db->prepare("INSERT INTO users (name, email, password_hash, phone, nid, role) VALUES (?, ?, ?, ?, ?, 'user')");
            $stmt->execute([$name, $email, $hash, $phone, $nid]);
            redirect('/pages/login.php', 'Registration successful! Please login.');
        } catch (PDOException $e) {
            $errors[] = "Error: " . $e->getMessage();
        }
    }

    if (!empty($errors)) {
        $_SESSION['flash'] = ['msg' => implode('<br>', $errors), 'type' => 'error'];
    }
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="auth-split-wrapper">
    <div class="auth-side-image"></div>
    <div class="auth-content" style="max-width: 600px;">
        <h2 class="mb-20">Create an Account</h2>
        <p class="text-muted mb-30">Join Orlene today and start exploring premium gear for your next adventure.</p>
        
        <form method="POST">
            <div class="grid" style="margin: 0 -5px;">
                <div class="grid-col col-6" style="padding: 0 5px;">
                    <div class="form-group">
                        <label>Full Name</label>
                        <input type="text" name="name" class="form-control" required value="<?= htmlspecialchars($_POST['name'] ?? '') ?>">
                    </div>
                </div>
                <div class="grid-col col-6" style="padding: 0 5px;">
                    <div class="form-group">
                        <label>Email Address</label>
                        <input type="email" name="email" class="form-control" required value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
                    </div>
                </div>
                <div class="grid-col col-6" style="padding: 0 5px;">
                    <div class="form-group">
                        <label>Phone Number</label>
                        <input type="text" name="phone" class="form-control" required value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>">
                    </div>
                </div>
                <div class="grid-col col-6" style="padding: 0 5px;">
                    <div class="form-group">
                        <label>NID / Student ID</label>
                        <input type="text" name="nid" class="form-control" required value="<?= htmlspecialchars($_POST['nid'] ?? '') ?>">
                    </div>
                </div>
                <div class="grid-col col-6" style="padding: 0 5px;">
                    <div class="form-group">
                        <label>Password</label>
                        <input type="password" name="password" class="form-control" required>
                    </div>
                </div>
                <div class="grid-col col-6" style="padding: 0 5px;">
                    <div class="form-group">
                        <label>Confirm Password</label>
                        <input type="password" name="confirm_password" class="form-control" required>
                    </div>
                </div>
            </div>
            <button type="submit" class="btn btn-primary" style="width: 100%; padding: 12px; margin-top: 10px;">Register</button>
        </form>
        
        <p class="text-center mt-20">
            Already have an account? <a href="<?= BASE_URL ?>/pages/login.php">Login here</a>
        </p>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
