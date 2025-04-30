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
        if (!$document instanceof Document\Email) {
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
