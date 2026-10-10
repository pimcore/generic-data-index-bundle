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

namespace Pimcore\Bundle\GenericDataIndexBundle\Tests\Unit\Service\SearchIndex\IndexService;

use Codeception\Test\Unit;
use Pimcore\Bundle\GenericDataIndexBundle\Enum\SearchIndex\FieldCategory;
use Pimcore\Bundle\GenericDataIndexBundle\Enum\SearchIndex\FieldCategory\SystemField;
use Pimcore\Bundle\GenericDataIndexBundle\Event\UpdateIndexDataEventInterface;
use Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\BulkOperationServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\SearchIndexServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\IndexService\ElementTypeAdapter\AbstractElementTypeAdapter;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\IndexService\ElementTypeAdapter\AdapterServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\IndexService\IndexService;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SettingsStoreServiceInterface;
use Pimcore\Model\Element\ElementInterface;
use Psr\Log\NullLogger;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @internal
 */
final class IndexServiceWorkflowChecksumTest extends Unit
{
    public function testChecksumChangesWithAppliedMappingWhenElementHasPlaces(): void
    {
        $before = $this->getChecksum(['wf_a' => ['draft']], 111);
        $after = $this->getChecksum(['wf_a' => ['draft']], 222);

        $this->assertNotSame($before, $after);
    }

    public function testChecksumIgnoresAppliedMappingWithoutPlaces(): void
    {
        $this->assertSame($this->getChecksum(null, 111), $this->getChecksum(null, 222));
    }

    private function getChecksum(?array $places, ?int $appliedMappingChecksum): int
    {
        $systemFields = [SystemField::ID->value => 5];
        if ($places !== null) {
            $systemFields[SystemField::WORKFLOW_PLACES->value] = $places;
        }

        $element = $this->makeEmpty(ElementInterface::class, ['getId' => 5]);
        $event = $this->makeEmpty(UpdateIndexDataEventInterface::class, ['getCustomFields' => []]);
        $normalizer = $this->makeEmpty(NormalizerInterface::class, ['normalize' => [
            FieldCategory::SYSTEM_FIELDS->value => $systemFields,
            FieldCategory::STANDARD_FIELDS->value => [],
        ]]);
        $adapter = $this->makeEmpty(AbstractElementTypeAdapter::class, [
            'getAliasIndexNameByElement' => 'index',
            'getNormalizer' => $normalizer,
            'getUpdateIndexDataEvent' => $event,
        ]);

        $written = [];
        $bulk = $this->makeEmpty(BulkOperationServiceInterface::class, [
            'add' => function (string $index, int $id, array $data) use (&$written): void {
                $written = $data;
            },
        ]);

        $service = new IndexService(
            $this->makeEmpty(AdapterServiceInterface::class, ['getTypeAdapter' => $adapter]),
            $this->makeEmpty(SearchIndexServiceInterface::class, ['getDocument' => []]),
            $bulk,
            $this->makeEmpty(EventDispatcherInterface::class),
            $this->makeEmpty(SettingsStoreServiceInterface::class, [
                'getWorkflowPlacesMappingChecksum' => function (string $indexName) use ($appliedMappingChecksum) {
                    $this->assertSame('index', $indexName);

                    return $appliedMappingChecksum;
                },
            ]),
        );
        $service->setLogger(new NullLogger());
        $service->updateIndexData($element);

        return $written[FieldCategory::SYSTEM_FIELDS->value][SystemField::CHECKSUM->value];
    }
}
