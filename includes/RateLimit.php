<?php
class RateLimit {
    private static function getKey($user_id, $action) {
        return "rate_limit:{$user_id}:{$action}";
    }
    
    public static function check($user_id, $action, $limit = 60) {
        $key = self::getKey($user_id, $action);
        $count = apcu_fetch($key) ?: 0;
        
        if ($count >= $limit) {
            return false;
        }
        
        apcu_inc($key, 1, $success, 60);
        return true;
    }
}