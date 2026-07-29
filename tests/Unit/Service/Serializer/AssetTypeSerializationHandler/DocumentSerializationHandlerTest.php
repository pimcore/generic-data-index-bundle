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

namespace Pimcore\Bundle\GenericDataIndexBundle\Tests\Unit\Service\Serializer\AssetTypeSerializationHandler;

use Codeception\Test\Unit;
use Exception;
use Pimcore\Bundle\GenericDataIndexBundle\Enum\SearchIndex\FieldCategory\SystemField\Asset\DocumentSystemField;
use Pimcore\Bundle\GenericDataIndexBundle\Service\Serializer\AssetTypeSerializationHandler\DocumentSerializationHandler;
use Pimcore\Model\Asset\Document;
use Psr\Log\NullLogger;

/**
 * Regression test for PEES-1311: Document::getPageCount()/getText()/getImageThumbnail() are
 * all backed by the physical file and can throw when it is missing. Each field must fail
 * independently instead of aborting the whole system-fields array, which would otherwise
 * abort the index queue batch this asset was processed in.
 *
 * @internal
 */
final class DocumentSerializationHandlerTest extends Unit
{
    public function testFileBackedFieldFailuresAreIsolated(): void
    {
        $document = $this->makeEmpty(Document::class, [
            'getId' => 43,
            'getImageThumbnail' => function () {
                throw new Exception('thumbnail generation failed: file not found');
            },
            'getPageCount' => function () {
                throw new Exception('page count extraction failed: file not found');
            },
            'getText' => function () {
                throw new Exception('text extraction failed: file not found');
            },
        ]);

        $handler = new DocumentSerializationHandler();
        $handler->setLogger(new NullLogger());

        $fields = $handler->getAdditionalSystemFields($document);

        $this->assertNull($fields[DocumentSystemField::IMAGE_THUMBNAIL->value]);
        $this->assertNull($fields[DocumentSystemField::PAGE_COUNT->value]);
        $this->assertNull($fields[DocumentSystemField::TEXT->value]);
    }
}
