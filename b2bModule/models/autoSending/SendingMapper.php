<?php
namespace b2b\models\autoSending;

use b2b\models\chatTemplate\ChatTemplateEntity;
use b2b\models\chatTemplate\ChatTemplateMapper;
use b2b\models\letterTemplate\LetterTemplateEntity;
use b2b\models\letterTemplate\LetterTemplateMapper;
use b2b\models\MapperInterface;

class SendingMapper implements MapperInterface
{
    public function mapToHash($map): array
    {
        if (!($map instanceof SendingEntity)) {
            throw new \InvalidArgumentException('Wrong instance of map');
        }

        $template = [];

        if (!is_null($map->template)) {
            if ($map->template instanceof ChatTemplateEntity) {
                $templateMapper = new ChatTemplateMapper();
            }

            if ($map->template instanceof LetterTemplateEntity) {
                $templateMapper = new LetterTemplateMapper();
            }

            $template = $templateMapper->mapToHash($map->template);
        }

        return [
            'fromUserId' => $map->fromUserId,
            'toUserId' => $map->toUserId,
            'type' => $map->type,
            'template' => $template,
        ];
    }

    public function hashToMap(array $hash)
    {
        if (!array_key_exists('fromUserId', $hash)) {
            throw new \InvalidArgumentException('Key \'fromUserId\' is not set');
        } elseif (!array_key_exists('toUserId', $hash)) {
            throw new \InvalidArgumentException('Key \'toUserId\' is not set');
        } elseif (!array_key_exists('type', $hash)) {
            throw new \InvalidArgumentException('Key \'type\' is not set');
        } elseif (!array_key_exists('template', $hash)) {
            throw new \InvalidArgumentException('Key \'template\' is not set');
        }

        if (!is_null($hash['template'])
            && !($hash['template'] instanceof ChatTemplateEntity || $hash['template'] instanceof LetterTemplateEntity)
        ) {
            throw new \InvalidArgumentException('Wrong instance of template');
        }

        return new SendingEntity(
            $hash['fromUserId'],
            $hash['toUserId'],
            $hash['type'],
            $hash['template']
        );
    }
}
