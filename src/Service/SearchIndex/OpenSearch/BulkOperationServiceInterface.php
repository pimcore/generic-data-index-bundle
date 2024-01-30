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

namespace Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\OpenSearch;

use Pimcore\Bundle\GenericDataIndexBundle\Exception\BulkOperationException;

/**
 * @internal
 */
interface BulkOperationServiceInterface
{
    public function add(array $data): BulkOperationService;

    /**
     * @throws BulkOperationException
     */
    public function commit(): void;
}
