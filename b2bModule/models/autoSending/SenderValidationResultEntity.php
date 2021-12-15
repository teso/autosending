<?php
namespace b2b\models\autoSending;

class SenderValidationResultEntity
{
    public $profileId;
    public $isValid;
    public $needChatTemplateCount;
    public $needLetterTemplateCount;

    public function __construct(
        string $profileId,
        bool $isValid,
        int $needChatTemplateCount,
        int $needLetterTemplateCount
    ) {
        $this->profileId = $profileId;
        $this->isValid = $isValid;
        $this->needChatTemplateCount = $needChatTemplateCount;
        $this->needLetterTemplateCount = $needLetterTemplateCount;
    }
}
