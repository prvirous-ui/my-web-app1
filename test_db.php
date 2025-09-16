<?php
require_once 'config.php';
echo "PDO connection successful!" . (isset($pdo) ? " - Connected" : " - Failed");
?>