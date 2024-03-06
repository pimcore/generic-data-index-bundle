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

namespace Pimcore\Bundle\GenericDataIndexBundle\Service\Serializer\DocumentTypeSerializationHandler;

use Exception;
use Pimcore\Bundle\GenericDataIndexBundle\Enum\SearchIndex\FieldCategory\SystemField\Document\EmailSystemField;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Document\SearchResult\DocumentSearchResultItem;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Document\SearchResult\SearchResultItem\Email;
use Pimcore\Model\Document;

/**
 * @internal
 */
final class EmailSerializationHandler extends AbstractSerializationHandler
{
    /**
     * @throws Exception
     */
    public function getAdditionalSystemFields(Document $document): array
    {
        if(!$document instanceof Document\Email) {
            return [];
        }

        $fields = $this->getPageSystemFields($document);
        $fields[EmailSystemField::SUBJECT->value] = $document->getSubject();
        $fields[EmailSystemField::FROM->value] = $document->getFrom();
        $fields[EmailSystemField::TO->value] = $document->getTo();
        $fields[EmailSystemField::REPLY_TO->value] = $document->getReplyTo();
        $fields[EmailSystemField::CC->value] = $document->getCc();
        $fields[EmailSystemField::BCC->value] = $document->getBcc();

        return $fields;
    }

    public function createSearchResultModel(array $indexData): DocumentSearchResultItem
    {
        $document = new Email();
        /** @var Email $model */
        $model = $this->getPageResultModelFields($document, $indexData);
        $model->setFrom(EmailSystemField::FROM->getData($indexData));
        $model->setTo(EmailSystemField::TO->getData($indexData));
        $model->setReplyTo(EmailSystemField::REPLY_TO->getData($indexData));
        $model->setCc(EmailSystemField::CC->getData($indexData));
        $model->setBcc(EmailSystemField::BCC->getData($indexData));
        $model->setSubject(EmailSystemField::SUBJECT->getData($indexData));

        return $model;
    }
}
