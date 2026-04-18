-- ============================================================
-- ORLENE STORED PROCEDURES
-- Run after schema.sql and views.sql
-- ============================================================
USE orlene_db;

DELIMITER $$

-- ------------------------------------------------------------
-- SP1: Approve a rental request
-- Called by: admin/actions/approve_rental.php
-- ------------------------------------------------------------
DROP PROCEDURE IF EXISTS sp_approve_rental$$
CREATE PROCEDURE sp_approve_rental(
  IN p_rental_id INT,
  IN p_admin_id  INT
)
BEGIN
  DECLARE v_status         VARCHAR(20);
  DECLARE v_product_id     INT;
  DECLARE v_product_status VARCHAR(30);

  SELECT status, product_id INTO v_status, v_product_id
  FROM rentals WHERE id = p_rental_id;

  SELECT status INTO v_product_status
  FROM products WHERE id = v_product_id;

  IF v_status != 'PENDING' THEN
    SIGNAL SQLSTATE '45000'
      SET MESSAGE_TEXT = 'Rental is not in PENDING status';
  ELSEIF v_product_status != 'AVAILABLE' THEN
    SIGNAL SQLSTATE '45000'
      SET MESSAGE_TEXT = 'Product is not available for rental';
  ELSE
    UPDATE rentals SET status = 'APPROVED' WHERE id = p_rental_id;
  END IF;
END$$

-- ------------------------------------------------------------
-- SP2: Reject a rental request
-- Called by: admin/actions/reject_rental.php
-- ------------------------------------------------------------
DROP PROCEDURE IF EXISTS sp_reject_rental$$
CREATE PROCEDURE sp_reject_rental(
  IN p_rental_id INT,
  IN p_reason    TEXT
)
BEGIN
  DECLARE v_status VARCHAR(20);

  SELECT status INTO v_status FROM rentals WHERE id = p_rental_id;

  IF v_status NOT IN ('PENDING','APPROVED') THEN
    SIGNAL SQLSTATE '45000'
      SET MESSAGE_TEXT = 'Rental cannot be rejected at this stage';
  ELSE
    UPDATE rentals
    SET status = 'REJECTED', rejection_reason = p_reason
    WHERE id = p_rental_id;
  END IF;
END$$

-- ------------------------------------------------------------
-- SP3: Confirm pickup (user side)
-- Called by: pages/actions/confirm_pickup.php
-- ------------------------------------------------------------
DROP PROCEDURE IF EXISTS sp_confirm_pickup$$
CREATE PROCEDURE sp_confirm_pickup(
  IN p_rental_id INT,
  IN p_user_id   INT
)
BEGIN
  DECLARE v_status VARCHAR(20);
  DECLARE v_product_id INT;

  SELECT status, product_id INTO v_status, v_product_id
  FROM rentals WHERE id = p_rental_id;

  IF v_status != 'APPROVED' THEN
    SIGNAL SQLSTATE '45000'
      SET MESSAGE_TEXT = 'Rental must be APPROVED before pickup';
  ELSE
    UPDATE rentals
    SET status = 'ACTIVE', pickup_at = NOW()
    WHERE id = p_rental_id;

    UPDATE products SET status = 'RENTED'
    WHERE id = v_product_id;

    INSERT INTO rental_confirmations (rental_id, confirmed_by, confirm_type)
    VALUES (p_rental_id, p_user_id, 'PICKUP');
  END IF;
END$$

-- ------------------------------------------------------------
-- SP4: Confirm return (user side)
-- Called by: pages/actions/confirm_return.php
-- ------------------------------------------------------------
DROP PROCEDURE IF EXISTS sp_confirm_return$$
CREATE PROCEDURE sp_confirm_return(
  IN p_rental_id INT,
  IN p_user_id   INT
)
BEGIN
  DECLARE v_status     VARCHAR(20);
  DECLARE v_product_id INT;

  SELECT status, product_id INTO v_status, v_product_id
  FROM rentals WHERE id = p_rental_id;

  IF v_status NOT IN ('ACTIVE', 'OVERDUE') THEN
    SIGNAL SQLSTATE '45000'
      SET MESSAGE_TEXT = 'Rental cannot be returned at this stage';
  ELSE
    UPDATE rentals
    SET status = 'RETURNED', returned_at = NOW()
    WHERE id = p_rental_id;

    UPDATE products SET status = 'PENDING_INSPECTION'
    WHERE id = v_product_id;

    INSERT INTO rental_confirmations (rental_id, confirmed_by, confirm_type)
    VALUES (p_rental_id, p_user_id, 'RETURN');
  END IF;
END$$

-- ------------------------------------------------------------
-- SP5: Submit inspection and complete rental
-- Called by: admin/actions/inspect_rental.php
-- ------------------------------------------------------------
DROP PROCEDURE IF EXISTS sp_complete_rental$$
CREATE PROCEDURE sp_complete_rental(
  IN p_rental_id INT,
  IN p_admin_id  INT,
  IN p_result    VARCHAR(4),
  IN p_notes     TEXT
)
BEGIN
  DECLARE v_product_id INT;
  DECLARE v_status     VARCHAR(20);

  SELECT product_id, status INTO v_product_id, v_status
  FROM rentals WHERE id = p_rental_id;

  IF v_status != 'RETURNED' THEN
    SIGNAL SQLSTATE '45000'
      SET MESSAGE_TEXT = 'Rental must be in RETURNED status to inspect';
  ELSE
    INSERT INTO inspections (rental_id, product_id, admin_id, result, notes)
    VALUES (p_rental_id, v_product_id, p_admin_id, p_result, p_notes);

    UPDATE rentals SET status = 'COMPLETED' WHERE id = p_rental_id;

    IF p_result = 'PASS' THEN
      -- Trigger trg_after_rental_complete will handle rental_count
      -- and auto maintenance if threshold reached
      -- Product status is set back to AVAILABLE here only if trigger doesn't override it
      UPDATE products SET status = 'AVAILABLE'
      WHERE id = v_product_id AND status = 'PENDING_INSPECTION';
    ELSE
      UPDATE products SET status = 'MAINTENANCE'
      WHERE id = v_product_id;

      INSERT INTO maintenance_log (product_id, reason)
      VALUES (v_product_id, CONCAT('Failed inspection after rental #', p_rental_id, ': ', p_notes));
    END IF;
  END IF;
END$$

-- ------------------------------------------------------------
-- SP6: Resolve SOS alert
-- Called by: admin/actions/resolve_sos.php
-- ------------------------------------------------------------
DROP PROCEDURE IF EXISTS sp_resolve_sos$$
CREATE PROCEDURE sp_resolve_sos(
  IN p_alert_id  INT,
  IN p_admin_id  INT,
  IN p_notes     TEXT
)
BEGIN
  UPDATE sos_alerts
  SET status = 'RESOLVED',
      resolved_at = NOW(),
      resolved_by = p_admin_id,
      resolution_notes = p_notes
  WHERE id = p_alert_id AND status = 'OPEN';
END$$

-- ------------------------------------------------------------
-- SP7: Send product to manual maintenance
-- Called by: admin/actions/send_maintenance.php
-- ------------------------------------------------------------
DROP PROCEDURE IF EXISTS sp_send_maintenance$$
CREATE PROCEDURE sp_send_maintenance(
  IN p_product_id INT,
  IN p_reason     TEXT
)
BEGIN
  UPDATE products SET status = 'MAINTENANCE'
  WHERE id = p_product_id;

  INSERT INTO maintenance_log (product_id, reason)
  VALUES (p_product_id, p_reason);
END$$

-- ------------------------------------------------------------
-- SP8: Resolve maintenance and return product to service
-- Called by: admin/actions/resolve_maintenance.php
-- ------------------------------------------------------------
DROP PROCEDURE IF EXISTS sp_resolve_maintenance$$
CREATE PROCEDURE sp_resolve_maintenance(
  IN p_log_id    INT,
  IN p_admin_id  INT,
  IN p_notes     TEXT
)
BEGIN
  DECLARE v_product_id INT;

  SELECT product_id INTO v_product_id
  FROM maintenance_log WHERE id = p_log_id;

  UPDATE maintenance_log
  SET resolved_at = NOW(),
      resolved_by = p_admin_id,
      resolution_notes = p_notes
  WHERE id = p_log_id;

  UPDATE products SET status = 'AVAILABLE'
  WHERE id = v_product_id;
END$$

DELIMITER ;
