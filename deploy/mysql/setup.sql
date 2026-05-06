-- ============================================================================
-- !! STOP — READ BEFORE RUNNING !!
--
-- 1. Replace 'CHANGE_THIS_PASSWORD' below with a STRONG random password
--    (24+ chars, mixed case + digits + symbols). Generate with:
--        openssl rand -base64 32
--
-- 2. Save the password securely — you'll need it in .env as DB_PASSWORD
--
-- 3. Run as MySQL root:
--        mysql -u root -p < deploy/mysql/setup.sql
--
-- 4. Verify with the SELECT/SHOW queries at the bottom of this file
-- ============================================================================
--
-- AuctionBall — MySQL 8.0 / MariaDB 10.6+ initial setup
-- Use utf8mb4 + utf8mb4_unicode_ci so Bengali script and emoji work everywhere.
-- ============================================================================

-- Defensive: refuse to run if the placeholder is still in place. Searching for
-- 'CHANGE_THIS_PASSWORD' literally below means leaving the placeholder will
-- create an account with a guessable password — block that path.
-- (MySQL has no native string-equality conditional, so we use a SIGNAL.)
DELIMITER $$
DROP PROCEDURE IF EXISTS _ab_check_password $$
CREATE PROCEDURE _ab_check_password(IN pw VARCHAR(255))
BEGIN
    IF pw = 'CHANGE_THIS_PASSWORD' OR pw = '' OR LENGTH(pw) < 12 THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Set a strong password (12+ chars) before running. See header of setup.sql.';
    END IF;
END $$
DELIMITER ;

CALL _ab_check_password('CHANGE_THIS_PASSWORD');
DROP PROCEDURE _ab_check_password;

CREATE DATABASE IF NOT EXISTS auctionball_prod
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

-- App user — restrict to localhost (or '%' if your DB lives on a different host
-- from the app server, in which case lock the firewall instead).
CREATE USER IF NOT EXISTS 'auctionball_app'@'localhost'
    IDENTIFIED BY 'CHANGE_THIS_PASSWORD';

-- Grant only what the app needs. No DROP/CREATE USER/GRANT — those are admin-only.
GRANT SELECT, INSERT, UPDATE, DELETE,
      CREATE, ALTER, INDEX, REFERENCES,
      CREATE TEMPORARY TABLES, LOCK TABLES, EXECUTE
    ON auctionball_prod.* TO 'auctionball_app'@'localhost';

-- Required for Laravel migrations to add/drop indexes & constraints
GRANT CREATE VIEW, SHOW VIEW ON auctionball_prod.* TO 'auctionball_app'@'localhost';

FLUSH PRIVILEGES;

-- Verify
SELECT user, host FROM mysql.user WHERE user = 'auctionball_app';
SHOW DATABASES LIKE 'auctionball_prod';
SHOW GRANTS FOR 'auctionball_app'@'localhost';
