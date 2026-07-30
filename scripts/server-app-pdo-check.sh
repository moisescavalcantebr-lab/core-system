#!/bin/sh
set -eu

php -r '
require "/var/www/html/app/bootstrap/bootstrap.php";
echo "PDO OK\n";
'

