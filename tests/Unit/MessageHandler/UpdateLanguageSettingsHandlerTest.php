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

namespace Pimcore\Bundle\GenericDataIndexBundle\Tests\Unit\MessageHandler;

use Codeception\Stub\Expected;
use Codeception\Test\Unit;
use Pimcore\Bundle\GenericDataIndexBundle\Exception\ClassDefinitionIndexUpdateFailedException;
use Pimcore\Bundle\GenericDataIndexBundle\Exception\DefaultSearch\ReindexTimeoutException;
use Pimcore\Bundle\GenericDataIndexBundle\Message\UpdateLanguageSettingsMessage;
use Pimcore\Bundle\GenericDataIndexBundle\MessageHandler\UpdateLanguageSettingsHandler;
use Pimcore\Bundle\GenericDataIndexBundle\Service\Messenger\MappingUpdateRetryPolicy;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\IndexQueue\EnqueueServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\LanguageServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\LatestLanguageChangeInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\ReindexServiceInterface;
use RuntimeException;
use Symfony\Component\Messenger\Exception\UnrecoverableMessageHandlingException;

/**
 * @internal
 */
final class UpdateLanguageSettingsHandlerTest extends Unit
{
    public function testUnchangedLanguagesUpdateNoIndex(): void
    {
        $handler = $this->createHandler(
            $this->makeEmpty(ReindexServiceInterface::class, ['reindexAll' => Expected::never()]),
            $this->makeEmpty(EnqueueServiceInterface::class, ['dispatchQueueMessages' => Expected::never()]),
            $this->makeEmpty(LanguageServiceInterface::class, [
                'getNewLanguages' => ['en', 'de'],
                'setValidLanguages' => Expected::never(),
            ])
        );

        $handler(new UpdateLanguageSettingsMessage(['en', 'de'], ['en', 'de']));
    }

    public function testChangedLanguagesCopyEveryIndexAndQueueTheElements(): void
    {
        $handler = $this->createHandler(
            $this->createReindexService(),
            $this->makeEmpty(EnqueueServiceInterface::class, [
                'dispatchQueueMessages' => Expected::once(function (bool $force) {
                    $this->assertTrue($force);
                }),
            ]),
            $this->makeEmpty(LanguageServiceInterface::class, [
                'getNewLanguages' => ['en', 'de'],
                'setValidLanguages' => Expected::once(function (array $languages) {
                    $this->assertSame(['en', 'de'], $languages);
                }),
            ])
        );

        $handler(new UpdateLanguageSettingsMessage(['en'], ['en', 'de']));
    }

    /**
     * The failure used to be logged only ("Updating languages failed"): the message counted as handled, nothing
     * retried it, and the indices after the failing one stayed as they were.
     */
    public function testAFailedUpdateIsReportedToMessengerInsteadOfBeingLoggedOnly(): void
    {
        $failure = new RuntimeException('cURL error 7: connection refused');
        $handler = $this->createHandler(
            $this->makeEmpty(ReindexServiceInterface::class, [
                'reindexAll' => Expected::once(static function () use ($failure): never {
                    throw $failure;
                }),
            ]),
            $this->makeEmpty(EnqueueServiceInterface::class, ['dispatchQueueMessages' => Expected::never()]),
            $this->makeEmpty(LanguageServiceInterface::class, ['getNewLanguages' => ['en', 'de']])
        );

        try {
            $handler(new UpdateLanguageSettingsMessage(['en'], ['en', 'de']));
        } catch (RuntimeException $e) {
            $this->assertSame($failure, $e, 'Messenger retries the message and moves it to the failed transport');

            return;
        }

        $this->fail('The failure must reach Messenger');
    }

    public function testACopyThatRanOutOfItsBudgetIsNotRetried(): void
    {
        $handler = $this->createHandler(
            $this->makeEmpty(ReindexServiceInterface::class, [
                'reindexAll' => Expected::once(static function (): never {
                    throw new ClassDefinitionIndexUpdateFailedException(
                        message: 'did not complete within 3600 seconds',
                        previous: new ReindexTimeoutException('Reindex task 1 did not complete within 3600 seconds')
                    );
                }),
            ]),
            $this->makeEmpty(EnqueueServiceInterface::class, ['dispatchQueueMessages' => Expected::never()]),
            $this->makeEmpty(LanguageServiceInterface::class, ['getNewLanguages' => ['en', 'de']])
        );

        $this->expectException(UnrecoverableMessageHandlingException::class);

        $handler(new UpdateLanguageSettingsMessage(['en'], ['en', 'de']));
    }

    /**
     * A failed message that is retried after a later settings save would reinstall the language list of its own
     * save (for example "de" added, then "fr" chosen instead) and copy every index with it.
     */
    public function testAMessageOfASaveThatALaterSaveReplacedIsSkipped(): void
    {
        $handler = $this->createHandler(
            $this->makeEmpty(ReindexServiceInterface::class, ['reindexAll' => Expected::never()]),
            $this->makeEmpty(EnqueueServiceInterface::class, ['dispatchQueueMessages' => Expected::never()]),
            $this->makeEmpty(LanguageServiceInterface::class, ['setValidLanguages' => Expected::never()]),
            $this->makeEmpty(LatestLanguageChangeInterface::class, [
                'isLatest' => Expected::once(function (array $languages): bool {
                    $this->assertSame(['en', 'de'], $languages);

                    return false;
                }),
            ])
        );

        $handler(new UpdateLanguageSettingsMessage(['en'], ['en', 'de']));
    }

    private function createReindexService(): ReindexServiceInterface
    {
        $reindexService = null;
        $reindexService = $this->makeEmpty(ReindexServiceInterface::class, [
            'reindexAll' => Expected::once(static function () use (&$reindexService) {
                return $reindexService;
            }),
        ]);

        return $reindexService;
    }

    private function createHandler(
        ReindexServiceInterface $reindexService,
        EnqueueServiceInterface $enqueueService,
        LanguageServiceInterface $languageService,
        ?LatestLanguageChangeInterface $latestLanguageChange = null
    ): UpdateLanguageSettingsHandler {
        return new UpdateLanguageSettingsHandler(
            $enqueueService,
            $languageService,
            $reindexService,
            new MappingUpdateRetryPolicy(),
            $latestLanguageChange ?? $this->makeEmpty(LatestLanguageChangeInterface::class, ['isLatest' => true])
        );
    }
}
