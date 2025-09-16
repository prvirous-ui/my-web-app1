// ایجاد فایل performance_optimizer.php
<?php
class PerformanceOptimizer {
    private static $cache = [];
    private static $cache_enabled = true;
    
    public static function enableCache($enabled = true) {
        self::$cache_enabled = $enabled;
    }
    
    public static function getCachedData($key, $callback, $ttl = 3600) {
        if (!self::$cache_enabled) {
            return call_user_func($callback);
        }
        
        if (isset(self::$cache[$key]) && (time() - self::$cache[$key]['timestamp']) < $ttl) {
            return self::$cache[$key]['data'];
        }
        
        $data = call_user_func($callback);
        self::$cache[$key] = [
            'data' => $data,
            'timestamp' => time()
        ];
        
        return $data;
    }
    
    public static function clearCache($key = null) {
        if ($key) {
            unset(self::$cache[$key]);
        } else {
            self::$cache = [];
        }
    }
}

// استفاده در کدها
function get_user_tasks($user_id, $force_refresh = false) {
    if ($force_refresh) {
        PerformanceOptimizer::clearCache("user_tasks_$user_id");
    }
    
    return PerformanceOptimizer::getCachedData("user_tasks_$user_id", function() use ($user_id) {
        global $pdo;
        
        try {
            $stmt = $pdo->prepare("SELECT * FROM tasks WHERE user_id = ? ORDER BY created_at DESC");
            $stmt->execute([$user_id]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch(PDOException $e) {
            error_log("Error fetching user tasks: " . $e->getMessage());
            return [];
        }
    }, 300); // کش برای 5 دقیقه
}
?>