-- 1. Users Table (Handles Admin, Manager, Mechanic, VehicleOwner)
CREATE TABLE IF NOT EXISTS Users (
    user_id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('Admin', 'Manager', 'Mechanic', 'VehicleOwner') NOT NULL,
    phone VARCHAR(20),
    specialty VARCHAR(100) DEFAULT NULL,
    experience VARCHAR(50) DEFAULT NULL,
    status VARCHAR(50) DEFAULT 'Available',
    avatar VARCHAR(255) DEFAULT NULL,
    workload INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- 2. Workshops Table
CREATE TABLE IF NOT EXISTS Workshops (
    workshop_id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    location VARCHAR(255) NOT NULL,
    manager_id INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (manager_id) REFERENCES Users(user_id) ON DELETE SET NULL
);

-- 3. Vehicles Table
CREATE TABLE IF NOT EXISTS Vehicles (
    vehicle_id INT AUTO_INCREMENT PRIMARY KEY,
    owner_id INT NOT NULL,
    make VARCHAR(50) NOT NULL,
    model VARCHAR(50) NOT NULL,
    year INT NOT NULL,
    license_plate VARCHAR(20) UNIQUE NOT NULL,
    vin VARCHAR(50) UNIQUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (owner_id) REFERENCES Users(user_id) ON DELETE CASCADE
);

-- 4. Service Categories
CREATE TABLE IF NOT EXISTS ServiceCategories (
    category_id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    base_rate DECIMAL(10, 2) DEFAULT 0.00,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 5. Appointments (Booking Requests)
CREATE TABLE IF NOT EXISTS Appointments (
    appointment_id INT AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(50) UNIQUE,
    owner_id INT NOT NULL,
    vehicle_id INT NOT NULL,
    workshop_id INT NOT NULL,
    service_category_id INT,
    preferred_date DATETIME NOT NULL,
    issue_description TEXT,
    priority ENUM('Low', 'Normal', 'High') DEFAULT 'Normal',
    status ENUM('Pending', 'Approved', 'Rejected', 'Completed', 'Cancelled') DEFAULT 'Pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (owner_id) REFERENCES Users(user_id),
    FOREIGN KEY (vehicle_id) REFERENCES Vehicles(vehicle_id),
    FOREIGN KEY (workshop_id) REFERENCES Workshops(workshop_id),
    FOREIGN KEY (service_category_id) REFERENCES ServiceCategories(category_id)
);

-- 6. Job Cards (Repair Orders)
CREATE TABLE IF NOT EXISTS JobCards (
    job_id INT AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(50) UNIQUE,
    work_order VARCHAR(50) UNIQUE,
    appointment_id INT UNIQUE NULL,
    manager_id INT NULL,
    mechanic_id INT NULL,
    status ENUM('Assigned', 'In Progress', 'Testing', 'Completed', 'Diagnosis', 'Repairing', 'Ready', 'Delivered', 'Awaiting Parts') DEFAULT 'Diagnosis',
    kanban_stage ENUM('PENDING', 'IN PROGRESS', 'COMPLETED') DEFAULT 'PENDING',
    progress_percentage INT DEFAULT 10,
    fault_report TEXT,
    service_text TEXT,
    estimated_cost DECIMAL(10, 2) DEFAULT 0.00,
    delivery_date VARCHAR(50) DEFAULT NULL,
    start_date DATETIME DEFAULT CURRENT_TIMESTAMP,
    completion_date DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (appointment_id) REFERENCES Appointments(appointment_id) ON DELETE SET NULL,
    FOREIGN KEY (manager_id) REFERENCES Users(user_id) ON DELETE SET NULL,
    FOREIGN KEY (mechanic_id) REFERENCES Users(user_id) ON DELETE SET NULL
);

-- 7. Spare Parts Inventory
CREATE TABLE IF NOT EXISTS SpareParts (
    part_id INT AUTO_INCREMENT PRIMARY KEY,
    workshop_id INT NOT NULL,
    sku VARCHAR(50) UNIQUE NOT NULL,
    name VARCHAR(100) NOT NULL,
    category VARCHAR(50),
    price DECIMAL(10, 2) NOT NULL,
    stock_quantity INT NOT NULL DEFAULT 0,
    reorder_level INT NOT NULL DEFAULT 5,
    unit VARCHAR(50) DEFAULT 'Units',
    supplier VARCHAR(100),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (workshop_id) REFERENCES Workshops(workshop_id)
);

-- 8. Job Parts & Labor (Cost Estimation & Tracking)
CREATE TABLE IF NOT EXISTS JobParts (
    job_part_id INT AUTO_INCREMENT PRIMARY KEY,
    job_id INT NOT NULL,
    part_id INT NOT NULL,
    quantity INT NOT NULL DEFAULT 1,
    unit_price DECIMAL(10, 2) NOT NULL,
    total_price DECIMAL(10, 2) NOT NULL,
    status ENUM('Pending Approval', 'Approved', 'Rejected') DEFAULT 'Pending Approval',
    rejection_reason VARCHAR(255) NULL,
    requested_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (job_id) REFERENCES JobCards(job_id) ON DELETE CASCADE,
    FOREIGN KEY (part_id) REFERENCES SpareParts(part_id)
);

CREATE TABLE IF NOT EXISTS JobLabor (
    labor_id INT AUTO_INCREMENT PRIMARY KEY,
    job_id INT NOT NULL,
    description VARCHAR(255) NOT NULL,
    hours DECIMAL(5, 2) NOT NULL,
    hourly_rate DECIMAL(10, 2) NOT NULL,
    FOREIGN KEY (job_id) REFERENCES JobCards(job_id) ON DELETE CASCADE
);

-- 9. Repair Cost Estimates
CREATE TABLE IF NOT EXISTS RepairEstimates (
    estimate_id INT AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(50) UNIQUE NOT NULL,
    job_id INT NOT NULL,
    status ENUM('Draft', 'Sent', 'Send to Customer', 'Approved') DEFAULT 'Draft',
    sent_date VARCHAR(50) DEFAULT '-',
    line_items LONGTEXT,
    subtotal DECIMAL(10, 2) DEFAULT 0.00,
    tax_rate DECIMAL(5, 3) DEFAULT 0.085,
    tax_amount DECIMAL(10, 2) DEFAULT 0.00,
    total_estimated_cost DECIMAL(10, 2) DEFAULT 0.00,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (job_id) REFERENCES JobCards(job_id) ON DELETE CASCADE
);

-- 10. Invoices (Revenue & Payment)
CREATE TABLE IF NOT EXISTS Invoices (
    invoice_id INT AUTO_INCREMENT PRIMARY KEY,
    invoice_number VARCHAR(50) UNIQUE,
    job_id INT NOT NULL,
    customer_id INT NOT NULL,
    total_amount DECIMAL(12, 2) NOT NULL,
    status ENUM('Unpaid', 'Pending Approval', 'Paid', 'Refunded', 'Pending', 'Overdue') DEFAULT 'Pending',
    issued_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    paid_date DATETIME NULL,
    pdf_url VARCHAR(255) NULL,
    FOREIGN KEY (job_id) REFERENCES JobCards(job_id),
    FOREIGN KEY (customer_id) REFERENCES Users(user_id)
);

-- 11. Service Broadcast (Admin Notices)
CREATE TABLE IF NOT EXISTS ServiceBroadcasts (
    broadcast_id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(150) NOT NULL,
    content TEXT NOT NULL,
    audience ENUM('All', 'Managers', 'Mechanics', 'VehicleOwners') DEFAULT 'All',
    priority ENUM('Low', 'Medium', 'High', 'Critical') DEFAULT 'Low',
    status ENUM('Draft', 'Scheduled', 'Published', 'Completed') DEFAULT 'Draft',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 12. Chat Messages
CREATE TABLE IF NOT EXISTS ChatMessages (
    message_id INT AUTO_INCREMENT PRIMARY KEY,
    sender_id INT NOT NULL,
    receiver_id INT NOT NULL,
    job_tag VARCHAR(50) NULL,
    message_text TEXT,
    attachment_url TEXT NULL,
    is_read BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (sender_id) REFERENCES Users(user_id) ON DELETE CASCADE,
    FOREIGN KEY (receiver_id) REFERENCES Users(user_id) ON DELETE CASCADE
);

-- 13. Repair Photos / Attachments
CREATE TABLE IF NOT EXISTS RepairPhotos (
    photo_id INT AUTO_INCREMENT PRIMARY KEY,
    job_id INT NOT NULL,
    photo_url VARCHAR(255) NOT NULL,
    description VARCHAR(255),
    uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (job_id) REFERENCES JobCards(job_id) ON DELETE CASCADE
);

-- 14. Service Offers
CREATE TABLE IF NOT EXISTS ServiceOffers (
    offer_id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(150) NOT NULL,
    description TEXT,
    discount_percentage DECIMAL(5, 2),
    valid_from DATETIME,
    valid_until DATETIME,
    status ENUM('Active', 'Scheduled', 'Expired') DEFAULT 'Active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 15. Repair Timeline
CREATE TABLE IF NOT EXISTS RepairTimeline (
    timeline_id INT AUTO_INCREMENT PRIMARY KEY,
    job_id INT NOT NULL,
    stage VARCHAR(50) NOT NULL,
    updated_by INT,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (job_id) REFERENCES JobCards(job_id) ON DELETE CASCADE,
    FOREIGN KEY (updated_by) REFERENCES Users(user_id) ON DELETE SET NULL
);

-- 16. Activity Logs
CREATE TABLE IF NOT EXISTS ActivityLogs (
    activity_id INT AUTO_INCREMENT PRIMARY KEY,
    text TEXT NOT NULL,
    type VARCHAR(20) DEFAULT 'blue',
    subtext VARCHAR(255) DEFAULT '',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
