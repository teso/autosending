<?php
namespace b2b\components\consumer;

use b2b\components\ConsumerInterface;
use b2b\components\autoSending\Sender;
use b2b\components\autoSending\SenderValidator;
use b2b\components\autoSending\State;
use b2b\components\AutoSending;
use b2b\components\ChatTemplate;
use b2b\components\LetterTemplate;
use b2b\components\Manager;
use b2b\components\Profile;

class AutoSendingConsumer implements ConsumerInterface
{
    private $sender;
    private $state;
    private $senderValidator;
    private $autoSending;
    private $chatTemplate;
    private $letterTemplate;
    private $manager;
    private $profile;

    public function __construct(
        Sender $sender,
        State $state,
        SenderValidator $senderValidator,
        AutoSending $autoSending,
        ChatTemplate $chatTemplate,
        LetterTemplate $letterTemplate,
        Manager $manager,
        Profile $profile
    ) {
        $this->sender = $sender;
        $this->state = $state;
        $this->senderValidator = $senderValidator;
        $this->autoSending = $autoSending;
        $this->chatTemplate = $chatTemplate;
        $this->letterTemplate = $letterTemplate;
        $this->manager = $manager;
        $this->profile = $profile;
    }

    /**
     * @uses onUserStatusChange(), onB2bManagerLogout(),
     * @uses onB2bChatTemplateApprove(), onB2bLetterTemplateApprove()
     * @uses onB2bChatTemplateUpdate(), onB2bLetterTemplateUpdate()
     */
    public function consume(string $eventName, array $eventsData): void
    {
        $this->$eventName($eventsData);
    }

    private function onUserStatusChange(array $items): void
    {
        $userIds = array_column($items, 'uniqueKey');

        $this->autoSending->planUsers($userIds);
    }

    private function onB2bManagerLogout(array $items): void
    {
        $managerIds = array_column($items, 'managerId');

        if (empty($managerIds)) {
            return;
        }

        $profiles = $this->profile->getAllProfilesByManagerIds($managerIds);
        $profileIds = $profiles->column('profileId');

        if (empty($profileIds)) {
            return;
        }

        $this->sender->deleteAll($profileIds);
        $this->state->disableAll($managerIds);
    }

    private function onB2bChatTemplateApprove(array $items): void
    {
        $templateIds = array_column($items, 'templateId');
        $templates = $this->chatTemplate->getAllTemplatesByIds($templateIds);
        $profileIds = $this->filterEnabledProfileIds($templates->column('profileId'));
        $allValidationResults = $this->senderValidator->validateAll($profileIds);
        $validProfileIds = [];

        foreach ($allValidationResults as $validationResult) {
            if ($validationResult->isValid) {
                $validProfileIds[] = $validationResult->profileId;
            }
        }

        $this->sender->addAll($validProfileIds);
    }

    private function onB2bLetterTemplateApprove(array $items): void
    {
        $templateIds = array_column($items, 'templateId');
        $templates = $this->letterTemplate->getAllTemplatesByIds($templateIds);
        $profileIds = $this->filterEnabledProfileIds($templates->column('profileId'));
        $allValidationResults = $this->senderValidator->validateAll($profileIds);
        $validProfileIds = [];

        foreach ($allValidationResults as $validationResult) {
            if ($validationResult->isValid) {
                $validProfileIds[] = $validationResult->profileId;
            }
        }

        $this->sender->addAll($validProfileIds);
    }

    private function onB2bChatTemplateUpdate(array $items): void
    {
        $templateIds = array_column($items, 'templateId');
        $templates = $this->chatTemplate->getAllTemplatesByIds($templateIds);
        $profileIds = $this->filterEnabledProfileIds($templates->column('profileId'));
        $allValidationResults = $this->senderValidator->validateAll($profileIds);
        $invalidProfileIds = [];

        foreach ($allValidationResults as $validationResult) {
            if (!$validationResult->isValid) {
                $invalidProfileIds[] = $validationResult->profileId;
            }
        }

        $this->sender->deleteAll($invalidProfileIds);
    }

    private function onB2bLetterTemplateUpdate(array $items): void
    {
        $templateIds = array_column($items, 'templateId');
        $templates = $this->letterTemplate->getAllTemplatesByIds($templateIds);
        $profileIds = $this->filterEnabledProfileIds($templates->column('profileId'));
        $allValidationResults = $this->senderValidator->validateAll($profileIds);
        $invalidProfileIds = [];

        foreach ($allValidationResults as $validationResult) {
            if (!$validationResult->isValid) {
                $invalidProfileIds[] = $validationResult->profileId;
            }
        }

        $this->sender->deleteAll($invalidProfileIds);
    }

    private function filterEnabledProfileIds(array $profileIds): array
    {
        $result = [];

        $managers = $this->manager->getAllManagersByProfileIds($profileIds);
        $managerIds = array_column($managers, 'id');
        $areEnabled = $this->state->areEnabled($managerIds);

        foreach ($profileIds as $profileId) {
            $manager = $managers[$profileId] ?? null;

            if (!empty($manager) && !empty($areEnabled[$manager->id])) {
                $result[] = $profileId;
            }
        }

        return $result;
    }
}
