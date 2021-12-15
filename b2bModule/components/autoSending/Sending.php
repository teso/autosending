<?php
namespace b2b\components\autoSending;

use b2b\models\autoSending\DebugOperationEnum;
use b2b\models\autoSending\SendingCollection;
use b2b\models\autoSending\SendingEntity;
use b2b\models\autoSending\SendingMapper;
use b2b\models\autoSending\SendingTypeEnum;
use phoenix\userStatus\components\UserStatus;
use phoenix\permissions\components\PermissionsComponent;
use b2b\components\CommunicationCtr;
use Phoenix\ActionsAggregator\SearchActionsAggregator;

class Sending
{
    private $sendingPoolManager;
    private $chatTemplate;
    private $letterTemplate;
    private $outcomeSendingState;
    private $incomeSendingState;
    private $debug;
    private $userStatus;
    private $permissions;
    private $communicationCtr;
    private $searchActionsAggregator;

    public function __construct(
        SendingPoolManager $sendingPoolManager,
        ChatTemplate $chatTemplate,
        LetterTemplate $letterTemplate,
        OutcomeSendingState $outcomeSendingState,
        IncomeSendingState $incomeSendingState,
        Debug $debug,
        UserStatus $userStatus,
        PermissionsComponent $permissions,
        CommunicationCtr $communicationCtr,
        SearchActionsAggregator $searchActionsAggregator
    ) {
        $this->sendingPoolManager = $sendingPoolManager;
        $this->chatTemplate = $chatTemplate;
        $this->letterTemplate = $letterTemplate;
        $this->outcomeSendingState = $outcomeSendingState;
        $this->incomeSendingState = $incomeSendingState;
        $this->debug = $debug;
        $this->userStatus = $userStatus;
        $this->permissions = $permissions;
        $this->communicationCtr = $communicationCtr;
        $this->searchActionsAggregator = $searchActionsAggregator;
    }

    public function getAll(array $fromUserIds, string $toUserId): SendingCollection
    {
        $sendings = new SendingCollection();

        $areOutcomeSent = $this->outcomeSendingState->areSent($fromUserIds, $toUserId);
        $areIncomeReceived = $this->incomeSendingState->areReceived($fromUserIds, $toUserId);
        $areOnline = $this->userStatus->areUsersOnline($fromUserIds);
        $chatTemplates = $this->chatTemplate->getAll($fromUserIds, $toUserId);
        $letterTemplates = $this->letterTemplate->getAll($fromUserIds, $toUserId);

        foreach ($fromUserIds as $fromUserId) {
            $sendingPool = new SendingCollection();
            $mapper = new SendingMapper();

            $sendingPool[] = $mapper->hashToMap(
                [
                    'fromUserId' => $fromUserId,
                    'toUserId' => $toUserId,
                    'type' => SendingTypeEnum::CHAT,
                    'template' => $chatTemplates[$fromUserId]
                ]
            );
            $sendingPool[] = $mapper->hashToMap(
                [
                    'fromUserId' => $fromUserId,
                    'toUserId' => $toUserId,
                    'type' => SendingTypeEnum::LETTER,
                    'template' => $letterTemplates[$fromUserId]
                ]
            );

            $sending = $this->sendingPoolManager->pick($sendingPool);

            if (empty($sending)
                || !empty($areOutcomeSent[$fromUserId])
                || !empty($areIncomeReceived[$fromUserId])
                || empty($areOnline[$fromUserId])
            ) {
                if (!empty($areOutcomeSent[$fromUserId])) {
                    $this->debug->saveOperation(
                        DebugOperationEnum::SKIP_SENDER_ALREADY_SENT,
                        $toUserId,
                        (array) $fromUserId
                    );
                }

                if (!empty($areIncomeReceived[$fromUserId])) {
                    $this->debug->saveOperation(
                        DebugOperationEnum::SKIP_SENDER_HAS_ANSWER,
                        $toUserId,
                        (array) $fromUserId
                    );
                }

                if (empty($areOnline[$fromUserId])) {
                    $this->debug->saveOperation(
                        DebugOperationEnum::SKIP_SENDER_SENDER_OFFLINE,
                        $toUserId,
                        (array) $fromUserId
                    );
                }

                continue;
            }

            $sendingMapper = new SendingMapper();

            $this->debug->saveOperation(
                DebugOperationEnum::GET_SENDING,
                $toUserId,
                (array) $fromUserId,
                $sendingMapper->mapToHash($sending)
            );

            $sendings[] = $sending;
        }

        if ($this->permissions->doesUserHaveMembership($toUserId)) {
            $commCtrs = $this->communicationCtr->getCtrsByProfileIds($fromUserIds);

            $sendings->usort(
                function (SendingEntity $sending1, SendingEntity $sending2) use ($commCtrs) {
                    return $commCtrs[$sending2->fromUserId]['ctrValue'] <=> $commCtrs[$sending1->fromUserId]['ctrValue'];
                }
            );

            $this->debug->saveOperation(
                DebugOperationEnum::SORT_SENDINGS,
                $toUserId,
                $sendings->column('fromUserId'),
                array_map(
                    function (string $fromUserId) use ($commCtrs) {
                        return [
                            'profileId' => $fromUserId,
                            'ctr' => $commCtrs[$fromUserId]['ctrValue'] ?? null,
                        ];
                    },
                    $sendings->column('fromUserId')
                )
            );
        } else {
            $searchCtrs = $this->searchActionsAggregator->getCtrsDataByUserIds($fromUserIds);

            $sendings->usort(
                function (SendingEntity $sending1, SendingEntity $sending2) use ($searchCtrs) {
                    return $searchCtrs[$sending2->fromUserId]['searchCtr'] <=> $searchCtrs[$sending1->fromUserId]['searchCtr'];
                }
            );

            $this->debug->saveOperation(
                DebugOperationEnum::SORT_SENDINGS,
                $toUserId,
                $sendings->column('fromUserId'),
                array_map(
                    function (string $fromUserId) use ($searchCtrs) {
                        return [
                            'profileId' => $fromUserId,
                            'ctr' => $searchCtrs[$fromUserId]['searchCtr'] ?? null,
                        ];
                    },
                    $sendings->column('fromUserId')
                )
            );
        }

        return $sendings;
    }
}
