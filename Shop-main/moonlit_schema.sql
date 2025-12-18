-- ============================
-- RESET DATABASE
-- ============================
DROP DATABASE IF EXISTS moonlit_store;

CREATE DATABASE moonlit_store
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE moonlit_store;

-- ============================
-- USER ACCOUNT
-- ============================
CREATE TABLE User_Account (
  UserID VARCHAR(10) PRIMARY KEY,
  Email VARCHAR(255) UNIQUE NOT NULL,
  PasswordHash VARCHAR(255) NOT NULL,
  Role ENUM('admin','customer') DEFAULT 'customer',
  CreatedAt DATETIME DEFAULT CURRENT_TIMESTAMP,
  Status ENUM('active','banned') DEFAULT 'active'
) ENGINE=InnoDB;

-- ============================
-- CATEGORIES
-- ============================
CREATE TABLE Categories (
  CategoryID INT AUTO_INCREMENT PRIMARY KEY,
  CategoryName VARCHAR(255) NOT NULL,
  Description TEXT,
  CreatedDate DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ============================
-- PUBLISHER
-- ============================
CREATE TABLE Publisher (
  PublisherID INT AUTO_INCREMENT PRIMARY KEY,
  PublisherName VARCHAR(255) NOT NULL
) ENGINE=InnoDB;

-- ============================
-- PRODUCT (IMAGE BLOB)
-- ============================
CREATE TABLE Product (
  ProductID INT AUTO_INCREMENT PRIMARY KEY,
  ProductName VARCHAR(255) NOT NULL,
  Description TEXT,

  Price DECIMAL(10,2) NOT NULL,
  DiscountPrice DECIMAL(10,2) DEFAULT NULL,

  Image LONGBLOB NULL,

  PublisherID INT DEFAULT NULL,
  SoldQuantity INT DEFAULT 0,
  `Condition` ENUM('New','Used') DEFAULT 'New',
  CreatedDate DATETIME DEFAULT CURRENT_TIMESTAMP,

  CONSTRAINT fk_product_publisher
    FOREIGN KEY (PublisherID)
    REFERENCES Publisher(PublisherID)
    ON DELETE SET NULL
    ON UPDATE CASCADE
) ENGINE=InnoDB;

-- ============================
-- PRODUCT - CATEGORY (MANY TO MANY)
-- ============================
CREATE TABLE Product_Categories (
  ProductID INT NOT NULL,
  CategoryID INT NOT NULL,
  PRIMARY KEY (ProductID, CategoryID),
  CONSTRAINT fk_pc_product
    FOREIGN KEY (ProductID) REFERENCES Product(ProductID)
    ON DELETE CASCADE
    ON UPDATE CASCADE,
  CONSTRAINT fk_pc_category
    FOREIGN KEY (CategoryID) REFERENCES Categories(CategoryID)
    ON DELETE CASCADE
    ON UPDATE CASCADE
) ENGINE=InnoDB;

-- ============================
-- REVIEW (RATING)
-- ============================
CREATE TABLE Review (
  ReviewID INT AUTO_INCREMENT PRIMARY KEY,
  ProductID INT NOT NULL,
  UserID VARCHAR(10),
  Rating TINYINT NOT NULL,
  Comment TEXT,
  CreatedAt DATETIME DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_review_product
    FOREIGN KEY (ProductID)
    REFERENCES Product(ProductID)
    ON DELETE CASCADE
    ON UPDATE CASCADE
) ENGINE=InnoDB;

-- ============================
-- BOOK POST
-- ============================
CREATE TABLE Book_Post (
  PostID INT AUTO_INCREMENT PRIMARY KEY,
  Title VARCHAR(255) NOT NULL,
  Slug VARCHAR(255) UNIQUE,
  Excerpt TEXT,
  Content LONGTEXT,
  Thumbnail LONGBLOB NULL,
  AuthorName VARCHAR(100),
  CreatedAt DATETIME DEFAULT CURRENT_TIMESTAMP,
  UpdatedAt DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  Status ENUM('draft','published') DEFAULT 'draft'
) ENGINE=InnoDB;

-- ============================
-- SAMPLE DATA (NO IMAGE BLOB)
-- ============================

INSERT INTO Categories (CategoryName) VALUES
('Tiểu thuyết'),
('Kinh doanh'),
('Kỹ năng sống'),
('Sách cũ');

INSERT INTO Publisher (PublisherName) VALUES
('NXB Trẻ'),
('NXB Kim Đồng'),
('NXB Nhã Nam');

INSERT INTO Product (ProductName, Description, Price, DiscountPrice, PublisherID, `Condition`)
VALUES
('Nhà Giả Kim', 'Một hành trình đi tìm kho báu.', 120000, 89000, 3, 'New'),
('Dám Bị Ghét', 'Sống đúng với bản thân.', 135000, 99000, 1, 'New'),
('Tư Duy Nhanh Và Chậm', 'Tâm lý học hành vi.', 189000, 159000, 2, 'New'),
('Harry Potter (cũ)', 'Sách cũ, còn đẹp.', 60000, NULL, 2, 'Used');

INSERT INTO Product_Categories VALUES
(1,1),
(2,3),
(3,2),
(4,4);

INSERT INTO Review (ProductID, Rating, Comment) VALUES
(1,5,'Hay vl'),
(1,4,'Đọc cuốn'),
(2,5,'Đúng gu'),
(3,4,'Khó nhưng đáng');
