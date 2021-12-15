<?php
namespace b2b\components\autoSending;

use Phoenix\Cache\ArrayExpiredCache;
use Phoenix\Cache\Cache;
use Phoenix\Cache\CascadeCache;

class DebugState
{
    public const STATE_TTL = '2 hours';
    private const STATE_KEY = 'autoSendingDebugState:';

    private $cache;

    public function __construct(
        Cache $redisCache
    ) {
        $this->cache = new CascadeCache();
        $this->cache->pushCacheProvider($redisCache);
        $this->cache->pushCacheProvider(new ArrayExpiredCache(10), 300);
    }

    public function isEnabled(string $userId): bool
    {
        return (bool) $this->cache->fetch(self::STATE_KEY . $userId);
    }

    public function enable(string $userId): void
    {
        $this->cache->add(
            self::STATE_KEY . $userId,
            1,
            strtotime(self::STATE_TTL) - time()
        );
    }

    public function disable(string $userId): void
    {
        $this->cache->delete(self::STATE_KEY . $userId);
    }
}
