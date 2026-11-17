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

namespace Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\Mapping;

use Pimcore\Bundle\GenericDataIndexBundle\Exception\TotalFieldsLimitException;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\SearchIndexConfigServiceInterface;

/**
 * @internal
 */
final readonly class TotalFieldsLimitGuard implements TotalFieldsLimitGuardInterface
{
    private MappingFieldCountServiceInterface $fieldCountService;

    private TotalFieldsLimitMessageServiceInterface $limitMessageService;

    /** The two collaborators are stateless, so a caller outside the container can leave them out. */
    public function __construct(
        private SearchIndexConfigServiceInterface $searchIndexConfigService,
        ?MappingFieldCountServiceInterface $fieldCountService = null,
        ?TotalFieldsLimitMessageServiceInterface $limitMessageService = null,
    ) {
        $this->fieldCountService = $fieldCountService ?? new MappingFieldCountService();
        $this->limitMessageService = $limitMessageService ?? new TotalFieldsLimitMessageService();
    }

    public function assertWithinLimit(string $indexName, array $mappingProperties): void
    {
        $limit = $this->getConfiguredLimit();
        if ($limit === null) {
            return;
        }

        $countedFields = $this->fieldCountService->countFields($mappingProperties);
        if ($countedFields <= $limit) {
            return;
        }

        throw new TotalFieldsLimitException(
            $this->limitMessageService->forFieldCount(
                $indexName,
                $countedFields,
                $limit,
                $this->fieldCountService->getLargestContributor($mappingProperties)
            )
        );
    }

    /** Null when the configuration names no limit. */
    private function getConfiguredLimit(): ?int
    {
        $limit = $this->searchIndexConfigService->getIndexSettings()['index']['mapping']['total_fields']['limit']
            ?? null;

        return is_numeric($limit) ? (int) $limit : null;
    }
}
