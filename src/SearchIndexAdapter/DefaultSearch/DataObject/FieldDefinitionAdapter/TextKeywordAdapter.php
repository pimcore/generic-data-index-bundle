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

namespace Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\DefaultSearch\DataObject\FieldDefinitionAdapter;

use Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\DataObject\FieldDefinitionServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\IndexMappingServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\SearchIndexConfigServiceInterface;

/**
 * @internal
 */
final class TextKeywordAdapter extends AbstractAdapter
{
    public function __construct(
        protected SearchIndexConfigServiceInterface $searchIndexConfigService,
        protected FieldDefinitionServiceInterface $fieldDefinitionService,
        private readonly IndexMappingServiceInterface $indexMappingService,
    ) {
        parent::__construct(
            $searchIndexConfigService,
            $fieldDefinitionService
        );
    }

    public function getIndexMapping(): array
    {
        return $this->indexMappingService->getMappingForTextKeyword(
            $this->searchIndexConfigService->getSearchAnalyzerAttributes()
        );
    }

    public function normalize(mixed $value): ?string
    {
        if ($value) {
            return preg_replace("/src=(['\"])data:[^;]+;base64,.+?\\1/", '', $value);
        }

        return null;
    }
}
