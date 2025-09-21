CREATE DATABASE IF NOT EXISTS lab_scheduler
	CHARACTER SET utf8mb4
    COLLATE utf8mb4_general_ci;
    
-- create an app-only user (skip if you added it in installer)
CREATE USER IF NOT EXISTS 'lab_user'@'localhost' IDENTIFIED BY 'Droidzzz@123';

-- grant access only to our DB(principle of least privilege)
GRANT ALL PRIVILEGES ON lab_scheduler.* TO 'lab_user'@'localhost';
FLUSH PRIVILEGES;