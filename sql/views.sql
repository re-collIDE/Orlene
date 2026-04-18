-- ============================================================
-- ORLENE VIEWS
-- Run after schema.sql
-- ============================================================
USE orlene_db;

-- ------------------------------------------------------------
-- V1: Active & Overdue Rentals with full user + product info
--     5-table JOIN — primary showcase query
-- ------------------------------------------------------------
CREATE OR REPLACE VIEW v_active_rentals AS
SELECT
  r.id          AS rental_id,
  u.id          AS user_id,
  u.name        AS user_name,
  u.email,
  u.phone,
  p.id          AS product_id,
  p.name        AS product_name,
  cat.name      AS category,
  sub.name      AS subcategory,
  r.start_date,
  r.end_date,
  r.days,
  r.total_fee,
  r.status,
  r.use_area,
  r.use_city,
  r.use_landmark,
  r.use_lat,
  r.use_lng,
  r.pickup_at,
  DATEDIFF(CURDATE(), r.end_date) AS days_overdue
FROM   rentals r
JOIN   users u          ON r.user_id      = u.id
JOIN   products p       ON r.product_id   = p.id
JOIN   subcategories sub ON p.subcategory_id = sub.id
JOIN   categories cat   ON sub.category_id  = cat.id
WHERE  r.status IN ('ACTIVE', 'OVERDUE');

-- ------------------------------------------------------------
-- V2: Product Status Report with rental counts and revenue
-- ------------------------------------------------------------
CREATE OR REPLACE VIEW v_product_status_report AS
SELECT
  p.id                          AS product_id,
  p.name                        AS product_name,
  cat.name                      AS category,
  sub.name                      AS subcategory,
  p.daily_rate,
  p.stock_qty,
  p.rental_count,
  p.maintenance_threshold,
  p.status,
  COUNT(r.id)                   AS total_rentals,
  SUM(CASE WHEN r.status = 'ACTIVE'     THEN 1 ELSE 0 END) AS active_rentals,
  SUM(CASE WHEN r.status = 'COMPLETED'  THEN 1 ELSE 0 END) AS completed_rentals,
  COALESCE(SUM(CASE WHEN r.status IN ('ACTIVE', 'COMPLETED', 'RETURNED') THEN r.total_fee ELSE 0 END), 0) AS total_revenue
FROM   products p
JOIN   subcategories sub ON p.subcategory_id = sub.id
JOIN   categories cat    ON sub.category_id  = cat.id
LEFT JOIN rentals r      ON r.product_id = p.id
GROUP BY p.id;

-- ------------------------------------------------------------
-- V3: User Rental Summary
-- ------------------------------------------------------------
CREATE OR REPLACE VIEW v_user_rental_summary AS
SELECT
  u.id,
  u.name,
  u.email,
  u.phone,
  u.created_at                  AS member_since,
  COUNT(r.id)                   AS total_rentals,
  SUM(CASE WHEN r.status = 'COMPLETED'  THEN 1 ELSE 0 END) AS completed,
  SUM(CASE WHEN r.status IN ('ACTIVE','OVERDUE') THEN 1 ELSE 0 END) AS active,
  SUM(CASE WHEN r.status = 'OVERDUE'    THEN 1 ELSE 0 END) AS overdue_count,
  COALESCE(SUM(CASE WHEN r.status IN ('ACTIVE', 'COMPLETED', 'RETURNED') THEN r.total_fee ELSE 0 END), 0) AS total_spent
FROM   users u
LEFT JOIN rentals r ON r.user_id = u.id
WHERE  u.role = 'user'
GROUP BY u.id;

-- ------------------------------------------------------------
-- V4: SOS Alerts with rental and user context
-- ------------------------------------------------------------
CREATE OR REPLACE VIEW v_sos_open AS
SELECT
  sa.id          AS alert_id,
  sa.trigger_type,
  sa.message,
  sa.created_at  AS alert_time,
  sa.status      AS alert_status,
  u.name         AS user_name,
  u.phone        AS user_phone,
  p.name         AS product_name,
  r.id           AS rental_id,
  r.end_date,
  r.use_area,
  r.use_city,
  r.use_lat,
  r.use_lng,
  TIMESTAMPDIFF(HOUR, sa.created_at, NOW()) AS hours_since_alert
FROM   sos_alerts sa
JOIN   rentals r  ON sa.rental_id  = r.id
JOIN   users u    ON sa.user_id    = u.id
JOIN   products p ON r.product_id  = p.id
WHERE  sa.status = 'OPEN'
ORDER BY sa.created_at DESC;

-- ------------------------------------------------------------
-- V5: Pending Admin Actions (dashboard summary)
-- ------------------------------------------------------------
CREATE OR REPLACE VIEW v_admin_dashboard AS
SELECT
  (SELECT COUNT(*) FROM rentals WHERE status = 'PENDING')                    AS pending_approvals,
  (SELECT COUNT(*) FROM rentals WHERE status IN ('ACTIVE','OVERDUE'))         AS active_rentals,
  (SELECT COUNT(*) FROM sos_alerts WHERE status = 'OPEN')                     AS open_sos,
  (SELECT COUNT(*) FROM maintenance_log WHERE resolved_at IS NULL)            AS in_maintenance,
  (SELECT COUNT(*) FROM rentals WHERE status = 'RETURNED')                    AS pending_inspection,
  (SELECT COALESCE(SUM(total_fee),0) FROM rentals WHERE status='COMPLETED')   AS total_revenue;
