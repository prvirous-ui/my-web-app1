<?php
/**
 * Authentication Functions
 */
session_start();

if (!function_exists('is_logged_in')) {
    function is_logged_in() {
        return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
    }
}

if (!function_exists('get_user_id')) {
    function get_user_id() {
        return $_SESSION['user_id'] ?? null;
    }
}

if (!function_exists('get_username')) {
    function get_username() {
        return $_SESSION['username'] ?? null;
    }
}

if (!function_exists('get_user_role')) {
    function get_user_role() {
        return $_SESSION['role'] ?? null;
    }
}

if (!function_exists('require_login')) {
    function require_login() {
        if (!is_logged_in()) {
            header("Location: login.php");
            exit;
        }
    }
}

if (!function_exists('require_admin')) {
    function require_admin() {
        require_login();
        if (!is_admin()) {
            header("Location: access_denied.php");
            exit;
        }
    }
}

if (!function_exists('is_admin')) {
    function is_admin() {
        if (!is_logged_in()) {
            return false;
        }
        
        global $pdo;
        try {
            $stmt = $pdo->prepare("SELECT role FROM users WHERE id = ?");
            $stmt->execute([$_SESSION['user_id']]);
            $user = $stmt->fetch();
            
            return $user && $user['role'] === 'admin';
        } catch (PDOException $e) {
            error_log("Error checking admin role: " . $e->getMessage());
            return false;
        }
    }
}

if (!function_exists('login_user')) {
    function login_user($user_id, $username, $role = 'user') {
        $_SESSION['user_id'] = $user_id;
        $_SESSION['username'] = $username;
        $_SESSION['role'] = $role;
        $_SESSION['login_time'] = time();
        
        session_regenerate_id(true);
    }
}

if (!function_exists('logout_user')) {
    function logout_user() {
        $_SESSION = array();
        
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params["path"], $params["domain"],
                $params["secure"], $params["httponly"]
            );
        }
        
        session_destroy();
    }
}

if (!function_exists('check_password_strength')) {
    function check_password_strength($password) {
        $strength = 0;
        
        if (strlen($password) >= 8) $strength++;
        if (preg_match('/[A-Z]/', $password)) $strength++;
        if (preg_match('/[a-z]/', $password)) $strength++;
        if (preg_match('/[0-9]/', $password)) $strength++;
        if (preg_match('/[^A-Za-z0-9]/', $password)) $strength++;
        
        return $strength;
    }
}

if (!function_exists('validate_email')) {
    function validate_email($email) {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }
}

if (!function_exists('verify_password')) {
    function verify_password($password, $hashed_password) {
        return password_verify($password, $hashed_password);
    }
}

if (!function_exists('hash_password')) {
    function hash_password($password) {
        return password_hash($password, PASSWORD_DEFAULT);
    }
}

if (!function_exists('sanitize_input')) {
    function sanitize_input($data) {
        if (is_array($data)) {
            return array_map('sanitize_input', $data);
        }
        return htmlspecialchars(stripslashes(trim($data)), ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('generate_csrf_token')) {
    function generate_csrf_token() {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }
}

if (!function_exists('csrf_field')) {
    function csrf_field() {
        return '<input type="hidden" name="csrf_token" value="' . generate_csrf_token() . '">';
    }
}

if (!function_exists('validate_csrf_token')) {
    function validate_csrf_token($token) {
        return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
    }
}

if (!function_exists('redirect')) {
    function redirect($url, $statusCode = 303) {
        header('Location: ' . $url, true, $statusCode);
        exit;
    }
}

if (!function_exists('set_flash_message')) {
    function set_flash_message($type, $message) {
        $_SESSION['flash'][$type] = $message;
    }
}

if (!function_exists('get_flash_message')) {
    function get_flash_message($type) {
        if (isset($_SESSION['flash'][$type])) {
            $message = $_SESSION['flash'][$type];
            unset($_SESSION['flash'][$type]);
            return $message;
        }
        return null;
    }
}

if (!function_exists('generate_random_token')) {
    function generate_random_token($length = 32) {
        return bin2hex(random_bytes($length));
    }
}

if (!function_exists('check_brute_force')) {
    function check_brute_force($user_id, $max_attempts = 5, $time_window = 900) {
        global $pdo;
        
        try {
            $stmt = $pdo->prepare("
                SELECT COUNT(*) as attempts 
                FROM login_attempts 
                WHERE user_id = ? AND attempt_time > DATE_SUB(NOW(), INTERVAL ? SECOND)
            ");
            $stmt->execute([$user_id, $time_window]);
            $result = $stmt->fetch();
            
            return $result['attempts'] >= $max_attempts;
        } catch (PDOException $e) {
            error_log("Brute force check error: " . $e->getMessage());
            return false;
        }
    }
}

if (!function_exists('record_login_attempt')) {
    function record_login_attempt($user_id, $success = false) {
        global $pdo;
        
        try {
            $stmt = $pdo->prepare("
                INSERT INTO login_attempts (user_id, ip_address, user_agent, success) 
                VALUES (?, ?, ?, ?)
            ");
            $stmt->execute([
                $user_id,
                $_SERVER['REMOTE_ADDR'],
                $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown',
                $success
            ]);
        } catch (PDOException $e) {
            error_log("Login attempt recording error: " . $e->getMessage());
        }
    }
}

if (!function_exists('has_permission')) {
    function has_permission($required_role) {
        if (!is_logged_in()) {
            return false;
        }
        
        $user_role = get_user_role();
        
        $hierarchy = [
            'user' => 1,
            'moderator' => 2,
            'admin' => 3
        ];
        
        $user_level = $hierarchy[$user_role] ?? 0;
        $required_level = $hierarchy[$required_role] ?? 0;
        
        return $user_level >= $required_level;
    }
}

if (!function_exists('get_current_user')) {
    function get_current_user() {
        if (!is_logged_in()) {
            return null;
        }
        
        global $pdo;
        try {
            $stmt = $pdo->prepare("
                SELECT id, username, email, role, created_at, last_login 
                FROM users 
                WHERE id = ?
            ");
            $stmt->execute([get_user_id()]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Get current user error: " . $e->getMessage());
            return null;
        }
    }
}
?>