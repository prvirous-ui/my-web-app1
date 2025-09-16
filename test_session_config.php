<?php
ini_set('session.save_path', '/home3/meir123/sessions'); // یا /home3/meir123/public_html/planner/sessions
session_start();
echo 'Session Save Path: ' . session_save_path() . '<br>';
echo 'Session Status: ' . (session_status() === PHP_SESSION_ACTIVE ? 'Active' : 'Inactive') . '<br>';
echo 'PHP Version: ' . phpversion() . '<br>';
echo 'Session Files: <br>';
$session_path = session_save_path();
if (is_dir($session_path) && is_writable($session_path)) {
    echo 'Session directory is writable.<br>';
    if ($handle = opendir($session_path)) {
        while (false !== ($file = readdir($handle))) {
            if ($file != '.' && $file != '..') {
                echo "$file<br>";
            }
        }
        closedir($handle);
    }
} else {
    echo 'Error: Session directory is not writable or does not exist.<br>';
}
?>