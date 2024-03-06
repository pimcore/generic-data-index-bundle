<?php
declare(strict_types=1);

/**
 * Pimcore
 *
 * This source file is available under following license:
 * - Pimcore Commercial License (PCL)
 *
 *  @copyright  Copyright (c) Pimcore GmbH (http://www.pimcore.org)
 *  @license    http://www.pimcore.org/license     PCL
 */

namespace Pimcore\Bundle\GenericDataIndexBundle\Model\SearchIndexAdapter;

use Pimcore\ValueObject\Collection\ArrayOfStrings;

final class MappingProperty
{
    public const NOT_LOCALIZED_KEY = 'default';

    private readonly ArrayOfStrings $languages;

    public function __construct(
        private readonly string $name,
        private readonly string $type,
        private readonly array $mapping,
        array $languages,
    ) {
        $this->languages = new ArrayOfStrings($languages);
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getMapping(): array
    {
        return $this->mapping;
    }

    /**
     * @return string[]
     */
    public function getLanguages(): array
    {
        return $this->languages->getValue();
    }
}
