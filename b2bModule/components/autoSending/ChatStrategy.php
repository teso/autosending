<?php
namespace b2b\components\autoSending;

use b2b\components\SentTemplate;
use b2b\models\autoSending\DebugOperationEnum;
use b2b\models\autoSending\SendingEntity;
use b2b\models\sentTemplate\TemplateTypeEnum;
use phoenix\userStatus\components\UserStatus;
use phoenix\internal_mail\components\InternalMail;

class ChatStrategy implements SendStrategyInterface
{
    public const DEFAULT_SUBJECT = 'hi';

    private const MIN_SEND_DELAY = 30;
    private const MAX_SEND_DELAY = 70;

    private $incomeSendingState;
    private $debug;
    private $sentTemplate;
    private $userStatus;
    private $internalMail;

    public function __construct(
        IncomeSendingState $incomeSendingState,
        Debug $debug,
        SentTemplate $sentTemplate,
        UserStatus $userStatus,
        InternalMail $internalMail
    ) {
        $this->incomeSendingState = $incomeSendingState;
        $this->debug = $debug;
        $this->sentTemplate = $sentTemplate;
        $this->userStatus = $userStatus;
        $this->internalMail = $internalMail;
    }

    public function getSendDelay(): int
    {
        return mt_rand(self::MIN_SEND_DELAY, self::MAX_SEND_DELAY);
    }

    public function getMessage(SendingEntity $sending): array
    {
        return [
            'userIdFrom' => $sending->fromUserId,
            'userIdTo' => $sending->toUserId,
            'subject' => self::DEFAULT_SUBJECT,
            'text' => $sending->template->messageText,
            'msgType' => 'icebreaker',
            'templateId' => $sending->template->id,
        ];
    }

    public function send(array $message): void
    {
        $this->debug->saveOperation(
            DebugOperationEnum::TRY_SEND,
            $message['userIdTo'],
            (array) $message['userIdFrom'],
            $message
        );

        if (!array_key_exists('userIdFrom', $message)
            || !array_key_exists('userIdTo', $message)
            || !array_key_exists('subject', $message)
            || !array_key_exists('text', $message)
            || !array_key_exists('msgType', $message)
            || !array_key_exists('templateId', $message)
        ) {
            throw new \InvalidArgumentException('Wrong message data');
        }

        $userId = $message['userIdTo'];
        $profileId = $message['userIdFrom'];
        $isSenderOnline = $this->userStatus->isUserOnline($profileId);
        $hasAnswerMessage = $this->incomeSendingState->isReceived($profileId, $userId);

        if ($isSenderOnline && !$hasAnswerMessage) {
            $sentResult = $this->internalMail->sendImb($message);

            if (ctype_xdigit($sentResult) && strlen($sentResult) === 32) {
                $this->sentTemplate->addTemplate(
                    [
                        'recipientId' => $userId,
                        'templateId' => $message['templateId'],
                        'templateType' => TemplateTypeEnum::CHAT,
                        'sentAt' => (new \DateTime())->format('Y-m-d H:i:s'),
                    ]
                );

                $this->debug->saveOperation(
                    DebugOperationEnum::SEND_OK,
                    $userId,
                    (array) $profileId,
                    [
                        'messageId' => $sentResult,
                        'message' => $message,
                    ]
                );
            } else {
                $this->debug->saveOperation(
                    DebugOperationEnum::SEND_FAIL,
                    $userId,
                    (array) $profileId,
                    [
                        'message' => $message,
                        'error' => $sentResult,
                    ]
                );
            }
        } else {
            $this->debug->saveOperation(
                DebugOperationEnum::SEND_FAIL,
                $userId,
                (array) $profileId,
                [
                    'message' => $message,
                    'isSenderOnline' => $isSenderOnline,
                    'hasAnswerMessage' => $hasAnswerMessage,
                ]
            );
        }
    }
}
