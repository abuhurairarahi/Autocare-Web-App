-- 1. Insert Users (Admin, Managers, Mechanics, VehicleOwners)
-- Passwords should be hashed in a real scenario. For demonstration, we use plain text or mock hashes.
INSERT INTO Users (name, email, password_hash, role, phone) VALUES 
('Pranta Biswas', 'admin@autocare.com', 'hashed_pwd_123', 'Admin', '01711223344'),
('Ariful Islam', 'ariful@autocare.com', 'hashed_pwd_123', 'Manager', '01711223345'),
('Sadia Rahman', 'sadia@autocare.com', 'hashed_pwd_123', 'Manager', '01811223345'),
('Tariqul Hasan', 'tariqul@autocare.com', 'hashed_pwd_123', 'Manager', '01911223345'),
('Karim Uddin', 'karim@autocare.com', 'hashed_pwd_123', 'Mechanic', '01722334455'),
('Rahim Mia', 'rahim@autocare.com', 'hashed_pwd_123', 'Mechanic', '01822334455'),
('Jamal Hossain', 'jamal@autocare.com', 'hashed_pwd_123', 'Mechanic', '01922334455'),
('Anisur Rahman', 'anisur@example.com', 'hashed_pwd_123', 'VehicleOwner', '01733445566'),
('Farhana Akter', 'farhana@example.com', 'hashed_pwd_123', 'VehicleOwner', '01833445566'),
('Nazmul Huda', 'nazmul@example.com', 'hashed_pwd_123', 'VehicleOwner', '01933445566');

-- 2. Insert Workshops
-- Assuming manager_id references Users 2, 3, 4
INSERT INTO Workshops (name, location, manager_id) VALUES 
('AutoCare Dhanmondi Branch', 'Dhanmondi 27, Dhaka', 2),
('AutoCare Gulshan Branch', 'Gulshan 1, Dhaka', 3),
('AutoCare Uttara Branch', 'Sector 7, Uttara, Dhaka', 4);

-- 3. Insert Vehicles
-- owner_id references Users 8, 9, 10
INSERT INTO Vehicles (owner_id, make, model, year, license_plate) VALUES 
(8, 'Toyota', 'Corolla', 2018, 'DHA-11-2345'),
(9, 'Honda', 'Civic', 2020, 'DHA-12-9876'),
(10, 'Hyundai', 'Tucson', 2021, 'CHT-14-5544');

-- 4. Insert Service Categories
INSERT INTO ServiceCategories (name, description) VALUES 
('Engine Diagnostics & Repair', 'Comprehensive engine health checks and overhauls.'),
('Electrical Systems', 'Battery replacement, wiring, and ECU diagnostics.'),
('Denting & Painting', 'Scratch removal, full body resprays, and ceramic coating.'),
('AC & HVAC Services', 'Compressor replacement, leak checks, and AC gas refill.');

-- 5. Insert Appointments
INSERT INTO Appointments (owner_id, vehicle_id, workshop_id, service_category_id, preferred_date, issue_description, status) VALUES 
(8, 1, 1, 1, '2026-10-10 10:00:00', 'Engine making a knocking sound', 'Approved'),
(9, 2, 2, 4, '2026-10-12 14:00:00', 'AC not cooling properly', 'Pending'),
(10, 3, 3, 3, '2026-10-15 09:30:00', 'Scratches on the left rear door', 'Completed');

-- 6. Insert Job Cards
INSERT INTO JobCards (appointment_id, mechanic_id, status, fault_report, estimated_cost, start_date) VALUES 
(1, 5, 'In Progress', 'Spark plugs worn out, timing belt loose.', 8500.00, '2026-10-10 10:30:00'),
(3, 7, 'Completed', 'Painted and buffed. Looks brand new.', 12500.00, '2026-10-15 10:00:00');

-- 7. Insert Spare Parts Inventory
INSERT INTO SpareParts (workshop_id, sku, name, category, price, stock_quantity, reorder_level, supplier) VALUES 
(1, 'SKU-1001', 'BOSCH Spark Plug', 'Engine', 1200.00, 50, 15, 'Rahim Motors'),
(1, 'SKU-1002', 'Toyota Synthetic Oil 5W-30', 'Consumables', 4500.00, 20, 10, 'Navana'),
(2, 'SKU-2001', 'Denso AC Compressor', 'HVAC', 15000.00, 3, 5, 'AutoParts BD'),
(3, 'SKU-3001', '3M Ceramic Coating Kit', 'Exterior', 8500.00, 10, 5, 'CarCare BD');

-- 8. Insert Invoices
INSERT INTO Invoices (job_id, customer_id, total_amount, status, issued_date) VALUES 
(1, 8, 8500.00, 'Unpaid', '2026-10-10 12:00:00'),
(2, 10, 12500.00, 'Paid', '2026-10-15 14:30:00');

-- 9. Insert Service Broadcasts (Admin Notices)
INSERT INTO ServiceBroadcasts (title, content, audience, priority, status) VALUES 
('Scheduled Server Reboot', 'Maintenance downtime scheduled for midnight.', 'All', 'High', 'Scheduled'),
('New Diagnostic Tool v2.1', 'The new OBD2 scanners have arrived. Please collect from inventory.', 'Mechanics', 'Medium', 'Published'),
('Holiday Office Closure', 'Offices will be closed during Eid holidays.', 'All', 'Low', 'Completed');

-- 10. Insert Service Offers
INSERT INTO ServiceOffers (title, description, discount_percentage, valid_until, status) VALUES 
('Eid Special AC Servicing', 'Flat 20% off on all AC checks before Eid.', 20.00, '2027-12-31', 'Active'),
('Winter Engine Oil Change', '15% discount on synthetic engine oils.', 15.00, '2026-12-30', 'Scheduled');
