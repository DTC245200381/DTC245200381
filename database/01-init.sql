CREATE TABLE IF NOT EXISTS recipes (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  title VARCHAR(180) NOT NULL,
  category VARCHAR(80) NOT NULL DEFAULT 'Khác',
  ingredients TEXT NOT NULL,
  instructions TEXT NOT NULL,
  minutes SMALLINT UNSIGNED NOT NULL DEFAULT 30,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_recipes_title (title),
  INDEX idx_recipes_category (category)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

INSERT INTO recipes (title, category, ingredients, instructions, minutes) VALUES
('Canh chua cá', 'Món chính', 'Cá, cà chua, dứa, me, giá, rau thơm, gia vị', '1. Sơ chế cá và rau.\n2. Nấu nước với me, cà chua và dứa.\n3. Cho cá vào nấu chín.\n4. Thêm rau thơm và nêm vừa ăn.', 35),
('Đậu hũ sốt cà chua', 'Món chay', 'Đậu hũ, cà chua, hành, dầu ăn, gia vị', '1. Cắt đậu hũ và áp chảo.\n2. Xào cà chua thành sốt.\n3. Cho đậu vào rim 5–7 phút.\n4. Nêm gia vị và dùng nóng.', 20),
('Chè đậu xanh', 'Món tráng miệng', 'Đậu xanh, đường, nước, lá dứa', '1. Ngâm đậu xanh.\n2. Nấu đậu đến mềm.\n3. Thêm đường và lá dứa.\n4. Dùng nóng hoặc lạnh.', 40);

