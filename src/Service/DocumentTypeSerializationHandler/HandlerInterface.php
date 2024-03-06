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

namespace Pimcore\Bundle\GenericDataIndexBundle\Service\DocumentTypeSerializationHandler;

use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Document\SearchResult\DocumentSearchResultItem;
use Pimcore\Model\Document;

interface HandlerInterface
{
    public function getAdditionalSystemFields(Document $document): array;

    /**
     * Type independent attributes will be added in DocumentSearchResultDenormalizer
     */
    public function createSearchResultModel(array $indexData): DocumentSearchResultItem;
}
