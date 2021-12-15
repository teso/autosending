<?php
namespace b2b\models\autoSending;

use b2b\models\AbstractCollection;

class SendingCollection extends AbstractCollection
{
    public function offsetSet($offset, $value)
    {
        if (!($value instanceof SendingEntity)) {
            throw new \InvalidArgumentException('Wrong instance of collection entity');
        }

        if (is_null($offset)) {
            $this->entities[] = $value;
        } else {
            $this->entities[$offset] = $value;
        }
    }

    public function toArray(): array
    {
        $mapper = new SendingMapper();
        $result = [];

        foreach ($this->entities as $entity) {
            $result[] = $mapper->mapToHash($entity);
        }

        return $result;
    }
}
