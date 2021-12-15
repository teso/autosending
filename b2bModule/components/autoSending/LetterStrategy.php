<?php
namespace b2b\components\autoSending;

use b2b\components\SentTemplate;
use b2b\components\TemplateMedia;
use b2b\models\autoSending\DebugOperationEnum;
use b2b\models\autoSending\SendingEntity;
use b2b\models\sentTemplate\TemplateTypeEnum;
use b2b\models\templateMedia\MediaTypeEnum;
use phoenix\userStatus\components\UserStatus;
use phoenix\internal_mail\components\Letter;

class LetterStrategy implements SendStrategyInterface
{
    private const MIN_SEND_DELAY = 30;
    private const MAX_SEND_DELAY = 90;

    private $incomeSendingState;
    private $debug;
    private $templateMedia;
    private $sentTemplate;
    private $userStatus;
    private $letter;

    public function __construct(
        IncomeSendingState $incomeSendingState,
        Debug $debug,
        TemplateMedia $templateMedia,
        SentTemplate $sentTemplate,
        UserStatus $userStatus,
        Letter $letter
    ) {
        $this->incomeSendingState = $incomeSendingState;
        $this->debug = $debug;
        $this->templateMedia = $templateMedia;
        $this->sentTemplate = $sentTemplate;
        $this->userStatus = $userStatus;
        $this->letter = $letter;
    }

    public function getSendDelay(): int
    {
        return mt_rand(self::MIN_SEND_DELAY, self::MAX_SEND_DELAY);
    }

    public function getMessage(SendingEntity $sending): array
    {
        $templateMedia = $this->templateMedia->getAllMediaByTemplateId($sending->template->id);
        $imageIds = [];
        $videoIds = [];
        $titleMediaId = null;

        foreach ($templateMedia as $media) {
            if ($media->type === MediaTypeEnum::IMAGE) {
                $imageIds[] = $media->mediaId;
            }

            if ($media->type === MediaTypeEnum::VIDEO) {
                $videoIds[] = $media->mediaId;
            }

            if ($media->isTitle) {
                $titleMediaId = $media->mediaId;
            }
        }

        return [
            'userIdFrom' => $sending->fromUserId,
            'userIdTo' => $sending->toUserId,
            'subject' => $sending->template->subject,
            'text' => $sending->template->messageText,
            'msgType' => 'iceletter',
            'isFree' => true, // Always true for auto sending because it sends only request letters
            'images' => $imageIds,
            'hasImage' => !empty($imageIds),
            'titleImageId' => $titleMediaId,
            'isIceletter' => true, // Always true for auto sending, need for statistics
            'visibleForRecipient' => true,
            'time' => time(),
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
            || !array_key_exists('isFree', $message)
            || !array_key_exists('images', $message)
            || !array_key_exists('hasImage', $message)
            || !array_key_exists('titleImageId', $message)
            || !array_key_exists('isIceletter', $message)
            || !array_key_exists('visibleForRecipient', $message)
            || !array_key_exists('time', $message)
            || !array_key_exists('templateId', $message)
        ) {
            throw new \InvalidArgumentException('Wrong message data');
        }

        $userId = $message['userIdTo'];
        $profileId = $message['userIdFrom'];
        $isSenderOnline = $this->userStatus->isUserOnline($profileId);
        $hasAnswerMessage = $this->incomeSendingState->isReceived($profileId, $userId);

        if ($isSenderOnline && !$hasAnswerMessage) {
            $sentResult = $this->letter->send(
                $message,
                true,
                true,
                $message['images']
            );

            if (ctype_xdigit($sentResult) && strlen($sentResult) === 32) {
                $this->sentTemplate->addTemplate(
                    [
                        'recipientId' => $userId,
                        'templateId' => $message['templateId'],
                        'templateType' => TemplateTypeEnum::LETTER,
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
