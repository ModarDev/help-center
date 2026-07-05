-- Migration: add performance indexes for CRM customer list and follow-up views
-- Target DB: office_login_system
-- Compatible with MySQL 5.7+

USE office_login_system;

START TRANSACTION;

SET @has_scr_table := (
  SELECT COUNT(*)
  FROM information_schema.tables
  WHERE table_schema = DATABASE()
    AND table_name = 'sales_customer_records'
);

SET @has_sct_table := (
  SELECT COUNT(*)
  FROM information_schema.tables
  WHERE table_schema = DATABASE()
    AND table_name = 'sales_customer_timeline'
);

-- sales_customer_records: branch + group + owner
SET @has_idx := (
  SELECT COUNT(*)
  FROM information_schema.statistics
  WHERE table_schema = DATABASE()
    AND table_name = 'sales_customer_records'
    AND index_name = 'idx_scr_branch_group_owner'
);
SET @sql := IF(
  @has_scr_table = 1 AND @has_idx = 0,
  'ALTER TABLE sales_customer_records ADD INDEX idx_scr_branch_group_owner (branch_id, group_id, owner_user_id)',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- sales_customer_records: branch + group + customer_phone
SET @has_idx := (
  SELECT COUNT(*)
  FROM information_schema.statistics
  WHERE table_schema = DATABASE()
    AND table_name = 'sales_customer_records'
    AND index_name = 'idx_scr_branch_group_phone'
);
SET @sql := IF(
  @has_scr_table = 1 AND @has_idx = 0,
  'ALTER TABLE sales_customer_records ADD INDEX idx_scr_branch_group_phone (branch_id, group_id, customer_phone)',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- sales_customer_records: branch + group + customer_line
SET @has_idx := (
  SELECT COUNT(*)
  FROM information_schema.statistics
  WHERE table_schema = DATABASE()
    AND table_name = 'sales_customer_records'
    AND index_name = 'idx_scr_branch_group_line'
);
SET @sql := IF(
  @has_scr_table = 1 AND @has_idx = 0,
  'ALTER TABLE sales_customer_records ADD INDEX idx_scr_branch_group_line (branch_id, group_id, customer_line)',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- sales_customer_records: branch + group + updated_at
SET @has_idx := (
  SELECT COUNT(*)
  FROM information_schema.statistics
  WHERE table_schema = DATABASE()
    AND table_name = 'sales_customer_records'
    AND index_name = 'idx_scr_branch_group_updated'
);
SET @sql := IF(
  @has_scr_table = 1 AND @has_idx = 0,
  'ALTER TABLE sales_customer_records ADD INDEX idx_scr_branch_group_updated (branch_id, group_id, updated_at)',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- sales_customer_records: branch + group + next_followup_at
SET @has_idx := (
  SELECT COUNT(*)
  FROM information_schema.statistics
  WHERE table_schema = DATABASE()
    AND table_name = 'sales_customer_records'
    AND index_name = 'idx_scr_branch_group_followup'
);
SET @sql := IF(
  @has_scr_table = 1 AND @has_idx = 0,
  'ALTER TABLE sales_customer_records ADD INDEX idx_scr_branch_group_followup (branch_id, group_id, next_followup_at)',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- sales_customer_records: branch + owner + updated_at
SET @has_idx := (
  SELECT COUNT(*)
  FROM information_schema.statistics
  WHERE table_schema = DATABASE()
    AND table_name = 'sales_customer_records'
    AND index_name = 'idx_scr_branch_owner_updated'
);
SET @sql := IF(
  @has_scr_table = 1 AND @has_idx = 0,
  'ALTER TABLE sales_customer_records ADD INDEX idx_scr_branch_owner_updated (branch_id, owner_user_id, updated_at)',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- sales_customer_timeline: customer + id (speed up latest activity lookup)
SET @has_idx := (
  SELECT COUNT(*)
  FROM information_schema.statistics
  WHERE table_schema = DATABASE()
    AND table_name = 'sales_customer_timeline'
    AND index_name = 'idx_sct_customer_id_id'
);
SET @sql := IF(
  @has_sct_table = 1 AND @has_idx = 0,
  'ALTER TABLE sales_customer_timeline ADD INDEX idx_sct_customer_id_id (customer_id, id)',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

COMMIT;
