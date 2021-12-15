<?php
namespace b2b\components\autoSending;

use b2b\models\autoSending\DebugOperationEnum;
use b2b\models\autoSending\SendingCollection;
use b2b\models\autoSending\SendingEntity;

class SendingPoolManager
{
    private $recipient;
    private $debug;

    public function __construct(
        Recipient $recipient,
        Debug $debug
    ) {
        $this->recipient = $recipient;
        $this->debug = $debug;
    }

    public function pick(SendingCollection $sendings): ?SendingEntity
    {
        $validSendings = [];

        /**
         * @var SendingEntity $sending
         */
        foreach ($sendings as $sending) {
            $isRecipientAllowed = $this->recipient->isAllowed($sending->toUserId, $sending->type);

            if (empty($sending->template)
                || !$isRecipientAllowed
            ) {
                if (empty($sending->template)) {
                    $this->debug->saveOperation(
                        DebugOperationEnum::SKIP_MESSAGE_TYPE_NO_TEMPLATE,
                        $sending->toUserId,
                        (array) $sending->fromUserId
                    );
                }

                if (!$isRecipientAllowed) {
                    $this->debug->saveOperation(
                        DebugOperationEnum::SKIP_MESSAGE_TYPE_NOT_ALLOWED_RECIPIENT,
                        $sending->toUserId,
                        (array) $sending->fromUserId,
                        [
                            'toUserId' => $sending->toUserId,
                            'type' => $sending->type,
                        ]
                    );
                }

                continue;
            }

            $validSendings[] = $sending;
        }

        shuffle($validSendings);

        return $validSendings[0] ?? null;
    }
}
