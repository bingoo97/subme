INSERT INTO `currencies` (`code`, `name`, `symbol`, `is_active`)
VALUES ('GBP', 'British Pound', '£', 1)
ON DUPLICATE KEY UPDATE
  `name` = VALUES(`name`),
  `symbol` = VALUES(`symbol`),
  `is_active` = VALUES(`is_active`);
