#!/bin/sh
set -eu

echo "[db-check] databases"
mysql -uroot -proot -e "SHOW DATABASES;"

echo "[db-check] core tables"
mysql -uroot -proot core -e "SHOW TABLES;"

echo "[db-check] core_settings"
mysql -uroot -proot core -e "DESCRIBE core_settings;"

