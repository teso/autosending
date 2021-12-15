<?php
namespace b2b\components\autoSending;

use b2b\models\autoSending\SendingEntity;

interface SendStrategyInterface
{
    public function getSendDelay(): int;

    public function getMessage(SendingEntity $sending): array;

    public function send(array $message): void;
}
