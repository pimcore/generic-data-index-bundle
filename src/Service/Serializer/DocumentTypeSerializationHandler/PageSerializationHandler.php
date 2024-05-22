<?php
declare(strict_types=1);

/**
 * Pimcore
 *
 * This source file is available under two different licenses:
 * - GNU General Public License version 3 (GPLv3)
 * - Pimcore Commercial License (PCL)
 * Full copyright and license information is available in
 * LICENSE.md which is distributed with this source code.
 *
 *  @copyright  Copyright (c) Pimcore GmbH (http://www.pimcore.org)
 *  @license    http://www.pimcore.org/license     GPLv3 and PCL
 */

namespace Pimcore\Bundle\GenericDataIndexBundle\Service\Serializer\DocumentTypeSerializationHandler;

use Exception;
use Pimcore\Bundle\GenericDataIndexBundle\Enum\SearchIndex\FieldCategory\SystemField\Document\PageSystemField;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Document\SearchResult\DocumentSearchResultItem;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Document\SearchResult\SearchResultItem\Page;
use Pimcore\Model\Document;

/**
 * @internal
 */
final class PageSerializationHandler extends AbstractSerializationHandler
{
    /**
     * @throws Exception
     */
    public function getAdditionalSystemFields(Document $document): array
    {
        if (!$document instanceof Document\Page) {
            return [];
        }

        $fields = $this->getPageSystemFields($document);
        $fields[PageSystemField::TITLE->value] = $document->getTitle();
        $fields[PageSystemField::DESCRIPTION->value] = $document->getDescription();
        $fields[PageSystemField::PRETTY_URL->value] = $document->getPrettyUrl();

        return $fields;
    }

    public function createSearchResultModel(array $indexData): DocumentSearchResultItem
    {
        $document = new Page();
        /** @var Page $model */
        $model = $this->getPageResultModelFields($document, $indexData);
        $model->setTitle(PageSystemField::TITLE->getData($indexData));
        $model->setDescription(PageSystemField::DESCRIPTION->getData($indexData));
        $model->setPrettyUrl(PageSystemField::PRETTY_URL->getData($indexData));

        return $model;
    }
}
