#!/bin/sh
set -eu

php -r '
$config = require "/var/www/html/env/env.production.php";
$db = $config["db"]["name"] ?? "";
$projectUser = $config["project_db"]["user"] ?? "";
echo "db={$db} project_user={$projectUser}\n";
'

