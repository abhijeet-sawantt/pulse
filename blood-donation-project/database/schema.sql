-- =========================================================
-- Pulse — Blood & Plasma Donor Readiness Network
-- Core schema (Phase 1 deliverable)
-- Import: mysql -u root -p pulse_db < schema.sql
-- =========================================================

CREATE DATABASE IF NOT EXISTS pulse_db;
USE pulse_db;

CREATE TABLE users (
  user_id       INT AUTO_INCREMENT PRIMARY KEY,
  name          VARCHAR(100)  NOT NULL,
  email         VARCHAR(150)  NOT NULL UNIQUE,
  password_hash VARCHAR(255)  NOT NULL,
  role          ENUM('donor','recipient','admin') NOT NULL,
  phone         VARCHAR(20),
  location      VARCHAR(150),
  created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE donors (
  donor_id          INT AUTO_INCREMENT PRIMARY KEY,
  user_id           INT NOT NULL,
  blood_group       ENUM('A+','A-','B+','B-','AB+','AB-','O+','O-') NOT NULL,
  plasma_eligible   BOOLEAN DEFAULT FALSE,
  last_donation_date DATE,
  next_eligible_date  DATE,
  is_available      BOOLEAN DEFAULT TRUE,
  blood_points      INT DEFAULT 0,
  FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
);

CREATE TABLE donation_requests (
  request_id       INT AUTO_INCREMENT PRIMARY KEY,
  recipient_id     INT NOT NULL,
  blood_group      ENUM('A+','A-','B+','B-','AB+','AB-','O+','O-') NOT NULL,
  component        ENUM('Blood','Plasma') NOT NULL,
  urgency          ENUM('Normal','Critical') DEFAULT 'Normal',
  hospital_address VARCHAR(255),
  status           ENUM('Open','Fulfilled') DEFAULT 'Open',
  posted_at        TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (recipient_id) REFERENCES users(user_id) ON DELETE CASCADE
);

CREATE TABLE rewards (
  reward_id        INT AUTO_INCREMENT PRIMARY KEY,
  partner_name     VARCHAR(150) NOT NULL,
  discount_details VARCHAR(255) NOT NULL,
  points_required  INT NOT NULL,
  coupon_code      VARCHAR(50)
);

CREATE TABLE redeemed_coupons (
  redemption_id  INT AUTO_INCREMENT PRIMARY KEY,
  donor_id       INT NOT NULL,
  reward_id      INT NOT NULL,
  date_redeemed  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (donor_id) REFERENCES donors(donor_id) ON DELETE CASCADE,
  FOREIGN KEY (reward_id) REFERENCES rewards(reward_id) ON DELETE CASCADE
);

CREATE TABLE donations (
  donation_id   INT AUTO_INCREMENT PRIMARY KEY,
  donor_id      INT NOT NULL,
  component     ENUM('Blood','Plasma') NOT NULL,
  donation_date DATE NOT NULL,
  status        ENUM('Pending','Verified','Rejected') DEFAULT 'Pending',
  created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (donor_id) REFERENCES donors(donor_id) ON DELETE CASCADE
);

-- Sample seed data for local testing
INSERT INTO users (name, email, password_hash, role, phone, location) VALUES
('Ritika Sharma', 'ritika@example.com', '$2y$10$AMxNimGhgGrBPtg8S7LByOVwzJ/uzw3NG5CdjlrpliFTiAbvGXi32', 'donor', '9810000001', 'Sector 12, Noida'),
('Fortis Greater Noida', 'fortis@example.com', '$2y$10$AMxNimGhgGrBPtg8S7LByOVwzJ/uzw3NG5CdjlrpliFTiAbvGXi32', 'recipient', '9810000002', 'Greater Noida'),
('System Admin', 'admin@example.com', '$2y$10$AMxNimGhgGrBPtg8S7LByOVwzJ/uzw3NG5CdjlrpliFTiAbvGXi32', 'admin', '9810000003', 'Greater Noida');

INSERT INTO donors (user_id, blood_group, plasma_eligible, last_donation_date, next_eligible_date, is_available, blood_points)
VALUES (1, 'O+', TRUE, '2026-07-15', '2026-09-09', TRUE, 420);

INSERT INTO rewards (partner_name, discount_details, points_required, coupon_code) VALUES
('Apollo Pharmacy', '15% off lab tests', 150, 'APOLLO15'),
('HealthKart', '₹300 wellness voucher', 250, 'HK300'),
('CarePlus Clinic', 'Free routine checkup', 400, 'CARE400');
