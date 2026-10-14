-- AutoCare Optimal Database Schema
-- Merged from autocare-database.sql and database.sql

SET FOREIGN_KEY_CHECKS = 0;

-- Drop existing tables to allow clean recreation
DROP TABLE IF EXISTS ServiceOffers;
DROP TABLE IF EXISTS RepairPhotos;
DROP TABLE IF EXISTS JobCardPhotos;
DROP TABLE IF EXISTS AdditionalFaults;
DROP TABLE IF EXISTS ChatMessages;
DROP TABLE IF EXISTS ServiceBroadcasts;
DROP TABLE IF EXISTS Notices;
DROP TABLE IF EXISTS Invoices;
DROP TABLE IF EXISTS JobCardLabor;
DROP TABLE IF EXISTS JobCardParts;
DROP TABLE IF EXISTS JobLabor;
DROP TABLE IF EXISTS JobParts;
DROP TABLE IF EXISTS RepairEstimates;
DROP TABLE IF EXISTS RepairTimeline;
DROP TABLE IF EXISTS JobCards;
DROP TABLE IF EXISTS Appointments;
DROP TABLE IF EXISTS ServiceRequests;
DROP TABLE IF EXISTS SpareParts;
DROP TABLE IF EXISTS ServiceCategories;
DROP TABLE IF EXISTS Vehicles;
DROP TABLE IF EXISTS Workshops;
DROP TABLE IF EXISTS Users;

SET FOREIGN_KEY_CHECKS = 1;

-- 1. Users Table (Handles Admin, Manager, Mechanic, VehicleOwner)
CREATE TABLE Users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    phone VARCHAR(20),
    role ENUM('Admin', 'Manager', 'Mechanic', 'VehicleOwner') NOT NULL,
    status ENUM('Pending', 'Approved', 'Rejected', 'Active', 'Inactive') DEFAULT 'Pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- 2. Workshops Table
CREATE TABLE Workshops (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    location VARCHAR(255) NOT NULL,
    manager_id INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (manager_id) REFERENCES Users(id) ON DELETE SET NULL
);

-- 3. Vehicles Table
CREATE TABLE Vehicles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    owner_id INT NOT NULL,
    make VARCHAR(50) NOT NULL,
    model VARCHAR(50) NOT NULL,
    year INT NOT NULL,
    license_plate VARCHAR(20) UNIQUE NOT NULL,
    vin VARCHAR(50) UNIQUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (owner_id) REFERENCES Users(id) ON DELETE CASCADE
);

-- 4. Service Categories
CREATE TABLE ServiceCategories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) UNIQUE NOT NULL,
    description TEXT,
    base_rate DECIMAL(10,2) DEFAULT 0.00,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 5. Appointments (Booking Requests)
CREATE TABLE Appointments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    owner_id INT NOT NULL,
    vehicle_id INT NOT NULL,
    workshop_id INT, -- Optional initially until assigned
    service_category_id INT,
    preferred_date DATETIME NOT NULL,
    issue_description TEXT,
    status ENUM('Pending', 'Approved', 'Rejected', 'Completed', 'Cancelled') DEFAULT 'Pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (owner_id) REFERENCES Users(id) ON DELETE CASCADE,
    FOREIGN KEY (vehicle_id) REFERENCES Vehicles(id) ON DELETE CASCADE,
    FOREIGN KEY (workshop_id) REFERENCES Workshops(id) ON DELETE SET NULL,
    FOREIGN KEY (service_category_id) REFERENCES ServiceCategories(id) ON DELETE SET NULL
);

-- 6. Job Cards (Repair Orders)
CREATE TABLE JobCards (
    id INT AUTO_INCREMENT PRIMARY KEY,
    appointment_id INT UNIQUE NOT NULL,
    manager_id INT,
    mechanic_id INT,
    status ENUM('Assigned', 'Diagnosis', 'In Progress', 'Testing', 'Ready', 'Delivered', 'Completed') DEFAULT 'Assigned',
    fault_report TEXT,
    estimated_cost DECIMAL(10, 2),
    start_date DATETIME,
    completion_date DATETIME,
    delivery_date DATETIME,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (appointment_id) REFERENCES Appointments(id) ON DELETE CASCADE,
    FOREIGN KEY (manager_id) REFERENCES Users(id) ON DELETE SET NULL,
    FOREIGN KEY (mechanic_id) REFERENCES Users(id) ON DELETE SET NULL
);

-- 7. Spare Parts Inventory
CREATE TABLE SpareParts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    workshop_id INT,
    sku VARCHAR(50) UNIQUE NOT NULL,
    name VARCHAR(100) NOT NULL,
    category VARCHAR(50),
    price DECIMAL(10, 2) NOT NULL,
    stock_quantity INT NOT NULL DEFAULT 0,
    reorder_level INT NOT NULL DEFAULT 5,
    supplier VARCHAR(100),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (workshop_id) REFERENCES Workshops(id) ON DELETE CASCADE
);

-- Insert Dummy Data for Spare Parts
INSERT INTO SpareParts (workshop_id, sku, name, category, price, stock_quantity, reorder_level, supplier) VALUES 
(NULL, 'BRK-001', 'Premium Brake Pads', 'Brakes & Suspension', 3500.00, 45, 10, 'Bosch'),
(NULL, 'FLT-002', 'Engine Oil Filter', 'Fluids & Filters', 850.00, 120, 20, 'Denso'),
(NULL, 'ELC-003', 'Spark Plugs Set', 'Electrical', 2100.00, 30, 15, 'Bosch'),
(NULL, 'SUS-004', 'Front Shock Absorber', 'Brakes & Suspension', 12500.00, 8, 10, 'Brembo');

-- 8. Repair Estimates (Cost Estimation Sent by Manager, Approved by Owner)
CREATE TABLE RepairEstimates (
    id INT AUTO_INCREMENT PRIMARY KEY,
    job_card_id INT NOT NULL,
    total_estimated_cost DECIMAL(10,2) NOT NULL,
    status ENUM('Pending Approval', 'Approved', 'Rejected') DEFAULT 'Pending Approval',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (job_card_id) REFERENCES JobCards(id) ON DELETE CASCADE
);

-- 9. Job Card Parts (Parts requested/used for a job)
CREATE TABLE JobCardParts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    job_card_id INT NOT NULL,
    part_id INT NOT NULL,
    quantity INT NOT NULL DEFAULT 1,
    unit_price DECIMAL(10, 2) NOT NULL, -- Price at the time of usage
    status ENUM('Pending Approval', 'Approved', 'Rejected', 'Used') DEFAULT 'Pending Approval',
    requested_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (job_card_id) REFERENCES JobCards(id) ON DELETE CASCADE,
    FOREIGN KEY (part_id) REFERENCES SpareParts(id) ON DELETE CASCADE
);

-- 10. Job Card Labor Hours
CREATE TABLE JobCardLabor (
    id INT AUTO_INCREMENT PRIMARY KEY,
    job_card_id INT NOT NULL,
    mechanic_id INT NOT NULL,
    description VARCHAR(255) NOT NULL,
    hours DECIMAL(5, 2) NOT NULL,
    hourly_rate DECIMAL(10, 2) NOT NULL,
    logged_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (job_card_id) REFERENCES JobCards(id) ON DELETE CASCADE,
    FOREIGN KEY (mechanic_id) REFERENCES Users(id) ON DELETE CASCADE
);

-- 11. Repair Timeline (Tracks live status updates)
CREATE TABLE RepairTimeline (
    id INT AUTO_INCREMENT PRIMARY KEY,
    job_card_id INT NOT NULL,
    stage VARCHAR(50) NOT NULL, -- e.g., Diagnosis, Repairing, Testing, Ready
    updated_by INT NOT NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (job_card_id) REFERENCES JobCards(id) ON DELETE CASCADE,
    FOREIGN KEY (updated_by) REFERENCES Users(id) ON DELETE CASCADE
);

-- 12. Job Card Photos / Attachments (Before/After uploads)
CREATE TABLE JobCardPhotos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    job_card_id INT NOT NULL,
    photo_url VARCHAR(255) NOT NULL,
    type ENUM('Before', 'After', 'Progress', 'Issue') NOT NULL DEFAULT 'Progress',
    description VARCHAR(255),
    uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (job_card_id) REFERENCES JobCards(id) ON DELETE CASCADE
);

-- 13. Additional Faults Found
CREATE TABLE AdditionalFaults (
    id INT AUTO_INCREMENT PRIMARY KEY,
    job_card_id INT NOT NULL,
    description TEXT NOT NULL,
    reported_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (job_card_id) REFERENCES JobCards(id) ON DELETE CASCADE
);

-- 14. Invoices (Revenue & Payment)
CREATE TABLE Invoices (
    id INT AUTO_INCREMENT PRIMARY KEY,
    job_card_id INT NOT NULL,
    customer_id INT NOT NULL,
    total_amount DECIMAL(12, 2) NOT NULL,
    pdf_url VARCHAR(255),
    status ENUM('Unpaid', 'Pending Approval', 'Paid', 'Refunded') DEFAULT 'Unpaid',
    issued_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    paid_date DATETIME,
    FOREIGN KEY (job_card_id) REFERENCES JobCards(id) ON DELETE CASCADE,
    FOREIGN KEY (customer_id) REFERENCES Users(id) ON DELETE CASCADE
);

-- 15. Chat Messages
CREATE TABLE ChatMessages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    sender_id INT NOT NULL,
    receiver_id INT NOT NULL,
    message_text TEXT NOT NULL,
    attachment_url VARCHAR(255),
    is_read BOOLEAN DEFAULT FALSE,
    sent_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (sender_id) REFERENCES Users(id) ON DELETE CASCADE,
    FOREIGN KEY (receiver_id) REFERENCES Users(id) ON DELETE CASCADE
);

-- 16. Service Broadcasts & Notices
CREATE TABLE ServiceBroadcasts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    admin_id INT,
    title VARCHAR(150) NOT NULL,
    content TEXT NOT NULL,
    audience ENUM('All', 'Managers', 'Mechanics', 'VehicleOwners') DEFAULT 'All',
    priority ENUM('Low', 'Medium', 'High', 'Critical') DEFAULT 'Low',
    status ENUM('Draft', 'Scheduled', 'Published', 'Completed') DEFAULT 'Draft',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (admin_id) REFERENCES Users(id) ON DELETE SET NULL
);

-- 17. Service Offers
CREATE TABLE ServiceOffers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(150) NOT NULL,
    description TEXT,
    discount_percentage DECIMAL(5, 2),
    valid_from DATETIME,
    valid_until DATETIME,
    status ENUM('Active', 'Scheduled', 'Expired') DEFAULT 'Active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
