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
use Error;
use Exception;
use Pimcore\Bundle\GenericDataIndexBundle\Enum\SearchIndex\FieldCategory\SystemField\Asset\VideoSystemField;
use Pimcore\Bundle\GenericDataIndexBundle\Service\Serializer\AssetTypeSerializationHandler\VideoSerializationHandler;
use Pimcore\Model\Asset\Video;
use Psr\Log\NullLogger;

/**
 * Regression test for PEES-1311: Video::getDuration()/getWidth()/getHeight() and
 * getImageThumbnail() are all backed by the physical file (e.g. via a video conversion
 * backend) and can throw when it is missing. Each field must fail independently instead
 * of aborting the whole system-fields array, which would otherwise abort the index queue
 * batch this asset was processed in.
 *
 * The thumbnail accessor throws an Error (not an Exception) specifically to prove the
 * getImageThumbnail() catch is widened to Throwable - the original PEES-1311 fix widened
 * it from Exception, and a narrower catch would let this case bubble up uncaught while
 * still passing an Exception-only assertion.
 *
 * @internal
 */
final class VideoSerializationHandlerTest extends Unit
{
    public function testFileBackedFieldFailuresAreIsolated(): void
    {
        $video = $this->makeEmpty(Video::class, [
            'getId' => 42,
            'getImageThumbnail' => function () {
                throw new Error('thumbnail generation failed: file not found');
            },
            'getDuration' => function () {
                throw new Exception('duration extraction failed: file not found');
            },
            'getWidth' => function () {
                throw new Exception('width extraction failed: file not found');
            },
            'getHeight' => function () {
                throw new Exception('height extraction failed: file not found');
            },
        ]);

        $handler = new VideoSerializationHandler();
        $handler->setLogger(new NullLogger());

        $fields = $handler->getAdditionalSystemFields($video);

        $this->assertNull($fields[VideoSystemField::IMAGE_THUMBNAIL->value]);
        $this->assertNull($fields[VideoSystemField::DURATION->value]);
        $this->assertNull($fields[VideoSystemField::WIDTH->value]);
        $this->assertNull($fields[VideoSystemField::HEIGHT->value]);
    }
}
