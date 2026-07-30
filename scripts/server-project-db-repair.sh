#!/bin/sh
set -eu

mysql -uroot -proot <<'SQL'
SHOW VARIABLES LIKE 'partial_revokes';

CREATE USER IF NOT EXISTS 'core_project'@'%' IDENTIFIED BY 'core_project';
ALTER USER 'core_project'@'%' IDENTIFIED BY 'core_project';
CREATE USER IF NOT EXISTS 'core_project'@'localhost' IDENTIFIED BY 'core_project';
ALTER USER 'core_project'@'localhost' IDENTIFIED BY 'core_project';
GRANT ALL PRIVILEGES ON `project\_%`.* TO 'core_project'@'%';
GRANT ALL PRIVILEGES ON `project\_%`.* TO 'core_project'@'localhost';
FLUSH PRIVILEGES;

SHOW GRANTS FOR 'core_project'@'%';
SHOW GRANTS FOR 'core_project'@'localhost';
SQL
