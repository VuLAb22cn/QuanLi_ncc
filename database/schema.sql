-- Sử dụng CSDL đã có và mở rộng thêm

USE quanli_ncc;

-- Bảng supplier: thông tin nhà cung cấp (đã có)
CREATE TABLE IF NOT EXISTS supplier (
    id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255),
    email VARCHAR(255),
    tel VARCHAR(20),
    address TEXT,
    status ENUM('active', 'inactive') DEFAULT 'active',
    rating DECIMAL(2,1) DEFAULT 0.0,
    total_purchase FLOAT DEFAULT 0,
    total_debt FLOAT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Thêm cột quality_stars vào bảng supplier nếu chưa có
-- ALTER TABLE supplier ADD COLUMN quality_stars FLOAT DEFAULT 0 AFTER contact_person;

-- Bảng product: sản phẩm do nhà cung cấp cung cấp (đã có)
CREATE TABLE IF NOT EXISTS product (
    id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255),
    image VARCHAR(1000),
    type VARCHAR(255),
    description TEXT,
    supplier_id BIGINT(20) UNSIGNED,
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (supplier_id) REFERENCES supplier(id) ON DELETE CASCADE
);

-- Bảng product_supplier: liên kết nhiều-nhiều giữa product và supplier (đã có)
CREATE TABLE IF NOT EXISTS product_supplier (
    id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    supplier_id BIGINT(20) UNSIGNED,
    product_id BIGINT(20) UNSIGNED,
    price FLOAT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (supplier_id) REFERENCES supplier(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES product(id) ON DELETE CASCADE
);

-- Bảng bill: hóa đơn (đã có, mở rộng thêm)
CREATE TABLE IF NOT EXISTS bill (
    id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    bill_code VARCHAR(50) UNIQUE,
    supplier_id BIGINT(20) UNSIGNED,
    date DATE,
    total_amount FLOAT DEFAULT 0,
    status ENUM('pending', 'paid', 'overdue') DEFAULT 'pending',
    due_date DATE,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (supplier_id) REFERENCES supplier(id) ON DELETE CASCADE
);

-- Bảng bill_product: chi tiết sản phẩm trong hóa đơn (đã có)
CREATE TABLE IF NOT EXISTS bill_product (
    id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    bill_id BIGINT(20) UNSIGNED,
    number INT,
    product_id BIGINT(20) UNSIGNED,
    price FLOAT,
    total_price FLOAT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (bill_id) REFERENCES bill(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES product(id) ON DELETE CASCADE
);

-- Bảng user: người dùng hệ thống (đã có)
CREATE TABLE IF NOT EXISTS user (
    id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(255) UNIQUE,
    password VARCHAR(255),
    email VARCHAR(255),
    name VARCHAR(255),
    tel VARCHAR(20),
    role ENUM('admin', 'user') DEFAULT 'user',
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Bảng mới: cashbook - sổ quỹ
CREATE TABLE IF NOT EXISTS cashbook (
    id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    transaction_code VARCHAR(50) UNIQUE,
    date DATE,
    type ENUM('income', 'expense') NOT NULL,
    amount FLOAT NOT NULL,
    description TEXT,
    category VARCHAR(100),
    reference_id BIGINT(20) UNSIGNED NULL,
    reference_type ENUM('bill', 'payment', 'other') NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Bảng mới: debts - công nợ
CREATE TABLE IF NOT EXISTS debts (
    id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    debt_code VARCHAR(50) UNIQUE,
    supplier_id BIGINT(20) UNSIGNED,
    bill_id BIGINT(20) UNSIGNED NULL,
    total_amount FLOAT NOT NULL,
    paid_amount FLOAT DEFAULT 0,
    remaining_amount FLOAT NOT NULL,
    due_date DATE,
    status ENUM('current', 'overdue', 'paid') DEFAULT 'current',
    description TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (supplier_id) REFERENCES supplier(id) ON DELETE CASCADE,
    FOREIGN KEY (bill_id) REFERENCES bill(id) ON DELETE SET NULL
);

-- Bảng mới: payments - thanh toán
CREATE TABLE IF NOT EXISTS payments (
    id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    payment_code VARCHAR(50) UNIQUE,
    supplier_id BIGINT(20) UNSIGNED,
    debt_id BIGINT(20) UNSIGNED NULL,
    amount FLOAT NOT NULL,
    payment_method ENUM('Tiền mặt', 'Séc', 'Chuyển khoản') NOT NULL,
    payment_date DATE,
    reference_code VARCHAR(100),
    description TEXT,
    status ENUM('pending', 'completed', 'failed') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (supplier_id) REFERENCES supplier(id) ON DELETE CASCADE,
    FOREIGN KEY (debt_id) REFERENCES debts(id) ON DELETE SET NULL
);

-- Bảng mới: contracts - hợp đồng
CREATE TABLE IF NOT EXISTS contracts (
    id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    contract_code VARCHAR(50) UNIQUE,
    supplier_id BIGINT(20) UNSIGNED,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    start_date DATE,
    end_date DATE,
    value FLOAT,
    status ENUM('draft', 'active', 'expired', 'terminated') DEFAULT 'draft',
    file_path VARCHAR(500),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (supplier_id) REFERENCES supplier(id) ON DELETE CASCADE
);

-- Bảng mới: supplier_ratings - xếp hạng nhà cung cấp
CREATE TABLE IF NOT EXISTS supplier_ratings (
    id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    supplier_id BIGINT(20) UNSIGNED,
    rating DECIMAL(2,1) NOT NULL,
    total_orders INT DEFAULT 0,
    total_amount FLOAT DEFAULT 0,
    on_time_delivery_rate DECIMAL(5,2) DEFAULT 0,
    quality_score DECIMAL(2,1) DEFAULT 0,
    payment_terms_compliance DECIMAL(5,2) DEFAULT 0,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (supplier_id) REFERENCES supplier(id) ON DELETE CASCADE,
    UNIQUE KEY unique_supplier_rating (supplier_id)
);

-- Thêm dữ liệu mẫu
INSERT INTO user (username, password, email, name, role) VALUES 
('Vu', MD5('123'), 'qlncc@gmail.com', 'Administrator', 'admin');

INSERT INTO supplier (name, email, tel, address, status, rating) VALUES
('Công ty TNHH Công Nghệ Việt Nam', 'contact@viettech.com', '0123456789', '123 Đường Lê Lợi, Quận 1, TP.HCM', 'active', 4.5),
('Tập đoàn Điện Tử Thành Công', 'info@thanhcong.com', '0987654321', '456 Đường Nguyễn Huệ, Quận 3, TP.HCM', 'active', 4.2),
('Công ty TNHH Thiết Bị Số Đông Á', 'sales@donga.com', '0369852147', '789 Đường Võ Văn Tần, Quận 7, TP.HCM', 'active', 3.8),
('Tập đoàn Giải Pháp Công Nghệ Sài Gòn', 'contact@sgtc.com', '0123456788', '321 Đường Nguyễn Đình Chiểu, Quận 2, TP.HCM', 'active', 4.0),
('Công ty TNHH Phân Phối Thiết Bị Việt', 'info@vietdist.com', '0987654322', '654 Đường Lê Văn Sỹ, Quận 5, TP.HCM', 'inactive', 3.5),
('Tập đoàn Công Nghệ Thông Minh', 'contact@smarttech.com', '0123456787', '987 Đường Nguyễn Văn Linh, Quận 4, TP.HCM', 'active', 4.3),
('Công ty TNHH Giải Pháp Số Toàn Cầu', 'info@globaldigital.com', '0987654323', '654 Đường Nguyễn Trãi, Quận 6, TP.HCM', 'active', 4.1),
('Tập đoàn Thiết Bị Điện Tử Việt Nam', 'sales@vietelec.com', '0369852148', '321 Đường Võ Văn Kiệt, Quận 8, TP.HCM', 'active', 3.9);

INSERT INTO product (name, type, description, supplier_id, status) VALUES
('Laptop Dell XPS 13', 'Máy tính xách tay', 'Laptop cao cấp với màn hình 13 inch', 1, 'active'),
('iPhone 14 Pro', 'Điện thoại di động', 'Điện thoại thông minh mới nhất', 1, 'active'),
('Samsung Galaxy S23', 'Điện thoại di động', 'Điện thoại Android cao cấp', 2, 'active'),
('MacBook Pro M2', 'Máy tính xách tay', 'Laptop Apple với chip M2', 3, 'active'),
('iPad Pro 12.9', 'Máy tính bảng', 'Máy tính bảng cao cấp', 3, 'active'),
('Sony WH-1000XM5', 'Tai nghe', 'Tai nghe không dây chống ồn', 4, 'active'),
('LG OLED TV', 'Tivi', 'TV OLED 4K 55 inch', 4, 'active'),
('Microsoft Surface Pro', 'Máy tính bảng 2 trong 1', 'Máy tính bảng 2 trong 1', 5, 'active'),
('Asus ROG Phone', 'Điện thoại di động', 'Điện thoại gaming cao cấp', 6, 'active'),
('Lenovo ThinkPad', 'Máy tính xách tay', 'Laptop doanh nhân', 6, 'active'),
('Bose QuietComfort', 'Tai nghe', 'Tai nghe chống ồn', 7, 'active'),
('Samsung QLED TV', 'Tivi', 'TV QLED 4K 65 inch', 7, 'active'),
('HP Spectre x360', 'Máy tính xách tay', 'Laptop cao cấp 2 trong 1', 8, 'active'),
('Google Pixel 8', 'Điện thoại di động', 'Điện thoại Android thuần', 8, 'active');

INSERT INTO product_supplier (supplier_id, product_id, price) VALUES
(1, 1, 15000000),
(1, 2, 3500000),
(2, 3, 2500000),
(2, 4, 1800000),
(3, 5, 120000),
(3, 6, 9500000),
(4, 7, 28000000),
(4, 8, 15000000),
(5, 9, 22000000),
(5, 10, 18000000),
(6, 11, 8500000),
(6, 12, 32000000),
(7, 13, 25000000),
(7, 14, 18000000);

INSERT INTO bill (bill_code, supplier_id, date, total_amount, status, due_date, description) VALUES
('BILL0001', 1, '2025-01-15', 25000000, 'paid', '2025-02-15', 'Thanh toán lô hàng tháng 1'),
('BILL0002', 2, '2025-01-20', 18000000, 'pending', '2025-02-20', 'Thanh toán lô hàng tháng 1'),
('BILL0003', 3, '2025-01-25', 32000000, 'paid', '2025-02-25', 'Thanh toán lô hàng tháng 1'),
('BILL0004', 4, '2025-02-01', 9500000, 'pending', '2025-03-01', 'Thanh toán lô hàng tháng 2'),
('BILL0005', 1, '2025-02-05', 15000000, 'overdue', '2025-02-05', 'Thanh toán lô hàng tháng 2'),
('BILL0006', 2, '2025-02-10', 22000000, 'pending', '2025-03-10', 'Thanh toán lô hàng tháng 2'),
('BILL0007', 3, '2025-02-15', 28000000, 'pending', '2025-03-15', 'Thanh toán lô hàng tháng 2'),
('BILL0008', 5, '2025-02-20', 35000000, 'pending', '2025-03-20', 'Thanh toán lô hàng tháng 2'),
('BILL0009', 6, '2025-02-25', 42000000, 'pending', '2025-03-25', 'Thanh toán lô hàng tháng 2'),
('BILL0010', 7, '2025-03-01', 28000000, 'pending', '2025-04-01', 'Thanh toán lô hàng tháng 3');

INSERT INTO bill_product (bill_id, number, product_id, price, total_price) VALUES
(1, 2, 1, 12500000, 25000000),
(2, 1, 3, 18000000, 18000000),
(3, 1, 4, 28000000, 28000000),
(3, 1, 5, 4000000, 4000000),
(4, 1, 6, 9500000, 9500000),
(5, 1, 2, 15000000, 15000000),
(6, 1, 3, 22000000, 22000000),
(7, 1, 4, 28000000, 28000000),
(8, 1, 9, 35000000, 35000000),
(9, 1, 10, 42000000, 42000000),
(10, 1, 11, 28000000, 28000000);

INSERT INTO debts (debt_code, supplier_id, bill_id, total_amount, paid_amount, remaining_amount, due_date, status, description) VALUES
('DEBT0001', 1, 1, 25000000, 25000000, 0, '2025-02-15', 'paid', 'Thanh toán hóa đơn BILL0001'),
('DEBT0002', 2, 2, 18000000, 0, 18000000, '2025-02-20', 'current', 'Thanh toán lô hàng tháng 1'),
('DEBT0003', 3, 3, 32000000, 32000000, 0, '2025-02-25', 'paid', 'Thanh toán lô hàng tháng 1'),
('DEBT0004', 4, 4, 9500000, 0, 9500000, '2025-03-01', 'current', 'Thanh toán lô hàng tháng 2'),
('DEBT0005', 1, 5, 15000000, 0, 15000000, '2025-02-05', 'overdue', 'Thanh toán lô hàng tháng 2'),
('DEBT0006', 2, 6, 22000000, 0, 22000000, '2025-03-10', 'current', 'Thanh toán lô hàng tháng 2'),
('DEBT0007', 3, 7, 28000000, 0, 28000000, '2025-03-15', 'current', 'Thanh toán lô hàng tháng 2'),
('DEBT0008', 5, 8, 35000000, 0, 35000000, '2025-03-20', 'current', 'Thanh toán lô hàng tháng 2'),
('DEBT0009', 6, 9, 42000000, 0, 42000000, '2025-03-25', 'current', 'Thanh toán lô hàng tháng 2'),
('DEBT0010', 7, 10, 28000000, 0, 28000000, '2025-04-01', 'current', 'Thanh toán lô hàng tháng 3');

INSERT INTO payments (payment_code, supplier_id, debt_id, amount, payment_method, payment_date, reference_code, description, status) VALUES
('PAY0001', 1, 1, 25000000, 'Chuyển khoản', '2025-02-10', 'TRF001', 'Thanh toán hóa đơn BILL0001', 'completed'),
('PAY0002', 3, 3, 32000000, 'Chuyển khoản', '2025-02-20', 'TRF002', 'Thanh toán hóa đơn BILL0003', 'completed'),
('PAY0003', 2, 2, 10000000, 'Tiền mặt', '2025-02-25', 'CASH001', 'Thanh toán một phần hóa đơn BILL0002', 'completed'),
('PAY0004', 4, 4, 5000000, 'Chuyển khoản', '2025-03-01', 'TRF003', 'Thanh toán một phần hóa đơn BILL0004', 'completed'),
('PAY0005', 5, 8, 15000000, 'Chuyển khoản', '2025-03-05', 'TRF004', 'Thanh toán một phần hóa đơn BILL0008', 'completed'),
('PAY0006', 6, 9, 20000000, 'Tiền mặt', '2025-03-10', 'CASH002', 'Thanh toán một phần hóa đơn BILL0009', 'completed');

INSERT INTO supplier_ratings (supplier_id, rating, total_orders, total_amount, on_time_delivery_rate, quality_score, payment_terms_compliance) VALUES
(1, 4.5, 15, 150000000, 95.5, 4.5, 98.0),
(2, 4.2, 12, 120000000, 92.0, 4.3, 95.5),
(3, 3.8, 10, 100000000, 88.5, 3.9, 90.0),
(4, 4.0, 8, 80000000, 90.0, 4.1, 92.5),
(5, 3.5, 5, 50000000, 85.0, 3.7, 88.0),
(6, 4.3, 18, 180000000, 94.0, 4.4, 96.5),
(7, 4.1, 14, 140000000, 91.5, 4.2, 94.0),
(8, 3.9, 9, 90000000, 89.0, 4.0, 91.0);

INSERT INTO contracts (contract_code, supplier_id, title, description, start_date, end_date, value, status, file_path) VALUES
('CTR0001', 1, 'Hợp đồng cung cấp thiết bị công nghệ cao', 'Cung cấp laptop và điện thoại thông minh', '2025-01-01', '2025-12-31', 500000000, 'active', 'contracts/ctr0001.pdf'),
('CTR0002', 2, 'Hợp đồng cung cấp linh kiện điện tử', 'Cung cấp linh kiện điện tử chất lượng cao', '2025-01-01', '2025-12-31', 300000000, 'active', 'contracts/ctr0002.pdf'),
('CTR0003', 3, 'Hợp đồng cung cấp thiết bị văn phòng hiện đại', 'Cung cấp máy tính và thiết bị văn phòng thông minh', '2025-01-01', '2025-12-31', 200000000, 'active', 'contracts/ctr0003.pdf'),
('CTR0004', 4, 'Hợp đồng cung cấp thiết bị âm thanh cao cấp', 'Cung cấp loa và tai nghe không dây', '2025-01-01', '2025-12-31', 150000000, 'active', 'contracts/ctr0004.pdf'),
('CTR0005', 5, 'Hợp đồng cung cấp thiết bị mạng và bảo mật', 'Cung cấp thiết bị mạng và giải pháp bảo mật', '2025-01-01', '2025-12-31', 100000000, 'draft', 'contracts/ctr0005.pdf'),
('CTR0006', 6, 'Hợp đồng cung cấp thiết bị gaming chuyên nghiệp', 'Cung cấp thiết bị gaming và phụ kiện cao cấp', '2025-01-01', '2025-12-31', 400000000, 'active', 'contracts/ctr0006.pdf'),
('CTR0007', 7, 'Hợp đồng cung cấp thiết bị giải trí thông minh', 'Cung cấp TV và thiết bị âm thanh cao cấp', '2025-01-01', '2025-12-31', 350000000, 'active', 'contracts/ctr0007.pdf'),
('CTR0008', 8, 'Hợp đồng cung cấp thiết bị di động thế hệ mới', 'Cung cấp điện thoại và máy tính bảng cao cấp', '2025-01-01', '2025-12-31', 250000000, 'active', 'contracts/ctr0008.pdf');

INSERT INTO cashbook (transaction_code, date, type, amount, description, category, reference_id, reference_type) VALUES
('TRX0001', '2025-02-10', 'expense', 25000000, 'Thanh toán hóa đơn BILL0001', 'supplier_payment', 1, 'bill'),
('TRX0002', '2025-02-20', 'expense', 32000000, 'Thanh toán hóa đơn BILL0003', 'supplier_payment', 3, 'bill'),
('TRX0003', '2025-02-25', 'expense', 10000000, 'Thanh toán một phần hóa đơn BILL0002', 'supplier_payment', 2, 'bill'),
('TRX0004', '2025-03-01', 'expense', 5000000, 'Thanh toán một phần hóa đơn BILL0004', 'supplier_payment', 4, 'bill'),
('TRX0005', '2025-03-05', 'expense', 15000000, 'Thanh toán một phần hóa đơn BILL0008', 'supplier_payment', 8, 'bill'),
('TRX0006', '2025-03-10', 'expense', 20000000, 'Thanh toán một phần hóa đơn BILL0009', 'supplier_payment', 9, 'bill'),
('TRX0007', '2025-03-15', 'income', 50000000, 'Thu tiền từ khách hàng', 'customer_payment', NULL, 'other'),
('TRX0008', '2025-03-20', 'income', 75000000, 'Thu tiền từ khách hàng', 'customer_payment', NULL, 'other');

