-- schema.sql
-- --------------------------------------------------------------
-- Run this file once to create the database and tables:
--   mysql -u root -p < schema.sql
-- (or import it through phpMyAdmin if you're using XAMPP)
-- --------------------------------------------------------------

CREATE DATABASE IF NOT EXISTS sgr_seatflow;
USE sgr_seatflow;

-- A stop along the Dar es Salaam - Dodoma SGR line.
CREATE TABLE station (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(80) NOT NULL,
    order_index INT NOT NULL UNIQUE,   -- position along the line: 0,1,2,3...
    km_from_origin FLOAT NOT NULL      -- distance in km from Dar es Salaam
);

-- A single train trip.
CREATE TABLE train (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(80) NOT NULL,
    departure_time VARCHAR(20) NOT NULL,
    fare_per_km FLOAT NOT NULL DEFAULT 120,
    total_seats INT NOT NULL DEFAULT 20
);

-- A passenger or an admin (station staff).
CREATE TABLE user (
    id INT AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(120) NOT NULL,
    nationality VARCHAR(80) NOT NULL DEFAULT 'Tanzanian',
    is_resident TINYINT(1) NOT NULL DEFAULT 1,
    identity_number VARCHAR(80) NULL,
    phone VARCHAR(20) NOT NULL UNIQUE,
    email VARCHAR(120) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    is_admin TINYINT(1) NOT NULL DEFAULT 0,
    wallet_balance FLOAT NOT NULL DEFAULT 0
);

-- Money moving in/out of a user's wallet.
CREATE TABLE transaction_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    amount FLOAT NOT NULL,            -- positive = credit, negative = debit
    kind VARCHAR(20) NOT NULL,        -- 'topup', 'payment', 'refund'
    note VARCHAR(200),
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES user(id)
);

-- A physical seat that belongs to one train.
CREATE TABLE seat (
    id INT AUTO_INCREMENT PRIMARY KEY,
    train_id INT NOT NULL,
    coach_number INT NOT NULL DEFAULT 1,
    seat_number VARCHAR(10) NOT NULL,
    UNIQUE (train_id, coach_number, seat_number),
    FOREIGN KEY (train_id) REFERENCES train(id)
);

-- THE CORE TABLE: a booking is a SEGMENT of a seat's journey
-- (origin -> destination), not the whole trip. This is what makes
-- automatic seat re-allocation ("harvesting") possible.
CREATE TABLE booking (
    id INT AUTO_INCREMENT PRIMARY KEY,
    seat_id INT NOT NULL,
    user_id INT NOT NULL,
    origin_station_id INT NOT NULL,
    destination_station_id INT NOT NULL,
    travel_date DATE NULL,
    fare_paid FLOAT NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'active',  -- active | exited | cancelled
    actual_exit_station_id INT NULL,               -- set when passenger exits early
    qr_code VARCHAR(64) NOT NULL UNIQUE,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (seat_id) REFERENCES seat(id),
    FOREIGN KEY (user_id) REFERENCES user(id),
    FOREIGN KEY (origin_station_id) REFERENCES station(id),
    FOREIGN KEY (destination_station_id) REFERENCES station(id),
    FOREIGN KEY (actual_exit_station_id) REFERENCES station(id)
);

-- Simulated SMS outbox. Real SMS gateways (Beem Africa, Africa's
-- Talking, etc.) charge per message and need an API key, so for now
-- we just log what WOULD have been sent. Swap send_sms() in
-- includes/notifications.php for a real API call once you have one,
-- and this table becomes a handy audit log too.
CREATE TABLE sms_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    phone VARCHAR(20) NOT NULL,
    message VARCHAR(300) NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- --------------------------------------------------------------
-- Seed data: real approximate SGR phase-1 stations, one train,
-- 20 seats, and two demo accounts so you can log in immediately.
-- --------------------------------------------------------------

INSERT INTO station (name, order_index, km_from_origin) VALUES
    ('Dar es Salaam', 0, 0),
    ('Soga', 1, 60),
    ('Ruvu', 2, 90),
    ('Morogoro', 3, 205),
    ('Kilosa', 4, 300),
    ('Gulwe', 5, 400),
    ('Dodoma', 6, 506);

INSERT INTO train (name, departure_time, fare_per_km, total_seats)
SELECT 'SGR Express 101', '07:30', 120, 20
WHERE NOT EXISTS (SELECT 1 FROM train WHERE departure_time = '07:30');

INSERT INTO train (name, departure_time, fare_per_km, total_seats)
SELECT 'SGR Express 102', '12:15', 120, 20
WHERE NOT EXISTS (SELECT 1 FROM train WHERE departure_time = '12:15');

INSERT INTO train (name, departure_time, fare_per_km, total_seats)
SELECT 'SGR Express 103', '15:45', 120, 20
WHERE NOT EXISTS (SELECT 1 FROM train WHERE departure_time = '15:45');

INSERT INTO train (name, departure_time, fare_per_km, total_seats)
SELECT 'SGR Express 104', '18:20', 120, 20
WHERE NOT EXISTS (SELECT 1 FROM train WHERE departure_time = '18:20');

INSERT INTO train (name, departure_time, fare_per_km, total_seats)
SELECT 'SGR Express 105', '22:10', 120, 20
WHERE NOT EXISTS (SELECT 1 FROM train WHERE departure_time = '22:10');

-- Each coach contains the same seat pattern A1-A5, B1-B5, C1-C5, D1-D5.
-- This lets the same seat number exist in coach 1, coach 2 and coach 3,
-- while keeping each booking uniquely identifiable by (train + coach + seat).
INSERT INTO seat (train_id, coach_number, seat_number)
SELECT t.id, coach.coach_number, s.seat_number
FROM train t
CROSS JOIN (
    SELECT 1 AS coach_number UNION ALL SELECT 2 UNION ALL SELECT 3
) coach
JOIN (
    SELECT 'A1' AS seat_number UNION ALL SELECT 'A2' UNION ALL SELECT 'A3' UNION ALL SELECT 'A4' UNION ALL SELECT 'A5'
    UNION ALL SELECT 'B1' UNION ALL SELECT 'B2' UNION ALL SELECT 'B3' UNION ALL SELECT 'B4' UNION ALL SELECT 'B5'
    UNION ALL SELECT 'C1' UNION ALL SELECT 'C2' UNION ALL SELECT 'C3' UNION ALL SELECT 'C4' UNION ALL SELECT 'C5'
    UNION ALL SELECT 'D1' UNION ALL SELECT 'D2' UNION ALL SELECT 'D3' UNION ALL SELECT 'D4' UNION ALL SELECT 'D5'
) s
WHERE t.departure_time IN ('07:30','12:15','15:45','18:20','22:10')
AND NOT EXISTS (
    SELECT 1 FROM seat s2
    WHERE s2.train_id = t.id
      AND s2.coach_number = coach.coach_number
      AND s2.seat_number = s.seat_number
);

-- Demo admin: phone 0700000001 / password admin123
-- Demo passenger: phone 0700000002 / password pass123
-- (password hashes below are bcrypt hashes generated with PHP's password_hash)
INSERT INTO user (full_name, nationality, is_resident, identity_number, phone, email, password_hash, is_admin, wallet_balance) VALUES
    ('Station Admin', 'Tanzanian', 1, 'ID-ADMIN-001', '0700000001', 'admin@example.com', '$2y$10$5n0OU4T584WuJQB7wNdIl.ajP90YVVBh0jPYAhW6y7lzNxUh0.78.', 1, 0),
    ('Demo Passenger', 'Tanzanian', 1, 'ID-1000002', '0700000002', 'passenger@example.com', '$2y$10$3Y9ADU2NkpXAyxM8z4Gn/.e96ofuJ0a4M/lQZqvYRUMsooNC3Hdoy', 0, 50000);
