-- K Supermarket Database Schema with Sample Data
-- Run this SQL file in phpMyAdmin or MySQL Command Line

-- Create Database
DROP DATABASE IF EXISTS k_supermarket;
CREATE DATABASE k_supermarket;
USE k_supermarket;

-- ===========================
-- Users Table
-- ===========================
CREATE TABLE users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    username VARCHAR(100) NOT NULL UNIQUE,
    email VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role ENUM('admin', 'staff', 'customer') NOT NULL DEFAULT 'customer',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- ===========================
-- Categories Table
-- ===========================
CREATE TABLE categories (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL UNIQUE,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ===========================
-- Suppliers Table
-- ===========================
CREATE TABLE suppliers (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL UNIQUE,
    contact_person VARCHAR(100),
    email VARCHAR(100),
    phone VARCHAR(15),
    address TEXT,
    city VARCHAR(50),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ===========================
-- Products Table
-- ===========================
CREATE TABLE products (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(150) NOT NULL,
    description TEXT,
    price DECIMAL(10, 2) NOT NULL,
    stock INT NOT NULL DEFAULT 0,
    category_id INT NOT NULL,
    supplier_id INT,
    image VARCHAR(255),
    barcode VARCHAR(50) UNIQUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE CASCADE,
    FOREIGN KEY (supplier_id) REFERENCES suppliers(id)
);

-- ===========================
-- Orders Table
-- ===========================
CREATE TABLE orders (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    order_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    total_amount DECIMAL(12, 2) NOT NULL,
    status ENUM('pending', 'processing', 'completed', 'cancelled') DEFAULT 'pending',
    delivery_address TEXT,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- ===========================
-- Order Items Table
-- ===========================
CREATE TABLE order_items (
    id INT PRIMARY KEY AUTO_INCREMENT,
    order_id INT NOT NULL,
    product_id INT NOT NULL,
    quantity INT NOT NULL DEFAULT 1,
    unit_price DECIMAL(10, 2) NOT NULL,
    subtotal DECIMAL(12, 2) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id)
);

-- ===========================
-- Stock History Table (Optional - for tracking)
-- ===========================
CREATE TABLE stock_history (
    id INT PRIMARY KEY AUTO_INCREMENT,
    product_id INT NOT NULL,
    quantity_change INT NOT NULL,
    reason VARCHAR(100),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
);

-- ===========================
-- SAMPLE DATA
-- ===========================

-- Insert Users (Admin, Staff, Customer)
-- Passwords are hashed with password_hash() - "password123"
INSERT INTO users (username, email, password, role) VALUES
('admin', 'admin@kmarketplace.com', '$2y$10$YourHashedPasswordHere1', 'admin'),
('staff1', 'staff1@kmarketplace.com', '$2y$10$YourHashedPasswordHere2', 'staff'),
('staff2', 'staff2@kmarketplace.com', '$2y$10$YourHashedPasswordHere3', 'staff'),
('customer1', 'customer1@kmarketplace.com', '$2y$10$YourHashedPasswordHere4', 'customer'),
('customer2', 'customer2@kmarketplace.com', '$2y$10$YourHashedPasswordHere5', 'customer'),
('customer3', 'customer3@kmarketplace.com', '$2y$10$YourHashedPasswordHere6', 'customer');

-- Insert Categories
INSERT INTO categories (name, description) VALUES
('Vegetables', 'Fresh organic vegetables'),
('Fruits', 'Fresh fruits from local farms'),
('Dairy & Eggs', 'Milk, cheese, butter, eggs'),
('Meat & Poultry', 'Fresh meat and poultry products'),
('Grains & Cereals', 'Rice, wheat, pulses, cereals'),
('Spices & Condiments', 'Various spices and condiments'),
('Beverages', 'Soft drinks, juices, tea, coffee'),
('Bakery', 'Bread, cakes, and baked items'),
('Frozen Foods', 'Frozen vegetables, meat, ready meals'),
('Snacks & Sweets', 'Chips, cookies, chocolates, sweets');

-- Insert Suppliers
INSERT INTO suppliers (name, contact_person, email, phone, address, city) VALUES
('Fresh Valley Farms', 'John Silva', 'john@freshvalley.com', '0771234567', '123 Farm Road', 'Colombo'),
('Mountain Herbs Co', 'Ravi Kumar', 'ravi@mountainherbs.com', '0712345678', '456 Hill Street', 'Kandy'),
('Coastal Fisheries', 'Maria Santos', 'maria@coastalfisheries.com', '0773456789', '789 Beach Avenue', 'Galle'),
('Dairy Farms Sri Lanka', 'Prema Jayasena', 'prema@dairyfarms.com', '0774567890', '321 Pasture Lane', 'Matara'),
('Spice Masters', 'Ahmed Hassan', 'ahmed@spicemasters.com', '0775678901', '654 Spice Market', 'Negombo'),
('Beverage Wholesalers', 'Lisa Wong', 'lisa@beveragew.com', '0776789012', '987 Trade Street', 'Jaffna');

-- Insert Products (Vegetables)
INSERT INTO products (name, description, price, stock, category_id, supplier_id, barcode) VALUES
('Tomatoes', 'Fresh red tomatoes', 80.00, 50, 1, 1, 'TOM001'),
('Carrots', 'Orange carrots - vitamin rich', 60.00, 75, 1, 1, 'CAR001'),
('Potatoes', 'Starchy potatoes', 40.00, 100, 1, 1, 'POT001'),
('Onions', 'Red and white onions', 50.00, 80, 1, 1, 'ONI001'),
('Bell Peppers', 'Colorful bell peppers', 120.00, 35, 1, 1, 'BPE001'),
('Cabbage', 'Fresh green cabbage', 45.00, 60, 1, 1, 'CAB001');

-- Insert Products (Fruits)
INSERT INTO products (name, description, price, stock, category_id, supplier_id, barcode) VALUES
('Bananas', 'Yellow bananas - 1kg', 100.00, 100, 2, 1, 'BAN001'),
('Apples', 'Red apples - imported', 200.00, 45, 2, 1, 'APP001'),
('Oranges', 'Fresh citrus oranges', 120.00, 65, 2, 1, 'ORA001'),
('Mangoes', 'Sweet mango fruits', 180.00, 30, 2, 1, 'MAN001'),
('Papaya', 'Fresh green papaya', 90.00, 40, 2, 1, 'PAP001');

-- Insert Products (Dairy & Eggs)
INSERT INTO products (name, description, price, stock, category_id, supplier_id, barcode) VALUES
('Milk - Full Cream 1L', 'Fresh whole milk', 110.00, 150, 3, 4, 'MLK001'),
('Cheddar Cheese', 'Aged cheddar cheese', 450.00, 25, 3, 4, 'CHE001'),
('Eggs - Dozen', 'Fresh chicken eggs', 280.00, 80, 3, 4, 'EGG001'),
('Butter 500g', 'Creamy unsalted butter', 350.00, 40, 3, 4, 'BUT001'),
('Yogurt 500ml', 'Plain greek yogurt', 150.00, 60, 3, 4, 'YOG001');

-- Insert Products (Meat & Poultry)
INSERT INTO products (name, description, price, stock, category_id, supplier_id, barcode) VALUES
('Chicken Breast 1kg', 'Fresh chicken breast', 680.00, 45, 4, 3, 'CHB001'),
('Ground Beef 500g', 'Lean ground beef', 750.00, 30, 4, 3, 'GBE001'),
('Fish Fillets 500g', 'Fresh white fish fillets', 580.00, 35, 4, 3, 'FIL001'),
('Shrimp 500g', 'Frozen shrimp', 950.00, 20, 4, 3, 'SHR001');

-- Insert Products (Grains & Cereals)
INSERT INTO products (name, description, price, stock, category_id, supplier_id, barcode) VALUES
('Basmati Rice 5kg', 'Premium basmati rice', 450.00, 100, 5, 1, 'RIC001'),
('Wheat Flour 2kg', 'All-purpose flour', 180.00, 80, 5, 1, 'WHE001'),
('Lentils 1kg', 'Red lentils', 220.00, 60, 5, 1, 'LEN001'),
('Oats 500g', 'Rolled oats', 280.00, 45, 5, 1, 'OAT001');

-- Insert Products (Spices & Condiments)
INSERT INTO products (name, description, price, stock, category_id, supplier_id, barcode) VALUES
('Curry Powder 200g', 'Spice curry blend', 320.00, 50, 6, 5, 'CUR001'),
('Turmeric 100g', 'Pure turmeric powder', 180.00, 75, 6, 5, 'TUR001'),
('Chili Powder 100g', 'Hot chili powder', 150.00, 60, 6, 5, 'CHI001'),
('Salt 1kg', 'Iodized table salt', 80.00, 100, 6, 5, 'SAL001'),
('Black Pepper 100g', 'Ground black pepper', 220.00, 45, 6, 5, 'BLK001');

-- Insert Products (Beverages)
INSERT INTO products (name, description, price, stock, category_id, supplier_id, barcode) VALUES
('Orange Juice 1L', 'Fresh orange juice', 180.00, 120, 7, 6, 'OJU001'),
('Coca-Cola 2L', 'Soft drink', 220.00, 150, 7, 6, 'CKL001'),
('Tea Bags 50 Pack', 'Black tea bags', 280.00, 80, 7, 6, 'TEA001'),
('Coffee 500g', 'Ground coffee beans', 450.00, 50, 7, 6, 'COF001'),
('Mineral Water 500ml', 'Purified water', 50.00, 300, 7, 6, 'WAT001');

-- Insert Products (Bakery)
INSERT INTO products (name, description, price, stock, category_id, supplier_id, barcode) VALUES
('White Bread', 'Fresh white bread loaf', 150.00, 40, 8, 1, 'WBR001'),
('Brown Bread', 'Whole wheat bread', 180.00, 35, 8, 1, 'BBR001'),
('Croissants 6 Pack', 'Butter croissants', 320.00, 25, 8, 1, 'CRO001');

-- Insert Products (Frozen Foods)
INSERT INTO products (name, description, price, stock, category_id, supplier_id, barcode) VALUES
('Frozen Mixed Vegetables 1kg', 'IQF vegetables', 280.00, 60, 9, 1, 'FVE001'),
('Frozen Chicken Wings 1kg', 'IQF chicken wings', 580.00, 30, 9, 3, 'FCW001'),
('Pizza Frozen', 'Frozen pizza', 420.00, 20, 9, 1, 'PIZ001');

-- Insert Products (Snacks & Sweets)
INSERT INTO products (name, description, price, stock, category_id, supplier_id, barcode) VALUES
('Potato Chips 150g', 'Crispy potato chips', 120.00, 100, 10, 1, 'CHI002'),
('Chocolate Bar', 'Milk chocolate', 180.00, 150, 10, 1, 'CHO001'),
('Cookies 250g', 'Assorted cookies', 220.00, 80, 10, 1, 'COO001'),
('Candy Mix 500g', 'Mixed sweets', 350.00, 60, 10, 1, 'CAN001');

-- ===========================
-- SAMPLE ORDERS
-- ===========================

-- Insert Order 1 (Customer 1)
INSERT INTO orders (user_id, total_amount, status, delivery_address) VALUES
(4, 1200.00, 'completed', '100 Main Street, Colombo');

-- Insert Order Items for Order 1
INSERT INTO order_items (order_id, product_id, quantity, unit_price, subtotal) VALUES
(1, 1, 2, 80.00, 160.00),
(1, 7, 1, 100.00, 100.00),
(1, 13, 1, 280.00, 280.00),
(1, 19, 1, 450.00, 450.00),
(1, 30, 1, 180.00, 180.00);

-- Insert Order 2 (Customer 2)
INSERT INTO orders (user_id, total_amount, status, delivery_address) VALUES
(5, 850.50, 'processing', '205 Market Road, Kandy');

-- Insert Order Items for Order 2
INSERT INTO order_items (order_id, product_id, quantity, unit_price, subtotal) VALUES
(2, 2, 3, 60.00, 180.00),
(2, 8, 1, 200.00, 200.00),
(2, 15, 1, 110.00, 110.00),
(2, 27, 2, 120.00, 240.00),
(2, 32, 1, 120.00, 120.00);

-- Insert Order 3 (Customer 3)
INSERT INTO orders (user_id, total_amount, status, delivery_address) VALUES
(6, 2150.75, 'pending', '450 Beach Avenue, Galle');

-- Insert Order Items for Order 3
INSERT INTO order_items (order_id, product_id, quantity, unit_price, subtotal) VALUES
(3, 21, 1, 680.00, 680.00),
(3, 12, 2, 450.00, 900.00),
(3, 33, 1, 280.00, 280.00),
(3, 5, 3, 120.00, 360.00),
(3, 38, 1, 350.00, 350.00);

-- Insert Order 4 (Customer 1 - another order)
INSERT INTO orders (user_id, total_amount, status, delivery_address) VALUES
(4, 680.00, 'completed', '100 Main Street, Colombo');

-- Insert Order Items for Order 4
INSERT INTO order_items (order_id, product_id, quantity, unit_price, subtotal) VALUES
(4, 21, 1, 680.00, 680.00);

-- ===========================
-- CREATE INDEXES FOR PERFORMANCE
-- ===========================
CREATE INDEX idx_user_role ON users(role);
CREATE INDEX idx_product_category ON products(category_id);
CREATE INDEX idx_product_supplier ON products(supplier_id);
CREATE INDEX idx_order_user ON orders(user_id);
CREATE INDEX idx_order_status ON orders(status);
CREATE INDEX idx_order_date ON orders(order_date);
CREATE INDEX idx_order_items_order ON order_items(order_id);
CREATE INDEX idx_order_items_product ON order_items(product_id);

-- ===========================
-- VIEWS (Optional - for analytics)
-- ===========================

-- View: Customer Order Summary
CREATE VIEW customer_order_summary AS
SELECT 
    u.id,
    u.username,
    u.email,
    COUNT(o.id) as total_orders,
    SUM(o.total_amount) as total_spent,
    MAX(o.order_date) as last_order_date
FROM users u
LEFT JOIN orders o ON u.id = o.user_id
WHERE u.role = 'customer'
GROUP BY u.id, u.username, u.email;

-- View: Product Sales
CREATE VIEW product_sales AS
SELECT 
    p.id,
    p.name,
    p.price,
    p.stock,
    c.name as category,
    COUNT(oi.id) as times_sold,
    SUM(oi.quantity) as total_quantity_sold,
    SUM(oi.subtotal) as total_revenue
FROM products p
LEFT JOIN order_items oi ON p.id = oi.product_id
LEFT JOIN categories c ON p.category_id = c.id
GROUP BY p.id, p.name, p.price, p.stock, c.name;

-- ===========================
-- FINAL MESSAGE
-- ===========================
-- Database setup complete!
-- All tables, sample data, indexes, and views have been created.
-- You can now use the application with this data.

-- NOTE: To set proper password hashes in the users table, run this PHP code:
-- echo password_hash('password123', PASSWORD_BCRYPT);
-- Then update the users table passwords with the actual hashes.

SELECT 'Database Setup Complete!' as Status;
