<?php
/**
 * Simple File-Based Caching System
 * Reduces database load by caching frequently accessed data
 */

class SimpleCache {
    private $cacheDir;
    private $defaultTTL = 300; // 5 minutes
    
    public function __construct() {
        $this->cacheDir = __DIR__ . '/../../cache/';
        
        // Create cache directory if it doesn't exist
        if (!is_dir($this->cacheDir)) {
            @mkdir($this->cacheDir, 0755, true);
        }
    }
    
    /**
     * Get cached data
     */
    public function get($key) {
        $filename = $this->getCacheFile($key);
        
        if (!file_exists($filename)) {
            return null;
        }
        
        $data = @json_decode(file_get_contents($filename), true);
        
        if (!$data) {
            return null;
        }
        
        // Check if expired
        if ($data['expires'] < time()) {
            @unlink($filename);
            return null;
        }
        
        return $data['value'];
    }
    
    /**
     * Set cache data
     */
    public function set($key, $value, $ttl = null) {
        $filename = $this->getCacheFile($key);
        $ttl = $ttl ?? $this->defaultTTL;
        
        $data = [
            'value' => $value,
            'expires' => time() + $ttl
        ];
        
        @file_put_contents($filename, json_encode($data));
    }
    
    /**
     * Delete cache
     */
    public function delete($key) {
        $filename = $this->getCacheFile($key);
        if (file_exists($filename)) {
            @unlink($filename);
        }
    }
    
    /**
     * Clear all cache
     */
    public function clear() {
        $files = glob($this->cacheDir . '*.cache');
        foreach ($files as $file) {
            @unlink($file);
        }
    }
    
    /**
     * Get cache filename
     */
    private function getCacheFile($key) {
        return $this->cacheDir . md5($key) . '.cache';
    }
}
?>
