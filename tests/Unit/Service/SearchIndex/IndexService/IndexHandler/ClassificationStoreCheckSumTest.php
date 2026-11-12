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

namespace Pimcore\Bundle\GenericDataIndexBundle\Tests\Unit\Service\SearchIndex\IndexService\IndexHandler;

use Codeception\Test\Unit;
use Doctrine\DBAL\Connection;
use Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\DataObject\AdapterInterface;
use Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\DataObject\FieldDefinitionServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\DefaultSearch\DataObject\FieldDefinitionAdapter\ClassificationStoreAdapter;
use Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\IndexMappingServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\SearchIndexServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\ClassificationStore\EngineCapabilityServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\ClassificationStore\KeyLocaleService;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\IndexService\IndexHandler\AbstractIndexHandler;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\LanguageServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\Mapping\IndexCopyServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\SearchIndexConfigServiceInterface;
use Pimcore\Bundle\StaticResolverBundle\Lib\Cache\RuntimeCacheResolverInterface;
use Pimcore\Bundle\StaticResolverBundle\Models\DataObject\ClassificationStore\ServiceResolverInterface;
use Pimcore\Model\DataObject\ClassDefinition\Data\Classificationstore;
use Pimcore\Model\DataObject\ClassDefinition\Data\Input;
use Pimcore\Model\DataObject\ClassDefinition\Data\MultiValue;
use Pimcore\Model\DataObject\Classificationstore\DefinitionCache;
use Pimcore\Model\DataObject\Classificationstore\KeyConfig;
use Pimcore\Model\DataObject\Classificationstore\KeyGroupRelation;
use Pimcore\Model\DataObject\Classificationstore\LocalizationResolver;
use ReflectionMethod;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * Nothing in this bundle subscribes to a classification store key or relation save, so a flipped
 * localization flag is picked up by the existing rebuild paths - class save,
 * `deployment:reindex`, `update:index` - and only if it changes the class mapping checksum.
 *
 * @internal
 */
final class ClassificationStoreCheckSumTest extends Unit
{
    private const KEY_ID = 42;

    private const GROUP_ID = 9;

    private const KEY_NAME = 'Size';

    protected function _after(): void
    {
        DefinitionCache::clear(null);
    }

    public function testFlippingTheKeyLocalizationFlagChangesTheClassMappingCheckSum(): void
    {
        $indexHandler = $this->getIndexHandler();

        $notLocalized = $this->getGroupMapping(false);
        $localized = $this->getGroupMapping(true);

        $this->assertNotSame(
            $indexHandler->getClassMappingCheckSum($notLocalized),
            $indexHandler->getClassMappingCheckSum($localized)
        );
    }

    /**
     * The checksum ignores the key order, so the language nodes have to differ in content, not only in
     * placement, for the rebuild paths to pick the class up.
     */
    public function testTheSameFlagProducesTheSameClassMappingCheckSum(): void
    {
        $indexHandler = $this->getIndexHandler();

        $this->assertSame(
            $indexHandler->getClassMappingCheckSum($this->getGroupMapping(true)),
            $indexHandler->getClassMappingCheckSum($this->getGroupMapping(true))
        );
    }

    /**
     * Declaring a variant adds a sibling property, so the checksum changes and the rebuild paths pick
     * the class up; removing it again restores the checksum, and an unchanged declaration keeps it.
     */
    public function testDeclaringOrRemovingAVariantChangesTheClassMappingCheckSum(): void
    {
        $indexHandler = $this->getIndexHandler();

        $none = $indexHandler->getClassMappingCheckSum($this->getGroupMapping(false, $this->createMultiValue([])));
        $declared = $indexHandler->getClassMappingCheckSum($this->getGroupMapping(false, $this->createMultiValue(['metric'])));

        $this->assertNotSame($none, $declared);
        $this->assertSame(
            $declared,
            $indexHandler->getClassMappingCheckSum($this->getGroupMapping(false, $this->createMultiValue(['metric'])))
        );
        $this->assertSame(
            $none,
            $indexHandler->getClassMappingCheckSum($this->getGroupMapping(false, $this->createMultiValue([])))
        );
    }

    /**
     * @param string[] $variants
     */
    private function createMultiValue(array $variants): MultiValue
    {
        $definition = new MultiValue();
        $definition->setVariants($variants);
        $definition->setDelegate(new Input());

        return $definition;
    }

    private function getGroupMapping(bool $keyLocalized, ?MultiValue $definition = null): array
    {
        $keyConfig = (new KeyConfig())->setId(self::KEY_ID);
        $keyConfig->setLocalized($keyLocalized);
        DefinitionCache::put($keyConfig);

        $relation = new KeyGroupRelation();
        $relation->setKeyId(self::KEY_ID);
        $relation->setGroupId(self::GROUP_ID);
        $relation->setName(self::KEY_NAME);
        $relation->setDefinition((string) json_encode(['fieldtype' => 'input', 'name' => self::KEY_NAME]));

        $adapter = $this->getClassificationStoreAdapter($definition);
        $languages = (new ReflectionMethod(ClassificationStoreAdapter::class, 'getValidLanguages'))
            ->invoke($adapter);

        return (new ReflectionMethod(ClassificationStoreAdapter::class, 'getMappingForGroupConfig'))
            ->invoke($adapter, [$relation], $languages);
    }

    private function getClassificationStoreAdapter(?MultiValue $definition = null): ClassificationStoreAdapter
    {
        $adapter = new ClassificationStoreAdapter(
            $this->makeEmpty(SearchIndexConfigServiceInterface::class),
            $this->makeEmpty(FieldDefinitionServiceInterface::class, [
                'getFieldDefinitionAdapter' => $this->makeEmpty(AdapterInterface::class, [
                    'getIndexMapping' => ['type' => 'keyword'],
                ]),
            ])
        );
        $adapter->setClassificationService($this->makeEmpty(ServiceResolverInterface::class, [
            'getFieldDefinitionFromKeyConfig' => $definition ?? new Input(),
        ]));
        $adapter->setLanguageService($this->makeEmpty(LanguageServiceInterface::class, [
            'getValidLanguages' => ['de', 'en'],
        ]));
        $adapter->setKeyLocaleService(
            new KeyLocaleService(new LocalizationResolver($this->makeEmpty(Connection::class)))
        );
        $adapter->setEngineCapabilityService(
            $this->makeEmpty(EngineCapabilityServiceInterface::class, ['supports' => true])
        );
        // nothing memoized, so each build resolves the index flag of the key from its definition again
        $adapter->setRuntimeCacheResolver($this->makeEmpty(RuntimeCacheResolverInterface::class, [
            'isRegistered' => false,
        ]));

        $fieldDefinition = new Classificationstore();
        $fieldDefinition->setLocalized(false);
        $adapter->setFieldDefinition($fieldDefinition);

        return $adapter;
    }

    private function getIndexHandler(): AbstractIndexHandler
    {
        $search = $this->makeEmpty(SearchIndexServiceInterface::class);
        $config = $this->makeEmpty(SearchIndexConfigServiceInterface::class);
        $events = $this->makeEmpty(EventDispatcherInterface::class);
        $mapping = $this->makeEmpty(IndexMappingServiceInterface::class);
        $copy = $this->makeEmpty(IndexCopyServiceInterface::class);

        return new class($search, $config, $events, $mapping, $copy) extends AbstractIndexHandler {
            protected function extractMappingProperties(mixed $context = null): array
            {
                return [];
            }

            protected function getAliasIndexName(mixed $context = null): string
            {
                return 'test';
            }
        };
    }
}
