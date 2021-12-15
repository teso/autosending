<?php
namespace b2b\models\autoSending;

use b2b\models\AbstractCollection;

class SenderValidationResultCollection extends AbstractCollection
{
    public function offsetSet($offset, $value)
    {
        if (!($value instanceof SenderValidationResultEntity)) {
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
        return []; // Not used
    }
}
