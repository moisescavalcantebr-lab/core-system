<?php
require __DIR__ . '/../../app/bootstrap/project_bootstrap.php';

projectLogout();

header('Location: ../admin/login.php');
exit;