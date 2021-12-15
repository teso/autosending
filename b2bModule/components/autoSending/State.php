<?php
namespace b2b\components\autoSending;

use Phoenix\Cache\RedisCache;

class State
{
    public const STATE_TTL = '12 hour';
    private const STATE_KEY = 'autoSendingState:';

    private $cache;

    public function __construct(
        RedisCache $cache
    ) {
        $this->cache = $cache;
    }

    public function enable(string $managerId): void
    {
        $this->cache->add(
            self::STATE_KEY . $managerId,
            1,
            strtotime(self::STATE_TTL) - time()
        );
    }

    public function isEnabled(string $managerId): bool
    {
        return (bool) $this->cache->fetch(self::STATE_KEY . $managerId);
    }

    /**
     * @return bool[] - manager id as a key
     */
    public function areEnabled(array $managerIds): array
    {
        $result = array_fill_keys($managerIds, false);

        $keys = array_map(
            function (string $managerId) {
                return self::STATE_KEY . $managerId;
            },
            $managerIds
        );
        $queryResult = $this->cache->fetchMulti($keys);

        foreach ($queryResult as $key => $value) {
            $managerId = str_replace(self::STATE_KEY, '', $key);

            if (!empty($value) && isset($result[$managerId])) {
                $result[$managerId] = true;
            }
        }

        return $result;
    }

    public function disable(string $managerId): void
    {
        $this->cache->delete(self::STATE_KEY . $managerId);
    }

    public function disableAll(array $managerIds): void
    {
        $keys = array_map(
            function ($managerId) {
                return self::STATE_KEY . $managerId;
            },
            $managerIds
        );

        $this->cache->deleteMulti($keys);
    }
}
