-- สร้างฐานข้อมูลสำหรับระบบล็อกอิน
CREATE DATABASE IF NOT EXISTS office_login_system CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

USE office_login_system;

-- ตารางบทบาทผู้ใช้งาน
CREATE TABLE roles (
    role_key VARCHAR(50) PRIMARY KEY,
    role_name VARCHAR(100) NOT NULL,
    dashboard_path VARCHAR(255) NOT NULL,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- ตารางผู้ใช้งาน
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id VARCHAR(50) UNIQUE NOT NULL,
    first_name VARCHAR(100) NOT NULL,
    last_name VARCHAR(100) NOT NULL,
    phone VARCHAR(20) NOT NULL,
    email VARCHAR(100) NOT NULL,
    position VARCHAR(100) NOT NULL,
    department VARCHAR(100) NOT NULL,
    company VARCHAR(100) NOT NULL,
    user_role VARCHAR(50) NOT NULL DEFAULT 'employee',
    password_hash VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    is_active BOOLEAN DEFAULT TRUE,
    FOREIGN KEY (user_role) REFERENCES roles(role_key) ON UPDATE CASCADE
);

-- ตารางสำหรับจัดการ sessions
CREATE TABLE user_sessions (
    session_id VARCHAR(128) PRIMARY KEY,
    user_id VARCHAR(50) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expires_at TIMESTAMP NOT NULL,
    ip_address VARCHAR(45),
    user_agent TEXT,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
);

-- ตารางสำหรับ login logs
CREATE TABLE login_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id VARCHAR(50),
    login_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    ip_address VARCHAR(45),
    user_agent TEXT,
    login_status ENUM('success', 'failed') NOT NULL,
    failure_reason VARCHAR(255),
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE SET NULL
);

-- ตารางตั้งค่ากิจการ (ศูนย์กลางสำหรับข้อมูลเอกสาร)
CREATE TABLE company_settings (
    id TINYINT UNSIGNED NOT NULL DEFAULT 1,
    company_type VARCHAR(50) NOT NULL,
    business_name VARCHAR(255) NOT NULL,
    tax_id VARCHAR(20) NOT NULL,
    trade_registration_no VARCHAR(20) NOT NULL,
    branch_code VARCHAR(20) NOT NULL,
    branch_name VARCHAR(255) NOT NULL,
    address_no VARCHAR(20) NOT NULL,
    moo VARCHAR(20) NOT NULL,
    subdistrict VARCHAR(100) NOT NULL,
    district VARCHAR(100) NOT NULL,
    road VARCHAR(150) NOT NULL,
    province VARCHAR(100) NOT NULL,
    postal_code VARCHAR(10) NOT NULL,
    office_phone VARCHAR(20) NOT NULL,
    email VARCHAR(150) NOT NULL,
    header_logo_path VARCHAR(255) DEFAULT NULL,
    created_by VARCHAR(50) DEFAULT NULL,
    updated_by VARCHAR(50) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id)
);

-- สร้างบทบาทเริ่มต้น
INSERT INTO roles (role_key, role_name, dashboard_path, is_active)
VALUES
('admin', 'System Administrator', '../app/admin/menuadmin', 1),
('manager', 'Manager', '../app/manager/menumanager', 1),
('employee', 'Employee', '../app/employee/menuemployee', 1);

-- สร้างผู้ใช้งานตัวอย่าง (admin)
INSERT INTO users (user_id, first_name, last_name, phone, email, position, department, company, user_role, password_hash) 
VALUES 
('admin001', 'ผู้ดูแล', 'ระบบ', '0800000000', 'admin@company.com', 'System Administrator', 'IT', 'Office Plus', 'admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi'),
('emp001', 'พนักงาน', 'ทดสอบ', '0811111111', 'employee@company.com', 'Staff', 'Sales', 'Office Plus', 'employee', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi'),
('mgr001', 'หัวหน้า', 'ทดสอบ', '0822222222', 'manager@company.com', 'Manager', 'Marketing', 'Office Plus', 'manager', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi');

-- หมายเหตุ: รหัสผ่านทั้งหมดคือ "password123"