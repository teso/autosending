<?php
namespace b2b\components\autoSending;

use b2b\components\AutoSending;
use b2b\components\ChatTemplate;
use b2b\components\LetterTemplate;
use b2b\models\autoSending\SenderValidationResultCollection;
use b2b\models\autoSending\SenderValidationResultEntity;
use b2b\models\chatTemplate\ChatTemplateFindOptions;
use b2b\models\chatTemplate\ChatTemplateStatusEnum;
use b2b\models\letterTemplate\LetterTemplateFindOptions;
use b2b\models\letterTemplate\LetterTemplateStatusEnum;

class SenderValidator
{
    private $chatTemplate;
    private $letterTemplate;

    public function __construct(
        ChatTemplate $chatTemplate,
        LetterTemplate $letterTemplate
    ) {
        $this->chatTemplate = $chatTemplate;
        $this->letterTemplate = $letterTemplate;
    }

    public function validateAll(array $profileIds): SenderValidationResultCollection
    {
        $validationResults = new SenderValidationResultCollection();

        $chatOptions = new ChatTemplateFindOptions();
        $chatOptions->profileIds = $profileIds;
        $chatOptions->status = ChatTemplateStatusEnum::APPROVED;

        $allChatTemplates = $this->chatTemplate->getAllTemplatesByOptions($chatOptions);
        $allChatTemplates = $allChatTemplates->group('profileId');
        $allChatTemplateCount = array_fill_keys($profileIds, 0);

        foreach ($allChatTemplates as $profileId => $chatTemplates) {
            $allChatTemplateCount[$profileId] = count($chatTemplates);
        }

        $letterOptions = new LetterTemplateFindOptions();
        $letterOptions->profileIds = $profileIds;
        $letterOptions->status = LetterTemplateStatusEnum::APPROVED;

        $allLetterTemplates = $this->letterTemplate->getAllTemplatesByOptions($letterOptions);
        $allLetterTemplates = $allLetterTemplates->group('profileId');
        $allLetterTemplateCount = array_fill_keys($profileIds, 0);

        foreach ($allLetterTemplates as $profileId => $letterTemplates) {
            $allLetterTemplateCount[$profileId] = count($letterTemplates);
        }

        foreach ($profileIds as $profileId) {
            $chatTemplateCount = $allChatTemplateCount[$profileId];
            $letterTemplateCount = $allLetterTemplateCount[$profileId];
            $needChatTemplateCount = 0;
            $needLetterTemplateCount = 0;
            $isValid = $chatTemplateCount >= AutoSending::CHAT_TEMPLATE_LIMIT
                || $letterTemplateCount >= AutoSending::LETTER_TEMPLATE_LIMIT;

            if ($chatTemplateCount < AutoSending::CHAT_TEMPLATE_LIMIT) {
                $needChatTemplateCount = AutoSending::CHAT_TEMPLATE_LIMIT - $chatTemplateCount;
            }

            if ($letterTemplateCount < AutoSending::LETTER_TEMPLATE_LIMIT) {
                $needLetterTemplateCount = AutoSending::LETTER_TEMPLATE_LIMIT - $letterTemplateCount;
            }

            $validationResult = new SenderValidationResultEntity(
                $profileId,
                $isValid,
                $needChatTemplateCount,
                $needLetterTemplateCount
            );

            $validationResults[] = $validationResult;
        }

        return $validationResults;
    }
}
