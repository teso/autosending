<?php
namespace b2b\components\autoSending;

use Phoenix\Cache\RedisCache;

class OutcomeSendingState
{
    private const STATE_KEY = 'autoSendingOutcomeState:';
    private const STATE_TTL = '1 day';

    private $cache;

    public function __construct(
        RedisCache $cache
    ) {
        $this->cache = $cache;
    }

    public function setSent(string $fromUserId, string $toUserId): void
    {
        $this->cache->add(
            self::STATE_KEY . $fromUserId . ':' . $toUserId,
            1,
            strtotime(self::STATE_TTL) - time()
        );
    }

    /**
     * @return bool[] - user id as a key
     */
    public function areSent(array $fromUserIds, string $toUserId): array
    {
        $result = array_fill_keys($fromUserIds, false);

        $keys = array_map(
            function (string $fromUserId) use ($toUserId) {
                return self::STATE_KEY . $fromUserId . ':' . $toUserId;
            },
            $fromUserIds
        );
        $queryResult = $this->cache->fetchMulti($keys);

        foreach ($queryResult as $key => $value) {
            list($fromUserId) = explode(':', str_replace(self::STATE_KEY, '', $key));

            if (!empty($value) && isset($result[$fromUserId])) {
                $result[$fromUserId] = true;
            }
        }

        return $result;
    }
}
