<?php

namespace FOQ\ElasticaBundle\Transformer;

use RuntimeException;
use Traversable;
use ArrayAccess;

/**
 * AutomaticObjectToArrayTransformer
 * Tries to convert objects by generating getters
 * based on the required keys
 */
class ObjectToArrayAutomaticTransformer implements ObjectToArrayTransformerInterface
{
    /**
     * Transforms an object into an array having the required keys
     *
     * @param object $object the object to convert
     * @param array $requiredKeys the keys we want to have in the returned array
     * @return array
     **/
    public function transform($object, array $requiredKeys)
    {
        $class = get_class($object);
        $array = array();
        foreach ($requiredKeys as $key) {
            $getter = 'get'.ucfirst($key);
            if (!method_exists($class, $getter)) {
                throw new RuntimeException(sprintf('The getter %s::%s does not exist', $class, $getter));
            }
            $array[$key] = $this->normalizeValue($object->$getter());
        }

        return array_filter($array);
    }

    public function normalizeValue($value)
    {
        if (is_array($value) || $value instanceof Traversable || $value instanceof ArrayAccess) {
            $values = array();
            foreach ($value as $v) {
                $values[] = (string) $v;
            }
            $value = implode(', ', $values);
        }

        return (string) $value;
    }
}
