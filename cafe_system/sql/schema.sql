-- Simplified Cafe Management System Schema (MySQL)

SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS order_items;
DROP TABLE IF EXISTS orders;
DROP TABLE IF EXISTS menu_items;
DROP TABLE IF EXISTS categories;
DROP TABLE IF EXISTS users;
SET FOREIGN_KEY_CHECKS = 1;

CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    fullname VARCHAR(100),
    phone VARCHAR(20),
    address TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    description TEXT
) ENGINE=InnoDB;

CREATE TABLE menu_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    category_id INT,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    price DECIMAL(10,2) NOT NULL DEFAULT 0,
    FOREIGN KEY (category_id) REFERENCES categories(id)
) ENGINE=InnoDB;

CREATE TABLE orders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    customer_name VARCHAR(100),
    total DECIMAL(10,2) DEFAULT 0,
    status VARCHAR(20) DEFAULT 'Pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id)
) ENGINE=InnoDB;

CREATE TABLE order_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT,
    item_id INT,
    quantity INT DEFAULT 1,
    subtotal DECIMAL(10,2) DEFAULT 0,
    FOREIGN KEY (order_id) REFERENCES orders(id),
    FOREIGN KEY (item_id) REFERENCES menu_items(id)
) ENGINE=InnoDB;

INSERT INTO users (username, password) VALUES
    ('demo_user', '$2y$10$ULqffLiVwAQFJBaWXGQ0LuTO7uy018Gsf0JjqMUUEb0oXGNw/DEzC');

INSERT INTO categories (name, description) VALUES
    ('Coffee', 'Coffee beverages'),
    ('Snacks', 'Light snacks and pastries');

INSERT INTO menu_items (category_id, name, description, price) VALUES
    (1, 'Espresso', 'Classic single espresso shot', 2.50),
    (1, 'Latte', 'Espresso with steamed milk', 3.75),
    (2, 'Croissant', 'Butter croissant', 2.00);
