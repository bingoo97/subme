-- awaiting_confirmation has 21 characters. Keep the full workflow status.
ALTER TABLE crypto_deposit_requests MODIFY COLUMN status VARCHAR(32) NOT NULL DEFAULT 'pending';
