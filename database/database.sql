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
    must_change_password TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Products Table 
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
    pictures LONGTEXT,
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

-- Session-based for guests persistent for loggedin
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

CREATE TABLE stock_receipts (
    id INT PRIMARY KEY AUTO_INCREMENT,
    product_id INT NOT NULL,
    admin_user_id INT NOT NULL,
    quantity INT NOT NULL,
    note VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    FOREIGN KEY (admin_user_id) REFERENCES users(id) ON DELETE CASCADE
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

-- Support Chat
CREATE TABLE support_conversations (
    id INT PRIMARY KEY AUTO_INCREMENT,
    session_key VARCHAR(190) NOT NULL UNIQUE,
    user_id INT NULL,
    status ENUM('open','pending_admin','closed') NOT NULL DEFAULT 'open',
    assigned_admin_id INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (assigned_admin_id) REFERENCES users(id) ON DELETE SET NULL
);

CREATE TABLE support_messages (
    id INT PRIMARY KEY AUTO_INCREMENT,
    conversation_id INT NOT NULL,
    sender_type ENUM('customer','admin','system') NOT NULL,
    sender_user_id INT NULL,
    message_text TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (conversation_id) REFERENCES support_conversations(id) ON DELETE CASCADE,
    FOREIGN KEY (sender_user_id) REFERENCES users(id) ON DELETE SET NULL
);

-- Sample Admin
INSERT INTO users (name, email, password, is_admin) VALUES 
('Admin', 'admin@laptro.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 1);

-- Sample Products
INSERT INTO products (name, brand, description, price, stock, cpu, ram, storage, gpu, screen_size, resolution, battery, weight, os, main_image, is_featured) VALUES
('Dell XPS 13', 'Dell', 'Ultra-portable laptop perfect for students', 1299.99, 15, 'Intel Core i7-1165G7', '16GB DDR4', '512GB SSD', 'Intel Iris Xe', '13.4"', '1920x1200', '52Wh', '1.2kg', 'Windows 11', 'dell-xps-13.jpg', 1),
('MacBook Air M2', 'Apple', 'Powerful and efficient laptop for creative students', 1199.99, 10, 'Apple M2', '8GB Unified', '256GB SSD', 'Apple M2 GPU', '13.6"', '2560x1664', '52.6Wh', '1.24kg', 'macOS', 'macbook-air-m2.jpg', 1),
('Lenovo ThinkPad X1', 'Lenovo', 'Business-class laptop with excellent keyboard', 1499.99, 12, 'Intel Core i7-1185G7', '16GB LPDDR4x', '1TB SSD', 'Intel Iris Xe', '14"', '1920x1200', '57Wh', '1.13kg', 'Windows 11 Pro', 'lenovo-thinkpad-x1.jpg', 1),
('HP Pavilion 15', 'HP', 'Affordable all-rounder for everyday tasks', 699.99, 20, 'AMD Ryzen 5 5500U', '8GB DDR4', '256GB SSD', 'AMD Radeon', '15.6"', '1920x1080', '41Wh', '1.75kg', 'Windows 11', 'hp-pavilion-15.jpg', 0),
('ASUS ROG Zephyrus', 'ASUS', 'Gaming laptop that doubles as a workstation', 1899.99, 8, 'AMD Ryzen 9 5900HS', '16GB DDR4', '1TB SSD', 'NVIDIA RTX 3060', '14"', '2560x1440', '76Wh', '1.6kg', 'Windows 11', 'asus-rog-zephyrus.jpg', 0),
('Acer Swift 3', 'Acer', 'Budget-friendly laptop with good performance', 599.99, 25, 'Intel Core i5-1135G7', '8GB LPDDR4x', '512GB SSD', 'Intel Iris Xe', '14"', '1920x1080', '56Wh', '1.2kg', 'Windows 11', 'acer-swift-3.jpg', 0),
('ASUS ROG Flow Z13', 'ASUS', 'Detachable 13” gaming tablet with Ryzen AI Max 390 and RTX 4050.', 2299.99, 8, 'AMD Ryzen AI Max 390', '32GB LPDDR5X', '1TB SSD', 'NVIDIA GeForce RTX 4050', '13"', '2560x1600', '56Wh', '1.2kg', 'Windows 11', 'rog-flow-z13.jpg', 1),
('Alienware 18 Area-51', 'Alienware', '18” desktop-replacement with Ultra 9 275HX and RTX 5080.', 3599.99, 5, 'Intel Core Ultra 9 275HX', '32GB DDR5', '2TB SSD', 'NVIDIA GeForce RTX 5080', '18"', '2560x1600', '97Wh', '3.5kg', 'Windows 11', 'alienware-area51-18.jpg', 1),
('MSI Pulse GL66 11UDK-025NL', 'MSI', '15.6” gaming laptop with i7-11800H and RTX 3050 Ti.', 1399.99, 12, 'Intel Core i7-11800H', '16GB DDR4', '1TB SSD', 'NVIDIA GeForce RTX 3050 Ti', '15.6"', '1920x1080', '53Wh', '2.3kg', 'Windows 11', 'msi-pulse-gl66.jpg', 1),
('Dell Latitude 5320', 'Dell', '13” business ultrabook with strong battery life and LTE-ready chassis.', 1099.99, 20, 'Intel Core i5-1145G7', '16GB LPDDR4x', '512GB SSD', 'Intel Iris Xe', '13.3"', '1920x1080', '63Wh', '1.2kg', 'Windows 11 Pro', 'dell-latitude-5320.jpg', 1),
('HP EliteBook 850 G5', 'HP', '15” enterprise laptop with solid build, privacy features, and long runtime.', 1299.99, 14, 'Intel Core i7-8650U', '16GB DDR4', '512GB SSD', 'Intel UHD 620', '15.6"', '1920x1080', '56Wh', '1.7kg', 'Windows 11 Pro', 'hp-elitebook-850-g5.jpg', 0),
('Microsoft Surface Pro 9', 'Microsoft', '13” 2-in-1 with 120Hz display and pen support, great for travel.', 1499.99, 18, 'Intel Core i7-1255U', '16GB LPDDR5', '512GB SSD', 'Intel Iris Xe', '13"', '2880x1920', '50Wh', '0.9kg', 'Windows 11', 'surface-pro-9.jpg', 1);
INSERT INTO products (name, brand, description, price, stock, cpu, ram, storage, gpu, screen_size, resolution, battery, weight, os, main_image, category, is_featured) VALUES
('MSI Vector 16 HX AI A2XWFG', 'MSI', '16-inch gaming laptop with Intel Core Ultra 9, NVIDIA GeForce RTX 5060, and 1TB SSD storage.', 1999.99, 9, 'Intel Core Ultra 9', '16GB DDR5', '1TB SSD', 'NVIDIA GeForce RTX 5060', '16"', '2560x1600', '90Wh', '2.7kg', 'Windows 11', 'msi-vector16.jpg', 'gaming', 1);

INSERT INTO products (name, brand, description, price, stock, cpu, ram, storage, gpu, screen_size, resolution, battery, weight, os, main_image, category, is_featured) VALUES
('Apple MacBook Pro 14', 'Apple', '14-inch MacBook Pro with Apple M5 Pro, 24GB unified memory, and 1TB SSD in Space Black.', 2399.99, 8, 'Apple M5 Pro', '24GB Unified', '1TB SSD', 'Apple Integrated Graphics', '14"', '3024x1964', '72.4Wh', '1.6kg', 'macOS', 'macbookpro14.jpg', 'high-performance', 1);

INSERT INTO products (name, brand, description, price, stock, cpu, ram, storage, gpu, screen_size, resolution, battery, weight, os, main_image, category, is_featured) VALUES
('LG Gram 14 14Z90S-G.AR55A1', 'LG', '14-inch refurbished laptop with Intel Core Ultra 5, 512GB SSD, and black finish in excellent condition.', 649.99, 7, 'Intel Core Ultra 5', '16GB LPDDR5X', '512GB SSD', 'Intel Arc Graphics', '14"', '1920x1200', '72Wh', '1.0kg', 'Windows 11', 'lggram14.jpg', 'portable', 0);

INSERT INTO products (name, brand, description, price, stock, cpu, ram, storage, gpu, screen_size, resolution, battery, weight, os, main_image, category, is_featured) VALUES
('ASUS Zenbook 14 OLED', 'ASUS', '14-inch OLED laptop with Intel Core Ultra 7, 32GB RAM, 1TB SSD, and Ponder Blue finish.', 1499.99, 8, 'Intel Core Ultra 7', '32GB LPDDR5X', '1TB SSD', 'Intel Arc Graphics', '14"', '2880x1800', '75Wh', '1.2kg', 'Windows 11', 'asuszenbook14.jpg', 'portable', 1);

INSERT INTO products (name, brand, description, price, stock, cpu, ram, storage, gpu, screen_size, resolution, battery, weight, os, main_image, category, is_featured) VALUES
('Lenovo Legion 5 15', 'Lenovo', '15-inch gaming laptop with Intel Core i7, NVIDIA GeForce RTX 5060, and 1TB SSD storage.', 1599.99, 8, 'Intel Core i7', '16GB DDR5', '1TB SSD', 'NVIDIA GeForce RTX 5060', '15"', '2560x1600', '80Wh', '2.4kg', 'Windows 11', 'lenovo legion 5.jpg', 'gaming', 1);

INSERT INTO products (name, brand, description, price, stock, cpu, ram, storage, gpu, screen_size, resolution, battery, weight, os, main_image, category, is_featured) VALUES
('Acer Nitro V15', 'Acer', '15.6-inch gaming laptop with AMD Ryzen 5, NVIDIA GeForce RTX 3050, and 512GB SSD storage.', 549.99, 9, 'AMD Ryzen 5', '16GB DDR5', '512GB SSD', 'NVIDIA GeForce RTX 3050', '15.6"', '1920x1080', '57Wh', '2.1kg', 'Windows 11', 'acernitrov15.jpg', 'gaming', 0);

INSERT INTO products (name, brand, description, price, stock, cpu, ram, storage, gpu, screen_size, resolution, battery, weight, os, main_image, category, is_featured) VALUES
('Lenovo Yoga Slim 7 14', 'Lenovo', '14-inch laptop with AMD Ryzen AI 5, 512GB SSD, and Tidal Teal finish.', 1199.99, 7, 'AMD Ryzen AI 5', '16GB LPDDR5X', '512GB SSD', 'AMD Radeon Graphics', '14"', '1920x1200', '70Wh', '1.4kg', 'Windows 11', 'lenovoyogaslim7.jpg', 'portable', 0);

INSERT INTO products (name, brand, description, price, stock, cpu, ram, storage, gpu, screen_size, resolution, battery, weight, os, main_image, category, is_featured) VALUES
('HP OmniBook X Flip NGAI 16', 'HP', '16-inch 2-in-1 Copilot+ PC with Intel Core Ultra 7, 1TB SSD, and Glacier Silver finish.', 1699.99, 6, 'Intel Core Ultra 7', '16GB LPDDR5X', '1TB SSD', 'Intel Arc Graphics', '16"', '2880x1800', '68Wh', '1.9kg', 'Windows 11', 'hpomnibook.jpg', 'portable', 1);

INSERT INTO products (name, brand, description, price, stock, cpu, ram, storage, gpu, screen_size, resolution, battery, weight, os, main_image, category, is_featured) VALUES
('Lenovo ThinkPad X1 Carbon Gen 9 i5', 'Lenovo', '14-inch certified refurbished ThinkPad X1 Carbon Gen 9 with Intel Core i5-1145G7 vPro, 32GB RAM, and 256GB SSD.', 679.99, 6, 'Intel Core i5-1145G7 vPro', '32GB LPDDR4x', '256GB SSD', 'Intel Iris Xe Graphics', '14"', '1920x1200', '57Wh', '1.1kg', 'Windows 11 Pro', 'thinkpadx1.jpg', 'portable', 0);

INSERT INTO products (name, brand, description, price, stock, cpu, ram, storage, gpu, screen_size, resolution, battery, weight, os, main_image, category, is_featured) VALUES
('Samsung Galaxy Book4 Ultra', 'Samsung', '16-inch laptop with Intel Core Ultra 7 155H, 16GB RAM, and 1TB SSD.', 1799.99, 7, 'Intel Core Ultra 7 155H', '16GB LPDDR5X', '1TB SSD', 'NVIDIA GeForce RTX 4050', '16"', '2880x1800', '76Wh', '1.9kg', 'Windows 11', 'Samsung Galaxy Book4 Ultra.jpg', 'high-performance', 1);

INSERT INTO products (name, brand, description, price, stock, cpu, ram, storage, gpu, screen_size, resolution, battery, weight, os, main_image, category, is_featured) VALUES
('LG Gram 16', 'LG', '16-inch laptop with Intel Core Ultra 5 125H, 16GB RAM, and 512GB SSD.', 1399.99, 7, 'Intel Core Ultra 5 125H', '16GB LPDDR5X', '512GB SSD', 'Intel Arc Graphics', '16"', '2560x1600', '77Wh', '1.2kg', 'Windows 11', 'LG Gram 16.jpg', 'portable', 1);

INSERT INTO products (name, brand, description, price, stock, cpu, ram, storage, gpu, screen_size, resolution, battery, weight, os, main_image, category, is_featured) VALUES
('ASUS TUF Gaming A16', 'ASUS', '16-inch gaming laptop with AMD Ryzen 7, GeForce RTX 4050, 16GB RAM, and 512GB SSD.', 1299.99, 8, 'AMD Ryzen 7', '16GB DDR5', '512GB SSD', 'NVIDIA GeForce RTX 4050', '16"', '1920x1200', '90Wh', '2.2kg', 'Windows 11 Home', 'a15tuf-gaming.jpg', 'gaming', 1);

INSERT INTO products (name, brand, description, price, stock, cpu, ram, storage, gpu, screen_size, resolution, battery, weight, os, main_image, category, is_featured) VALUES
('Razer Blade 14', 'Razer', '14-inch laptop with AMD Ryzen 9 7940HS, 16GB RAM, and 1TB SSD.', 1899.99, 6, 'AMD Ryzen 9 7940HS', '16GB DDR5', '1TB SSD', 'NVIDIA GeForce RTX 4060', '14"', '2560x1600', '68Wh', '1.8kg', 'Windows 11 Home', 'razer-blade15.jpg', 'gaming', 1);

-- Normalize product categories 
UPDATE products SET category = 'gaming' WHERE name IN (
    'ASUS ROG Zephyrus',
    'MSI Pulse GL66 11UDK-025NL',
    'Lenovo Legion 5 15',
    'ASUS TUF Gaming A16',
    'Razer Blade 14'
);

UPDATE products SET category = 'high-speed' WHERE name IN (
    'ASUS ROG Flow Z13',
    'Alienware 18 Area-51',
    'MSI Vector 16 HX AI A2XWFG',
    'Apple MacBook Pro 14',
    'Samsung Galaxy Book4 Ultra'
);

UPDATE products SET category = 'portable' WHERE name IN (
    'Microsoft Surface Pro 9',
    'MacBook Air M2',
    'ASUS Zenbook 14 OLED',
    'Lenovo Yoga Slim 7 14',
    'LG Gram 16'
);

UPDATE products SET category = 'business' WHERE name IN (
    'Dell Latitude 5320',
    'HP EliteBook 850 G5',
    'Dell XPS 13',
    'Lenovo ThinkPad X1',
    'HP OmniBook X Flip NGAI 16'
);

UPDATE products SET category = 'budget' WHERE name IN (
    'Acer Swift 3',
    'HP Pavilion 15',
    'LG Gram 14 14Z90S-G.AR55A1',
    'Acer Nitro V15',
    'Lenovo ThinkPad X1 Carbon Gen 9 i5'
);
