CREATE DATABASE IF NOT EXISTS lms_db DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
USE lms_db;

CREATE TABLE IF NOT EXISTS users (
                                     id INT AUTO_INCREMENT PRIMARY KEY,
                                     name VARCHAR(100) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    role ENUM('user','admin') DEFAULT 'user',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS books (
                                     id INT AUTO_INCREMENT PRIMARY KEY,
                                     title VARCHAR(200) NOT NULL,
    author VARCHAR(100),
    category VARCHAR(100),
    cover_url VARCHAR(500) NULL COMMENT 'URL หรือ path รูปหน้าปกหนังสือ',
    price DECIMAL(10,2) DEFAULT 0,
    copies INT DEFAULT 1
    ) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS issues (
                                      id INT AUTO_INCREMENT PRIMARY KEY,
                                      user_id INT NOT NULL,
                                      book_id INT NOT NULL,
                                      issue_date DATE,
                                      due_date DATE,
                                      return_date DATE NULL,
                                      fine DECIMAL(10,2) DEFAULT 0,
    amount DECIMAL(10,2) NOT NULL DEFAULT 0 COMMENT 'ค่าเช่าที่เรียกเก็บจริง ณ วันทำรายการ',
    description VARCHAR(255) NULL COMMENT 'หมายเหตุรายการ',
    status ENUM('reserved','active','returned','cancelled') DEFAULT 'reserved',
    FOREIGN KEY (user_id) REFERENCES users(id),
    FOREIGN KEY (book_id) REFERENCES books(id)
    ) ENGINE=InnoDB;

-- admin เริ่มต้น (อีเมล admin@test.com / รหัส 1234) — hash นี้ตรวจสอบแล้วใช้ได้จริง
INSERT INTO users (name, email, password, role) VALUES
    ('Admin Staff', 'admin@test.com', '$2y$10$4SYe0IwreEGGOLHmhg1OKuduMDViSLU8LFOtn57WT2wBno85TfETq', 'admin');

-- ข้อมูลหนังสือตัวอย่างไว้ทดสอบ
-- cover_url: วางไฟล์รูปไว้ที่ lms-frontend/img/covers/ แล้วอ้างเป็น path สัมพัทธ์
--            (ไม่พึ่งเว็บภายนอก -> ลิงก์ไม่ตาย และไม่ติดลิขสิทธิ์รูปของเว็บอื่น)
INSERT INTO books (title, author, category, cover_url, price, copies) VALUES
                                                               ('แฮร์รี่ พอตเตอร์ เล่ม 1', 'J.K. Rowling', 'แฟนตาซี', 'img/covers/harry-potter-1.jpg', 350.00, 3),
                                                               ('เพชรพระอุมา', 'พนมเทียน', 'ผจญภัย', 'img/covers/petchprauma.jpg', 280.00, 2),
                                                               ('โต๊ะโตะจัง', 'คุโรยานางิ เท็ตสึโกะ', 'วรรณกรรม', 'img/covers/totto-chan.jpg', 220.00, 5),
                                                               ('Clean Code', 'Robert C. Martin', 'เทคโนโลยี', 'img/covers/clean-code.jpg', 590.00, 1),
                                                               ('สามก๊ก', 'หลัว กวั้นจง', 'ประวัติศาสตร์', 'img/covers/samkok.jpg', 450.00, 0);
