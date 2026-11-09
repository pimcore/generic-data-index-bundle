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

namespace Pimcore\Bundle\GenericDataIndexBundle\Tests\Unit\Service\SearchIndex;

use Codeception\Stub\Expected;
use Codeception\Test\Unit;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\LatestLanguageChange;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SettingsStoreServiceInterface;

/**
 * @internal
 */
final class LatestLanguageChangeTest extends Unit
{
    public function testTheLatestSaveIsRecordedInTheSettingsStore(): void
    {
        $store = $this->makeEmpty(SettingsStoreServiceInterface::class, [
            'storeIndexState' => Expected::once(function (string $key, string $value) {
                $this->assertSame('language_change_requested', $key);
                $this->assertSame('["en","de"]', $value);
            }),
        ]);

        (new LatestLanguageChange($store))->record(['en', 'de']);
    }

    public function testTheLanguagesOfTheLatestSaveAreTheLatest(): void
    {
        $store = $this->makeEmpty(SettingsStoreServiceInterface::class, ['getIndexState' => '["en","de"]']);

        $this->assertTrue((new LatestLanguageChange($store))->isLatest(['en', 'de']));
        $this->assertFalse((new LatestLanguageChange($store))->isLatest(['en', 'fr']), 'A later save chose fr');
        $this->assertFalse((new LatestLanguageChange($store))->isLatest(['en']), 'A later save reverted to en');
        $this->assertFalse((new LatestLanguageChange($store))->isLatest(['de', 'en']), 'The order is a change too');
    }

    public function testEverythingIsLatestBeforeTheFirstRecordedSave(): void
    {
        $store = $this->makeEmpty(SettingsStoreServiceInterface::class, ['getIndexState' => null]);

        $this->assertTrue((new LatestLanguageChange($store))->isLatest(['en', 'de']));
    }
}
