<?php
namespace b2b\components\autoSending;

use b2b\components\SentTemplate;
use b2b\components\TemplateMedia;
use b2b\models\autoSending\SendingEntity;
use b2b\models\autoSending\SendingTypeEnum;
use phoenix\userStatus\components\UserStatus;
use phoenix\internal_mail\components\InternalMail;
use phoenix\internal_mail\components\Letter;

class SendStrategyFactory
{
    private $incomeSendingState;
    private $debug;
    private $templateMedia;
    private $sentTemplate;
    private $userStatus;
    private $internalMail;
    private $letter;

    public function __construct(
        IncomeSendingState $incomeSendingState,
        Debug $debug,
        TemplateMedia $templateMedia,
        SentTemplate $sentTemplate,
        UserStatus $userStatus,
        InternalMail $internalMail,
        Letter $letter
    ) {
        $this->incomeSendingState = $incomeSendingState;
        $this->debug = $debug;
        $this->templateMedia = $templateMedia;
        $this->sentTemplate = $sentTemplate;
        $this->userStatus = $userStatus;
        $this->internalMail = $internalMail;
        $this->letter = $letter;
    }

    public function buildBySending(SendingEntity $sending): SendStrategyInterface
    {
        if ($sending->type === SendingTypeEnum::CHAT) {
            return new ChatStrategy(
                $this->incomeSendingState,
                $this->debug,
                $this->sentTemplate,
                $this->userStatus,
                $this->internalMail
            );
        } elseif ($sending->type === SendingTypeEnum::LETTER) {
            return new LetterStrategy(
                $this->incomeSendingState,
                $this->debug,
                $this->templateMedia,
                $this->sentTemplate,
                $this->userStatus,
                $this->letter
            );
        } else {
            throw new \InvalidArgumentException('Wrong sending type');
        }
    }

    public function buildByMessage(array $message): SendStrategyInterface
    {
        if (empty($message['msgType'])) {
            throw new \InvalidArgumentException('Empty message type');
        }

        if ($message['msgType'] === 'icebreaker') {
            return new ChatStrategy(
                $this->incomeSendingState,
                $this->debug,
                $this->sentTemplate,
                $this->userStatus,
                $this->internalMail
            );
        } elseif ($message['msgType'] === 'iceletter') {
            return new LetterStrategy(
                $this->incomeSendingState,
                $this->debug,
                $this->templateMedia,
                $this->sentTemplate,
                $this->userStatus,
                $this->letter
            );
        } else {
            throw new \InvalidArgumentException('Wrong message type');
        }
    }
}
