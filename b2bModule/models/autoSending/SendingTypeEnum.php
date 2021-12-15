<?php
namespace b2b\models\autoSending;

use b2b\models\AbstractEnum;

class SendingTypeEnum extends AbstractEnum
{
    public const CHAT = 'chat';
    public const LETTER = 'letter';
}
