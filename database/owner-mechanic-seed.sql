-- AutoCare: Vehicle Owner + Mechanic demo data
-- Additive only: run AFTER autocare-db.sql (and optionally seed.sql). Nothing is updated or
-- deleted; every row uses a fixed id >= 1001 with INSERT IGNORE, so re-running is harmless.
--
-- Login accounts live in AuthUsers (created by api/login-api.php):
--   owner@autocare.com / password123     -> Users.id 1001 (VehicleOwner)
--   mechanic@autocare.com / password123  -> Users.id 1002 (Mechanic)
-- The panels link a login to its Users row by email.
--
-- Rows that need a manager or workshop use the first existing one (seed.sql: John Manager,
-- AutoCare Central Downtown); chat rows with a manager are skipped if there is none.
-- Dates are relative to NOW() so dashboards always look current.

-- 1. Users linked to the team login accounts (password_hash is unused: login checks AuthUsers)
INSERT IGNORE INTO Users (id, name, email, password_hash, phone, role, status) VALUES
(1001, 'Vehicle Owner', 'owner@autocare.com', 'see_AuthUsers', '555-1001', 'VehicleOwner', 'Active'),
(1002, 'Mechanic User', 'mechanic@autocare.com', 'see_AuthUsers', '555-1002', 'Mechanic', 'Active');

-- 2. Service categories used below (names are UNIQUE; existing ones are kept)
INSERT IGNORE INTO ServiceCategories (name, description, base_rate) VALUES
('General Servicing', 'Oil change, filter replacement, and general checkup', 49.99),
('Brake Repair', 'Brake pad replacement, rotor resurfacing, brake fluid flush', 149.99),
('Engine Diagnostics', 'Computer diagnostic check for check engine light', 89.99),
('Tire Services', 'Tire rotation, balancing, and alignment', 79.99);

-- 3. Vehicles
INSERT IGNORE INTO Vehicles (id, owner_id, make, model, year, license_plate, vin) VALUES
(1001, 1001, 'Toyota', 'Corolla', 2019, 'DHA-1001', 'JTDBR32E190100101'),
(1002, 1001, 'Honda', 'CR-V', 2021, 'DHA-1002', '5J6RW2H50ML100102'),
(1003, 1001, 'Nissan', 'X-Trail', 2017, 'DHA-1003', 'JN1TBNT32Z0100103');

-- 4. Appointments (1001-1003, 1006 have job cards; 1004-1005 are upcoming bookings)
INSERT IGNORE INTO Appointments (id, owner_id, vehicle_id, workshop_id, service_category_id, preferred_date, issue_description, status, created_at) VALUES
(1001, 1001, 1001, (SELECT id FROM Workshops ORDER BY id LIMIT 1), (SELECT id FROM ServiceCategories WHERE name = 'Brake Repair'),
    DATE_SUB(NOW(), INTERVAL 2 DAY), 'Squealing noise when braking.', 'Approved', DATE_SUB(NOW(), INTERVAL 4 DAY)),
(1002, 1001, 1002, (SELECT id FROM Workshops ORDER BY id LIMIT 1), (SELECT id FROM ServiceCategories WHERE name = 'Engine Diagnostics'),
    DATE_SUB(NOW(), INTERVAL 1 DAY), 'Check engine light is on.', 'Approved', DATE_SUB(NOW(), INTERVAL 2 DAY)),
(1003, 1001, 1003, (SELECT id FROM Workshops ORDER BY id LIMIT 1), (SELECT id FROM ServiceCategories WHERE name = 'General Servicing'),
    DATE_SUB(NOW(), INTERVAL 40 DAY), 'Regular 10,000 km service.', 'Completed', DATE_SUB(NOW(), INTERVAL 42 DAY)),
(1004, 1001, 1001, (SELECT id FROM Workshops ORDER BY id LIMIT 1), (SELECT id FROM ServiceCategories WHERE name = 'General Servicing'),
    DATE_ADD(CURDATE(), INTERVAL 3 DAY) + INTERVAL 9 HOUR, 'Oil change after the brake job.', 'Pending', DATE_SUB(NOW(), INTERVAL 6 HOUR)),
(1005, 1001, 1002, (SELECT id FROM Workshops ORDER BY id LIMIT 1), (SELECT id FROM ServiceCategories WHERE name = 'Tire Services'),
    DATE_ADD(CURDATE(), INTERVAL 7 DAY) + INTERVAL 13 HOUR, 'Rotate and balance tires.', 'Approved', DATE_SUB(NOW(), INTERVAL 1 DAY)),
(1006, 1001, 1003, (SELECT id FROM Workshops ORDER BY id LIMIT 1), (SELECT id FROM ServiceCategories WHERE name = 'Tire Services'),
    DATE_SUB(NOW(), INTERVAL 3 DAY), 'Vibration at highway speed.', 'Approved', DATE_SUB(NOW(), INTERVAL 5 DAY));

-- 5. Job cards assigned to the mechanic
--   1001 In Progress (timeline: Repairing)  1002 Diagnosis  1003 Completed  1004 Ready
INSERT IGNORE INTO JobCards (id, appointment_id, manager_id, mechanic_id, status, fault_report, estimated_cost, start_date, completion_date, delivery_date, created_at) VALUES
(1001, 1001, (SELECT id FROM Users WHERE role = 'Manager' ORDER BY id LIMIT 1), 1002, 'In Progress',
    'Front brake pads worn below 3 mm; squeal on braking.', 4575.00,
    DATE_SUB(NOW(), INTERVAL 2 DAY), NULL, DATE_ADD(CURDATE(), INTERVAL 1 DAY) + INTERVAL 17 HOUR, DATE_SUB(NOW(), INTERVAL 2 DAY)),
(1002, 1002, (SELECT id FROM Users WHERE role = 'Manager' ORDER BY id LIMIT 1), 1002, 'Diagnosis',
    'Code P0420 stored; checking catalytic converter and O2 sensors.', 2250.00,
    DATE_SUB(NOW(), INTERVAL 20 HOUR), NULL, DATE_ADD(CURDATE(), INTERVAL 3 DAY) + INTERVAL 17 HOUR, DATE_SUB(NOW(), INTERVAL 1 DAY)),
(1003, 1003, (SELECT id FROM Users WHERE role = 'Manager' ORDER BY id LIMIT 1), 1002, 'Completed',
    'Routine service, no issues found.', 1000.00,
    DATE_SUB(NOW(), INTERVAL 40 DAY), DATE_SUB(NOW(), INTERVAL 38 DAY), DATE_SUB(NOW(), INTERVAL 38 DAY), DATE_SUB(NOW(), INTERVAL 40 DAY)),
(1004, 1006, (SELECT id FROM Users WHERE role = 'Manager' ORDER BY id LIMIT 1), 1002, 'Ready',
    'Front-left wheel out of balance.', 1225.00,
    DATE_SUB(NOW(), INTERVAL 3 DAY), NULL, CURDATE() + INTERVAL 18 HOUR, DATE_SUB(NOW(), INTERVAL 3 DAY));

-- 6. Parts on the jobs (SpareParts rows from autocare-db.sql, matched by SKU)
INSERT IGNORE INTO JobCardParts (id, job_card_id, part_id, quantity, unit_price, status, requested_at) VALUES
(1001, 1001, (SELECT id FROM SpareParts WHERE sku = 'BRK-001'), 1, 3500.00, 'Approved', DATE_SUB(NOW(), INTERVAL 2 DAY)),
(1002, 1001, (SELECT id FROM SpareParts WHERE sku = 'FLT-002'), 1, 850.00, 'Pending Approval', NOW()),
(1003, 1002, (SELECT id FROM SpareParts WHERE sku = 'ELC-003'), 1, 2100.00, 'Pending Approval', DATE_SUB(NOW(), INTERVAL 18 HOUR)),
(1004, 1003, (SELECT id FROM SpareParts WHERE sku = 'FLT-002'), 1, 850.00, 'Used', DATE_SUB(NOW(), INTERVAL 40 DAY)),
(1005, 1004, (SELECT id FROM SpareParts WHERE sku = 'FLT-002'), 1, 850.00, 'Rejected', DATE_SUB(NOW(), INTERVAL 3 DAY));

-- 7. Labor logged by the mechanic
INSERT IGNORE INTO JobCardLabor (id, job_card_id, mechanic_id, description, hours, hourly_rate, logged_at) VALUES
(1001, 1001, 1002, 'Removed and replaced front brake pads', 1.50, 150.00, DATE_SUB(NOW(), INTERVAL 1 DAY)),
(1002, 1002, 1002, 'OBD-II diagnostic scan and sensor tests', 1.00, 150.00, DATE_SUB(NOW(), INTERVAL 18 HOUR)),
(1003, 1003, 1002, 'Oil and filter change, multi-point inspection', 1.00, 150.00, DATE_SUB(NOW(), INTERVAL 39 DAY)),
(1004, 1004, 1002, 'Wheel balancing and road test', 2.50, 150.00, DATE_SUB(NOW(), INTERVAL 2 DAY));

-- 8. Estimates (1002 awaits the owner's approval)
INSERT IGNORE INTO RepairEstimates (id, job_card_id, total_estimated_cost, status, created_at) VALUES
(1001, 1001, 4575.00, 'Approved', DATE_SUB(NOW(), INTERVAL 2 DAY)),
(1002, 1002, 2250.00, 'Pending Approval', DATE_SUB(NOW(), INTERVAL 12 HOUR)),
(1003, 1003, 1000.00, 'Approved', DATE_SUB(NOW(), INTERVAL 40 DAY)),
(1004, 1004, 375.00, 'Approved', DATE_SUB(NOW(), INTERVAL 3 DAY));

-- 9. Invoices (1003 paid, 1004 ready for pickup and unpaid)
INSERT IGNORE INTO Invoices (id, job_card_id, customer_id, total_amount, pdf_url, status, issued_date, paid_date) VALUES
(1001, 1003, 1001, 1000.00, NULL, 'Paid', DATE_SUB(NOW(), INTERVAL 38 DAY), DATE_SUB(NOW(), INTERVAL 37 DAY)),
(1002, 1004, 1001, 375.00, NULL, 'Unpaid', DATE_SUB(NOW(), INTERVAL 2 HOUR), NULL);

-- 10. Repair timeline (stage may be a mechanic sub-stage such as 'Repairing')
INSERT IGNORE INTO RepairTimeline (id, job_card_id, stage, updated_by, updated_at) VALUES
(1001, 1001, 'Diagnosis', 1002, DATE_SUB(NOW(), INTERVAL 2 DAY)),
(1002, 1001, 'Repairing', 1002, DATE_SUB(NOW(), INTERVAL 1 DAY)),
(1003, 1002, 'Diagnosis', 1002, DATE_SUB(NOW(), INTERVAL 20 HOUR)),
(1004, 1003, 'Diagnosis', 1002, DATE_SUB(NOW(), INTERVAL 40 DAY)),
(1005, 1003, 'Repairing', 1002, DATE_SUB(NOW(), INTERVAL 39 DAY)),
(1006, 1003, 'Testing', 1002, DATE_SUB(NOW(), INTERVAL 38 DAY) - INTERVAL 2 HOUR),
(1007, 1003, 'Completed', 1002, DATE_SUB(NOW(), INTERVAL 38 DAY)),
(1008, 1004, 'Diagnosis', 1002, DATE_SUB(NOW(), INTERVAL 3 DAY)),
(1009, 1004, 'Repairing', 1002, DATE_SUB(NOW(), INTERVAL 2 DAY)),
(1010, 1004, 'Testing', 1002, DATE_SUB(NOW(), INTERVAL 1 DAY)),
(1011, 1004, 'Ready', 1002, DATE_SUB(NOW(), INTERVAL 3 HOUR));

-- 11. Photos (existing images under assets/images)
INSERT IGNORE INTO JobCardPhotos (id, job_card_id, photo_url, type, description, uploaded_at) VALUES
(1001, 1001, '../../assets/images/repair-gallery-1.jpg', 'Before', 'Worn front brake pads', DATE_SUB(NOW(), INTERVAL 2 DAY)),
(1002, 1001, '../../assets/images/repair-gallery-2.jpg', 'Progress', 'New pads fitted', DATE_SUB(NOW(), INTERVAL 1 DAY)),
(1003, 1002, '../../assets/images/engine.jpg', 'Issue', 'Engine bay during diagnostics', DATE_SUB(NOW(), INTERVAL 18 HOUR)),
(1004, 1003, '../../assets/images/repair-gallery-3.jpg', 'After', 'Service completed', DATE_SUB(NOW(), INTERVAL 38 DAY));

-- 12. Additional faults (block format parsed by api/mechanic/mechanic-data.php)
INSERT IGNORE INTO AdditionalFaults (id, job_card_id, description, reported_at) VALUES
(1001, 1001, CONCAT('[Medium] Braking System: Rear rotor scoring\n',
    'Light scoring on both rear rotors, still within tolerance.\n',
    'Recommendation: Resurface rotors at next service\n',
    'Estimated cost: ৳1,800.00\n',
    'Reported by Mechanic User on ', DATE_FORMAT(DATE_SUB(NOW(), INTERVAL 1 DAY), '%Y-%m-%d %H:%i')),
    DATE_SUB(NOW(), INTERVAL 1 DAY)),
(1002, 1002, CONCAT('[High] Engine: Upstream O2 sensor reading low\n',
    'Sensor voltage stays below 0.2 V at idle.\n',
    'Recommendation: Replace upstream O2 sensor\n',
    'Reported by Mechanic User on ', DATE_FORMAT(DATE_SUB(NOW(), INTERVAL 16 HOUR), '%Y-%m-%d %H:%i')),
    DATE_SUB(NOW(), INTERVAL 16 HOUR));

-- 13. Chat: owner <-> mechanic
INSERT IGNORE INTO ChatMessages (id, sender_id, receiver_id, message_text, is_read, sent_at) VALUES
(1001, 1001, 1002, 'Hi, how is the brake job on the Corolla going?', TRUE, DATE_SUB(NOW(), INTERVAL 5 HOUR)),
(1002, 1002, 1001, 'New front pads are on. I am finishing up and it should be ready tomorrow afternoon.', FALSE, DATE_SUB(NOW(), INTERVAL 4 HOUR));

-- Chat with the first manager (skipped when there is no manager)
INSERT IGNORE INTO ChatMessages (id, sender_id, receiver_id, message_text, is_read, sent_at)
SELECT 1003, 1001, m.id, 'Can I pick up the X-Trail this evening?', TRUE, DATE_SUB(NOW(), INTERVAL 3 HOUR)
  FROM Users m WHERE m.role = 'Manager' ORDER BY m.id LIMIT 1;
INSERT IGNORE INTO ChatMessages (id, sender_id, receiver_id, message_text, is_read, sent_at)
SELECT 1004, m.id, 1001, 'Yes, it is ready. The invoice is in your account.', FALSE, DATE_SUB(NOW(), INTERVAL 2 HOUR)
  FROM Users m WHERE m.role = 'Manager' ORDER BY m.id LIMIT 1;
INSERT IGNORE INTO ChatMessages (id, sender_id, receiver_id, message_text, is_read, sent_at)
SELECT 1005, 1002, m.id, 'Requested an oil filter for the Corolla brake job, please approve.', FALSE, DATE_SUB(NOW(), INTERVAL 1 HOUR)
  FROM Users m WHERE m.role = 'Manager' ORDER BY m.id LIMIT 1;
