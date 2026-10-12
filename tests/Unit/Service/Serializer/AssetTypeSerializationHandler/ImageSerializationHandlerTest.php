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
use Pimcore\Bundle\GenericDataIndexBundle\Enum\SearchIndex\FieldCategory\SystemField\Asset\ImageSystemField;
use Pimcore\Bundle\GenericDataIndexBundle\Service\Serializer\AssetTypeSerializationHandler\ImageSerializationHandler;
use Pimcore\Model\Asset\Image;
use Psr\Log\NullLogger;

/**
 * Regression test for PEES-1311: Image::getThumbnail()/getWidth()/getHeight() are all
 * backed by the physical file and can throw when it is missing. Each field must fail
 * independently instead of aborting the whole system-fields array, which would otherwise
 * abort the index queue batch this asset was processed in.
 *
 * @internal
 */
final class ImageSerializationHandlerTest extends Unit
{
    public function testFileBackedFieldFailuresAreIsolated(): void
    {
        $image = $this->makeEmpty(Image::class, [
            'getId' => 44,
            'getThumbnail' => function () {
                throw new Exception('thumbnail generation failed: file not found');
            },
            'getWidth' => function () {
                throw new Exception('width extraction failed: file not found');
            },
            'getHeight' => function () {
                throw new Exception('height extraction failed: file not found');
            },
        ]);

        $handler = new ImageSerializationHandler();
        $handler->setLogger(new NullLogger());

        $fields = $handler->getAdditionalSystemFields($image);

        $this->assertNull($fields[ImageSystemField::THUMBNAIL->value]);
        $this->assertNull($fields[ImageSystemField::WIDTH->value]);
        $this->assertNull($fields[ImageSystemField::HEIGHT->value]);
    }
}
