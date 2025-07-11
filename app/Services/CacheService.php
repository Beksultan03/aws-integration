<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Log;

class CacheService
{
    private const CACHE_TTL = 86400; // 24 hours in seconds
    private const CACHE_PREFIX = 'amazon_ads:stats';

    /**
     * Get data from cache or store it if not exists
     */
    public function remember(string $key, callable $callback)
    {
        try {
            log::info('remember', [$key]);
            // Check if Redis is available
            Redis::ping();
            
            return Cache::store('redis')->remember(
                $this->buildKey($key),
                self::CACHE_TTL,
                $callback
            );
        } catch (\Exception $e) {
            Log::warning('Cache operation failed, falling back to direct data fetch', [
                'key' => $key,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            // If Redis is not available, just return the data directly
            return $callback();
        }
    }

    /**
     * Generate cache key for statistics
     */
    public function getStatisticsKey(
        int $companyId,
        array $filters,
        string $type,
        ?string $parentId = null
    ): string {
        $filterHash = md5(json_encode([
            'filters' => $filters,
            'type' => $type,
            'parentId' => $parentId,
        ]));
        
        return "company:{$companyId}:type:{$type}:{$filterHash}";
    }

    /**
     * Clear all cache for a specific company
     */
    public function clearCache(int $companyId): void
    {
        try {
            $pattern = $this->buildKey("company:{$companyId}:*");
            $this->deleteByPattern($pattern);
            
            Log::info('Company cache cleared successfully', [
                'company_id' => $companyId
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to clear company cache', [
                'company_id' => $companyId,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Clear cache for specific type within a company
     */
    public function clearTypeCache(int $companyId, string $type): void
    {
        try {
            $pattern = $this->buildKey("company:{$companyId}:type:{$type}:*");
            $this->deleteByPattern($pattern);
            
            Log::info('Type cache cleared successfully', [
                'company_id' => $companyId,
                'type' => $type
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to clear type cache', [
                'company_id' => $companyId,
                'type' => $type,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Delete cache entries by pattern
     */
    private function deleteByPattern(string $pattern): void
    {
        try {
            $keys = Redis::keys($pattern);
            
            if (!empty($keys)) {
                Redis::del($keys);
                Log::info('Cache entries deleted', [
                    'pattern' => $pattern,
                    'keys_removed' => count($keys)
                ]);
            }
        } catch (\Exception $e) {
            Log::error('Failed to delete cache entries', [
                'pattern' => $pattern,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Check if cache exists
     */
    public function has(string $key): bool
    {
        try {
            return Cache::store('redis')->has($this->buildKey($key));
        } catch (\Exception $e) {
            Log::warning('Cache check failed', [
                'key' => $key,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Get cache statistics
     */
    public function getStats(): array
    {
        try {
            $info = Redis::info();
            return [
                'used_memory' => $info['used_memory_human'],
                'total_keys' => Redis::dbsize(),
                'last_save' => date('Y-m-d H:i:s', $info['rdb_last_save_time']),
                'hits' => $info['keyspace_hits'] ?? 0,
                'misses' => $info['keyspace_misses'] ?? 0,
                'hit_rate' => $this->calculateHitRate(
                    $info['keyspace_hits'] ?? 0,
                    $info['keyspace_misses'] ?? 0
                ),
            ];
        } catch (\Exception $e) {
            Log::error('Failed to get cache stats', [
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }

    /**
     * Calculate cache hit rate
     */
    private function calculateHitRate(int $hits, int $misses): float
    {
        $total = $hits + $misses;
        return $total > 0 ? round(($hits / $total) * 100, 2) : 0.0;
    }

    /**
     * Build cache key with prefix
     */
    private function buildKey(string $key): string
    {
        return self::CACHE_PREFIX . ':' . $key;
    }

    /**
     * Check if Redis is available
     */
    public function isAvailable(): bool
    {
        try {
            Redis::ping();
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Clear all cache (use with caution)
     */
    public function clearAll(): void
    {
        try {
            $pattern = $this->buildKey('*');
            $this->deleteByPattern($pattern);
            
            Log::info('All cache cleared successfully');
        } catch (\Exception $e) {
            Log::error('Failed to clear all cache', [
                'error' => $e->getMessage()
            ]);
        }
    }
}