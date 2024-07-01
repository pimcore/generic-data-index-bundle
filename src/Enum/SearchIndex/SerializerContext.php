<?php
declare(strict_types=1);

namespace Pimcore\Bundle\GenericDataIndexBundle\Enum\SearchIndex;

/**
 * @internal
 */
enum SerializerContext
{
    case SKIP_LAZY_LOADED_FIELDS;

    public function containedInContext(array $context): bool
    {
        return $context[$this->name] ?? false;
    }

    public function createContext(): array
    {
        return [$this->name => true];
    }
}
