<?php
declare(strict_types=1);

/**
 * Pimcore
 *
 * This source file is available under following license:
 * - Pimcore Commercial License (PCL)
 *
 *  @copyright  Copyright (c) Pimcore GmbH (http://www.pimcore.org)
 *  @license    http://www.pimcore.org/license     PCL
 */

namespace Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\DataObject\FieldDefinitionAdapter;

use InvalidArgumentException;
use Pimcore\Bundle\GenericDataIndexBundle\Enum\SearchIndex\OpenSearch\AttributeType;
use Pimcore\Bundle\StaticResolverBundle\Models\DataObject\ClassificationStore\ServiceResolverInterface;
use Pimcore\Model\DataObject\ClassDefinition\Data;
use Pimcore\Model\DataObject\ClassDefinition\Data\Classificationstore;
use Pimcore\Model\DataObject\Classificationstore\GroupConfig;
use Pimcore\Model\DataObject\Classificationstore\GroupConfig\Listing as GroupListing;
use Pimcore\Model\DataObject\Classificationstore\KeyGroupRelation;
use Pimcore\Model\DataObject\Classificationstore\KeyGroupRelation\Listing as KeyGroupRelationListing;
use Symfony\Contracts\Service\Attribute\Required;

/**
 * @internal
 */
final class ClassificationStoreAdapter extends AbstractAdapter
{
    private ServiceResolverInterface $classificationStoreService;

    #[Required]
    public function setClassificationStoreService(ServiceResolverInterface $serviceResolver): void
    {
        $this->classificationStoreService = $serviceResolver;
    }

    public function getOpenSearchMapping(): array
    {
        $classificationStore = $this->getFieldDefinition();
        if (!$classificationStore instanceof Classificationstore) {
            throw new InvalidArgumentException(
                'Field definition must be an instance of ' . Classificationstore::class
            );
        }
        $mapping = [];

        $groups = $this->getClassificationStoreGroups($classificationStore->getStoreId());
        foreach ($groups as $group) {
            $keys = $this->getClassificationStoreKeysFromGroup($group);
            $mapping[$group->getName()]['properties'] = $this->getMappingForGroupConfig($keys);
        }

        return [
            'type' => AttributeType::NESTED,
            'properties' => $mapping,
        ];
    }

    /**
     * @param KeyGroupRelation[] $groupConfigs
     */
    private function getMappingForGroupConfig(array $groupConfigs): array
    {
        $groupMapping = [];
        foreach ($groupConfigs as $key) {
            $definition = $this->classificationStoreService->getFieldDefinitionFromKeyConfig($key);
            if ($definition instanceof Data) {
                $adapter = $this->getFieldDefinitionService()->getFieldDefinitionAdapter($definition);

                if ($adapter) {
                    $groupMapping['default']['properties'][$key->getName()] = $adapter->getOpenSearchMapping();
                }
            }
        }

        return $groupMapping;
    }

    /**
     * @return GroupConfig[]
     */
    private function getClassificationStoreGroups(int $id): array
    {
        $listing = new GroupListing();
        $listing->setCondition('storeId = :storeId', ['storeId' => $id]);

        return $listing->getList();
    }

    /**
     * @return KeyGroupRelation[]
     */
    private function getClassificationStoreKeysFromGroup(GroupConfig $groupConfig): array
    {
        $listing = new KeyGroupRelationListing();
        $listing->addConditionParam('groupId = ?', $groupConfig->getId());

        return $listing->getList();
    }
}
