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

use Pimcore\Model\DataObject\ClassDefinition\Data\Classificationstore;

/**
 * Answers which language node a classification store key is indexed under.
 *
 * The mapping and the document declare that node per key, so one group can hold keys indexed under
 * different nodes, and a filter addressing the wrong one returns an empty result rather than an
 * error. This is the resolution the index itself performs, exposed for the callers that have to
 * address it - the `$locale` of
 * {@see \Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Modifier\Filter\FieldType\ClassificationStoreFilter}.
 */
interface KeyLocaleServiceInterface
{
    /**
     * The language node the key is indexed under for the given language: that language for a key
     * that is localized in this field, the non-localized node for one that is not.
     */
    public function getLocaleForKey(int $keyId, Classificationstore $fieldDefinition, string $language): string;

    /**
     * Every language node the key is indexed under, out of the candidates offered: all of them for a
     * key that is localized in this field, the non-localized node alone for one that is not. This is
     * what the mapping declares and what the document writes, which is why a caller filtering a
     * single key wants {@see self::getLocaleForKey()} instead.
     *
     * @param string[] $candidateLanguages the non-localized node plus every valid language
     *
     * @return string[]
     */
    public function getLocalesForKey(
        int $keyId,
        Classificationstore $fieldDefinition,
        array $candidateLanguages
    ): array;
}
