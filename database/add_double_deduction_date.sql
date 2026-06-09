CREATE TABLE IF NOT EXISTS `double_deduction_date` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `branch_id` INT NOT NULL,
  `special_date` DATE NOT NULL,
  `description` VARCHAR(255) DEFAULT NULL,
  `is_active` ENUM('0','1') NOT NULL DEFAULT '1',
  `created_at` DATETIME DEFAULT NULL,
  `updated_at` DATETIME DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_branch_special_date` (`branch_id`, `special_date`),
  KEY `idx_branch_active_date` (`branch_id`, `is_active`, `special_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
