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

namespace Functional\SearchIndex;

use Codeception\Test\Unit;
use Pimcore\Bundle\GenericDataIndexBundle\Enum\SearchIndex\FieldCategory;
use Pimcore\Bundle\GenericDataIndexBundle\Model\SearchIndex\Mapping\MappingUpdateResult;
use Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\DefaultSearch\DefaultSearchService;
use Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\SearchIndexServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\ClassDefinition\ClassDefinitionReindexServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\IndexService\ElementTypeAdapter\DataObjectTypeAdapter;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\IndexUpdateServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Tests\IndexTester;
use Pimcore\Cache\RuntimeCache;
use Pimcore\Db;
use Pimcore\Model\DataObject\ClassDefinition;
use Pimcore\Model\DataObject\ClassDefinition\Data;
use Pimcore\Model\DataObject\ClassDefinition\Data\Fieldcollections;
use Pimcore\Model\DataObject\ClassDefinition\Data\Input;
use Pimcore\Model\DataObject\ClassDefinition\Data\Localizedfields;
use Pimcore\Model\DataObject\ClassDefinition\Data\Numeric;
use Pimcore\Model\DataObject\ClassDefinition\Data\Objectbricks;
use Pimcore\Model\DataObject\ClassDefinition\Layout\Panel;
use Pimcore\Model\DataObject\Concrete;
use Pimcore\Model\DataObject\Fieldcollection;
use Pimcore\Model\DataObject\Fieldcollection\Data\AbstractData as FieldcollectionItem;
use Pimcore\Model\DataObject\Fieldcollection\Definition as FieldcollectionDefinition;
use Pimcore\Model\DataObject\Objectbrick\Data\AbstractData as ObjectbrickItem;
use Pimcore\Model\DataObject\Objectbrick\Definition as ObjectbrickDefinition;
use Pimcore\Tests\Support\Util\TestHelper;

/**
 * A field type change of a class, applied to its index with real objects: the index is copied
 * without the changed field instead of being recreated empty, and the rebuild from the database
 * fills the field again with the new type.
 *
 * Two constraints of the functional environment shape the setup:
 * - MySQL rejects converting a column with text values into a numeric column (strict mode), so the
 *   database values are cleared before the type change while the index keeps the text values. That
 *   is the state the copy has to handle: documents written under the old mapping.
 * - The PHP classes generated for the old definition stay loaded in this process, so their typed
 *   getters may hand a number over as a numeric string. The index maps the field as float either way.
 *
 * @internal
 */
final class FieldTypeChangeTest extends Unit
{
    private const OBJECT_NAMESPACE = 'Pimcore\\Model\\DataObject\\';

    private const FIELDCOLLECTION_NAMESPACE = 'Pimcore\\Model\\DataObject\\Fieldcollection\\Data\\';

    private const OBJECTBRICK_NAMESPACE = 'Pimcore\\Model\\DataObject\\Objectbrick\\Data\\';

    private const DEPLOYMENT_COMMAND = 'generic-data-index:deployment:reindex';

    private const FIELD = 'f_text';

    private const KEEP_FIELD = 'f_keep';

    private const NUMBER_FIELD = 'f_number';

    private const LOCALIZED_FIELD = 'l_text';

    private const LOCALIZED_KEEP_FIELD = 'l_keep';

    private const LANGUAGES = ['en', 'de'];

    private const COLLECTION_FIELD = 'items';

    private const COLLECTION_KEY = 'GdiFtcItem';

    private const COLLECTION_ITEM_FIELD = 'fc_text';

    private const COLLECTION_KEEP_FIELD = 'fc_keep';

    private const BRICKS_FIELD = 'bricks';

    private const BRICK_KEY = 'GdiFtcBrick';

    private const BRICK_FIELD = 'b_text';

    private const BRICK_KEEP_FIELD = 'b_keep';

    private const SEEDS = [1, 2, 3];

    protected IndexTester $tester;

    private ?ClassDefinition $classDefinition = null;

    private ?FieldcollectionDefinition $fieldcollectionDefinition = null;

    private ?ObjectbrickDefinition $objectbrickDefinition = null;

    private bool $indexStateTouched = false;

    protected function _before(): void
    {
        $this->tester->clearQueue();
        $this->tester->enableSynchronousProcessing();
    }

    protected function _after(): void
    {
        $this->tester->enableSynchronousProcessing();
        $this->deleteObjectsWithoutLoading();
        TestHelper::cleanUp();

        $this->objectbrickDefinition?->delete();
        $this->objectbrickDefinition = null;

        if ($this->classDefinition !== null) {
            $alias = $this->getAlias($this->classDefinition);
            $this->classDefinition->delete();
            $this->classDefinition = null;
            $this->deleteIndices($alias);
        }

        $this->fieldcollectionDefinition?->delete();
        $this->fieldcollectionDefinition = null;

        if ($this->indexStateTouched) {
            $this->deleteIndexState();
            $this->indexStateTouched = false;
        }

        $this->tester->clearQueue();
        $this->tester->disableSynchronousProcessing();
    }

    /**
     * T1: input -> numeric on a top-level field.
     */
    public function testTopLevelInputToNumericIsCopiedWithoutTheFieldAndRebuilt(): void
    {
        $class = $this->createClass('GdiFtcTopLevel', [$this->input(self::FIELD), $this->input(self::KEEP_FIELD)]);
        $objects = $this->createObjects($class, static function (Concrete $object, int $seed): void {
            $object->set(self::FIELD, 'abc-' . $seed);
            $object->set(self::KEEP_FIELD, 'keep-' . $seed);
        });
        foreach ($objects as $seed => $object) {
            $this->assertSame('abc-' . $seed, $this->getStandardFields($class, $object)[self::FIELD]);
        }
        $liveIndex = $this->tester->getIndexName($class->getName(), true);

        $this->tester->disableSynchronousProcessing();
        $this->updateObjects($objects, static function (Concrete $object): void {
            $object->set(self::FIELD, null);
        });
        $this->changeClassFields($class, [$this->numeric(self::FIELD), $this->input(self::KEEP_FIELD)]);
        $this->updateObjects($objects, static function (Concrete $object, int $seed): void {
            $object->set(self::FIELD, (string) (40 + $seed));
        });
        $this->tester->clearQueue();

        $result = $this->applyClassMapping($class);

        $this->assertSame([$this->standardFieldPath(self::FIELD)], $result->getStrippedPaths());
        $this->assertFalse($result->isRecreated());
        $this->assertCopiedIntoOtherIndex($class, $liveIndex);
        $this->assertSame('float', $this->getStandardFieldMapping($class)[self::FIELD]['type'] ?? null);
        foreach ($objects as $seed => $object) {
            $fields = $this->getStandardFields($class, $object);
            $this->assertArrayNotHasKey(self::FIELD, $fields, 'A3: the changed field is left out of the copy');
            $this->assertSame('keep-' . $seed, $fields[self::KEEP_FIELD]);
        }
        $this->assertSame(
            count($objects),
            $this->tester->countQueuedDataObjects($this->getIds($objects)),
            'Stripped fields queue the rebuild even though the caller did not ask for it'
        );

        $this->tester->processIndexQueue();

        foreach ($objects as $seed => $object) {
            $this->assertNumericValue(40 + $seed, $this->getStandardFields($class, $object)[self::FIELD] ?? null);
        }
    }

    /**
     * T3: numeric -> input keeps the values in the copy and needs no rebuild.
     */
    public function testNumericToInputKeepsTheValues(): void
    {
        $class = $this->createClass('GdiFtcNumericToInput', [$this->numeric(self::NUMBER_FIELD)]);
        $objects = $this->createObjects($class, static function (Concrete $object, int $seed): void {
            $object->set(self::NUMBER_FIELD, 12.5 + $seed);
        });
        $indexedValues = [];
        foreach ($objects as $seed => $object) {
            $indexedValues[$seed] = $this->getStandardFields($class, $object)[self::NUMBER_FIELD];
        }
        $liveIndex = $this->tester->getIndexName($class->getName(), true);

        $this->tester->disableSynchronousProcessing();
        $this->changeClassFields($class, [$this->input(self::NUMBER_FIELD)]);
        $this->tester->clearQueue();

        $result = $this->applyClassMapping($class);

        $this->assertSame([], $result->getStrippedPaths());
        $this->assertSame([$this->standardFieldPath(self::NUMBER_FIELD)], $result->getKeptPaths());
        $this->assertFalse($result->requiresRebuild());
        $this->assertCopiedIntoOtherIndex($class, $liveIndex);
        foreach ($objects as $seed => $object) {
            $this->assertSame(
                $indexedValues[$seed],
                $this->getStandardFields($class, $object)[self::NUMBER_FIELD] ?? null,
                'The value written under the old mapping is copied unchanged'
            );
        }
        $this->assertSame(0, $this->tester->countQueuedDataObjects($this->getIds($objects)));
    }

    /**
     * T21: input -> numeric on a localized field; every language is left out of the copy.
     */
    public function testLocalizedInputToNumericIsCopiedWithoutTheFieldAndRebuilt(): void
    {
        $class = $this->createClass('GdiFtcLocalized', [
            $this->localizedFields([$this->input(self::LOCALIZED_FIELD), $this->input(self::LOCALIZED_KEEP_FIELD)]),
        ]);
        $objects = $this->createObjects($class, static function (Concrete $object, int $seed): void {
            foreach (self::LANGUAGES as $language) {
                $object->set(self::LOCALIZED_FIELD, sprintf('abc-%s-%d', $language, $seed), $language);
                $object->set(self::LOCALIZED_KEEP_FIELD, sprintf('keep-%s-%d', $language, $seed), $language);
            }
        });
        foreach ($objects as $seed => $object) {
            $this->assertSame(
                'abc-en-' . $seed,
                $this->getStandardFields($class, $object)[self::LOCALIZED_FIELD]['en'] ?? null
            );
        }
        $liveIndex = $this->tester->getIndexName($class->getName(), true);

        $this->tester->disableSynchronousProcessing();
        $this->updateObjects($objects, static function (Concrete $object): void {
            foreach (self::LANGUAGES as $language) {
                $object->set(self::LOCALIZED_FIELD, null, $language);
            }
        });
        $this->changeClassFields($class, [
            $this->localizedFields([$this->numeric(self::LOCALIZED_FIELD), $this->input(self::LOCALIZED_KEEP_FIELD)]),
        ]);
        $this->updateObjects($objects, static function (Concrete $object, int $seed): void {
            foreach (self::LANGUAGES as $language) {
                $object->set(self::LOCALIZED_FIELD, (string) (40 + $seed), $language);
            }
        });
        $this->tester->clearQueue();

        $result = $this->applyClassMapping($class);

        $this->assertEqualsCanonicalizing(
            array_map(
                fn (string $language): string => $this->standardFieldPath(self::LOCALIZED_FIELD, $language),
                self::LANGUAGES
            ),
            $result->getStrippedPaths()
        );
        $this->assertCopiedIntoOtherIndex($class, $liveIndex);
        foreach ($objects as $seed => $object) {
            $fields = $this->getStandardFields($class, $object);
            foreach (self::LANGUAGES as $language) {
                $this->assertArrayNotHasKey($language, $fields[self::LOCALIZED_FIELD] ?? []);
                $this->assertSame(
                    sprintf('keep-%s-%d', $language, $seed),
                    $fields[self::LOCALIZED_KEEP_FIELD][$language] ?? null
                );
            }
        }
        $this->assertSame(count($objects), $this->tester->countQueuedDataObjects($this->getIds($objects)));

        $this->tester->processIndexQueue();

        foreach ($objects as $seed => $object) {
            $fields = $this->getStandardFields($class, $object);
            foreach (self::LANGUAGES as $language) {
                $this->assertNumericValue(40 + $seed, $fields[self::LOCALIZED_FIELD][$language] ?? null);
            }
        }
    }

    /**
     * T23: input -> numeric inside a field collection; the field is removed from every item.
     */
    public function testFieldCollectionInputToNumericIsCopiedWithoutTheFieldAndRebuilt(): void
    {
        $this->fieldcollectionDefinition = $this->createFieldcollectionDefinition([
            $this->input(self::COLLECTION_ITEM_FIELD),
            $this->input(self::COLLECTION_KEEP_FIELD),
        ]);
        $class = $this->createClass('GdiFtcCollection', [$this->fieldcollections()]);
        $objects = $this->createObjects($class, function (Concrete $object, int $seed): void {
            $object->set(self::COLLECTION_FIELD, new Fieldcollection(
                [$this->createCollectionItem($seed, 1), $this->createCollectionItem($seed, 2)],
                self::COLLECTION_FIELD
            ));
        });
        foreach ($objects as $seed => $object) {
            $items = $this->getStandardFields($class, $object)[self::COLLECTION_FIELD];
            $this->assertSame(
                sprintf('abc-%d-1', $seed),
                $items[0][self::COLLECTION_KEY][self::COLLECTION_ITEM_FIELD] ?? null
            );
        }
        $liveIndex = $this->tester->getIndexName($class->getName(), true);

        $this->tester->disableSynchronousProcessing();
        $this->updateObjects($objects, function (Concrete $object): void {
            $this->setCollectionValues($object, static fn (): ?string => null);
        });
        $this->fieldcollectionDefinition->setLayoutDefinitions($this->layout([
            $this->decimalNumeric(self::COLLECTION_ITEM_FIELD),
            $this->input(self::COLLECTION_KEEP_FIELD),
        ]));
        $this->fieldcollectionDefinition->save();
        // Unlike an object brick definition, a saved field collection definition does not replace
        // the instance in the runtime cache, so the mapping would still be built from the old one.
        RuntimeCache::set('fieldcollection_' . self::COLLECTION_KEY, $this->fieldcollectionDefinition);
        $this->updateObjects($objects, function (Concrete $object, int $seed): void {
            $this->setCollectionValues($object, static fn (): string => (string) (40 + $seed));
        });
        $this->tester->clearQueue();

        $result = $this->applyClassMapping($class);

        $this->assertSame(
            [$this->standardFieldPath(self::COLLECTION_FIELD, self::COLLECTION_KEY, self::COLLECTION_ITEM_FIELD)],
            $result->getStrippedPaths()
        );
        $this->assertCopiedIntoOtherIndex($class, $liveIndex);
        foreach ($objects as $seed => $object) {
            $items = $this->getStandardFields($class, $object)[self::COLLECTION_FIELD] ?? [];
            $this->assertCount(2, $items);
            foreach ($items as $position => $item) {
                $this->assertArrayNotHasKey(self::COLLECTION_ITEM_FIELD, $item[self::COLLECTION_KEY]);
                $this->assertSame(
                    sprintf('keep-%d-%d', $seed, $position + 1),
                    $item[self::COLLECTION_KEY][self::COLLECTION_KEEP_FIELD] ?? null
                );
            }
        }
        $this->assertSame(count($objects), $this->tester->countQueuedDataObjects($this->getIds($objects)));

        $this->tester->processIndexQueue();

        foreach ($objects as $seed => $object) {
            foreach ($this->getStandardFields($class, $object)[self::COLLECTION_FIELD] as $item) {
                $this->assertNumericValue(40 + $seed, $item[self::COLLECTION_KEY][self::COLLECTION_ITEM_FIELD] ?? null);
            }
        }
    }

    /**
     * T22/T45: input -> numeric inside an object brick, applied by deployment:reindex, whose plan
     * names the field that is left out of the copy.
     */
    public function testObjectBrickInputToNumericAppliedByDeploymentReindex(): void
    {
        $this->deleteIndexState();
        $this->indexStateTouched = true;
        $class = $this->createClass('GdiFtcBrickHost', [$this->objectbricks()], false);
        $this->objectbrickDefinition = $this->createObjectbrickDefinition($class, [
            $this->input(self::BRICK_FIELD),
            $this->input(self::BRICK_KEEP_FIELD),
        ]);
        $class = $this->reloadClass($class);
        $this->createClassIndex($class);
        $this->tester->runConsoleCommand(self::DEPLOYMENT_COMMAND, ['--classes' => true]);

        $objects = $this->createObjects($class, function (Concrete $object, int $seed): void {
            $brick = $this->createBrick($object);
            $brick->setValue(self::BRICK_FIELD, 'abc-' . $seed);
            $brick->setValue(self::BRICK_KEEP_FIELD, 'keep-' . $seed);
            $object->get(self::BRICKS_FIELD)->set(self::BRICK_KEY, $brick);
        });
        foreach ($objects as $seed => $object) {
            $this->assertSame(
                'abc-' . $seed,
                $this->getBrickFields($class, $object)[self::BRICK_FIELD] ?? null
            );
        }
        $liveIndex = $this->tester->getIndexName($class->getName(), true);

        $this->tester->disableSynchronousProcessing();
        $this->updateObjects($objects, function (Concrete $object): void {
            $this->setBrickValue($object, null);
        });
        $this->objectbrickDefinition->setLayoutDefinitions($this->layout([
            $this->decimalNumeric(self::BRICK_FIELD),
            $this->input(self::BRICK_KEEP_FIELD),
        ]));
        $this->objectbrickDefinition->save();
        $this->updateObjects($objects, function (Concrete $object, int $seed): void {
            $this->setBrickValue($object, (string) (40 + $seed));
        });
        $this->tester->clearQueue();
        $strippedPath = $this->standardFieldPath(self::BRICKS_FIELD, self::BRICK_KEY, self::BRICK_FIELD);

        $output = $this->tester->runConsoleCommand(
            self::DEPLOYMENT_COMMAND,
            ['--classes' => true, '--dry-run' => true]
        );

        $this->assertStringContainsString('fields stripped in the copy: ' . $strippedPath, $output);
        $this->assertSame(
            $liveIndex,
            $this->tester->getIndexName($class->getName(), true),
            '--dry-run changes nothing'
        );

        $output = $this->tester->runConsoleCommand(self::DEPLOYMENT_COMMAND, ['--classes' => true]);

        $this->assertStringContainsString('Dispatch queue messages', $output);
        $this->assertCopiedIntoOtherIndex($class, $liveIndex);
        foreach ($objects as $seed => $object) {
            $brick = $this->getBrickFields($class, $object);
            $this->assertArrayNotHasKey(self::BRICK_FIELD, $brick);
            $this->assertSame('keep-' . $seed, $brick[self::BRICK_KEEP_FIELD] ?? null);
        }
        $this->assertSame(count($objects), $this->tester->countQueuedDataObjects($this->getIds($objects)));

        $this->tester->processIndexQueue();

        foreach ($objects as $seed => $object) {
            $this->assertNumericValue(
                40 + $seed,
                $this->getBrickFields($class, $object)[self::BRICK_FIELD] ?? null
            );
        }
    }

    /**
     * T30: generic-data-index:reindex applies a pending incompatible change, queues the class
     * although it only reindexes natively, and says so.
     */
    public function testReindexCommandQueuesClassWithStrippedFieldsAndPrintsANotice(): void
    {
        $class = $this->createClass('GdiFtcReindex', [$this->input(self::FIELD)]);
        $objects = $this->createObjects($class, static function (Concrete $object, int $seed): void {
            $object->set(self::FIELD, 'abc-' . $seed);
        });
        $liveIndex = $this->tester->getIndexName($class->getName(), true);

        $this->tester->disableSynchronousProcessing();
        $this->updateObjects($objects, static function (Concrete $object): void {
            $object->set(self::FIELD, null);
        });
        // The class mapping message of the save is dropped, so the change is still pending.
        $this->changeClassFields($class, [$this->numeric(self::FIELD)]);
        $this->tester->clearQueue();

        $output = $this->tester->runConsoleCommand('generic-data-index:reindex');

        $this->assertStringContainsString(
            sprintf('%s: fields left out of the copy: %s', $class->getName(), $this->standardFieldPath(self::FIELD)),
            $output
        );
        $this->assertStringContainsString('all its elements were queued for a rebuild from the database', $output);
        $this->assertStringContainsString('Run the messenger workers to fill these indices.', $output);
        $this->assertCopiedIntoOtherIndex($class, $liveIndex);
        foreach ($objects as $object) {
            $this->assertArrayNotHasKey(self::FIELD, $this->getStandardFields($class, $object));
        }
        $this->assertSame(count($objects), $this->tester->countQueuedDataObjects($this->getIds($objects)));
    }

    /**
     * T32: update:index -r still deletes and recreates the index, the documents come back from the queue.
     */
    public function testUpdateIndexWithRecreateStillRecreatesTheIndex(): void
    {
        $class = $this->createClass('GdiFtcRecreate', [$this->input(self::FIELD)]);
        $objects = $this->createObjects($class, static function (Concrete $object, int $seed): void {
            $object->set(self::FIELD, 'abc-' . $seed);
        });

        $this->tester->disableSynchronousProcessing();
        $this->updateObjects($objects, static function (Concrete $object): void {
            $object->set(self::FIELD, null);
        });
        $this->changeClassFields($class, [$this->numeric(self::FIELD)]);
        $this->updateObjects($objects, static function (Concrete $object, int $seed): void {
            $object->set(self::FIELD, (string) (40 + $seed));
        });
        $this->tester->clearQueue();

        $this->tester->runConsoleCommand(
            'generic-data-index:update:index',
            ['--class-definition-id' => $class->getId(), '--recreate_index' => true]
        );

        $this->assertSame(
            0,
            $this->tester->countIndexDocuments($this->getAlias($class)),
            'The recreated index is empty until the queue is processed'
        );
        $this->assertSame('float', $this->getStandardFieldMapping($class)[self::FIELD]['type'] ?? null);
        $this->assertSame(count($objects), $this->tester->countQueuedDataObjects($this->getIds($objects)));

        $this->tester->processIndexQueue();

        $this->assertSame(count($objects), $this->tester->countIndexDocuments($this->getAlias($class)));
        foreach ($objects as $seed => $object) {
            $this->assertNumericValue(40 + $seed, $this->getStandardFields($class, $object)[self::FIELD] ?? null);
        }
    }

    /**
     * @param Data[] $fields
     */
    private function createClass(string $name, array $fields, bool $createIndex = true): ClassDefinition
    {
        // Leftover of an aborted run.
        ClassDefinition::getByName($name)?->delete();

        $class = new ClassDefinition();
        $class->setId(strtolower($name));
        $class->setName($name);
        // The test database is recreated per run, the search indices are not: documents of an
        // aborted run would be counted as documents of this one.
        $this->deleteIndices($this->getAlias($class));
        $class->setLayoutDefinitions($this->layout($fields));
        $class->save();
        $this->classDefinition = $class;

        if ($createIndex) {
            $this->createClassIndex($class);
        }

        return $class;
    }

    /**
     * In production the index is created by the class mapping message of the class save; here it is
     * created directly, before the first object is indexed into it.
     */
    private function createClassIndex(ClassDefinition $class): void
    {
        /** @var IndexUpdateServiceInterface $indexUpdateService */
        $indexUpdateService = $this->tester->grabService(IndexUpdateServiceInterface::class);
        $indexUpdateService->setReCreateIndex(false)->updateClassDefinition($class);
        $this->tester->clearQueue();
        $this->assertTrue($this->getSearchIndexService()->existsAlias($this->getAlias($class)));
    }

    /**
     * @param Data[] $fields
     */
    private function changeClassFields(ClassDefinition $class, array $fields): void
    {
        $class->setLayoutDefinitions($this->layout($fields));
        $class->save();
        // The runtime cache still holds the instance loaded with the objects, i.e. the old fields; a
        // command run in a new process (like update:index) would read the saved definition.
        RuntimeCache::set('class_' . $class->getId(), $class);
    }

    private function reloadClass(ClassDefinition $class): ClassDefinition
    {
        $reloaded = ClassDefinition::getById($class->getId(), true);
        $this->assertNotNull($reloaded);
        $this->classDefinition = $reloaded;

        return $reloaded;
    }

    /**
     * Applies the current class definition to its index like the class mapping message does, but
     * without asking for the rebuild: queue entries afterwards come from the mapping update itself.
     */
    private function applyClassMapping(ClassDefinition $class): MappingUpdateResult
    {
        /** @var ClassDefinitionReindexServiceInterface $reindexService */
        $reindexService = $this->tester->grabService(ClassDefinitionReindexServiceInterface::class);
        $result = $reindexService->reindexClassDefinitionMapping($this->reloadClass($class), true, false);
        $this->assertNotNull($result, 'The changed mapping must be applied');

        return $result;
    }

    /**
     * @param Data[] $fields
     */
    private function createFieldcollectionDefinition(array $fields): FieldcollectionDefinition
    {
        FieldcollectionDefinition::getByKey(self::COLLECTION_KEY)?->delete();

        $definition = new FieldcollectionDefinition();
        $definition->setKey(self::COLLECTION_KEY);
        $definition->setLayoutDefinitions($this->layout($fields));
        $definition->save();

        return $definition;
    }

    /**
     * @param Data[] $fields
     */
    private function createObjectbrickDefinition(ClassDefinition $class, array $fields): ObjectbrickDefinition
    {
        ObjectbrickDefinition::getByKey(self::BRICK_KEY)?->delete();

        $definition = new ObjectbrickDefinition();
        $definition->setKey(self::BRICK_KEY);
        $definition->setLayoutDefinitions($this->layout($fields));
        $definition->setClassDefinitions([['classname' => $class->getName(), 'fieldname' => self::BRICKS_FIELD]]);
        $definition->save();

        return $definition;
    }

    /**
     * @param Data[] $fields
     */
    private function layout(array $fields): Panel
    {
        $panel = new Panel();
        $panel->setName('root');
        foreach ($fields as $field) {
            $panel->addChild($field);
        }

        return $panel;
    }

    private function input(string $name): Input
    {
        $field = new Input();
        $field->setName($name);

        return $field;
    }

    private function numeric(string $name): Numeric
    {
        $field = new Numeric();
        $field->setName($name);

        return $field;
    }

    /**
     * A numeric field with decimal columns, used inside field collections and object bricks: their
     * data access objects pass a loaded or saved numeric value through the setter of the generated
     * PHP class, and the class generated for the old input field (still loaded in this process)
     * accepts only a string. A decimal numeric value is a string; a plain one an int or a float.
     * The index maps both as float.
     */
    private function decimalNumeric(string $name): Numeric
    {
        $field = $this->numeric($name);
        $field->setDecimalSize(10);
        $field->setDecimalPrecision(2);

        return $field;
    }

    /**
     * @param Data[] $fields
     */
    private function localizedFields(array $fields): Localizedfields
    {
        $localizedFields = new Localizedfields();
        $localizedFields->setName('localizedfields');
        foreach ($fields as $field) {
            $localizedFields->addChild($field);
        }

        return $localizedFields;
    }

    private function fieldcollections(): Fieldcollections
    {
        $field = new Fieldcollections();
        $field->setName(self::COLLECTION_FIELD);
        $field->setAllowedTypes([self::COLLECTION_KEY]);

        return $field;
    }

    private function objectbricks(): Objectbricks
    {
        $field = new Objectbricks();
        $field->setName(self::BRICKS_FIELD);

        return $field;
    }

    /**
     * @param callable(Concrete, int): void $fill
     *
     * @return array<int, Concrete> objects by seed
     */
    private function createObjects(ClassDefinition $class, callable $fill): array
    {
        $className = self::OBJECT_NAMESPACE . ucfirst($class->getName());
        $objects = [];
        foreach (self::SEEDS as $seed) {
            /** @var Concrete $object */
            $object = new $className();
            $object->setKey(sprintf('field-type-change-%d-%s', $seed, uniqid()));
            $object->setParentId(1);
            $object->setPublished(true);
            $fill($object, $seed);
            $object->save();
            $objects[$seed] = $object;
        }

        $this->assertSame(count(self::SEEDS), $this->tester->countIndexDocuments($this->getAlias($class)));

        return $objects;
    }

    /**
     * @param array<int, Concrete> $objects
     * @param callable(Concrete, int): void $update
     */
    private function updateObjects(array $objects, callable $update): void
    {
        foreach ($objects as $seed => $object) {
            $update($object, $seed);
            $object->save();
        }
    }

    private function createCollectionItem(int $seed, int $position): FieldcollectionItem
    {
        $className = self::FIELDCOLLECTION_NAMESPACE . self::COLLECTION_KEY;
        /** @var FieldcollectionItem $item */
        $item = new $className();
        $item->setValue(self::COLLECTION_ITEM_FIELD, sprintf('abc-%d-%d', $seed, $position));
        $item->setValue(self::COLLECTION_KEEP_FIELD, sprintf('keep-%d-%d', $seed, $position));

        return $item;
    }

    /**
     * @param callable(): ?string $value
     */
    private function setCollectionValues(Concrete $object, callable $value): void
    {
        /** @var Fieldcollection $collection */
        $collection = $object->get(self::COLLECTION_FIELD);
        foreach ($collection->getItems() as $item) {
            $item->setValue(self::COLLECTION_ITEM_FIELD, $value());
        }
        $object->markFieldDirty(self::COLLECTION_FIELD);
    }

    private function createBrick(Concrete $object): ObjectbrickItem
    {
        $className = self::OBJECTBRICK_NAMESPACE . self::BRICK_KEY;
        /** @var ObjectbrickItem $brick */
        $brick = new $className($object);

        return $brick;
    }

    private function setBrickValue(Concrete $object, ?string $value): void
    {
        /** @var ObjectbrickItem $brick */
        $brick = $object->get(self::BRICKS_FIELD)->get(self::BRICK_KEY);
        $brick->setValue(self::BRICK_FIELD, $value);
        $object->markFieldDirty(self::BRICKS_FIELD);
    }

    /**
     * A1/A2: the documents were copied into the other -odd/-even index (the alias moved) and none
     * got lost, i.e. the live index was not recreated empty.
     */
    private function assertCopiedIntoOtherIndex(ClassDefinition $class, string $previousIndex): void
    {
        $this->assertNotSame(
            $previousIndex,
            $this->tester->getIndexName($class->getName(), true),
            'A1: the alias points to the other index'
        );
        $this->assertSame(
            count(self::SEEDS),
            $this->tester->countIndexDocuments($this->getAlias($class)),
            'A2: no document is missing right after the copy'
        );
    }

    private function assertNumericValue(int $expected, mixed $actual): void
    {
        $isNumber = is_int($actual) || is_float($actual) || (is_string($actual) && is_numeric($actual));
        $this->assertTrue($isNumber, sprintf('A4: expected a number, got %s', var_export($actual, true)));
        $this->assertSame((float) $expected, (float) $actual, 'A4: the rebuild filled in the new value');
    }

    private function getStandardFields(ClassDefinition $class, Concrete $object): array
    {
        $response = $this->tester->checkIndexEntry($object->getId(), $this->getAlias($class));

        return $response['_source'][FieldCategory::STANDARD_FIELDS->value] ?? [];
    }

    private function getBrickFields(ClassDefinition $class, Concrete $object): array
    {
        return $this->getStandardFields($class, $object)[self::BRICKS_FIELD][self::BRICK_KEY] ?? [];
    }

    private function getStandardFieldMapping(ClassDefinition $class): array
    {
        $indexName = $this->tester->getIndexName($class->getName(), true);
        $mapping = $this->tester->getIndexMapping($indexName);

        return $mapping[$indexName]['mappings']['properties'][FieldCategory::STANDARD_FIELDS->value]['properties']
            ?? [];
    }

    private function standardFieldPath(string ...$segments): string
    {
        return implode('.', [FieldCategory::STANDARD_FIELDS->value, ...$segments]);
    }

    /**
     * @param array<int, Concrete> $objects
     *
     * @return int[]
     */
    private function getIds(array $objects): array
    {
        return array_values(array_map(static fn (Concrete $object): int => $object->getId(), $objects));
    }

    private function getAlias(ClassDefinition $class): string
    {
        return $this->tester->grabService(DataObjectTypeAdapter::class)->getAliasIndexName($class);
    }

    private function getSearchIndexService(): SearchIndexServiceInterface
    {
        return $this->tester->grabService(SearchIndexServiceInterface::class);
    }

    private function deleteIndices(string $alias): void
    {
        $searchIndexService = $this->getSearchIndexService();
        foreach ([DefaultSearchService::INDEX_VERSION_ODD, DefaultSearchService::INDEX_VERSION_EVEN] as $version) {
            $searchIndexService->deleteIndex($alias . '-' . $version, true);
        }
    }

    /**
     * The PHP class generated for the definition before the change stays loaded in this process, and
     * its setters call methods of the old field type on the new field definition (e.g. preSetData()
     * of Numeric on an Input). Loading the objects again - which TestHelper::cleanUp() and
     * ClassDefinition::delete() both do - would therefore fail, so the object rows are removed
     * directly, like ClassDefinition\Dao::delete() does for the data tables.
     */
    private function deleteObjectsWithoutLoading(): void
    {
        if ($this->classDefinition === null) {
            return;
        }

        Db::get()->delete('objects', ['classId' => $this->classDefinition->getId()]);
    }

    private function deleteIndexState(): void
    {
        Db::get()->executeStatement(
            "DELETE FROM settings_store WHERE scope = 'generic_data_index' AND id LIKE 'index\\_state\\_%'"
        );
    }
}
