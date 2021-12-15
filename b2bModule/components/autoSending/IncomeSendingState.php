<?php
namespace b2b\components\autoSending;

use b2b\components\LastCommunication;
use b2b\models\lastCommunication\LastCommunicationEntity;
use b2b\models\lastCommunication\LastCommunicationFindOptions;
use b2b\models\lastCommunication\TypeEnum;

class IncomeSendingState
{
    private $lastCommunication;

    public function __construct(
        LastCommunication $lastCommunication
    ) {
        $this->lastCommunication = $lastCommunication;
    }

    public function isReceived(string $fromUserId, string $toUserId): bool
    {
        $areReceived = $this->areReceived((array) $fromUserId, $toUserId);

        return $areReceived[$fromUserId];
    }

    /**
     * @return bool[] - user id as a key
     */
    public function areReceived(array $fromUserIds, string $toUserId): array
    {
        $result = array_fill_keys($fromUserIds, false);

        $options = new LastCommunicationFindOptions();
        $options->senderId = $toUserId;
        $options->types = [
            TypeEnum::PRIVATE_CHAT,
            TypeEnum::LETTER,
        ];

        $activities = $this->lastCommunication->getAllActivitiesByOptions($options);
        $activities = $activities->column(null, 'recipientId');

        /**
         * @var LastCommunicationEntity $activity
         */
        foreach ($fromUserIds as $fromUserId) {
            if (!empty($activities[$fromUserId])) {
                $result[$fromUserId] = true;
            }
        }

        return $result;
    }
}
