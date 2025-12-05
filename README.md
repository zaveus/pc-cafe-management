# PC Café POS System
A simple Point-of-Sale and time management system designed for PC cafés.  
This project was created for a database course and demonstrates basic full-stack development using React, PHP, and MySQL.

## Features
- User login and session time tracking  (haven't been fully integrated)
- Basic ID verification  
- Store page for ordering items  
- User Dashboard  
- Store Dashboard  
- Order Queue  
- MySQL database for users, sessions, and orders

## Technologies Used
- React + Vite (TypeScript)  
- PHP  
- MySQL  
- CSS

## Notes
This is a basic project meant to show core functionality. More features can be added later such as payments, membership cards, or improved UI.



## SQL Query
```
CREATE TABLE time_wallet (
  wallet_id CHAR(5) PRIMARY KEY,
  time_credits DECIMAL(10,2) DEFAULT 0.00
);

CREATE TABLE members_table (
  member_id CHAR(5) PRIMARY KEY,
  wallet_id CHAR(5) NOT NULL,
  role ENUM('User','Guest','Admin') DEFAULT 'User',
  phone_number VARCHAR(20),
  email VARCHAR(100),
  first_name VARCHAR(50),
  last_name VARCHAR(50),
  date_of_birth DATE,
  FOREIGN KEY (wallet_id) REFERENCES time_wallet(wallet_id)
);

CREATE TABLE memberships_table (
  membership_id INT PRIMARY KEY AUTO_INCREMENT,
  member_id CHAR(5) NOT NULL,
  date_joined DATE,
  expiry_date DATE,
  status ENUM('Expired','Active') DEFAULT 'Active',
  FOREIGN KEY (member_id) REFERENCES members_table(member_id)
);

CREATE TABLE product_table (
  item_id CHAR(5) PRIMARY KEY,
  item_name VARCHAR(100),
  category VARCHAR(50),
  price DECIMAL(10,2),
  is_active TINYINT(1) DEFAULT 1
);

CREATE TABLE order_table (
  order_id INT PRIMARY KEY AUTO_INCREMENT,
  member_id CHAR(5),
  order_date DATETIME DEFAULT CURRENT_TIMESTAMP,
  total_amount DECIMAL(10,2),
  FOREIGN KEY (member_id) REFERENCES members_table(member_id)
);

CREATE TABLE order_items (
  order_item_id INT PRIMARY KEY AUTO_INCREMENT,
  order_id INT NOT NULL,
  item_id CHAR(5) NOT NULL,
  quantity INT DEFAULT 1,
  subtotal DECIMAL(10,2),
  FOREIGN KEY (order_id) REFERENCES order_table(order_id),
  FOREIGN KEY (item_id) REFERENCES product_table(item_id)
);

INSERT INTO time_wallet (wallet_id, time_credits) VALUES
('ADMIN', 0),
('W0001', 3600);

INSERT INTO members_table (member_id, wallet_id, role, phone_number, email, first_name, last_name)
VALUES
('admin', 'ADMIN', 'Admin', '0000000000', 'admin@admin.com', 'Admin', 'User'),
('M0001', 'W0001', 'User', '1234567890', 'user@example.com', 'Juan', 'Dela Cruz');

INSERT INTO memberships_table (member_id, date_joined, expiry_date, status)
VALUES
('admin', CURDATE(), NULL, 'Active'),
('M0001', CURDATE(), DATE_ADD(CURDATE(), INTERVAL 1 YEAR), 'Active');

INSERT INTO product_table (item_id, item_name, category, price) VALUES
('DR001', 'Iced Tea', 'Drinks', 2.99),
('DR002', 'Bottled Water', 'Drinks', 1.99),
('SN001', 'Chips', 'Snacks', 3.49),
('ML001', 'Meal A', 'Meals', 14.99),
('CR001', '1hr Extension', 'Credits', 5.99);
```



