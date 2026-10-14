-- AutoCare Optimal Database Seed Data

SET FOREIGN_KEY_CHECKS = 0;

-- 1. Users
-- password_hash is a dummy hash for this seed data
INSERT INTO Users (id, name, email, password_hash, phone, role, status) VALUES 
(1, 'System Admin', 'admin@autocare.com', 'hashed_password_123', '555-0101', 'Admin', 'Active'),
(2, 'John Manager', 'manager@autocare.com', 'hashed_password_123', '555-0102', 'Manager', 'Active'),
(3, 'Mike Mechanic', 'mechanic@autocare.com', 'hashed_password_123', '555-0103', 'Mechanic', 'Active'),
(4, 'Sarah Mechanic', 'sarah.mech@autocare.com', 'hashed_password_123', '555-0104', 'Mechanic', 'Active'),
(5, 'Alice Owner', 'owner@autocare.com', 'hashed_password_123', '555-0201', 'VehicleOwner', 'Active'),
(6, 'Bob Owner', 'bob.owner@example.com', 'hashed_password_123', '555-0202', 'VehicleOwner', 'Active');

-- 2. Workshops
INSERT INTO Workshops (id, name, location, manager_id) VALUES 
(1, 'AutoCare Central Downtown', '123 Main St, Downtown', 2),
(2, 'AutoCare Northside', '456 North Ave, Northside', 2);

-- 3. Vehicles
INSERT INTO Vehicles (id, owner_id, make, model, year, license_plate, vin) VALUES 
(1, 5, 'Toyota', 'Camry', 2018, 'XYZ-1234', '1TABCDEF1234567'),
(2, 5, 'Honda', 'Civic', 2020, 'ABC-9876', '2HABCDEF9876543'),
(3, 6, 'Ford', 'Mustang', 2015, 'FST-5555', '1FABCDEF5555555');

-- 4. Service Categories
INSERT INTO ServiceCategories (id, name, description, base_rate) VALUES 
(1, 'General Servicing', 'Oil change, filter replacement, and general checkup', 49.99),
(2, 'Brake Repair', 'Brake pad replacement, rotor resurfacing, brake fluid flush', 149.99),
(3, 'Engine Diagnostics', 'Computer diagnostic check for check engine light', 89.99),
(4, 'Transmission Service', 'Transmission fluid flush and filter change', 199.99),
(5, 'Tire Services', 'Tire rotation, balancing, and alignment', 79.99);

-- 5. Appointments
INSERT INTO Appointments (id, owner_id, vehicle_id, workshop_id, service_category_id, preferred_date, issue_description, status) VALUES 
(1, 5, 1, 1, 1, DATE_ADD(CURRENT_DATE(), INTERVAL 2 DAY), 'Time for regular oil change and checkup', 'Approved'),
(2, 6, 3, 1, 3, DATE_ADD(CURRENT_DATE(), INTERVAL 1 DAY), 'Check engine light came on yesterday', 'Approved'),
(3, 5, 2, 2, 2, DATE_ADD(CURRENT_DATE(), INTERVAL 5 DAY), 'Brakes are squeaking when stopping', 'Pending');

-- 6. Job Cards
INSERT INTO JobCards (id, appointment_id, manager_id, mechanic_id, status, fault_report, estimated_cost, start_date) VALUES 
(1, 1, 2, 3, 'In Progress', 'Standard wear on oil filter', 65.00, CURRENT_TIMESTAMP),
(2, 2, 2, 4, 'Diagnosis', 'Engine misfire detected', 250.00, CURRENT_TIMESTAMP);

-- 7. Spare Parts (Adding more parts specific to the workshops)
INSERT INTO SpareParts (id, workshop_id, sku, name, category, price, stock_quantity, reorder_level, supplier) VALUES 
(5, 1, 'OIL-5W30', 'Synthetic Engine Oil 5W-30', 'Fluids & Filters', 25.00, 50, 15, 'Mobil1'),
(6, 1, 'WIP-001', 'Windshield Wiper Blades 22"', 'Accessories', 15.50, 40, 10, 'Bosch'),
(7, 2, 'BRK-002', 'Ceramic Brake Pads', 'Brakes & Suspension', 4500.00, 20, 5, 'Brembo');

-- 8. Repair Estimates
INSERT INTO RepairEstimates (id, job_card_id, total_estimated_cost, status) VALUES 
(1, 1, 65.00, 'Approved'),
(2, 2, 250.00, 'Pending Approval');

-- 9. Job Card Parts
INSERT INTO JobCardParts (job_card_id, part_id, quantity, unit_price, status) VALUES 
(1, 1, 1, 3500.00, 'Approved'), 
(1, 5, 1, 25.00, 'Used');

-- 10. Job Card Labor
INSERT INTO JobCardLabor (job_card_id, mechanic_id, description, hours, hourly_rate) VALUES 
(1, 3, 'Oil change and filter replacement', 1.0, 40.00),
(2, 4, 'Computer diagnostic and code reading', 0.5, 80.00);

-- 11. Repair Timeline
INSERT INTO RepairTimeline (job_card_id, stage, updated_by) VALUES 
(1, 'Diagnosis', 3),
(1, 'In Progress', 3),
(2, 'Diagnosis', 4);

-- 12. Job Card Photos
INSERT INTO JobCardPhotos (job_card_id, photo_url, type, description) VALUES 
(1, 'https://example.com/photos/before_oil.jpg', 'Before', 'Old oil filter condition'),
(2, 'https://example.com/photos/engine_code.jpg', 'Progress', 'Diagnostic tool reading error code P0301');

-- 13. Additional Faults Found
INSERT INTO AdditionalFaults (job_card_id, description) VALUES 
(1, 'Noticed front left tire tread is low, recommend replacement soon.');

-- 14. Invoices
INSERT INTO Invoices (job_card_id, customer_id, total_amount, pdf_url, status) VALUES 
(1, 5, 65.00, 'https://example.com/invoices/INV-1.pdf', 'Unpaid');

-- 15. Chat Messages
INSERT INTO ChatMessages (sender_id, receiver_id, message_text, is_read) VALUES 
(5, 2, 'Hi, I dropped off my Camry earlier. Just checking if you need anything else from me?', TRUE),
(2, 5, 'Hello Alice, we have everything we need. Mike is starting the oil change now.', FALSE);

-- 16. Service Broadcasts
INSERT INTO ServiceBroadcasts (admin_id, title, content, audience, priority, status) VALUES 
(1, 'System Maintenance', 'The platform will be down for 30 minutes tonight at 2 AM for an upgrade.', 'All', 'High', 'Published');

-- 17. Service Offers
INSERT INTO ServiceOffers (title, description, discount_percentage, valid_from, valid_until, status) VALUES 
('Winter Readiness Package', 'Get 15% off on battery check, anti-freeze top-up, and wiper replacements.', 15.00, CURRENT_DATE(), DATE_ADD(CURRENT_DATE(), INTERVAL 30 DAY), 'Active');

SET FOREIGN_KEY_CHECKS = 1;
