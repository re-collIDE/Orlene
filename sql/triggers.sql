-- ============================================================
-- ORLENE TRIGGERS & EVENTS
-- Run after schema.sql, views.sql, procedures.sql
-- ============================================================
USE orlene_db;

DELIMITER $$

-- ------------------------------------------------------------
-- TRIGGER 1: Auto maintenance after N completed rentals
-- Fires AFTER UPDATE on rentals
-- When a rental transitions to COMPLETED, increments
-- products.rental_count and checks if threshold reached.
-- If threshold reached: sets product to MAINTENANCE and
-- logs it in maintenance_log.
-- ------------------------------------------------------------
DROP TRIGGER IF EXISTS trg_after_rental_complete$$
CREATE TRIGGER trg_after_rental_complete
AFTER UPDATE ON rentals
FOR EACH ROW
BEGIN
  DECLARE v_threshold INT;
  DECLARE v_count     INT;

  IF NEW.status = 'COMPLETED' AND OLD.status != 'COMPLETED' THEN

    UPDATE products
    SET rental_count = rental_count + 1
    WHERE id = NEW.product_id;

    SELECT rental_count, maintenance_threshold
    INTO v_count, v_threshold
    FROM products WHERE id = NEW.product_id;

    IF (v_count % v_threshold) = 0 THEN
      UPDATE products
      SET status = 'MAINTENANCE'
      WHERE id = NEW.product_id;

      INSERT INTO maintenance_log (product_id, reason)
      VALUES (
        NEW.product_id,
        CONCAT('Auto-scheduled: completed ', v_count, ' total rentals (threshold: ', v_threshold, ')')
      );
    END IF;

  END IF;
END$$

-- ------------------------------------------------------------
-- TRIGGER 2: Prevent booking unavailable products
-- Fires BEFORE INSERT on rentals
-- Guards against booking a product that is not AVAILABLE.
-- (Second safety net — PHP also checks this)
-- ------------------------------------------------------------
DROP TRIGGER IF EXISTS trg_prevent_double_booking$$
CREATE TRIGGER trg_prevent_double_booking
BEFORE INSERT ON rentals
FOR EACH ROW
BEGIN
  DECLARE v_status VARCHAR(30);
  SELECT status INTO v_status FROM products WHERE id = NEW.product_id;

  IF v_status != 'AVAILABLE' THEN
    SIGNAL SQLSTATE '45000'
      SET MESSAGE_TEXT = 'Product is not available for booking';
  END IF;
END$$

-- ------------------------------------------------------------
-- TRIGGER 3: Log when a product is retired
-- Fires AFTER UPDATE on products
-- When status changes to RETIRED, log it to maintenance_log
-- as a record-keeping entry.
-- ------------------------------------------------------------
DROP TRIGGER IF EXISTS trg_product_retired$$
CREATE TRIGGER trg_product_retired
AFTER UPDATE ON products
FOR EACH ROW
BEGIN
  IF NEW.status = 'RETIRED' AND OLD.status != 'RETIRED' THEN
    INSERT INTO maintenance_log (product_id, reason)
    VALUES (NEW.product_id, 'Product retired from catalog');
  END IF;
END$$

DELIMITER ;

-- ------------------------------------------------------------
-- MySQL EVENT: Hourly overdue rental check
-- Marks ACTIVE rentals past end_date as OVERDUE
-- Creates SOS alerts for newly overdue rentals
-- Requires event_scheduler = ON
-- ------------------------------------------------------------
SET GLOBAL event_scheduler = ON;

DROP EVENT IF EXISTS ev_check_overdue;

CREATE EVENT ev_check_overdue
ON SCHEDULE EVERY 1 HOUR
STARTS CURRENT_TIMESTAMP
COMMENT 'Mark overdue rentals and generate SOS alerts'
DO
  BEGIN
    -- Step 1: Mark active rentals past their end date as OVERDUE
    UPDATE rentals
    SET status = 'OVERDUE'
    WHERE status = 'ACTIVE'
      AND end_date < CURDATE();

    -- Step 2: Create SOS alerts for newly overdue rentals
    --         (only if no OVERDUE alert already exists for that rental)
    INSERT INTO sos_alerts (rental_id, user_id, trigger_type, message, status)
    SELECT
      r.id,
      r.user_id,
      'OVERDUE',
      CONCAT('Rental overdue since ', r.end_date, '. Product not returned.'),
      'OPEN'
    FROM rentals r
    WHERE r.status = 'OVERDUE'
      AND NOT EXISTS (
        SELECT 1 FROM sos_alerts s
        WHERE s.rental_id = r.id
          AND s.trigger_type = 'OVERDUE'
      );
  END;
