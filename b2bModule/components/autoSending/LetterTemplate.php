<?php
namespace b2b\components\autoSending;

use b2b\components\LastCommunication;
use b2b\components\LetterTemplate as Template;
use b2b\models\lastCommunication\LastCommunicationFindOptions;
use b2b\models\lastCommunication\TypeEnum;
use b2b\models\lastCommunication\LastCommunicationEntity;
use b2b\models\letterTemplate\LetterTemplateEntity;
use b2b\models\letterTemplate\LetterTemplateFindOptions;
use b2b\models\letterTemplate\LetterTemplateStatusEnum;

class LetterTemplate
{
    private $template;
    private $lastCommunication;

    public function __construct(
        Template $template,
        LastCommunication $lastCommunication
    ) {
        $this->template = $template;
        $this->lastCommunication = $lastCommunication;
    }

    /**
     * @return LetterTemplateEntity[]
     */
    public function getAll(array $fromUserIds, string $toUserId): array
    {
        $result = array_fill_keys($fromUserIds, null);

        $findOptions = new LetterTemplateFindOptions();
        $findOptions->profileIds = $fromUserIds;
        $findOptions->status = LetterTemplateStatusEnum::APPROVED;

        $allTemplates = $this->template->getAllTemplatesByOptions($findOptions);
        $allTemplates = $allTemplates->group('profileId');
        $areSent = $this->areSent($fromUserIds, $toUserId);

        foreach ($allTemplates as $profileId => $templates) {
            if (empty($areSent[$profileId])) {
                $result[$profileId] = $templates[array_rand($templates)];
            }
        }

        return $result;
    }

    /**
     * @return bool[] - user id as a key
     */
    private function areSent(array $fromUserIds, string $toUserId): array
    {
        $result = array_fill_keys($fromUserIds, false);

        $options = new LastCommunicationFindOptions();
        $options->recipientId = $toUserId;
        $options->type = TypeEnum::LETTER; // iceletter sends as letter

        $activities = $this->lastCommunication->getAllActivitiesByOptions($options);
        $activities = $activities->column(null, 'senderId');

        /**
         * @var LastCommunicationEntity $activity
         */
        foreach ($fromUserIds as $fromUserId) {
            if (!empty($activities[$fromUserId])) {
                $result[$fromUserId] = true;
            }
        }

        return $result;
    }
}
