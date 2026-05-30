DROP TRIGGER IF EXISTS `trg_crypto_wallet_assignments_exclusive_insert`;
DROP TRIGGER IF EXISTS `trg_crypto_wallet_assignments_exclusive_update`;
DROP TRIGGER IF EXISTS `trg_crypto_deposit_requests_wallet_guard_insert`;
DROP TRIGGER IF EXISTS `trg_crypto_deposit_requests_wallet_guard_update`;

DELIMITER $$

CREATE TRIGGER `trg_crypto_wallet_assignments_exclusive_insert`
BEFORE INSERT ON `crypto_wallet_assignments`
FOR EACH ROW
BEGIN
  DECLARE v_shared_enabled TINYINT DEFAULT 0;

  SELECT COALESCE(MAX(`crypto_wallet_shared_assignments_enabled`), 0)
    INTO v_shared_enabled
    FROM `app_settings`;

  IF v_shared_enabled = 0 AND NEW.`status` IN ('reserved', 'active') THEN
    IF EXISTS (
      SELECT 1
        FROM `crypto_wallet_assignments` AS existing_assignment
       WHERE existing_assignment.`wallet_address_id` = NEW.`wallet_address_id`
         AND existing_assignment.`customer_id` <> NEW.`customer_id`
         AND existing_assignment.`status` IN ('reserved', 'active')
       LIMIT 1
    ) THEN
      SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'Crypto wallet is already assigned to another customer.';
    END IF;

	    IF EXISTS (
	      SELECT 1
	        FROM `crypto_deposit_requests` AS request
	        LEFT JOIN `crypto_wallet_assignments` AS request_assignment
	          ON request_assignment.`id` = request.`wallet_assignment_id`
	       WHERE request.`customer_id` <> NEW.`customer_id`
	         AND (
	              request.`wallet_address_id` = NEW.`wallet_address_id`
	           OR request_assignment.`wallet_address_id` = NEW.`wallet_address_id`
	         )
	         AND request.`status` IN ('pending', 'pending_payment', 'awaiting_confirmation', 'awaiting_review')
	       LIMIT 1
	    ) THEN
	      SIGNAL SQLSTATE '45000'
	        SET MESSAGE_TEXT = 'Crypto wallet already has an active request for another customer.';
	    END IF;

	    IF EXISTS (
	      SELECT 1
	        FROM `crypto_deposit_requests` AS request
	        LEFT JOIN `crypto_wallet_assignments` AS request_assignment
	          ON request_assignment.`id` = request.`wallet_assignment_id`
	       WHERE request.`customer_id` <> NEW.`customer_id`
	         AND (
	              request.`wallet_address_id` = NEW.`wallet_address_id`
	           OR request_assignment.`wallet_address_id` = NEW.`wallet_address_id`
	         )
	         AND request.`status` NOT IN ('pending', 'pending_payment', 'cancelled')
	       LIMIT 1
	    ) THEN
      SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'Crypto wallet already has payment history for another customer.';
    END IF;
  END IF;
END$$

CREATE TRIGGER `trg_crypto_wallet_assignments_exclusive_update`
BEFORE UPDATE ON `crypto_wallet_assignments`
FOR EACH ROW
BEGIN
  DECLARE v_shared_enabled TINYINT DEFAULT 0;

  SELECT COALESCE(MAX(`crypto_wallet_shared_assignments_enabled`), 0)
    INTO v_shared_enabled
    FROM `app_settings`;

  IF v_shared_enabled = 0 AND NEW.`status` IN ('reserved', 'active') THEN
    IF EXISTS (
      SELECT 1
        FROM `crypto_wallet_assignments` AS existing_assignment
       WHERE existing_assignment.`id` <> NEW.`id`
         AND existing_assignment.`wallet_address_id` = NEW.`wallet_address_id`
         AND existing_assignment.`customer_id` <> NEW.`customer_id`
         AND existing_assignment.`status` IN ('reserved', 'active')
       LIMIT 1
    ) THEN
      SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'Crypto wallet is already assigned to another customer.';
    END IF;

	    IF EXISTS (
	      SELECT 1
	        FROM `crypto_deposit_requests` AS request
	        LEFT JOIN `crypto_wallet_assignments` AS request_assignment
	          ON request_assignment.`id` = request.`wallet_assignment_id`
	       WHERE request.`customer_id` <> NEW.`customer_id`
	         AND (
	              request.`wallet_address_id` = NEW.`wallet_address_id`
	           OR request_assignment.`wallet_address_id` = NEW.`wallet_address_id`
	         )
	         AND request.`status` IN ('pending', 'pending_payment', 'awaiting_confirmation', 'awaiting_review')
	       LIMIT 1
	    ) THEN
	      SIGNAL SQLSTATE '45000'
	        SET MESSAGE_TEXT = 'Crypto wallet already has an active request for another customer.';
	    END IF;

	    IF EXISTS (
	      SELECT 1
	        FROM `crypto_deposit_requests` AS request
	        LEFT JOIN `crypto_wallet_assignments` AS request_assignment
	          ON request_assignment.`id` = request.`wallet_assignment_id`
	       WHERE request.`customer_id` <> NEW.`customer_id`
	         AND (
	              request.`wallet_address_id` = NEW.`wallet_address_id`
	           OR request_assignment.`wallet_address_id` = NEW.`wallet_address_id`
	         )
	         AND request.`status` NOT IN ('pending', 'pending_payment', 'cancelled')
	       LIMIT 1
	    ) THEN
      SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'Crypto wallet already has payment history for another customer.';
    END IF;
  END IF;
END$$

CREATE TRIGGER `trg_crypto_deposit_requests_wallet_guard_insert`
BEFORE INSERT ON `crypto_deposit_requests`
FOR EACH ROW
BEGIN
  DECLARE v_shared_enabled TINYINT DEFAULT 0;
  DECLARE v_assignment_wallet_id BIGINT UNSIGNED DEFAULT NULL;
  DECLARE v_assignment_customer_id INT UNSIGNED DEFAULT NULL;
  DECLARE v_assignment_status VARCHAR(20) DEFAULT NULL;

  SELECT COALESCE(MAX(`crypto_wallet_shared_assignments_enabled`), 0)
    INTO v_shared_enabled
    FROM `app_settings`;

  IF NEW.`status` IN ('pending', 'pending_payment', 'awaiting_confirmation', 'awaiting_review')
     AND (NEW.`wallet_assignment_id` IS NULL OR NEW.`wallet_assignment_id` <= 0) THEN
    SIGNAL SQLSTATE '45000'
      SET MESSAGE_TEXT = 'Crypto payment request requires a wallet assignment.';
  END IF;

  IF NEW.`wallet_assignment_id` IS NOT NULL AND NEW.`wallet_assignment_id` > 0 THEN
    SELECT
        MAX(assignment.`wallet_address_id`),
        MAX(assignment.`customer_id`),
        MAX(assignment.`status`)
      INTO v_assignment_wallet_id, v_assignment_customer_id, v_assignment_status
      FROM `crypto_wallet_assignments` AS assignment
     WHERE assignment.`id` = NEW.`wallet_assignment_id`;
  END IF;

  IF NEW.`status` IN ('pending', 'pending_payment', 'awaiting_confirmation', 'awaiting_review')
     AND NEW.`wallet_assignment_id` IS NOT NULL
     AND NEW.`wallet_assignment_id` > 0
     AND (
     v_assignment_wallet_id IS NULL
     OR v_assignment_wallet_id <> NEW.`wallet_address_id`
     OR v_assignment_customer_id <> NEW.`customer_id`
     OR v_assignment_status NOT IN ('reserved', 'active')
     ) THEN
    SIGNAL SQLSTATE '45000'
      SET MESSAGE_TEXT = 'Crypto payment request wallet does not match the customer assignment.';
  END IF;

  IF v_shared_enabled = 0 AND NEW.`status` IN ('pending', 'pending_payment', 'awaiting_confirmation', 'awaiting_review') THEN
    IF EXISTS (
      SELECT 1
        FROM `crypto_wallet_assignments` AS existing_assignment
       WHERE existing_assignment.`wallet_address_id` = NEW.`wallet_address_id`
         AND existing_assignment.`customer_id` <> NEW.`customer_id`
         AND existing_assignment.`status` IN ('reserved', 'active')
       LIMIT 1
    ) THEN
      SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'Crypto wallet is already assigned to another customer.';
    END IF;

    IF EXISTS (
      SELECT 1
        FROM `crypto_deposit_requests` AS request
        LEFT JOIN `crypto_wallet_assignments` AS request_assignment
          ON request_assignment.`id` = request.`wallet_assignment_id`
       WHERE request.`customer_id` <> NEW.`customer_id`
         AND (
              request.`wallet_address_id` = NEW.`wallet_address_id`
           OR request_assignment.`wallet_address_id` = NEW.`wallet_address_id`
         )
         AND request.`status` IN ('pending', 'pending_payment', 'awaiting_confirmation', 'awaiting_review')
       LIMIT 1
    ) THEN
      SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'Crypto wallet already has an active request for another customer.';
    END IF;

    IF EXISTS (
      SELECT 1
        FROM `crypto_deposit_requests` AS request
        LEFT JOIN `crypto_wallet_assignments` AS request_assignment
          ON request_assignment.`id` = request.`wallet_assignment_id`
       WHERE request.`customer_id` <> NEW.`customer_id`
         AND (
              request.`wallet_address_id` = NEW.`wallet_address_id`
           OR request_assignment.`wallet_address_id` = NEW.`wallet_address_id`
         )
         AND request.`status` NOT IN ('pending', 'pending_payment', 'cancelled')
       LIMIT 1
    ) THEN
      SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'Crypto wallet already has payment history for another customer.';
    END IF;
  END IF;
END$$

CREATE TRIGGER `trg_crypto_deposit_requests_wallet_guard_update`
BEFORE UPDATE ON `crypto_deposit_requests`
FOR EACH ROW
BEGIN
  DECLARE v_shared_enabled TINYINT DEFAULT 0;
  DECLARE v_assignment_wallet_id BIGINT UNSIGNED DEFAULT NULL;
  DECLARE v_assignment_customer_id INT UNSIGNED DEFAULT NULL;
  DECLARE v_assignment_status VARCHAR(20) DEFAULT NULL;

  SELECT COALESCE(MAX(`crypto_wallet_shared_assignments_enabled`), 0)
    INTO v_shared_enabled
    FROM `app_settings`;

  IF NEW.`status` IN ('pending', 'pending_payment', 'awaiting_confirmation', 'awaiting_review')
     AND (NEW.`wallet_assignment_id` IS NULL OR NEW.`wallet_assignment_id` <= 0) THEN
    SIGNAL SQLSTATE '45000'
      SET MESSAGE_TEXT = 'Crypto payment request requires a wallet assignment.';
  END IF;

  IF NEW.`wallet_assignment_id` IS NOT NULL AND NEW.`wallet_assignment_id` > 0 THEN
    SELECT
        MAX(assignment.`wallet_address_id`),
        MAX(assignment.`customer_id`),
        MAX(assignment.`status`)
      INTO v_assignment_wallet_id, v_assignment_customer_id, v_assignment_status
      FROM `crypto_wallet_assignments` AS assignment
     WHERE assignment.`id` = NEW.`wallet_assignment_id`;
  END IF;

  IF NEW.`status` IN ('pending', 'pending_payment', 'awaiting_confirmation', 'awaiting_review')
     AND NEW.`wallet_assignment_id` IS NOT NULL
     AND NEW.`wallet_assignment_id` > 0
     AND (
     v_assignment_wallet_id IS NULL
     OR v_assignment_wallet_id <> NEW.`wallet_address_id`
     OR v_assignment_customer_id <> NEW.`customer_id`
     OR v_assignment_status NOT IN ('reserved', 'active')
     ) THEN
    SIGNAL SQLSTATE '45000'
      SET MESSAGE_TEXT = 'Crypto payment request wallet does not match the customer assignment.';
  END IF;

  IF v_shared_enabled = 0 AND NEW.`status` IN ('pending', 'pending_payment', 'awaiting_confirmation', 'awaiting_review') THEN
    IF EXISTS (
      SELECT 1
        FROM `crypto_wallet_assignments` AS existing_assignment
       WHERE existing_assignment.`wallet_address_id` = NEW.`wallet_address_id`
         AND existing_assignment.`customer_id` <> NEW.`customer_id`
         AND existing_assignment.`status` IN ('reserved', 'active')
       LIMIT 1
    ) THEN
      SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'Crypto wallet is already assigned to another customer.';
    END IF;

    IF EXISTS (
      SELECT 1
        FROM `crypto_deposit_requests` AS request
        LEFT JOIN `crypto_wallet_assignments` AS request_assignment
          ON request_assignment.`id` = request.`wallet_assignment_id`
       WHERE request.`id` <> NEW.`id`
         AND request.`customer_id` <> NEW.`customer_id`
         AND (
              request.`wallet_address_id` = NEW.`wallet_address_id`
           OR request_assignment.`wallet_address_id` = NEW.`wallet_address_id`
         )
         AND request.`status` IN ('pending', 'pending_payment', 'awaiting_confirmation', 'awaiting_review')
       LIMIT 1
    ) THEN
      SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'Crypto wallet already has an active request for another customer.';
    END IF;

    IF EXISTS (
      SELECT 1
        FROM `crypto_deposit_requests` AS request
        LEFT JOIN `crypto_wallet_assignments` AS request_assignment
          ON request_assignment.`id` = request.`wallet_assignment_id`
       WHERE request.`id` <> NEW.`id`
         AND request.`customer_id` <> NEW.`customer_id`
         AND (
              request.`wallet_address_id` = NEW.`wallet_address_id`
           OR request_assignment.`wallet_address_id` = NEW.`wallet_address_id`
         )
         AND request.`status` NOT IN ('pending', 'pending_payment', 'cancelled')
       LIMIT 1
    ) THEN
      SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'Crypto wallet already has payment history for another customer.';
    END IF;
  END IF;
END$$

DELIMITER ;
