CREATE DATABASE IF NOT EXISTS laptro_db;
USE laptro_db;

-- Users Table
CREATE TABLE users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    phone VARCHAR(20),
    address TEXT,
    city VARCHAR(50),
    postal_code VARCHAR(10),
    is_admin TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Products Table (Laptops)
CREATE TABLE products (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(200) NOT NULL,
    brand VARCHAR(50) NOT NULL,
    description TEXT,
    price DECIMAL(10, 2) NOT NULL,
    stock INT DEFAULT 0,
    -- Specifications
    cpu VARCHAR(100),
    ram VARCHAR(50),
    storage VARCHAR(100),
    gpu VARCHAR(100),
    screen_size VARCHAR(20),
    resolution VARCHAR(50),
    battery VARCHAR(50),
    weight VARCHAR(20),
    os VARCHAR(50),
    -- Images
    main_image VARCHAR(255),
    image_2 VARCHAR(255),
    image_3 VARCHAR(255),
    image_4 VARCHAR(255),
    -- Meta
    category VARCHAR(50) DEFAULT 'laptop',
    is_featured TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Reviews Table
CREATE TABLE reviews (
    id INT PRIMARY KEY AUTO_INCREMENT,
    product_id INT NOT NULL,
    user_id INT NOT NULL,
    rating INT NOT NULL CHECK (rating BETWEEN 1 AND 5),
    title VARCHAR(200),
    comment TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Orders Table
CREATE TABLE orders (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    total_amount DECIMAL(10, 2) NOT NULL,
    status ENUM('pending', 'processing', 'completed', 'cancelled') DEFAULT 'pending',
    shipping_address TEXT NOT NULL,
    payment_method VARCHAR(50),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Order Items Table
CREATE TABLE order_items (
    id INT PRIMARY KEY AUTO_INCREMENT,
    order_id INT NOT NULL,
    product_id INT NOT NULL,
    quantity INT NOT NULL,
    price DECIMAL(10, 2) NOT NULL,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
);

-- Cart Table (Session-based for guests, persistent for logged-in users)
CREATE TABLE cart (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT,
    session_id VARCHAR(100),
    product_id INT NOT NULL,
    quantity INT NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
);

-- Quiz Results Table (for buying guide)
CREATE TABLE quiz_results (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT,
    field_of_study VARCHAR(100),
    budget_min DECIMAL(10, 2),
    budget_max DECIMAL(10, 2),
    recommended_products TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
);

-- Insert Sample Admin User 
INSERT INTO users (name, email, password, is_admin) VALUES 
('Admin', 'admin@laptro.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 1);

-- Insert Sample Products
INSERT INTO products (name, brand, description, price, stock, cpu, ram, storage, gpu, screen_size, resolution, battery, weight, os, main_image, is_featured) VALUES
('Dell XPS 13', 'Dell', 'Ultra-portable laptop perfect for students', 1299.99, 15, 'Intel Core i7-1165G7', '16GB DDR4', '512GB SSD', 'Intel Iris Xe', '13.4"', '1920x1200', '52Wh', '1.2kg', 'Windows 11', 'dell-xps-13.jpg', 1),
('MacBook Air M2', 'Apple', 'Powerful and efficient laptop for creative students', 1199.99, 10, 'Apple M2', '8GB Unified', '256GB SSD', 'Apple M2 GPU', '13.6"', '2560x1664', '52.6Wh', '1.24kg', 'macOS', 'macbook-air-m2.jpg', 1),
('Lenovo ThinkPad X1', 'Lenovo', 'Business-class laptop with excellent keyboard', 1499.99, 12, 'Intel Core i7-1185G7', '16GB LPDDR4x', '1TB SSD', 'Intel Iris Xe', '14"', '1920x1200', '57Wh', '1.13kg', 'Windows 11 Pro', 'lenovo-thinkpad-x1.jpg', 1),
('HP Pavilion 15', 'HP', 'Affordable all-rounder for everyday tasks', 699.99, 20, 'AMD Ryzen 5 5500U', '8GB DDR4', '256GB SSD', 'AMD Radeon', '15.6"', '1920x1080', '41Wh', '1.75kg', 'Windows 11', 'hp-pavilion-15.jpg', 0),
('ASUS ROG Zephyrus', 'ASUS', 'Gaming laptop that doubles as a workstation', 1899.99, 8, 'AMD Ryzen 9 5900HS', '16GB DDR4', '1TB SSD', 'NVIDIA RTX 3060', '14"', '2560x1440', '76Wh', '1.6kg', 'Windows 11', 'asus-rog-zephyrus.jpg', 0),
('Acer Swift 3', 'Acer', 'Budget-friendly laptop with good performance', 599.99, 25, 'Intel Core i5-1135G7', '8GB LPDDR4x', '512GB SSD', 'Intel Iris Xe', '14"', '1920x1080', '56Wh', '1.2kg', 'Windows 11', 'acer-swift-3.jpg', 0);

