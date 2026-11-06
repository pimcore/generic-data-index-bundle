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

namespace Pimcore\Bundle\GenericDataIndexBundle\Tests\Unit\Message;

use Codeception\Test\Unit;
use Pimcore\Bundle\GenericDataIndexBundle\Message\UpdateLanguageSettingsMessage;

/**
 * @internal
 */
final class UpdateLanguageSettingsMessageTest extends Unit
{
    public function testTheDispatchTimeIsCarried(): void
    {
        $message = new UpdateLanguageSettingsMessage(['en'], ['en', 'de'], 1700000000000);

        $this->assertSame(1700000000000, $message->getDispatchedAt());
        $this->assertSame(['en'], $message->getCurrentLanguages());
        $this->assertSame(['en', 'de'], $message->getValidLanguages());
    }

    /**
     * A message queued by a version without the timestamp is unserialized without the property; reading it must
     * not fail, the handler treats it as a message of unknown age.
     */
    public function testAMessageQueuedByAnEarlierVersionHasNoDispatchTime(): void
    {
        $serialized = serialize(new UpdateLanguageSettingsMessage(['en'], ['en', 'de']));
        $withoutTimestamp = preg_replace('/s:\d+:"\0[^"]*\0dispatchedAt";N;/', '', $serialized, 1, $removed);
        $this->assertSame(1, $removed, 'Precondition: the serialized message carries the timestamp property');
        $legacy = unserialize(preg_replace('/:3:\{/', ':2:{', (string) $withoutTimestamp, 1));

        $this->assertInstanceOf(UpdateLanguageSettingsMessage::class, $legacy);
        $this->assertNull($legacy->getDispatchedAt());
        $this->assertSame(['en', 'de'], $legacy->getValidLanguages());
    }
}
