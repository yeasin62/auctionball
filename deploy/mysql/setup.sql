-- ============================================================================
-- !! STOP — READ BEFORE RUNNING !!
--
-- 1. Replace 'CHANGE_THIS_PASSWORD' on the SET @db_password line below with a
--    strong random password (12+ chars). Generate one with:
--        openssl rand -base64 32
--
-- 2. Save the password — you'll need it in .env as DB_PASSWORD
--
-- 3. Run as MySQL root:
--        mysql -u root -p < deploy/mysql/setup.sql
--
-- 4. Verify with the SELECT/SHOW queries at the bottom
-- ============================================================================
--
-- AuctionBall — MySQL 8.0 / MariaDB 10.6+ initial setup
-- utf8mb4 + utf8mb4_unicode_ci so Bengali script and emoji work everywhere.
-- ============================================================================

-- ============================================================
-- ↓↓↓ ONLY CHANGE THIS LINE ↓↓↓
SET @db_password = 'CHANGE_THIS_PASSWORD';
-- ↑↑↑ ONLY CHANGE THIS LINE ↑↑↑
-- ============================================================

-- Defensive guard — refuses placeholder, empty, or too-short passwords. Same
-- variable that's used below, so it stays in sync — no drift between the
-- check and the actual CREATE USER call.
DELIMITER $$
DROP PROCEDURE IF EXISTS _ab_check_password $$
CREATE PROCEDURE _ab_check_password()
BEGIN
    IF @db_password = 'CHANGE_THIS_PASSWORD' OR @db_password IS NULL OR @db_password = '' OR LENGTH(@db_password) < 12 THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Set a strong password (12+ chars) on the SET @db_password line before running. See the header of setup.sql.';
    END IF;
END $$
DELIMITER ;

CALL _ab_check_password();
DROP PROCEDURE IF EXISTS _ab_check_password;

-- ============================================================================
-- Database
-- ============================================================================
CREATE DATABASE IF NOT EXISTS auctionball_prod
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

-- ============================================================================
-- App user — restrict to localhost. Use '%' if your DB lives on a different
-- host from the app server, in which case lock the firewall instead.
--
-- MySQL's CREATE USER doesn't accept user variables in IDENTIFIED BY, so we
-- build the statement dynamically via PREPARE / EXECUTE — single source of
-- truth (@db_password) avoids drift between the check above and the CREATE.
-- ============================================================================
SET @create_user_sql = CONCAT(
    "CREATE USER IF NOT EXISTS 'auctionball_app'@'localhost' IDENTIFIED BY '",
    REPLACE(@db_password, "'", "''"), "'"
);
PREPARE _create_user_stmt FROM @create_user_sql;
EXECUTE _create_user_stmt;
DEALLOCATE PREPARE _create_user_stmt;

-- If you're rotating an existing password (re-running this script after a
-- password change), uncomment the ALTER USER block below — CREATE USER
-- IF NOT EXISTS won't update an existing user's credentials.
--
-- SET @alter_user_sql = CONCAT(
--     "ALTER USER 'auctionball_app'@'localhost' IDENTIFIED BY '",
--     REPLACE(@db_password, "'", "''"), "'"
-- );
-- PREPARE _alter_user_stmt FROM @alter_user_sql;
-- EXECUTE _alter_user_stmt;
-- DEALLOCATE PREPARE _alter_user_stmt;

-- ============================================================================
-- Grants — minimum required for the Laravel app + migrations.
-- No DROP / CREATE USER / GRANT — those stay admin-only.
-- ============================================================================
GRANT SELECT, INSERT, UPDATE, DELETE,
      CREATE, ALTER, INDEX, REFERENCES,
      CREATE TEMPORARY TABLES, LOCK TABLES, EXECUTE
    ON auctionball_prod.* TO 'auctionball_app'@'localhost';

GRANT CREATE VIEW, SHOW VIEW
    ON auctionball_prod.* TO 'auctionball_app'@'localhost';

FLUSH PRIVILEGES;

-- ============================================================================
-- Verify
-- ============================================================================
SELECT user, host FROM mysql.user WHERE user = 'auctionball_app';
SHOW DATABASES LIKE 'auctionball_prod';
SHOW GRANTS FOR 'auctionball_app'@'localhost';
