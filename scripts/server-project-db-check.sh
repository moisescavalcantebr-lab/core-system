#!/bin/sh
set -eu

echo "[project-db-check] grants"
mysql -uroot -proot -e "SHOW GRANTS FOR 'core_project'@'%';"
mysql -uroot -proot -e "SHOW GRANTS FOR 'core_project'@'localhost';"
mysql -uroot -proot -e "SHOW VARIABLES LIKE 'partial_revokes';"

echo "[project-db-check] create/use/drop project permission test"
mysql -uroot -proot <<'SQL'
DROP DATABASE IF EXISTS project_permission_test;
FLUSH PRIVILEGES;
SQL

mysql -ucore_project -pcore_project <<'SQL'
CREATE DATABASE project_permission_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE project_permission_test;
CREATE TABLE permission_probe (id INT NOT NULL PRIMARY KEY);
INSERT INTO permission_probe (id) VALUES (1);
DROP TABLE permission_probe;
DROP DATABASE project_permission_test;
SQL
