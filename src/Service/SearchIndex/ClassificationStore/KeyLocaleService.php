<?php
declare(strict_types=1);

/**
 * This source file is available under the terms of the
 * Pimcore Open Core License (POCL)
 * Full copyright and license information is available in
 * LICENSE.md which is distributed with this source code.
 *
 *  @copyright  Copyright (c) Pimcore GmbH (https://www.pimcore.com)
 *  @license    Pimcore Open Core License (POCL)
 */

namespace Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\ClassificationStore;

use Pimcore\Bundle\GenericDataIndexBundle\Model\SearchIndexAdapter\MappingProperty;
use Pimcore\Model\DataObject\ClassDefinition\Data\Classificationstore;
use Pimcore\Model\DataObject\Classificationstore\LocalizationResolver;

/**
 * The only place the bundle asks whether a key is localized. The rule - the key's own flag decides
 * and a key without one inherits the class definition field's - belongs to {@see LocalizationResolver}
 * and is not restated here, so the two cannot drift apart.
 *
 * @internal
 */
final readonly class KeyLocaleService implements KeyLocaleServiceInterface
{
    public function __construct(
        private LocalizationResolver $localizationResolver,
    ) {
    }

    public function getLocaleForKey(int $keyId, Classificationstore $fieldDefinition, string $language): string
    {
        if (!$this->localizationResolver->isKeyLocalized($keyId, $fieldDefinition)) {
            return MappingProperty::NOT_LOCALIZED_KEY;
        }

        return $language;
    }

    public function getLocalesForKey(
        int $keyId,
        Classificationstore $fieldDefinition,
        array $candidateLanguages
    ): array {
        if (!$this->localizationResolver->isKeyLocalized($keyId, $fieldDefinition)) {
            return [MappingProperty::NOT_LOCALIZED_KEY];
        }

        return $candidateLanguages;
    }
}
