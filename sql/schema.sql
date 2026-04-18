-- ============================================================
-- ORLENE DATABASE SCHEMA
-- MySQL 8.x
-- Run this file first, then views.sql, procedures.sql,
-- triggers.sql, then seed.sql
-- ============================================================

SET FOREIGN_KEY_CHECKS = 0;
DROP DATABASE IF EXISTS orlene_db;
CREATE DATABASE orlene_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE orlene_db;
SET FOREIGN_KEY_CHECKS = 1;

-- ------------------------------------------------------------
-- USERS
-- ------------------------------------------------------------
CREATE TABLE users (
  id            INT AUTO_INCREMENT PRIMARY KEY,
  name          VARCHAR(100) NOT NULL,
  email         VARCHAR(150) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  phone         VARCHAR(20),
  nid           VARCHAR(50),
  role          ENUM('user','admin') DEFAULT 'user',
  created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- PRODUCT HIERARCHY
-- ------------------------------------------------------------
CREATE TABLE categories (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  name        VARCHAR(50) NOT NULL,
  description TEXT
) ENGINE=InnoDB;

CREATE TABLE subcategories (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  category_id INT NOT NULL,
  name        VARCHAR(100) NOT NULL,
  FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- PRODUCTS
-- ------------------------------------------------------------
CREATE TABLE products (
  id                    INT AUTO_INCREMENT PRIMARY KEY,
  subcategory_id        INT NOT NULL,
  name                  VARCHAR(150) NOT NULL,
  description           TEXT,
  daily_rate            DECIMAL(8,2) NOT NULL,
  stock_qty             INT DEFAULT 1,
  status                ENUM('AVAILABLE','RENTED','PENDING_INSPECTION','MAINTENANCE','RETIRED') DEFAULT 'AVAILABLE',
  rental_count          INT DEFAULT 0,
  maintenance_threshold INT DEFAULT 5,
  image_path            VARCHAR(255) DEFAULT 'assets/img/products/default.jpg',
  created_at            TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (subcategory_id) REFERENCES subcategories(id) ON DELETE RESTRICT
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- RENTALS
-- ------------------------------------------------------------
CREATE TABLE rentals (
  id               INT AUTO_INCREMENT PRIMARY KEY,
  user_id          INT NOT NULL,
  product_id       INT NOT NULL,
  start_date       DATE NOT NULL,
  end_date         DATE NOT NULL,
  days             INT NOT NULL,
  total_fee        DECIMAL(10,2) NOT NULL,
  status           ENUM('PENDING','APPROVED','ACTIVE','RETURNED','COMPLETED','REJECTED','OVERDUE') DEFAULT 'PENDING',
  use_area         VARCHAR(150),
  use_city         VARCHAR(100),
  use_landmark     VARCHAR(200),
  use_lat          DECIMAL(10,7),
  use_lng          DECIMAL(10,7),
  rejection_reason TEXT,
  created_at       TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  pickup_at        DATETIME,
  returned_at      DATETIME,
  FOREIGN KEY (user_id)    REFERENCES users(id)    ON DELETE RESTRICT,
  FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE RESTRICT
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- PICKUP / RETURN CONFIRMATIONS
-- ------------------------------------------------------------
CREATE TABLE rental_confirmations (
  id           INT AUTO_INCREMENT PRIMARY KEY,
  rental_id    INT NOT NULL,
  confirmed_by INT NOT NULL,
  confirm_type ENUM('PICKUP','RETURN') NOT NULL,
  confirmed_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (rental_id)    REFERENCES rentals(id) ON DELETE CASCADE,
  FOREIGN KEY (confirmed_by) REFERENCES users(id)   ON DELETE RESTRICT
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- INSPECTIONS
-- ------------------------------------------------------------
CREATE TABLE inspections (
  id           INT AUTO_INCREMENT PRIMARY KEY,
  rental_id    INT NOT NULL,
  product_id   INT NOT NULL,
  admin_id     INT NOT NULL,
  result       ENUM('PASS','FAIL') NOT NULL,
  notes        TEXT,
  inspected_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (rental_id)  REFERENCES rentals(id)  ON DELETE CASCADE,
  FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE RESTRICT,
  FOREIGN KEY (admin_id)   REFERENCES users(id)    ON DELETE RESTRICT
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- MAINTENANCE LOG
-- ------------------------------------------------------------
CREATE TABLE maintenance_log (
  id               INT AUTO_INCREMENT PRIMARY KEY,
  product_id       INT NOT NULL,
  reason           TEXT,
  sent_at          DATETIME DEFAULT CURRENT_TIMESTAMP,
  resolved_at      DATETIME,
  resolved_by      INT,
  resolution_notes TEXT,
  FOREIGN KEY (product_id)  REFERENCES products(id) ON DELETE CASCADE,
  FOREIGN KEY (resolved_by) REFERENCES users(id)    ON DELETE SET NULL
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- SOS ALERTS
-- ------------------------------------------------------------
CREATE TABLE sos_alerts (
  id               INT AUTO_INCREMENT PRIMARY KEY,
  rental_id        INT NOT NULL,
  user_id          INT NOT NULL,
  trigger_type     ENUM('MANUAL','OVERDUE') NOT NULL,
  message          TEXT,
  status           ENUM('OPEN','RESOLVED') DEFAULT 'OPEN',
  created_at       DATETIME DEFAULT CURRENT_TIMESTAMP,
  resolved_at      DATETIME,
  resolved_by      INT,
  resolution_notes TEXT,
  FOREIGN KEY (rental_id)   REFERENCES rentals(id) ON DELETE CASCADE,
  FOREIGN KEY (user_id)     REFERENCES users(id)   ON DELETE RESTRICT,
  FOREIGN KEY (resolved_by) REFERENCES users(id)   ON DELETE SET NULL
) ENGINE=InnoDB;
