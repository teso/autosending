<?php
namespace b2b\components\autoSending;

use b2b\models\autoSending\DebugOperationEnum;
use Phoenix\DataStructures\ListInterface;

class Debug
{
    private const DATA_KEY = 'autoSendingDebug:';
    private const DATA_TTL = DebugState::STATE_TTL;

    private $debugState;
    private $list;

    public function __construct(
        DebugState $debugState,
        ListInterface $list
    ) {
        $this->debugState = $debugState;
        $this->list = $list;
    }

    public function saveOperation(
        int $operationId,
        string $userId,
        array $profileIds = [],
        array $context = []
    ): void {
        if (!$this->debugState->isEnabled($userId)) {
            return;
        }

        $data = [
            'time' => time(),
            'operationId' => $operationId,
            'userId' => $userId,
            'profileIds' => $profileIds,
            'context' => $context,
        ];

        $this->list->push(
            self::DATA_KEY . $userId,
            serialize($data),
            strtotime(self::DATA_TTL)
        );
    }

    public function getOperations(string $userId, int $offset = 0, int $limit = 100): array
    {
        $operations = [];

        $rows = $this->list->range(self::DATA_KEY . $userId, $offset, $offset + $limit - 1);

        foreach ($rows as $row) {
            $data = unserialize($row);
            $operationDictionary = array_flip(DebugOperationEnum::toArray());

            $operations[] = [
                'time' => date('H:i:s', $data['time']),
                'operationName' => $operationDictionary[$data['operationId']],
                'userId' => $userId,
                'profileIds' => $data['profileIds'],
                'context' => $data['context']
            ];
        }

        return $operations;
    }

    public function getOperationCount(string $userId): int
    {
        return $this->list->length(self::DATA_KEY . $userId);
    }

    public function clearOperations(string $userId): void
    {
        $this->list->clear(self::DATA_KEY . $userId);
    }
}
