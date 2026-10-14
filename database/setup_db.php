<?php
$host = 'localhost';
$dbname = 'autocare';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Create database if not exists
    $pdo->exec("CREATE DATABASE IF NOT EXISTS `$dbname` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;");
    $pdo->exec("USE `$dbname`;");

    // Read and run autocare-database.sql
    $sql = file_get_contents(__DIR__ . '/autocare-database.sql');
    $pdo->exec($sql);
    echo "Schema executed successfully.\n";

    // Add any missing columns to existing tables if needed (safe alter)
    $alterStatements = [
        "ALTER TABLE Users ADD COLUMN IF NOT EXISTS specialty VARCHAR(100) DEFAULT NULL",
        "ALTER TABLE Users ADD COLUMN IF NOT EXISTS experience VARCHAR(50) DEFAULT NULL",
        "ALTER TABLE Users ADD COLUMN IF NOT EXISTS status VARCHAR(50) DEFAULT 'Available'",
        "ALTER TABLE Users ADD COLUMN IF NOT EXISTS avatar VARCHAR(255) DEFAULT NULL",
        "ALTER TABLE Users ADD COLUMN IF NOT EXISTS workload INT DEFAULT 0",
        
        "ALTER TABLE ServiceCategories ADD COLUMN IF NOT EXISTS base_rate DECIMAL(10,2) DEFAULT 0.00",

        "ALTER TABLE Appointments ADD COLUMN IF NOT EXISTS code VARCHAR(50) NULL",
        "ALTER TABLE Appointments ADD COLUMN IF NOT EXISTS priority ENUM('Low', 'Normal', 'High') DEFAULT 'Normal'",

        "ALTER TABLE JobCards ADD COLUMN IF NOT EXISTS code VARCHAR(50) NULL",
        "ALTER TABLE JobCards ADD COLUMN IF NOT EXISTS work_order VARCHAR(50) NULL",
        "ALTER TABLE JobCards ADD COLUMN IF NOT EXISTS manager_id INT NULL",
        "ALTER TABLE JobCards ADD COLUMN IF NOT EXISTS kanban_stage ENUM('PENDING', 'IN PROGRESS', 'COMPLETED') DEFAULT 'PENDING'",
        "ALTER TABLE JobCards ADD COLUMN IF NOT EXISTS progress_percentage INT DEFAULT 10",
        "ALTER TABLE JobCards ADD COLUMN IF NOT EXISTS service_text TEXT NULL",
        "ALTER TABLE JobCards ADD COLUMN IF NOT EXISTS delivery_date VARCHAR(50) DEFAULT NULL",

        "ALTER TABLE SpareParts ADD COLUMN IF NOT EXISTS unit VARCHAR(50) DEFAULT 'Units'",

        "ALTER TABLE JobParts ADD COLUMN IF NOT EXISTS total_price DECIMAL(10,2) DEFAULT 0.00",
        "ALTER TABLE JobParts ADD COLUMN IF NOT EXISTS status ENUM('Pending Approval', 'Approved', 'Rejected') DEFAULT 'Pending Approval'",
        "ALTER TABLE JobParts ADD COLUMN IF NOT EXISTS rejection_reason VARCHAR(255) NULL",
        "ALTER TABLE JobParts ADD COLUMN IF NOT EXISTS requested_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP",

        "ALTER TABLE Invoices ADD COLUMN IF NOT EXISTS invoice_number VARCHAR(50) NULL",
        "ALTER TABLE Invoices ADD COLUMN IF NOT EXISTS pdf_url VARCHAR(255) NULL",

        "ALTER TABLE ChatMessages ADD COLUMN IF NOT EXISTS job_tag VARCHAR(50) NULL"
    ];

    foreach ($alterStatements as $alter) {
        try {
            $pdo->exec($alter);
        } catch (Exception $e) {
            // Ignore if column already exists or syntax differs
        }
    }

    // Now seed data if empty
    $checkUsers = $pdo->query("SELECT COUNT(*) FROM Users")->fetchColumn();
    if ($checkUsers == 0) {
        echo "Seeding database with comprehensive data...\n";

        // 1. Users
        $usersSql = "INSERT INTO Users (user_id, name, email, password_hash, role, phone, specialty, experience, status, avatar, workload) VALUES
        (1, 'System Admin', 'admin@autocare.com', '" . password_hash('admin123', PASSWORD_DEFAULT) . "', 'Admin', '+880 1711-000111', NULL, NULL, 'Active', NULL, 0),
        (2, 'Alex Johnson', 'alex.j@autocare.com', '" . password_hash('manager123', PASSWORD_DEFAULT) . "', 'Manager', '+880 1711-222333', 'Workshop Manager', '10 Years', 'Active', 'https://images.unsplash.com/photo-1534528741775-53994a69daeb?auto=format&fit=crop&q=80&w=150', 0),
        (3, 'David Chui', 'david.c@autocare.com', '" . password_hash('mechanic123', PASSWORD_DEFAULT) . "', 'Mechanic', '+880 1711-333444', 'Engine Specialist', '8 Years', 'Available', 'https://images.unsplash.com/photo-1560250097-0b93528c311a?auto=format&fit=crop&q=80&w=150', 20),
        (4, 'Sarah Jenkins', 'sarah.j@autocare.com', '" . password_hash('mechanic123', PASSWORD_DEFAULT) . "', 'Mechanic', '+880 1711-444555', 'Electrician', '5 Years', 'Busy', 'https://images.unsplash.com/photo-1573496359142-b8d87734a5a2?auto=format&fit=crop&q=80&w=150', 90),
        (5, 'Marc Lowe', 'marc.l@autocare.com', '" . password_hash('mechanic123', PASSWORD_DEFAULT) . "', 'Mechanic', '+880 1711-555666', 'Suspension Spec.', '3 Years', 'Off Shift', 'https://images.unsplash.com/photo-1534528741775-53994a69daeb?auto=format&fit=crop&q=80&w=150', 0),
        (6, 'Kamal H.', 'kamal.h@autocare.com', '" . password_hash('mechanic123', PASSWORD_DEFAULT) . "', 'Mechanic', '+880 1711-666777', 'Transmission Expert', '7 Years', 'Busy', 'https://i.pravatar.cc/100?img=12', 65),
        (7, 'Rafiq M.', 'rafiq.m@autocare.com', '" . password_hash('mechanic123', PASSWORD_DEFAULT) . "', 'Mechanic', '+880 1711-777888', 'Diagnostic Spec.', '4 Years', 'Busy', 'https://i.pravatar.cc/100?img=33', 90),
        (8, 'Mike Davis', 'mike.d@autocare.com', '" . password_hash('mechanic123', PASSWORD_DEFAULT) . "', 'Mechanic', '+880 1711-888999', 'Brake Specialist', '6 Years', 'Busy', 'https://images.unsplash.com/photo-1534528741775-53994a69daeb?auto=format&fit=crop&w=80&q=80', 80),
        (101, 'Apex Logistics', 'ops@apexlogistics.com', '" . password_hash('owner123', PASSWORD_DEFAULT) . "', 'VehicleOwner', '(555) 123-4567', NULL, NULL, 'Active', NULL, 0),
        (102, 'Sarah Connor', 'sarah.c@gmail.com', '" . password_hash('owner123', PASSWORD_DEFAULT) . "', 'VehicleOwner', '(555) 345-6789', NULL, NULL, 'Active', NULL, 0),
        (103, 'Rahim Uddin', 'rahim.u@gmail.com', '" . password_hash('owner123', PASSWORD_DEFAULT) . "', 'VehicleOwner', '+880 1819-112233', NULL, NULL, 'Active', NULL, 0),
        (104, 'Enterprise Logistics Co.', 'contact@enterpriselog.com', '" . password_hash('owner123', PASSWORD_DEFAULT) . "', 'VehicleOwner', '+880 1912-334455', NULL, NULL, 'Active', NULL, 0),
        (105, 'Nusrat Jahan', 'nusrat.j@hotmail.com', '" . password_hash('owner123', PASSWORD_DEFAULT) . "', 'VehicleOwner', '+880 1712-998877', NULL, NULL, 'Active', NULL, 0),
        (106, 'Acme Logistics Corp', 'contact@acme.com', '" . password_hash('owner123', PASSWORD_DEFAULT) . "', 'VehicleOwner', '(555) 987-6543', NULL, NULL, 'Active', NULL, 0),
        (107, 'Global Express', 'billing@globalexp.net', '" . password_hash('owner123', PASSWORD_DEFAULT) . "', 'VehicleOwner', '(555) 456-7890', NULL, NULL, 'Active', NULL, 0),
        (108, 'Metro Transport', 'accounts@metro.com', '" . password_hash('owner123', PASSWORD_DEFAULT) . "', 'VehicleOwner', '(555) 678-1234', NULL, NULL, 'Active', NULL, 0);";
        $pdo->exec($usersSql);

        // 2. Workshops
        $pdo->exec("INSERT INTO Workshops (workshop_id, name, location, manager_id) VALUES
        (1, 'AutoCare Central Hub', 'Mirpur Road, Dhaka', 2),
        (2, 'AutoCare Gulshan Bay', 'Gulshan Avenue, Dhaka', 2),
        (3, 'AutoCare Chattogram Depot', 'Agrabad C/A, Chattogram', 2);");

        // 3. Vehicles
        $pdo->exec("INSERT INTO Vehicles (vehicle_id, owner_id, make, model, year, license_plate, vin) VALUES
        (1, 101, 'Ford', 'Transit 350 (2018)', 2018, 'LND-294-K', '1FTYR2XG2JKA89123'),
        (2, 102, 'Toyota', 'Camry (2021)', 2021, 'NYC-882-M', '4T1B11HK5MU12345'),
        (3, 103, 'Toyota', 'Corolla (2018)', 2018, 'Dhaka-Metro-Ga-12-3456', 'JT2BF22K1W0678912'),
        (4, 104, 'Hino', 'Dutro Truck', 2019, 'Chattogram-Na-11-2233', 'JH4DB7550SS991122'),
        (5, 105, 'Honda', 'Vezel (2020)', 2020, 'Sylhet-Gha-15-9988', 'RU3-1209384556677'),
        (6, 106, 'Freightliner', 'Cascadia', 2020, 'TEX-901-TR', '1FUJ8902B19827364'),
        (7, 107, 'Kenworth', 'T680', 2021, 'CAL-442-KW', '1XK4419C298371928'),
        (8, 108, 'Volvo', 'VNL 860', 2022, 'FLA-108-VL', '4V40021D384729182'),
        (9, 101, 'Ford', 'F-150 (2019)', 2019, 'LND-551-F', '1FTEW1E45KFA12984');");

        // 4. Service Categories
        $pdo->exec("INSERT INTO ServiceCategories (category_id, name, description, base_rate) VALUES
        (1, 'Engine Diagnostics & Repair', 'Comprehensive diagnostics, tuning, sensor overhaul', 1500.00),
        (2, 'Transmission Overhaul', 'Gearbox repair, fluid flush, torque converter replacement', 3500.00),
        (3, 'Brake System Service', 'Brake pad replacement, rotor machining, caliper service', 1200.00),
        (4, 'Electrical & Sensor Tuning', 'ECU mapping, wiring repair, alternator & battery check', 1800.00),
        (5, 'Routine Periodic Maintenance', 'Engine oil, filter replacement, suspension lubrications', 800.00);");

        // 5. Appointments (Booking Requests)
        $pdo->exec("INSERT INTO Appointments (appointment_id, code, owner_id, vehicle_id, workshop_id, service_category_id, preferred_date, issue_description, priority, status) VALUES
        (145, 'BRQ-2023-145', 101, 1, 1, 2, '2026-10-24 09:00:00', 'Driver reports harsh shifting between 2nd and 3rd gear. Occasional slipping when under heavy load. Check transmission fluid levels and perform diagnostic.', 'High', 'Pending'),
        (146, 'BRQ-2023-146', 102, 2, 1, 5, '2026-10-25 14:30:00', 'Scheduled standard 50k miles inspection. Oil change, cabin air filter replacement, wheel alignment, and check front brake pad wear.', 'Normal', 'Pending'),
        (147, 'BRQ-2023-147', 103, 3, 1, 1, '2026-10-26 11:00:00', 'Engine check light illuminated with code P0304. Rough idling during cold start in morning.', 'Normal', 'Pending'),
        (148, 'BRQ-2023-148', 105, 5, 1, 4, '2026-10-26 15:00:00', 'Battery drainage issue. Vehicle fails to crank after sitting for more than 24 hours. Suspected parasitic electrical draw.', 'High', 'Pending');");

        // 6. Job Cards
        $pdo->exec("INSERT INTO JobCards (job_id, code, work_order, appointment_id, manager_id, mechanic_id, status, kanban_stage, progress_percentage, fault_report, service_text, estimated_cost, delivery_date, start_date) VALUES
        (1045, 'JC-1045', '#WO-2049', NULL, 2, 6, 'Repairing', 'IN PROGRESS', 65, 'Harsh shifting, slipping in 3rd gear. Transmission clutch packs worn.', 'Engine Diagnostic & Cylinder Misfire Repair', 12500.00, 'Oct 28, 2026', '2026-10-24 09:30:00'),
        (1046, 'JC-1046', '#WO-2051', NULL, 2, 8, 'Diagnosis', 'PENDING', 30, 'Low brake fluid and grinding noise in front calipers.', 'Brake Pad Replacement & Transmission Overhaul', 45000.00, 'Oct 30, 2026', '2026-10-25 10:15:00'),
        (1042, 'JC-1042', '#WO-2042', NULL, 2, 7, 'Testing', 'IN PROGRESS', 90, 'ECU communication fault code stored. Sensor replaced, undergoing road testing.', 'Electrical Sensor Tuning & Quality Control', 8200.00, 'Oct 26, 2026', '2026-10-23 11:20:00'),
        (1040, 'JC-1040', '#WO-2038', NULL, 2, 3, 'Diagnosis', 'PENDING', 15, 'Front rotors warped beyond spec threshold. Resurfacing required.', 'Brake Rotor Machining', 6500.00, 'Oct 27, 2026', '2026-10-24 14:00:00');");

        // 7. Spare Parts
        $pdo->exec("INSERT INTO SpareParts (part_id, workshop_id, sku, name, category, price, stock_quantity, reorder_level, unit, supplier) VALUES
        (1, 1, 'BP-2049-F', 'Brake Pad Set - Front', 'Brakes', 85.00, 45, 10, 'Sets', 'Brembo BD'),
        (2, 1, 'MO-5W30-SYN', 'Synthetic Motor Oil 5W-30', 'Consumables', 9.50, 140, 25, 'Quarts', 'Mobil 1 BD'),
        (3, 1, 'ALT-130-OEM', 'Alternator Assembly - 130A', 'Electrical', 345.00, 4, 5, 'Unit', 'Denso OEM'),
        (4, 1, 'TR-KIT-889', 'Transmission Overhaul Kit (OEM)', 'Transmission', 2800.00, 6, 3, 'Kit', 'Aisin BD'),
        (5, 1, 'EXH-MAN-F150', 'Exhaust Manifold (Cracked Replacement)', 'Exhaust', 420.00, 3, 2, 'Pcs', 'Ford Genuine Parts'),
        (6, 1, 'FI-NOZ-V6', 'Fuel Injector Nozzle Set', 'Fuel System', 180.00, 12, 5, 'Sets', 'Bosch BD'),
        (7, 1, 'SUS-STR-221', 'Suspension Strut Assembly', 'Suspension', 220.00, 8, 4, 'Unit', 'KYB Corp');");

        // 8. Job Parts (Parts Requests)
        $pdo->exec("INSERT INTO JobParts (job_part_id, job_id, part_id, quantity, unit_price, total_price, status, requested_at) VALUES
        (1, 1045, 1, 2, 85.00, 170.00, 'Pending Approval', '2026-10-24 10:15:00'),
        (2, 1046, 2, 6, 9.50, 57.00, 'Pending Approval', '2026-10-24 11:30:00'),
        (3, 1042, 3, 1, 345.00, 345.00, 'Pending Approval', '2026-10-24 13:45:00');");

        // 9. Repair Cost Estimates
        $est1Items = json_encode([
            ['description' => 'Diagnostic Labor', 'hours_or_qty' => 3, 'unit_price' => 150.00, 'total' => 450.00],
            ['description' => 'Transmission Kit (OEM)', 'hours_or_qty' => 1, 'unit_price' => 2800.00, 'total' => 2800.00]
        ]);
        $est2Items = json_encode([
            ['description' => 'Front Struts Pair', 'hours_or_qty' => 2, 'unit_price' => 440.00, 'total' => 880.00],
            ['description' => 'Labor (2.5 hrs)', 'hours_or_qty' => 2.5, 'unit_price' => 128.20, 'total' => 320.50]
        ]);
        $est3Items = json_encode([
            ['description' => 'Brake Drum Replacement', 'hours_or_qty' => 4, 'unit_price' => 1200.00, 'total' => 4800.00],
            ['description' => 'Air Brake Line Servicing', 'hours_or_qty' => 1, 'unit_price' => 2500.00, 'total' => 2500.00],
            ['description' => 'Fleet Certified Labor', 'hours_or_qty' => 8, 'unit_price' => 200.00, 'total' => 1600.00]
        ]);

        $pdo->prepare("INSERT INTO RepairEstimates (estimate_id, code, job_id, status, sent_date, line_items, subtotal, tax_rate, tax_amount, total_estimated_cost) VALUES
        (1, 'EST-2026-001', 1042, 'Draft', '-', ?, 3250.00, 0.085, 276.25, 3526.25),
        (2, 'EST-2026-002', 1045, 'Sent', 'Oct 12, 2026', ?, 1200.50, 0.085, 102.04, 1302.54),
        (3, 'EST-2026-003', 1046, 'Send to Customer', 'Oct 10, 2026', ?, 8900.00, 0.085, 756.50, 9656.50)")
        ->execute([$est1Items, $est2Items, $est3Items]);

        // 10. Invoices
        $pdo->exec("INSERT INTO Invoices (invoice_id, invoice_number, job_id, customer_id, total_amount, status, issued_date) VALUES
        (89, 'INV-2026-089', 1042, 106, 3240.50, 'Paid', '2026-10-24 10:00:00'),
        (90, 'INV-2026-090', 1045, 107, 1150.00, 'Pending', '2026-10-26 14:00:00'),
        (85, 'INV-2026-085', 1040, 108, 4890.75, 'Overdue', '2026-10-12 09:00:00'),
        (91, 'INV-2026-091', 1046, 101, 2450.00, 'Pending', '2026-10-27 16:30:00');");

        // 11. Chat Messages
        $pdo->exec("INSERT INTO ChatMessages (message_id, sender_id, receiver_id, job_tag, message_text, attachment_url, is_read, created_at) VALUES
        (1, 8, 2, 'JOB #8492', 'Hey boss, I got the F-150 up on the lift. Diagnostic showed misfire on cylinder 4, but while inspecting I found something else.', NULL, 1, '2026-10-24 10:30:00'),
        (2, 2, 8, 'JOB #8492', 'Copy that, Mike. What did you find? Is it going to affect the estimate for Fleet Logistics?', NULL, 1, '2026-10-24 10:35:00'),
        (3, 8, 2, 'JOB #8492', 'Found a cracked exhaust manifold. Pretty bad. Sending pics now. We will definitely need to update the estimate.', '[\"../../assets/images/engine.jpg\", \"../../assets/images/shop.jpg\"]', 1, '2026-10-24 10:42:00');");

        // 12. Repair Timeline
        $pdo->exec("INSERT INTO RepairTimeline (timeline_id, job_id, stage, updated_by, updated_at) VALUES
        (1, 1045, 'Diagnosis', 2, '2026-10-24 09:30:00'),
        (2, 1045, 'Repairing', 6, '2026-10-24 14:00:00'),
        (3, 1042, 'Quality Control', 7, '2026-10-25 11:20:00');");

        // 13. Activity Logs
        $pdo->exec("INSERT INTO ActivityLogs (text, type, subtext) VALUES
        ('Job Card <strong>#JC-1042</strong> marked as Quality Control by Rafiq M.', 'blue', 'Honda Vezel'),
        ('Spare Parts Approval requested for Job Card <strong>#JC-1045</strong>.', 'red', 'Toyota Camry'),
        ('Invoice <strong>#INV-2026-089</strong> paid by customer.', 'gray', '৳3,240.50'),
        ('New Booking Request <strong>BRQ-2023-145</strong> submitted by Apex Logistics.', 'blue', 'Ford Transit');");

        echo "Seed data inserted successfully.\n";
    } else {
        echo "Database already contains users. Skipping seed insert.\n";
    }

} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}
