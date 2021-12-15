<?php
namespace b2b\components\autoSending;

use Phoenix\UserBase\EUser;
use Phoenix\Config\ConfigInterface;

class Recipient
{
    private $eUser;
    private $config;

    public function __construct(
        EUser $eUser,
        ConfigInterface $config
    ) {
        $this->eUser = $eUser;
        $this->config = $config;
    }

    public function isAllowed(string $userId, string $sendingType): bool
    {
        $user = $this->eUser->findById(
            $userId,
            [
                'useStaticCache' => true,
            ]
        );

        if (empty($user)) {
            return false;
        }

        return $this->config->get(
            'b2b/allowAutoSendingType',
            [
                'isTestUser' => (bool) $user['testerProfile'],
                'sendingType' => $sendingType,
            ]
        );
    }
}
