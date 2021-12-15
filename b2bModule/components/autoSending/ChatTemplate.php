<?php
namespace b2b\components\autoSending;

use b2b\components\ChatTemplate as Template;
use b2b\components\SentTemplate;
use b2b\models\sentTemplate\TemplateTypeEnum;
use b2b\models\chatTemplate\ChatTemplateFindOptions;
use b2b\models\chatTemplate\ChatTemplateStatusEnum;
use b2b\models\OrderRuleCollection;
use b2b\models\OrderRuleEntity;
use b2b\models\chatTemplate\ChatTemplateEntity;
use b2b\models\sentTemplate\SentTemplateFindOptions;

class ChatTemplate
{
    private $template;
    private $sentTemplate;

    public function __construct(
        Template $template,
        SentTemplate $sentTemplate
    ) {
        $this->template = $template;
        $this->sentTemplate = $sentTemplate;
    }

    /**
     * @return ChatTemplateEntity[]
     */
    public function getAll(array $fromUserIds, string $toUserId): array
    {
        $result = array_fill_keys($fromUserIds, null);

        $chatOptions = new ChatTemplateFindOptions();
        $chatOptions->profileIds = $fromUserIds;
        $chatOptions->status = ChatTemplateStatusEnum::APPROVED;
        $chatOrderRules = new OrderRuleCollection();
        $chatOrderRules[] = new OrderRuleEntity('createdAt', true);

        $allTemplates = $this->template->getAllTemplatesByOptions(
            $chatOptions,
            null,
            null,
            $chatOrderRules
        );
        $allTemplates = $allTemplates->group('profileId');

        $sentOptions = new SentTemplateFindOptions();
        $sentOptions->recipientId = $toUserId;
        $sentOptions->templateType = TemplateTypeEnum::CHAT;

        $sentTemplates = $this->sentTemplate->getAllTemplatesByOptions($sentOptions);
        $sentTemplates = $sentTemplates->column(null, 'templateId');

        foreach ($fromUserIds as $fromUserId) {
            $templates = $allTemplates[$fromUserId] ?? [];

            foreach ($templates as $template) {
                if (empty($sentTemplates[$template->id])) {
                    $result[$fromUserId] = $template;

                    break;
                }
            }
        }

        return $result;
    }
}
