<?php
/**
 * Simple Rate Limiter
 * Prevents abuse and distributes load
 */

class RateLimiter {
    private $cacheDir;
    
    public function __construct() {
        $this->cacheDir = __DIR__ . '/../../cache/rate_limits/';
        if (!is_dir($this->cacheDir)) {
            @mkdir($this->cacheDir, 0755, true);
        }
    }
    
    /**
     * Check if action is allowed
     * 
     * @param string $identifier - User ID or IP address
     * @param int $maxRequests - Max requests per window
     * @param int $windowSeconds - Time window in seconds
     * @return bool
     */
    public function isAllowed($identifier, $maxRequests = 60, $windowSeconds = 60) {
        $key = md5($identifier);
        $file = $this->cacheDir . $key . '.json';
        
        $now = time();
        
        if (file_exists($file)) {
            $data = @json_decode(file_get_contents($file), true);
            
            if (!$data) {
                $data = ['requests' => []];
            }
            
            // Clean old requests
            $data['requests'] = array_filter($data['requests'], function($timestamp) use ($now, $windowSeconds) {
                return ($now - $timestamp) < $windowSeconds;
            });
            
            // Check if limit exceeded
            if (count($data['requests']) >= $maxRequests) {
                return false;
            }
            
            // Add current request
            $data['requests'][] = $now;
        } else {
            $data = ['requests' => [$now]];
        }
        
        @file_put_contents($file, json_encode($data));
        return true;
    }
    
    /**
     * Get remaining requests
     */
    public function getRemaining($identifier, $maxRequests = 60, $windowSeconds = 60) {
        $key = md5($identifier);
        $file = $this->cacheDir . $key . '.json';
        
        if (!file_exists($file)) {
            return $maxRequests;
        }
        
        $data = @json_decode(file_get_contents($file), true);
        if (!$data) {
            return $maxRequests;
        }
        
        $now = time();
        $validRequests = array_filter($data['requests'], function($timestamp) use ($now, $windowSeconds) {
            return ($now - $timestamp) < $windowSeconds;
        });
        
        return max(0, $maxRequests - count($validRequests));
    }
}
?>
