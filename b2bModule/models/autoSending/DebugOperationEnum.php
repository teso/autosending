<?php
namespace b2b\models\autoSending;

use b2b\models\AbstractEnum;

class DebugOperationEnum extends AbstractEnum
{
    public const GET_SENDERS = 1;
    public const SKIP_MESSAGE_TYPE_NO_TEMPLATE = 2;
    public const SKIP_MESSAGE_TYPE_NOT_ALLOWED_RECIPIENT = 3;
    public const SKIP_SENDER_ALREADY_SENT = 4;
    public const SKIP_SENDER_HAS_ANSWER = 5;
    public const SKIP_SENDER_SENDER_OFFLINE = 6;
    public const GET_SENDING = 7;
    public const SORT_SENDINGS = 8;
    public const PLAN_SENDINGS = 9;
    public const TRY_SEND = 10;
    public const SEND_OK = 11;
    public const SEND_FAIL = 12;
}
