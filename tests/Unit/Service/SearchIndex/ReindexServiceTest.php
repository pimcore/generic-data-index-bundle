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
use Pimcore\Bundle\GenericDataIndexBundle\Enum\SearchIndex\DeploymentReindexScope;
use Pimcore\Bundle\GenericDataIndexBundle\Enum\SearchIndex\ReindexResult;
use Pimcore\Bundle\GenericDataIndexBundle\Model\SearchIndex\Deployment\IndexStateTarget;
use Pimcore\Bundle\GenericDataIndexBundle\Model\SearchIndex\Mapping\IndexCopyResult;
use Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\IndexMappingServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\SearchIndexServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\Asset\MetadataProviderServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\ClassDefinition\ClassDefinitionReindexServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\Deployment\IndexStateServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\GlobalIndexAliasServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\IndexQueue\EnqueueServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\IndexService\ElementTypeAdapter\AssetTypeAdapter;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\IndexService\ElementTypeAdapter\DocumentTypeAdapter;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\IndexService\IndexHandler\AssetIndexHandler;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\IndexService\IndexHandler\DocumentIndexHandler;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\Mapping\IndexCopyServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\ReindexService;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\SearchIndexConfigServiceInterface;
use Psr\Log\NullLogger;
use ReflectionClass;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * generic-data-index:reindex copies the asset and document indices without a rebuild, unless the copy left out
 * fields: then the elements are queued, and the stored index state records the rebuild.
 *
 * @internal
 */
final class ReindexServiceTest extends Unit
{
    /**
     * @dataProvider scopeProvider
     */
    public function testAStrippingCopyQueuesTheElementsWithoutAnEnqueueRequest(
        DeploymentReindexScope $scope,
        string $enqueueMethod
    ): void {
        $refreshed = [];
        $service = $this->createService(
            new IndexCopyResult(ReindexResult::SUCCESS, ['standard_fields.title']),
            [$enqueueMethod => Expected::once(fn () => $this->makeEmpty(EnqueueServiceInterface::class))],
            $refreshed
        );

        $result = $scope === DeploymentReindexScope::ASSETS
            ? $service->reindexAssets(false)
            : $service->reindexDocuments(false);

        $this->assertInstanceOf(ReindexService::class, $result);
        $this->assertSame([[$scope, true, true, true]], $refreshed);
    }

    /**
     * @dataProvider scopeProvider
     */
    public function testAPlainCopyQueuesNothingAndLeavesTheMappingStateOpen(
        DeploymentReindexScope $scope,
        string $enqueueMethod
    ): void {
        $refreshed = [];
        $service = $this->createService(
            new IndexCopyResult(ReindexResult::SUCCESS),
            [$enqueueMethod => Expected::never()],
            $refreshed
        );

        $scope === DeploymentReindexScope::ASSETS ? $service->reindexAssets(false) : $service->reindexDocuments(false);

        $this->assertSame([[$scope, false, true, false]], $refreshed);
    }

    public static function scopeProvider(): array
    {
        return [
            'assets' => [DeploymentReindexScope::ASSETS, 'enqueueAssets'],
            'documents' => [DeploymentReindexScope::DOCUMENTS, 'enqueueDocuments'],
        ];
    }

    /**
     * @param array<string, mixed> $enqueueExpectations
     * @param array<int, array{DeploymentReindexScope, bool, bool, bool}> $refreshed
     */
    private function createService(
        IndexCopyResult $copyResult,
        array $enqueueExpectations,
        array &$refreshed
    ): ReindexService {
        $search = $this->makeEmpty(SearchIndexServiceInterface::class, [
            'existsAlias' => true,
            'getCurrentIndexVersion' => 'odd',
        ]);
        $copy = $this->makeEmpty(IndexCopyServiceInterface::class, ['copy' => $copyResult]);
        $config = $this->makeEmpty(SearchIndexConfigServiceInterface::class, [
            'getSystemFieldsSettings' => [],
            'getSearchAnalyzerAttributes' => [],
            'getIndexName' => static fn (string $name): string => 'test_' . $name,
        ]);
        $events = $this->makeEmpty(EventDispatcherInterface::class);
        $mapping = $this->makeEmpty(IndexMappingServiceInterface::class, ['getMappingForTextKeyword' => []]);
        $aliases = $this->makeEmpty(GlobalIndexAliasServiceInterface::class);

        // Applying the mapping only needs the index names from the type adapters, not their normalizers.
        $assetAdapter = (new ReflectionClass(AssetTypeAdapter::class))->newInstanceWithoutConstructor();
        $assetAdapter->setSearchIndexConfigService($config);
        $documentAdapter = (new ReflectionClass(DocumentTypeAdapter::class))->newInstanceWithoutConstructor();
        $documentAdapter->setSearchIndexConfigService($config);

        $assetHandler = new AssetIndexHandler(
            $search,
            $config,
            $events,
            $mapping,
            $assetAdapter,
            $this->makeEmpty(MetadataProviderServiceInterface::class, ['getMappingProperties' => []]),
            $aliases,
            $copy
        );
        $documentHandler = new DocumentIndexHandler(
            $search,
            $config,
            $events,
            $mapping,
            $documentAdapter,
            $aliases,
            $copy
        );
        $assetHandler->setLogger(new NullLogger());
        $documentHandler->setLogger(new NullLogger());

        $stateService = $this->makeEmpty(IndexStateServiceInterface::class, [
            'refresh' => static function (
                IndexStateTarget $target,
                bool $mapping,
                bool $settings,
                bool $dataVersion
            ) use (&$refreshed): void {
                $refreshed[] = [$target->getScope(), $mapping, $settings, $dataVersion];
            },
        ]);

        return new ReindexService(
            $assetHandler,
            $documentHandler,
            $this->makeEmpty(EnqueueServiceInterface::class, $enqueueExpectations),
            $this->makeEmpty(ClassDefinitionReindexServiceInterface::class),
            $stateService
        );
    }
}
