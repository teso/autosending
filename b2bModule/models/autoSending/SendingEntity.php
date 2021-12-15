<?php
namespace b2b\models\autoSending;

use b2b\models\chatTemplate\ChatTemplateEntity;
use b2b\models\letterTemplate\LetterTemplateEntity;

class SendingEntity
{
    public $fromUserId;
    public $toUserId;
    public $type;
    public $template;

    /**
     * @param ChatTemplateEntity|LetterTemplateEntity|null $template
     */
    public function __construct(
        string $fromUserId,
        string $toUserId,
        string $type,
        $template
    ) {
        $this->fromUserId = $fromUserId;
        $this->toUserId = $toUserId;
        $this->type = $type;
        $this->template = $template;
    }
}
