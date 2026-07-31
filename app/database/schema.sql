SET NAMES utf8mb4;
SET UNIQUE_CHECKS=0;
SET FOREIGN_KEY_CHECKS=0;

CREATE TABLE IF NOT EXISTS core_settings (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  setting_key VARCHAR(100) NOT NULL,
  setting_value TEXT NULL,
  updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uniq_setting_key (setting_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS core_users (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  name VARCHAR(100) NOT NULL,
  email VARCHAR(150) NOT NULL,
  password VARCHAR(255) NOT NULL,
  avatar VARCHAR(255) NULL,
  role ENUM('SUPER_ADMIN','ADMIN','USER') DEFAULT 'USER',
  status TINYINT DEFAULT 0,
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uniq_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS bases (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  cloned_from_id INT UNSIGNED NULL,
    name VARCHAR(150) NOT NULL,
    slug VARCHAR(150) NOT NULL,
    description TEXT NULL,
    showcase_title VARCHAR(150) NULL,
    showcase_summary TEXT NULL,
    showcase_features TEXT NULL,
    showcase_cover_image VARCHAR(255) NULL,
    showcase_banner_image VARCHAR(255) NULL,
    showcase_detail_url VARCHAR(500) NULL,
    showcase_cta_text VARCHAR(80) NULL,
    showcase_featured TINYINT DEFAULT 0,
    showcase_order INT DEFAULT 0,
    showcase_status TINYINT DEFAULT 0,
    allows_users TINYINT DEFAULT 1,
  max_admins INT DEFAULT 1,
  status TINYINT DEFAULT 1,
  base_stage ENUM('laboratory','published','legacy','archived') NOT NULL DEFAULT 'laboratory',
  is_protected TINYINT DEFAULT 0,
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uniq_slug (slug),
  KEY fk_bases_cloned (cloned_from_id),
  CONSTRAINT fk_bases_cloned FOREIGN KEY (cloned_from_id) REFERENCES bases(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS plans (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  name VARCHAR(100) NOT NULL,
  billing_cycle ENUM('free','monthly','annual') DEFAULT 'free',
  price DECIMAL(10,2) DEFAULT 0.00,
  limits_json LONGTEXT NULL,
  status TINYINT DEFAULT 1,
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS plan_bases (
  plan_id INT UNSIGNED NOT NULL,
  base_id INT UNSIGNED NOT NULL,
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (plan_id, base_id),
  KEY idx_plan_bases_base (base_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS plan_prices (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  plan_id INT UNSIGNED NOT NULL,
  billing_cycle ENUM('free','monthly','annual') NOT NULL DEFAULT 'monthly',
  price DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  status TINYINT DEFAULT 1,
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uniq_plan_cycle (plan_id, billing_cycle),
  KEY idx_plan_prices_plan (plan_id),
  KEY idx_plan_prices_cycle (billing_cycle)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS base_plan_prices (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  base_id INT UNSIGNED NOT NULL,
  plan_price_id INT UNSIGNED NOT NULL,
  custom_price DECIMAL(10,2) NULL,
  status TINYINT DEFAULT 1,
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uniq_base_plan_price (base_id, plan_price_id),
  KEY idx_base_plan_prices_base (base_id),
  KEY idx_base_plan_prices_price (plan_price_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS base_module_prices (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  base_id INT UNSIGNED NOT NULL,
  module_slug VARCHAR(120) NOT NULL,
  commercial_category ENUM('included','extra','coming_soon') DEFAULT 'extra',
  monthly_price DECIMAL(10,2) NULL,
  annual_price DECIMAL(10,2) NULL,
  status TINYINT DEFAULT 1,
  display_order INT DEFAULT 0,
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uniq_base_module_price (base_id, module_slug),
  KEY idx_base_module_prices_base (base_id),
  KEY idx_base_module_prices_category (commercial_category),
  KEY idx_base_module_prices_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS projects (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  name VARCHAR(150) NOT NULL,
  slug VARCHAR(150) NOT NULL,
  owner_name VARCHAR(100) NOT NULL,
  owner_email VARCHAR(150) NOT NULL,
  base_id INT UNSIGNED NOT NULL,
  plan_id INT UNSIGNED NOT NULL,
  plan_price_id INT UNSIGNED NULL,
  module_id INT UNSIGNED NULL,
  path VARCHAR(255) NOT NULL,
  billing_status ENUM('pending','active','suspended') DEFAULT 'pending',
  expires_at DATE NULL,
  status ENUM('pending','active','blocked','deleted') DEFAULT 'pending',
  deletion_requested_at DATETIME NULL,
  deletion_scheduled_at DATETIME NULL,
  deletion_canceled_at DATETIME NULL,
  deleted_at DATETIME NULL,
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uniq_slug (slug),
  KEY idx_base (base_id),
  KEY idx_plan (plan_id),
  KEY idx_plan_price (plan_price_id),
  KEY idx_module (module_id),
  KEY idx_status (status),
  KEY idx_billing_status (billing_status),
  KEY idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS plan_upgrade_requests (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  project_id INT UNSIGNED NOT NULL,
  plan_id INT UNSIGNED NOT NULL,
  plan_price_id INT UNSIGNED NULL,
  requested_by_name VARCHAR(150) NULL,
  requested_by_email VARCHAR(150) NULL,
  amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  receipt_path VARCHAR(255) NULL,
  requested_modules_json LONGTEXT NULL,
  notes TEXT NULL,
  status ENUM('pending','approved','rejected') DEFAULT 'pending',
  reviewed_by_user_id INT UNSIGNED NULL,
  reviewed_at DATETIME NULL,
  review_notes TEXT NULL,
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_upgrade_project (project_id),
  KEY idx_upgrade_plan (plan_id),
  KEY idx_upgrade_plan_price (plan_price_id),
  KEY idx_upgrade_status (status),
  KEY idx_upgrade_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS plan_refund_requests (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  upgrade_request_id INT UNSIGNED NOT NULL,
  project_id INT UNSIGNED NOT NULL,
  plan_id INT UNSIGNED NOT NULL,
  plan_price_id INT UNSIGNED NULL,
  amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  reason TEXT NULL,
  status ENUM('pending','approved','rejected') DEFAULT 'pending',
  reviewed_by_user_id INT UNSIGNED NULL,
  reviewed_at DATETIME NULL,
  review_notes TEXT NULL,
  sent_by_user_id INT UNSIGNED NULL,
  sent_at DATETIME NULL,
  sent_receipt_path VARCHAR(255) NULL,
  sent_notes TEXT NULL,
  requested_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uniq_refund_upgrade (upgrade_request_id),
  KEY idx_refund_project (project_id),
  KEY idx_refund_status (status),
  KEY idx_refund_requested (requested_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS project_wallet_requests (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  project_id INT UNSIGNED NOT NULL,
  requested_by_name VARCHAR(150) NULL,
  requested_by_email VARCHAR(150) NULL,
  amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  payment_method VARCHAR(40) NULL,
  receipt_path VARCHAR(255) NULL,
  notes TEXT NULL,
  status ENUM('pending','approved','rejected') DEFAULT 'pending',
  reviewed_by_user_id INT UNSIGNED NULL,
  reviewed_at DATETIME NULL,
  review_notes TEXT NULL,
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_wallet_request_project (project_id),
  KEY idx_wallet_request_status (status),
  KEY idx_wallet_request_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS project_wallet_movements (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  project_id INT UNSIGNED NOT NULL,
  movement_type ENUM('credit','debit') NOT NULL,
  source ENUM('balance_request','upgrade','refund','manual_adjustment') NOT NULL DEFAULT 'manual_adjustment',
  amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  description VARCHAR(255) NULL,
  reference_table VARCHAR(80) NULL,
  reference_id INT UNSIGNED NULL,
  status ENUM('applied','canceled') DEFAULT 'applied',
  created_by_user_id INT UNSIGNED NULL,
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_wallet_movement_project (project_id),
  KEY idx_wallet_movement_source (source),
  KEY idx_wallet_movement_status (status),
  KEY idx_wallet_movement_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS project_logs (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  project_id INT UNSIGNED NULL,
  action VARCHAR(100) NOT NULL,
  message TEXT NULL,
  level ENUM('info','warning','error') DEFAULT 'info',
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_project (project_id),
  KEY idx_action (action),
  KEY idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS project_access_tokens (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  project_id INT UNSIGNED NOT NULL,
  email VARCHAR(150) NOT NULL,
  token VARCHAR(255) NOT NULL,
  expires_at DATETIME NOT NULL,
  used TINYINT DEFAULT 0,
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uniq_token (token),
  KEY idx_project (project_id),
  KEY idx_email (email),
  KEY idx_expires (expires_at),
  KEY idx_token_lookup (token)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS project_requests (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  name VARCHAR(150) NOT NULL,
  slug VARCHAR(150) NOT NULL,
  owner_name VARCHAR(100) NOT NULL,
  owner_email VARCHAR(150) NOT NULL,
  status ENUM('pending','approved','rejected') DEFAULT 'pending',
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_status (status),
  KEY idx_email (owner_email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS leads (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  name VARCHAR(120) NULL,
  phone VARCHAR(40) NULL,
  email VARCHAR(120) NULL,
  state VARCHAR(60) NULL,
  city VARCHAR(60) NULL,
  site_name VARCHAR(150) NULL,
  slug VARCHAR(150) NULL,
  base_id INT UNSIGNED NULL,
  ip_address VARCHAR(45) NULL,
  user_agent VARCHAR(500) NULL,
  referer VARCHAR(500) NULL,
  content_campaign_key VARCHAR(120) NULL,
  content_source VARCHAR(120) NULL,
  continue_token VARCHAR(120) NULL,
  continue_expires_at DATETIME NULL,
  implementation_status VARCHAR(30) DEFAULT 'pending',
  status VARCHAR(20) DEFAULT 'new',
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_content_campaign_key (content_campaign_key),
  KEY idx_content_source (content_source),
  KEY idx_leads_continue_token (continue_token)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS core_media (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  file_name VARCHAR(255) NOT NULL,
  type ENUM('image') DEFAULT 'image',
  width INT NULL,
  height INT NULL,
  size INT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS core_page_contents (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  slug VARCHAR(100) NOT NULL,
  model_slug VARCHAR(100) NULL,
  title VARCHAR(150) NOT NULL,
  area ENUM('public','admin') NOT NULL,
  type ENUM('page','model','blog') DEFAULT 'model',
  status ENUM('draft','published') DEFAULT 'draft',
  category VARCHAR(100) NULL,
  sub_category VARCHAR(100) NULL,
  content_path VARCHAR(255) NOT NULL,
  version INT UNSIGNED DEFAULT 1,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uniq_slug_area (slug, area)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS blog_categories (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  name VARCHAR(100) NOT NULL,
  slug VARCHAR(120) NOT NULL,
  status TINYINT DEFAULT 1,
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uniq_blog_category_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS blog_subcategories (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  category_id INT UNSIGNED NOT NULL,
  name VARCHAR(100) NOT NULL,
  slug VARCHAR(120) NOT NULL,
  status TINYINT DEFAULT 1,
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uniq_blog_subcategory_slug (category_id, slug),
  KEY idx_blog_subcategory_category (category_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO core_settings (setting_key, setting_value)
VALUES
  ('app_name', 'MEU PROJETO WEB'),
  ('app_logo', 'meu_projeto_web_logo.svg'),
  ('app_favicon', 'meu_projeto_web_favicon.svg'),
  ('theme', 'dark')
ON DUPLICATE KEY UPDATE setting_key = VALUES(setting_key);

INSERT INTO bases (name, slug, description, allows_users, max_admins, status, base_stage, is_protected)
VALUES
  ('Base', 'base', 'Base inicial do sistema (nao deletar)', 1, 1, 1, 'published', 1),
  ('Base Cripto', 'cripto', 'Base para gestao cripto DCA Spot', 1, 1, 1, 'laboratory', 0),
  ('Base Divulgacao', 'divulgacao', 'Base para landing pages, captura de leads e campanhas simples.', 1, 1, 1, 'published', 1),
  ('Base Futebol', 'futebol', 'Base para projetos de futebol com jogadores, elenco e modulos esportivos.', 1, 1, 1, 'laboratory', 0),
  ('Base Tips Survivor', 'tips-survivor', 'Base para competicoes de palpites survivor com vidas, pontos e tokens internos.', 1, 1, 1, 'laboratory', 0)
ON DUPLICATE KEY UPDATE
  name = VALUES(name),
  description = VALUES(description),
  allows_users = VALUES(allows_users),
  max_admins = VALUES(max_admins),
  status = VALUES(status),
  base_stage = VALUES(base_stage),
  is_protected = VALUES(is_protected);

INSERT INTO plans (id, name, billing_cycle, price, limits_json, status)
VALUES
  (1, 'Plano Gratis', 'free', 0.00, '{"dashboard":"Dashboard administrativa","profile_settings":"Perfil e configuracoes","modules":"Modulos gratuitos da base","basic_reports":"Leitura basica dos dados"}', 1),
  (2, 'Plano Start', 'monthly', 0.00, '{"dashboard":"Dashboard administrativa","profile_settings":"Perfil e configuracoes","modules":"Modulos Start da base","advanced_categories":"Subcategorias e tags","support":"Suporte padrao"}', 1),
  (3, 'Plano Plus', 'annual', 0.00, '{"dashboard":"Dashboard administrativa","profile_settings":"Perfil e configuracoes","modules":"Modulos Plus da base","advanced_categories":"Subcategorias e tags","annual_benefit":"Melhor beneficio anual","support":"Suporte prioritario"}', 1)
ON DUPLICATE KEY UPDATE
  name = VALUES(name),
  billing_cycle = VALUES(billing_cycle),
  price = VALUES(price),
  limits_json = VALUES(limits_json),
  status = VALUES(status);

INSERT IGNORE INTO plan_bases (plan_id, base_id)
SELECT p.id, b.id
FROM plans p
CROSS JOIN bases b
WHERE b.slug IN ('base', 'cripto', 'divulgacao', 'futebol', 'tips-survivor');

INSERT INTO plan_prices (id, plan_id, billing_cycle, price, status)
VALUES
  (1, 1, 'free', 0.00, 1),
  (2, 2, 'monthly', 0.00, 1),
  (5, 3, 'annual', 0.00, 1)
ON DUPLICATE KEY UPDATE
  plan_id = VALUES(plan_id),
  billing_cycle = VALUES(billing_cycle),
  price = VALUES(price),
  status = VALUES(status);

INSERT IGNORE INTO base_plan_prices (base_id, plan_price_id, custom_price, status)
SELECT b.id, pp.id, pp.price, 1
FROM bases b
CROSS JOIN plan_prices pp
WHERE b.slug IN ('base', 'cripto', 'divulgacao', 'futebol', 'tips-survivor');

SET FOREIGN_KEY_CHECKS=1;
SET UNIQUE_CHECKS=1;
