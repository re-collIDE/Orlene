<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
if (session_status() === PHP_SESSION_NONE) session_start();
require_login();

$db = get_db();
$product_id = (int)($_GET['product_id'] ?? $_POST['product_id'] ?? 0);

if (!$product_id) {
    header('Location: ' . BASE_URL . '/pages/products.php');
    exit;
}

$stmt = $db->prepare("SELECT * FROM products WHERE id = ? AND status = 'AVAILABLE'");
$stmt->execute([$product_id]);
$product = $stmt->fetch();

if (!$product) {
    redirect('/pages/products.php', 'Product is no longer available.', 'error');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $start_date = $_POST['start_date'] ?? '';
    $end_date = $_POST['end_date'] ?? '';
    $area = trim($_POST['use_area'] ?? '');
    $city = trim($_POST['use_city'] ?? '');
    $landmark = trim($_POST['use_landmark'] ?? '');
    $lat = $_POST['use_lat'] ? (float)$_POST['use_lat'] : null;
    $lng = $_POST['use_lng'] ? (float)$_POST['use_lng'] : null;

    $errors = [];

    if (!$start_date || !$end_date || !$area || !$city) {
        $errors[] = "Please fill in all required fields.";
    }

    $start = new DateTime($start_date);
    $end = new DateTime($end_date);
    $today = new DateTime('today');

    if ($start < $today) {
        $errors[] = "Start date cannot be in the past.";
    }

    if ($end <= $start) {
        $errors[] = "End date must be after start date.";
    }

    $interval = $start->diff($end);
    $days = $interval->days;

    if ($days > 15) {
        $errors[] = "Maximum rental period is 15 days.";
    }

    if (empty($errors)) {
        try {
            $total_fee = $days * $product['daily_rate'];
            
            $stmt = $db->prepare("INSERT INTO rentals (user_id, product_id, start_date, end_date, days, total_fee, status, use_area, use_city, use_landmark, use_lat, use_lng) VALUES (?, ?, ?, ?, ?, ?, 'PENDING', ?, ?, ?, ?, ?)");
            $stmt->execute([
                $_SESSION['user_id'],
                $product_id,
                $start_date,
                $end_date,
                $days,
                $total_fee,
                $area,
                $city,
                $landmark,
                $lat,
                $lng
            ]);
            
            redirect('/pages/my_rentals.php', 'Booking submitted! Awaiting admin approval.');
        } catch (PDOException $e) {
            $errors[] = "Database error: " . $e->getMessage();
        }
    }

    if (!empty($errors)) {
        $_SESSION['flash'] = ['msg' => implode('<br>', $errors), 'type' => 'error'];
    }
}

require_once __DIR__ . '/../includes/header.php';
?>

<div style="max-width: 800px; margin: 0 auto;">
    <h1 class="mb-20">Book Gear: <?= htmlspecialchars($product['name']) ?></h1>
    
    <div class="grid">
        <div class="grid-col col-4">
            <div class="card mb-20">
                <img src="<?= BASE_URL ?>/<?= htmlspecialchars($product['image_path']) ?>" alt="<?= htmlspecialchars($product['name']) ?>" style="width: 100%; border-radius: var(--radius); margin-bottom: 10px;">
                <h4 style="margin-bottom: 5px;"><?= htmlspecialchars($product['name']) ?></h4>
                <p class="text-primary" style="font-weight: 600;">৳<?= number_format($product['daily_rate'], 0) ?> / day</p>
                <input type="hidden" id="daily_rate" value="<?= $product['daily_rate'] ?>">
            </div>
            
            <div class="card card-accent" style="border-left-color: var(--color-info);">
                <h4>Rental Summary</h4>
                <div class="flex justify-between mt-10">
                    <span>Daily Rate:</span>
                    <span>৳<?= number_format($product['daily_rate'], 0) ?></span>
                </div>
                <div class="flex justify-between mt-10">
                    <span>Days:</span>
                    <span id="days_display">0</span>
                </div>
                <hr style="margin: 15px 0; border: 0; border-top: 1px solid #eee;">
                <div class="flex justify-between" style="font-weight: 600; font-size: 18px;">
                    <span>Total Fee:</span>
                    <span>৳<span id="fee_display">0.00</span></span>
                </div>
            </div>
        </div>
        
        <div class="grid-col col-8">
            <div class="card">
                <form method="POST">
                    <input type="hidden" name="product_id" value="<?= $product['id'] ?>">
                    
                    <h3 class="mb-20" style="border-bottom: 1px solid #eee; padding-bottom: 10px;">Rental Period</h3>
                    <div class="grid">
                        <div class="grid-col col-6">
                            <div class="form-group">
                                <label>Start Date</label>
                                <input type="date" name="start_date" id="start_date" class="form-control" required min="<?= date('Y-m-d') ?>" value="<?= htmlspecialchars($_POST['start_date'] ?? '') ?>">
                            </div>
                        </div>
                        <div class="grid-col col-6">
                            <div class="form-group">
                                <label>End Date</label>
                                <input type="date" name="end_date" id="end_date" class="form-control" required min="<?= date('Y-m-d', strtotime('+1 day')) ?>" value="<?= htmlspecialchars($_POST['end_date'] ?? '') ?>">
                            </div>
                        </div>
                    </div>
                    <p id="date_error" style="color: var(--color-danger); font-size: 13px; margin-top: -10px; margin-bottom: 20px;"></p>

                    <h3 class="mb-20" style="border-bottom: 1px solid #eee; padding-bottom: 10px; margin-top: 20px;">Use Location</h3>
                    <div class="form-group">
                        <label>Area / Neighborhood</label>
                        <input type="text" name="use_area" class="form-control" required placeholder="e.g. Dhanmondi, Banani" value="<?= htmlspecialchars($_POST['use_area'] ?? '') ?>">
                    </div>
                    <div class="grid">
                        <div class="grid-col col-6">
                            <div class="form-group">
                                <label>City / District</label>
                                <input type="text" name="use_city" class="form-control" required placeholder="e.g. Dhaka" value="<?= htmlspecialchars($_POST['use_city'] ?? '') ?>">
                            </div>
                        </div>
                        <div class="grid-col col-6">
                            <div class="form-group">
                                <label>Landmark (Optional)</label>
                                <input type="text" name="use_landmark" class="form-control" placeholder="e.g. Near Lake" value="<?= htmlspecialchars($_POST['use_landmark'] ?? '') ?>">
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label>GPS Coordinates (Optional)</label>
                        <div class="flex gap-10">
                            <input type="text" name="use_lat" id="use_lat" class="form-control" placeholder="Latitude" readonly value="<?= htmlspecialchars($_POST['use_lat'] ?? '') ?>">
                            <input type="text" name="use_lng" id="use_lng" class="form-control" placeholder="Longitude" readonly value="<?= htmlspecialchars($_POST['use_lng'] ?? '') ?>">
                            <button type="button" id="use_location_btn" class="btn btn-outline" style="white-space: nowrap;">Use My Location</button>
                        </div>
                        <small class="text-muted">Improves safety and helps us find you in case of emergency.</small>
                    </div>

                    <div style="margin-top: 30px; padding-top: 20px; border-top: 1px solid #eee;">
                        <button type="submit" class="btn btn-primary" style="width: 100%; padding: 12px; font-size: 16px;">Confirm Booking</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
