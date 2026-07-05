-- Migration: align users table structure without dropping the table
-- Target DB: office_login_system
-- Compatible with MySQL 5.7+

USE office_login_system;

START TRANSACTION;

-- 1) Align users.user_role collation with roles.role_key
SET @role_charset := (
  SELECT c.character_set_name
  FROM information_schema.columns c
  WHERE c.table_schema = DATABASE()
    AND c.table_name = 'roles'
    AND c.column_name = 'role_key'
  LIMIT 1
);
SET @role_collation := (
  SELECT c.collation_name
  FROM information_schema.columns c
  WHERE c.table_schema = DATABASE()
    AND c.table_name = 'roles'
    AND c.column_name = 'role_key'
  LIMIT 1
);
SET @sql := IF(
  @role_charset IS NOT NULL AND @role_collation IS NOT NULL,
  CONCAT(
    'ALTER TABLE users MODIFY COLUMN user_role VARCHAR(50) CHARACTER SET ',
    @role_charset,
    ' COLLATE ',
    @role_collation,
    ' NOT NULL DEFAULT ''employee''' 
  ),
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 2) Normalize invalid role values before enforcing FK
UPDATE users u
LEFT JOIN roles r ON r.role_key = u.user_role
SET u.user_role = 'employee'
WHERE u.user_role IS NULL OR u.user_role = '' OR r.role_key IS NULL;

-- 3) Ensure PRIMARY KEY(id) exists
SET @has_pk := (
  SELECT COUNT(*)
  FROM information_schema.table_constraints
  WHERE table_schema = DATABASE()
    AND table_name = 'users'
    AND constraint_type = 'PRIMARY KEY'
);
SET @sql := IF(@has_pk = 0, 'ALTER TABLE users ADD PRIMARY KEY (id)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 4) Align column definitions
ALTER TABLE users
MODIFY COLUMN id INT NOT NULL AUTO_INCREMENT,
MODIFY COLUMN user_id VARCHAR(50) NOT NULL,
MODIFY COLUMN first_name VARCHAR(100) NOT NULL,
MODIFY COLUMN last_name VARCHAR(100) NOT NULL,
MODIFY COLUMN phone VARCHAR(20) NOT NULL,
MODIFY COLUMN email VARCHAR(100) NOT NULL,
MODIFY COLUMN position VARCHAR(100) NOT NULL,
MODIFY COLUMN department VARCHAR(100) NOT NULL,
MODIFY COLUMN company VARCHAR(100) NOT NULL,
MODIFY COLUMN password_hash VARCHAR(255) NOT NULL,
MODIFY COLUMN created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
MODIFY COLUMN updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
MODIFY COLUMN is_active BOOLEAN DEFAULT TRUE;

-- 5) Ensure UNIQUE(user_id) exists
SET @has_uk_user_id := (
  SELECT COUNT(*)
  FROM information_schema.statistics
  WHERE table_schema = DATABASE()
    AND table_name = 'users'
    AND column_name = 'user_id'
    AND non_unique = 0
);
SET @sql := IF(@has_uk_user_id = 0, 'ALTER TABLE users ADD UNIQUE KEY uq_users_user_id (user_id)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 6) Recreate FK users.user_role -> roles.role_key with ON UPDATE CASCADE
SET @fk_name := (
  SELECT kcu.constraint_name
  FROM information_schema.key_column_usage kcu
  WHERE kcu.table_schema = DATABASE()
    AND kcu.table_name = 'users'
    AND kcu.column_name = 'user_role'
    AND kcu.referenced_table_name = 'roles'
    AND kcu.referenced_column_name = 'role_key'
  LIMIT 1
);
SET @sql := IF(@fk_name IS NOT NULL, CONCAT('ALTER TABLE users DROP FOREIGN KEY ', @fk_name), 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

ALTER TABLE users
ADD CONSTRAINT fk_users_role
FOREIGN KEY (user_role) REFERENCES roles(role_key)
ON UPDATE CASCADE;

COMMIT;
