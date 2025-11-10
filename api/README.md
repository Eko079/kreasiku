# Kreasiku API (PHP)

## ENV / DB
Edit `api/config.php` dengan kredensial MySQL hosting kamu.

## Buat Tabel (MySQL)
Jalankan di phpMyAdmin:

```sql
CREATE TABLE users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  email VARCHAR(190) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  name VARCHAR(120) DEFAULT '',
  class VARCHAR(120) DEFAULT '',
  avatar_url VARCHAR(255) DEFAULT NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  deleted_at DATETIME DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE designs (
  id INT AUTO_INCREMENT PRIMARY KEY,
  owner_id INT NOT NULL,
  type ENUM('image','figma') NOT NULL,
  title VARCHAR(190) DEFAULT '',
  description TEXT,
  category ENUM('portofolio','website','cv','desain','logo') NOT NULL,
  visibility ENUM('public','private') DEFAULT 'public',
  comment_enabled TINYINT(1) DEFAULT 1,
  allow_download TINYINT(1) DEFAULT 0,
  status ENUM('draft','scheduled','published') DEFAULT 'published',
  scheduled_at DATETIME DEFAULT NULL,
  published_at DATETIME DEFAULT NULL,
  likes_count INT DEFAULT 0,
  saves_count INT DEFAULT 0,
  comments_count INT DEFAULT 0,
  figma_url VARCHAR(500) DEFAULT NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  KEY idx_pub (status, category, published_at),
  CONSTRAINT fk_design_user FOREIGN KEY (owner_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE design_images (
  id INT AUTO_INCREMENT PRIMARY KEY,
  design_id INT NOT NULL,
  url VARCHAR(500) NOT NULL,
  sort_order INT DEFAULT 0,
  CONSTRAINT fk_img_design FOREIGN KEY (design_id) REFERENCES designs(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
