<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
if (session_status() === PHP_SESSION_NONE) session_start();
require_admin();

$id = (int)($_GET['id'] ?? 0);
if (!$id) {
    header('Location: ' . BASE_URL . '/admin/rentals.php');
    exit;
}

$db = get_db();
$stmt = $db->prepare("
    SELECT r.*, u.name as user_name, u.email as user_email, u.phone as user_phone, u.nid as user_nid,
           p.name as product_name, c.name as category_name, p.image_path, p.daily_rate,
           (SELECT result FROM inspections WHERE rental_id = r.id LIMIT 1) as inspection_result,
           (SELECT notes FROM inspections WHERE rental_id = r.id LIMIT 1) as inspection_notes,
           (SELECT name FROM users WHERE id = (SELECT admin_id FROM inspections WHERE rental_id = r.id LIMIT 1)) as inspector_name
    FROM rentals r
    JOIN users u ON r.user_id = u.id
    JOIN products p ON r.product_id = p.id
    JOIN subcategories s ON p.subcategory_id = s.id
    JOIN categories c ON s.category_id = c.id
    WHERE r.id = ?
");
$stmt->execute([$id]);
$rental = $stmt->fetch();

if (!$rental) {
    redirect('/admin/rentals.php', 'Rental not found.', 'error');
}

// Fetch SOS alerts for this rental
$stmt = $db->prepare("SELECT * FROM sos_alerts WHERE rental_id = ? ORDER BY created_at DESC");
$stmt->execute([$id]);
$sos_alerts = $stmt->fetchAll();

require_once __DIR__ . '/../includes/admin_header.php';
?>

<div class="flex justify-between align-center mb-20">
    <h2>Rental Details #<?= $rental['id'] ?></h2>
    <div class="flex gap-10">
        <?php if ($rental['status'] === 'PENDING'): ?>
            <form method="POST" action="<?= BASE_URL ?>/admin/actions/approve_rental.php">
                <input type="hidden" name="rental_id" value="<?= $rental['id'] ?>">
                <button type="submit" class="btn btn-primary">Approve Request</button>
            </form>
            <button onclick="document.getElementById('reject_modal').style.display='flex'" class="btn btn-danger">Reject</button>
        <?php endif; ?>
    </div>
</div>

<div class="grid">
    <div class="grid-col col-8 flex-col">
        <div class="grid">
            <div class="grid-col col-6 flex-col">
                <div class="card">
                    <h3 class="mb-15" style="border-bottom: 1px solid #eee; padding-bottom: 8px;">Customer Information</h3>
                    <p class="mb-10"><strong>Name:</strong> <?= htmlspecialchars($rental['user_name']) ?></p>
                    <p class="mb-10"><strong>Email:</strong> <?= htmlspecialchars($rental['user_email']) ?></p>
                    <p class="mb-10"><strong>Phone:</strong> <?= htmlspecialchars($rental['user_phone']) ?></p>
                    <p class="mb-10"><strong>NID/ID:</strong> <?= htmlspecialchars($rental['user_nid']) ?></p>
                </div>
            </div>
            <div class="grid-col col-6 flex-col">
                <div class="card">
                    <h3 class="mb-15" style="border-bottom: 1px solid #eee; padding-bottom: 8px;">Rental Status</h3>
                    <div class="mb-15">
                        <span class="badge badge-<?= strtolower($rental['status']) ?>" style="font-size: 14px; padding: 5px 15px;">
                            <?= str_replace('_', ' ', $rental['status']) ?>
                        </span>
                    </div>
                    <p class="mb-10"><strong>Booked On:</strong> <?= date('M d, Y H:i', strtotime($rental['created_at'])) ?></p>
                    <?php if ($rental['pickup_at']): ?>
                        <p class="mb-10"><strong>Picked Up:</strong> <?= date('M d, Y H:i', strtotime($rental['pickup_at'])) ?></p>
                    <?php endif; ?>
                    <?php if ($rental['returned_at']): ?>
                        <p class="mb-10"><strong>Returned:</strong> <?= date('M d, Y H:i', strtotime($rental['returned_at'])) ?></p>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="card mt-20" id="map-section">
            <div class="flex justify-between align-center mb-15" style="border-bottom: 1px solid #eee; padding-bottom: 8px;">
                <h3 style="margin: 0;">Use Location & Map</h3>
                <button id="find_police_btn" class="btn btn-outline btn-sm">Find Nearest Police Station</button>
            </div>
            <p class="mb-10"><strong>Address:</strong> 
                <?php if (!empty($rental['use_area']) || !empty($rental['use_city'])): ?>
                    <?= htmlspecialchars($rental['use_landmark'] ? $rental['use_landmark'] . ', ' : '') ?><?= htmlspecialchars($rental['use_area'] ?? '') ?>, <?= htmlspecialchars($rental['use_city'] ?? '') ?>
                <?php else: ?>
                    <span class="text-muted">No address provided</span>
                <?php endif; ?>
            </p>
            
            <?php if ($rental['use_lat'] && $rental['use_lng']): ?>
                <div id="map"></div>
                <div class="mt-10 flex gap-20" style="font-size: 12px;">
                    <div class="flex align-center gap-5">
                        <span style="display:inline-block; width:12px; height:12px; background:green; border-radius:50%;"></span> Product Location
                    </div>
                    <div class="flex align-center gap-5">
                        <span style="display:inline-block; width:12px; height:12px; background:blue; border-radius:50%;"></span> Police Station
                    </div>
                </div>
                <script>
                    document.addEventListener('DOMContentLoaded', function() {
                        const lat = <?= $rental['use_lat'] ?>;
                        const lng = <?= $rental['use_lng'] ?>;
                        const map = L.map('map').setView([lat, lng], 13);
                        
                        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                            attribution: '&copy; OpenStreetMap contributors'
                        }).addTo(map);

                        const gearIcon = L.icon({
                            iconUrl: 'https://raw.githubusercontent.com/pointhi/leaflet-color-markers/master/img/marker-icon-2x-green.png',
                            shadowUrl: 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/0.7.7/images/marker-shadow.png',
                            iconSize: [25, 41],
                            iconAnchor: [12, 41],
                            popupAnchor: [1, -34],
                            shadowSize: [41, 41]
                        });

                        L.marker([lat, lng], {icon: gearIcon}).addTo(map)
                            .bindPopup('<b><?= htmlspecialchars($rental['product_name']) ?></b><br>User: <?= htmlspecialchars($rental['user_name']) ?>')
                            .openPopup();

                        // Fetch police stations using Overpass API
                        function fetchPolice() {
                            const btn = document.getElementById('find_police_btn');
                            const originalText = btn.textContent;
                            btn.textContent = 'Searching...';
                            btn.disabled = true;

                            const overpassUrl = `https://overpass-api.de/api/interpreter?data=[out:json];node["amenity"="police"](around:5000,${lat},${lng});out;`;
                            
                            fetch(overpassUrl)
                                .then(r => {
                                    if (!r.ok) throw new Error('API server returned ' + r.status);
                                    return r.json();
                                })
                                .then(data => {
                                    if (!data.elements || data.elements.length === 0) {
                                        alert('No police stations found within 5km of this location.');
                                        return;
                                    }
                                    
                                    data.elements.forEach(station => {
                                        L.marker([station.lat, station.lon]).addTo(map)
                                            .bindPopup(station.tags.name || 'Police Station');
                                    });
                                    
                                    const markers = data.elements.map(s => L.marker([s.lat, s.lon]));
                                    markers.push(L.marker([lat, lng])); // Include original location
                                    const group = L.featureGroup(markers);
                                    map.fitBounds(group.getBounds().pad(0.1));
                                    
                                    btn.textContent = 'Search again';
                                    btn.disabled = false;
                                })
                                .catch(err => {
                                    console.error('Error fetching police stations:', err);
                                    // Fallback to secondary API if primary fails
                                    const fallbackUrl = `https://overpass.kumi.systems/api/interpreter?data=[out:json];node["amenity"="police"](around:5000,${lat},${lng});out;`;
                                    
                                    fetch(fallbackUrl)
                                        .then(r => r.json())
                                        .then(data => {
                                            // Repeat logic for fallback
                                            if (!data.elements || data.elements.length === 0) {
                                                alert('No police stations found within 5km.');
                                                return;
                                            }
                                            data.elements.forEach(station => {
                                                L.marker([station.lat, station.lon]).addTo(map)
                                                    .bindPopup(station.tags.name || 'Police Station');
                                            });
                                            map.fitBounds(L.featureGroup(data.elements.map(s => L.marker([s.lat, s.lon])).concat([L.marker([lat, lng])])).getBounds().pad(0.1));
                                            btn.textContent = 'Search again';
                                            btn.disabled = false;
                                        })
                                        .catch(err2 => {
                                            alert('The police station search service is currently overloaded. Please try again in a few minutes.');
                                            btn.textContent = originalText;
                                            btn.disabled = false;
                                        });
                                });
                        }

                        document.getElementById('find_police_btn').addEventListener('click', fetchPolice);
                    });
                </script>
            <?php else: ?>
                <div style="background: #f8f9fa; border: 1px dashed #ccc; padding: 40px; text-align: center; border-radius: 4px;">
                    <p class="text-muted">No GPS coordinates provided for this rental.</p>
                </div>
                <script>
                    document.getElementById('find_police_btn').disabled = true;
                </script>
            <?php endif; ?>
        </div>

        <?php if ($rental['status'] === 'RETURNED' || $rental['inspection_result']): ?>
            <div class="card mt-20" id="inspect">
                <h3 class="mb-15" style="border-bottom: 1px solid #eee; padding-bottom: 8px;">Inspection Details</h3>
                <?php if ($rental['inspection_result']): ?>
                    <div class="flex align-center gap-10 mb-15">
                        <strong>Result:</strong>
                        <span class="badge badge-<?= $rental['inspection_result'] === 'PASS' ? 'success' : 'rejected' ?>">
                            <?= $rental['inspection_result'] ?>
                        </span>
                        <span class="text-muted" style="font-size: 13px;">Inspected by <?= htmlspecialchars($rental['inspector_name']) ?></span>
                    </div>
                    <p class="text-muted"><strong>Notes:</strong></p>
                    <p class="mb-20"><?= nl2br(htmlspecialchars($rental['inspection_notes'])) ?></p>
                <?php elseif ($rental['status'] === 'RETURNED'): ?>
                    <form method="POST" action="<?= BASE_URL ?>/admin/actions/inspect_rental.php">
                        <input type="hidden" name="rental_id" value="<?= $rental['id'] ?>">
                        <div class="form-group">
                            <label>Inspection Result</label>
                            <div class="flex gap-20">
                                <label style="font-weight: normal; cursor: pointer;">
                                    <input type="radio" name="result" value="PASS" required> Pass (Return to Service)
                                </label>
                                <label style="font-weight: normal; cursor: pointer;">
                                    <input type="radio" name="result" value="FAIL"> Fail (Send to Maintenance)
                                </label>
                            </div>
                        </div>
                        <div class="form-group">
                            <label>Inspection Notes</label>
                            <textarea name="notes" class="form-control" rows="4" placeholder="Describe the condition of the returned gear..."></textarea>
                        </div>
                        <button type="submit" class="btn btn-primary">Submit Inspection & Complete Rental</button>
                    </form>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <?php if ($sos_alerts): ?>
            <div class="card mt-20 flex-grow">
                <h3 class="mb-15" style="border-bottom: 1px solid #eee; padding-bottom: 8px;">SOS Alerts</h3>
                <?php foreach ($sos_alerts as $alert): ?>
                    <div style="padding: 12px; border: 1px solid var(--color-danger-lt); background: var(--color-danger-lt); border-radius: 4px; margin-bottom: 10px;">
                        <div class="flex justify-between mb-5">
                            <span class="badge badge-overdue"><?= $alert['trigger_type'] ?> SOS</span>
                            <span style="font-size: 11px; color: #888;"><?= date('M d, H:i', strtotime($alert['created_at'])) ?></span>
                        </div>
                        <p style="font-size: 13px;"><?= nl2br(htmlspecialchars($alert['message'] ?: 'No message provided.')) ?></p>
                        <?php if ($alert['status'] === 'RESOLVED'): ?>
                            <div style="margin-top: 10px; padding-top: 10px; border-top: 1px solid rgba(0,0,0,0.1); font-size: 12px;">
                                <strong style="color: var(--color-success);">RESOLVED:</strong> <?= htmlspecialchars($alert['resolution_notes']) ?>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <div class="grid-col col-4 flex-col">
        <div class="card mb-20">
            <h3 class="mb-15" style="border-bottom: 1px solid #eee; padding-bottom: 8px;">Product Info</h3>
            <img src="<?= BASE_URL ?>/<?= htmlspecialchars($rental['image_path']) ?>" alt="" style="width: 100%; border-radius: var(--radius); margin-bottom: 15px;">
            <p class="mb-10"><strong><?= htmlspecialchars($rental['product_name']) ?></strong></p>
            <p class="text-muted mb-10" style="font-size: 13px;"><?= htmlspecialchars($rental['category_name']) ?></p>
            <p class="mb-10"><strong>Daily Rate:</strong> ৳<?= number_format($rental['daily_rate'], 0) ?></p>
            <a href="<?= BASE_URL ?>/admin/products.php?id=<?= $rental['product_id'] ?>" style="font-size: 13px;">View Product Details &rarr;</a>
        </div>

        <div class="card flex-grow">
            <h3 class="mb-15" style="border-bottom: 1px solid #eee; padding-bottom: 8px;">Financials</h3>
            <div class="flex justify-between mb-10">
                <span>Duration:</span>
                <span><?= $rental['days'] ?> days</span>
            </div>
            <div class="flex justify-between mb-10">
                <span>Rate:</span>
                <span>৳<?= number_format($rental['daily_rate'], 0) ?></span>
            </div>
            <hr style="margin: 10px 0; border: 0; border-top: 1px solid #eee;">
            <div class="flex justify-between" style="font-weight: 600; font-size: 18px; color: var(--color-primary-dk);">
                <span>Total Fee:</span>
                <span>৳<?= number_format($rental['total_fee'], 0) ?></span>
            </div>
        </div>
    </div>
</div>

<!-- Reject Modal -->
<div id="reject_modal" class="modal-overlay">
  <div class="modal-content">
    <h3 class="mb-10">Reject Rental Request</h3>
    <form method="POST" action="<?= BASE_URL ?>/admin/actions/reject_rental.php">
      <input type="hidden" name="rental_id" value="<?= $rental['id'] ?>">
      <div class="form-group">
        <label>Reason for Rejection</label>
        <textarea name="reason" class="form-control" required rows="4" placeholder="Explain why this request is being rejected..."></textarea>
      </div>
      <div class="flex gap-10">
        <button type="submit" class="btn btn-danger">Confirm Rejection</button>
        <button type="button" onclick="document.getElementById('reject_modal').style.display='none'" class="btn btn-outline">Cancel</button>
      </div>
    </form>
  </div>
</div>

<?php require_once __DIR__ . '/../includes/admin_footer.php'; ?>
