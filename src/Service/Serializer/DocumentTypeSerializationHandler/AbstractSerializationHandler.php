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

use Pimcore\Bundle\GenericDataIndexBundle\Enum\SearchIndex\FieldCategory\SystemField\Document\PageSystemField;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Document\SearchResult\DocumentSearchResultItem;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Document\SearchResult\SearchResultItem;
use Pimcore\Bundle\GenericDataIndexBundle\Service\DocumentTypeSerializationHandler\HandlerInterface;
use Pimcore\Model\Document;

abstract class AbstractSerializationHandler implements HandlerInterface
{
    public function getAdditionalSystemFields(Document $document): array
    {
        return [];
    }

    protected function getPageResultModelFields(
        DocumentSearchResultItem $document,
        array $indexData
    ): DocumentSearchResultItem {
        if (!$document instanceof SearchResultItem\Page &&
            !$document instanceof SearchResultItem\Snippet &&
            !$document instanceof SearchResultItem\Email
        ) {
            return new DocumentSearchResultItem();
        }

        return $document
            ->setController(PageSystemField::CONTROLLER->getData($indexData))
            ->setTemplate(PageSystemField::TEMPLATE->getData($indexData))
            ->setContentMainDocumentId(PageSystemField::CONTENT_MAIN_DOCUMENT_ID->getData($indexData))
            ->setSupportsContentMain(PageSystemField::SUPPORTS_CONTENT_MAIN->getData($indexData))
            ->setMissingRequiredEditable(PageSystemField::MISSING_REQUIRED_EDITABLE->getData($indexData))
            ->setStaticGeneratorEnabled(PageSystemField::STATIC_GENERATOR_ENABLED->getData($indexData))
            ->setStaticGeneratorLifetime(PageSystemField::STATIC_GENERATOR_LIFETIME->getData($indexData));
    }

    protected function getPageSystemFields(Document $document): array
    {
        if (!$document instanceof Document\PageSnippet
        ) {
            return [];
        }

        return [
            PageSystemField::CONTROLLER->value => $document->getController(),
            PageSystemField::TEMPLATE->value => $document->getTemplate(),
            PageSystemField::CONTENT_MAIN_DOCUMENT_ID->value => $document->getContentMainDocumentId(),
            PageSystemField::SUPPORTS_CONTENT_MAIN->value => $document->supportsContentMain(),
            PageSystemField::MISSING_REQUIRED_EDITABLE->value => $document->getMissingRequiredEditable(),
            PageSystemField::STATIC_GENERATOR_ENABLED->value => $document->getStaticGeneratorEnabled(),
            PageSystemField::STATIC_GENERATOR_LIFETIME->value => $document->getStaticGeneratorLifetime(),
        ];
    }
}
