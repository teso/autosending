<?php
namespace b2b\components;

use b2b\models\autoSending\DebugOperationEnum;
use Phoenix\DeferredTask\DeferredTask;
use Phoenix\Cache\RedisCache;
use b2b\models\autoSending\SendingEntity;
use Phoenix\UserBase\EUser;
use phoenix\userStatus\components\UserStatus;
use Phoenix\Config\ConfigInterface;
use Psr\Log\LoggerInterface;

class AutoSending
{
    public const DEFERRED_DELAY_LIMIT = '5 minutes';
    public const LETTER_TEMPLATE_LIMIT = 1;
    public const CHAT_TEMPLATE_LIMIT = 2;
    private const DEFERRED_SET_FLAG_KEY = 'deferredSet:';
    private const DEFERRED_SET_FLAG_TTL = self::DEFERRED_DELAY_LIMIT;

    private $sendStrategyFactory;
    private $sending;
    private $sender;
    private $outcomeSendingState;
    private $debug;
    private $deferredTask;
    private $eUser;
    private $userStatus;
    private $cache;
    private $config;
    private $logger;

    public function __construct(
        autoSending\SendStrategyFactory $sendStrategyFactory,
        autoSending\Sending $sending,
        autoSending\Sender $sender,
        autoSending\OutcomeSendingState $outcomeSendingState,
        autoSending\Debug $debug,
        DeferredTask $deferredTask,
        EUser $eUser,
        UserStatus $userStatus,
        RedisCache $cache,
        ConfigInterface $config,
        LoggerInterface $logger
    ) {
        $this->sendStrategyFactory = $sendStrategyFactory;
        $this->sending = $sending;
        $this->sender = $sender;
        $this->outcomeSendingState = $outcomeSendingState;
        $this->debug = $debug;
        $this->deferredTask = $deferredTask;
        $this->eUser = $eUser;
        $this->userStatus = $userStatus;
        $this->cache = $cache;
        $this->config = $config;
        $this->logger = $logger;
    }

    public function planUsers(array $userIds): void
    {
        if (empty($userIds)) {
            return;
        }

        $profileIds = $this->sender->getAll();

        if (empty($profileIds)) {
            return;
        }

        $canBePlanned = $this->canBePlanned($userIds);

        $plannedSendings = [];

        foreach ($userIds as $userId) {
            if (empty($canBePlanned[$userId])) {
                continue;
            }

            $this->setPlanned($userId);

            $this->debug->saveOperation(
                DebugOperationEnum::GET_SENDERS,
                $userId,
                $profileIds
            );

            $sendings = $this->sending->getAll($profileIds, $userId);

            $sendDelay = 0;
            $sendDelayLimit = strtotime(self::DEFERRED_DELAY_LIMIT) - time();

            /**
             * @var SendingEntity $sending
             */
            foreach ($sendings as $sending) {
                $sendStrategy = $this->sendStrategyFactory->buildBySending($sending);

                $sendDelay += $sendStrategy->getSendDelay();
                $sendMessage = $sendStrategy->getMessage($sending, $userId);

                $plannedSendings[] = [
                    'b2bCommunication.delayed',
                    (new \DateTime($sendDelay . ' seconds'))->format('Y-m-d H:i:s'),
                    $sendMessage
                ];

                if ($sendDelayLimit < $sendDelay) {
                    break;
                }
            }

            $this->debug->saveOperation(
                DebugOperationEnum::PLAN_SENDINGS,
                $userId,
                $sendings->column('fromUserId'),
                $plannedSendings
            );
        }

        if (!empty($plannedSendings)) {
            $this->deferredTask->deferBatch($plannedSendings);
        }

        $this->sender->cleanOld();
    }

    public function sendMessage(array $message): void
    {
        try {
            $sendStrategy = $this->sendStrategyFactory->buildByMessage($message);

            $sendStrategy->send($message);

            $this->outcomeSendingState->setSent($message['userIdFrom'], $message['userIdTo']);
        } catch (\Throwable $exception) {
            $this->logger->warning(
                'Can\'t send message',
                [
                    'message' => $message,
                    'exception' => $exception,
                ]
            );
        }
    }

    /**
     * @return bool[] - user id as a key
     */
    private function arePlanned(array $userIds): array
    {
        if (empty($userIds)) {
            return [];
        }

        $result = array_fill_keys($userIds, false);

        $keys = array_map(
            function (string $userId) {
                return self::DEFERRED_SET_FLAG_KEY . $userId;
            },
            $userIds
        );
        $queryResult = $this->cache->fetchMulti($keys);

        foreach ($queryResult as $key => $value) {
            $userId = str_replace(self::DEFERRED_SET_FLAG_KEY, '', $key);

            if (!empty($value) && isset($result[$userId])) {
                $result[$userId] = true;
            }
        }

        return $result;
    }

    private function setPlanned(string $userId): void
    {
        $this->cache->add(
            self::DEFERRED_SET_FLAG_KEY . $userId,
            1,
            strtotime(self::DEFERRED_SET_FLAG_TTL) - time()
        );
    }

    /**
     * @return bool[]
     */
    private function canBePlanned(array $userIds): array
    {
        $result = array_fill_keys($userIds, false);

        $users = $this->eUser->findByIds(
            $userIds,
            [
                'useStaticCache' => true,
            ]
        );
        $statuses = $this->userStatus->doUsersHaveStatuses(
            $userIds,
            'default',
            'actualDataOnly'
        );
        $arePlanned = $this->arePlanned($userIds);

        foreach ($userIds as $userId) {
            if (empty($users[$userId]) || !empty($arePlanned[$userId])) {
                continue;
            }

            // expectsFunnelComplete = `false` in two cases: default value (if not set) or set to `false`
            // so we need also check onlineStatus - if `false` than all statuses are invalid (not set yet)
            $hasCompletedFunnel = !empty($statuses[$userId]['onlineStatus'])
                && empty($statuses[$userId]['expectsFunnelComplete']);

            if ($this->config->get(
                'b2b/canBePlannedForAutoSending',
                [
                    'siteId' => $users[$userId]['siteId'],
                    'gender' => $users[$userId]['gender'],
                    'hasCompletedFunnel' => $hasCompletedFunnel,
                ]
            )) {
                $result[$userId] = true;
            }
        }

        return $result;
    }
}
