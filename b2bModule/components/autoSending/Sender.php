<?php
namespace b2b\components\autoSending;

use Phoenix\DataStructures\SortedSetInterface;
use Phoenix\Utils\Utils;

class Sender
{
    private const SENDER_KEY = 'autoSendingSenders';
    private const SENDER_TTL = State::STATE_TTL;
    private const SENDER_TIMEOUT = State::STATE_TTL;

    private $sortedSet;

    public function __construct(
        SortedSetInterface $sortedSet
    ) {
        $this->sortedSet = $sortedSet;
    }

    public function addAll(array $profileIds): void
    {
        if (empty($profileIds)) {
            return;
        }

        $profileSet = [];

        foreach ($profileIds as $profileId) {
            $profileSet[Utils::char32ToBinary16($profileId)] = time();
        }

        $this->sortedSet->multiAdd(
            self::SENDER_KEY,
            $profileSet,
            true,
            strtotime(self::SENDER_TTL)
        );
    }

    public function deleteAll(array $profileIds): void
    {
        if (empty($profileIds)) {
            return;
        }

        $profileIds = array_map(
            function ($value) {
                return Utils::char32ToBinary16($value);
            },
            $profileIds
        );

        $this->sortedSet->multiRemove(
            self::SENDER_KEY,
            $profileIds
        );
    }

    public function getAll(): array
    {
        $profileIds = $this->sortedSet->rangeByScore(
            self::SENDER_KEY,
            strtotime('-' . self::SENDER_TIMEOUT),
            '+inf'
        );

        if (empty($profileIds)) {
            return [];
        }

        $profileIds = array_map(
            function ($value) {
                return Utils::binary16ToChar32($value);
            },
            $profileIds
        );

        return $profileIds;
    }

    public function cleanOld(): void
    {
        $this->sortedSet->removeByScore(
            self::SENDER_KEY,
            '-inf',
            strtotime('-' . self::SENDER_TIMEOUT)
        );
    }
}
