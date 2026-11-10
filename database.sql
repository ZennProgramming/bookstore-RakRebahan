CREATE DATABASE IF NOT EXISTS rak_rebahan
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE rak_rebahan;
SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS users (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name          VARCHAR(100) NOT NULL,
  email         VARCHAR(150) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  role          ENUM('customer','admin') NOT NULL DEFAULT 'customer',
  created_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS categories (
  id   INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(60) NOT NULL UNIQUE,
  slug VARCHAR(80) NOT NULL UNIQUE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS products (
  id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  category_id    INT UNSIGNED NOT NULL,
  title          VARCHAR(200) NOT NULL,
  author         VARCHAR(120) NOT NULL,
  type           ENUM('fisik','ebook','bundle') NOT NULL DEFAULT 'fisik',
  price          INT UNSIGNED NOT NULL,
  stock          INT UNSIGNED NOT NULL DEFAULT 0,
  sold           INT UNSIGNED NOT NULL DEFAULT 0,
  cover_image    VARCHAR(255) NULL,
  description    TEXT NULL,
  is_active      TINYINT(1) NOT NULL DEFAULT 1,
  created_at     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_prod_cat FOREIGN KEY (category_id) REFERENCES categories(id),
  INDEX idx_type_cat (type, category_id),
  INDEX idx_sold (sold)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS orders (
  id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  order_code      VARCHAR(20) NOT NULL UNIQUE,
  user_id         INT UNSIGNED NOT NULL,
  customer_name   VARCHAR(100) NOT NULL,
  phone           VARCHAR(20)  NOT NULL,
  address         VARCHAR(255) NULL,
  city            VARCHAR(80)  NULL,
  postal_code     VARCHAR(10)  NULL,
  shipping_method ENUM('none','reguler','express') NOT NULL DEFAULT 'none',
  shipping_cost   INT UNSIGNED NOT NULL DEFAULT 0,
  subtotal        INT UNSIGNED NOT NULL,
  total           INT UNSIGNED NOT NULL,
  payment_method  ENUM('Transfer Bank BCA','QRIS','COD','GoPay / OVO') NOT NULL,
  status          ENUM('pending','paid','processing','shipped','completed','cancelled')
                  NOT NULL DEFAULT 'pending',
  tracking_number VARCHAR(60) NULL,
  created_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_ord_user FOREIGN KEY (user_id) REFERENCES users(id),
  INDEX idx_user (user_id),
  INDEX idx_status (status)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS order_items (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  order_id      INT UNSIGNED NOT NULL,
  product_id    INT UNSIGNED NOT NULL,
  product_title VARCHAR(200) NOT NULL,
  price         INT UNSIGNED NOT NULL,
  qty           INT UNSIGNED NOT NULL DEFAULT 1,
  CONSTRAINT fk_item_order FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
  CONSTRAINT fk_item_prod  FOREIGN KEY (product_id) REFERENCES products(id),
  CONSTRAINT chk_qty CHECK (qty > 0)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS messages (
  id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name       VARCHAR(100) NOT NULL,
  message    TEXT NOT NULL,
  is_read    TINYINT(1) NOT NULL DEFAULT 0,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

SET @perintah := IF(
  (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'products' AND CONSTRAINT_NAME = 'chk_price') > 0,
  'ALTER TABLE products DROP CONSTRAINT chk_price',
  'SELECT 1');
PREPARE ubah FROM @perintah;
EXECUTE ubah;
DEALLOCATE PREPARE ubah;

SET @perintah := IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'products' AND COLUMN_NAME = 'original_price') > 0,
  'ALTER TABLE products DROP COLUMN original_price',
  'SELECT 1');
PREPARE ubah FROM @perintah;
EXECUTE ubah;
DEALLOCATE PREPARE ubah;

SET @perintah := IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'products' AND COLUMN_NAME = 'rating') > 0,
  'ALTER TABLE products DROP COLUMN rating',
  'SELECT 1');
PREPARE ubah FROM @perintah;
EXECUTE ubah;
DEALLOCATE PREPARE ubah;

INSERT IGNORE INTO categories (name, slug) VALUES
 ('Fiksi','fiksi'), ('Non-Fiksi','non-fiksi'), ('Bisnis','bisnis'),
 ('Pengembangan Diri','pengembangan-diri'), ('Anak','anak'), ('Pendidikan','pendidikan');

INSERT IGNORE INTO users (name, email, password_hash, role) VALUES
 ('Admin Rak Rebahan', 'admin@rakrebahan.id', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin');

INSERT INTO products
  (category_id, title, author, type, price, stock, sold, description)
SELECT c.id, t.title, t.author, t.type, t.price, t.stock, t.sold, t.description
FROM (
    SELECT 'Pengembangan Diri' AS kategori, 'Atomic Habits' AS title, 'James Clear' AS author, 'fisik' AS type, 98000 AS price, 12 AS stock, 4210 AS sold, 'Cara membangun kebiasaan kecil yang konsisten sampai hasilnya terasa besar. Praktis dan mudah diterapkan.' AS description
    UNION ALL SELECT 'Pengembangan Diri', 'Filosofi Teras', 'Henry Manampiring', 'fisik', 72000, 40, 3120, 'Pengantar filsafat Stoa untuk menghadapi stres dan emosi negatif sehari-hari, ditulis dengan gaya ringan.'
    UNION ALL SELECT 'Fiksi', 'Laskar Pelangi', 'Andrea Hirata', 'fisik', 69000, 3, 5890, 'Kisah persahabatan sepuluh anak di Belitung yang berjuang demi bisa bersekolah.'
    UNION ALL SELECT 'Fiksi', 'Bumi Manusia', 'Pramoedya A. Toer', 'fisik', 89000, 18, 2760, 'Novel pertama Tetralogi Buru tentang Minke di masa kolonial. Salah satu karya terbaik sastra Indonesia.'
    UNION ALL SELECT 'Non-Fiksi', 'Sapiens', 'Yuval Noah Harari', 'fisik', 115000, 25, 1980, 'Sejarah singkat umat manusia, dari zaman batu hingga era teknologi modern.'
    UNION ALL SELECT 'Bisnis', 'Psychology of Money', 'Morgan Housel', 'fisik', 85000, 2, 2340, 'Pelajaran tentang cara berpikir soal uang, kekayaan, dan keputusan finansial yang waras.'
    UNION ALL SELECT 'Bisnis', 'Rich Dad Poor Dad', 'Robert Kiyosaki', 'fisik', 79000, 31, 3450, 'Dua sudut pandang tentang uang dan investasi yang mengubah cara banyak orang memandang kekayaan.'
    UNION ALL SELECT 'Fiksi', 'Laut Bercerita', 'Leila S. Chudori', 'fisik', 84000, 9, 1670, 'Novel tentang aktivis yang hilang di akhir 1990-an dan keluarga yang menunggu kabarnya.'
    UNION ALL SELECT 'Anak', 'Seri Dongeng Nusantara', 'Tim Kreatif', 'fisik', 55000, 60, 980, 'Kumpulan dongeng dari berbagai daerah dengan bahasa sederhana untuk dibacakan sebelum tidur.'
    UNION ALL SELECT 'Pengembangan Diri', 'Ikigai (E-Book)', 'Héctor García', 'ebook', 19000, 99, 2890, 'Rahasia hidup panjang dan bermakna ala Jepang. Format PDF/ePub, dikirim setelah pembayaran.'
    UNION ALL SELECT 'Pengembangan Diri', 'Atomic Habits (E-Book)', 'James Clear', 'ebook', 25000, 99, 3320, 'Versi digital Atomic Habits, bisa dibaca di HP, tablet, atau laptop.'
    UNION ALL SELECT 'Bisnis', 'Think and Grow Rich (E-Book)', 'Napoleon Hill', 'ebook', 15000, 99, 1450, 'Klasik tentang pola pikir sukses, tersedia dalam format digital.'
    UNION ALL SELECT 'Pendidikan', 'Belajar Python Dasar (E-Book)', 'Tim Dev', 'ebook', 29000, 99, 760, 'Panduan belajar pemrograman Python dari nol dengan contoh kode yang mudah diikuti.'
    UNION ALL SELECT 'Fiksi', 'Paket Novel Indonesia (5 Buku)', 'Bundling', 'bundle', 299000, 7, 520, 'Lima novel Indonesia pilihan dalam satu paket dengan harga lebih hemat.'
    UNION ALL SELECT 'Bisnis', 'Paket Bisnis Starter (3 Buku)', 'Bundling', 'bundle', 219000, 14, 610, 'Tiga buku bisnis dan keuangan untuk memulai, lebih murah dibanding beli satuan.'
    UNION ALL SELECT 'Anak', 'Paket Anak Pintar (6 Buku)', 'Bundling', 'bundle', 189000, 20, 430, 'Enam buku cerita dan belajar untuk anak, cocok sebagai hadiah.'
) AS t
JOIN categories c ON c.name = t.kategori
WHERE NOT EXISTS (SELECT 1 FROM products);
