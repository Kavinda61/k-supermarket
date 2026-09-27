-- K Supermarket Offers & Discounts
-- Run this once after database.sql in the k_supermarket database.
USE k_supermarket;

CREATE TABLE IF NOT EXISTS discounts (
    id INT PRIMARY KEY AUTO_INCREMENT,
    title VARCHAR(150) NOT NULL,
    description TEXT,
    promo_code VARCHAR(50) UNIQUE,
    discount_type ENUM('percentage', 'flat') NOT NULL DEFAULT 'percentage',
    discount_value DECIMAL(10, 2) NOT NULL,
    minimum_order_amount DECIMAL(12, 2) NOT NULL DEFAULT 0.00,
    max_discount_amount DECIMAL(12, 2) DEFAULT NULL,
    starts_at DATETIME NOT NULL,
    ends_at DATETIME NOT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_discounts_active_dates (is_active, starts_at, ends_at),
    INDEX idx_discounts_promo_code (promo_code)
);

-- Order audit fields keep the original order total while recording the
-- subtotal, applied offer, and discount used at checkout.
ALTER TABLE orders
    ADD COLUMN subtotal_amount DECIMAL(12, 2) NULL AFTER total_amount,
    ADD COLUMN discount_amount DECIMAL(12, 2) NOT NULL DEFAULT 0.00 AFTER subtotal_amount,
    ADD COLUMN promo_code VARCHAR(50) NULL AFTER discount_amount,
    ADD COLUMN discount_id INT NULL AFTER promo_code,
    ADD INDEX idx_orders_discount_id (discount_id),
    ADD CONSTRAINT fk_orders_discount
        FOREIGN KEY (discount_id) REFERENCES discounts(id) ON DELETE SET NULL;
