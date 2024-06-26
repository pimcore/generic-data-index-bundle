<?php
declare(strict_types=1);

/**
 * Pimcore
 *
 * This source file is available under two different licenses:
 * - GNU General Public License version 3 (GPLv3)
 * - Pimcore Commercial License (PCL)
 * Full copyright and license information is available in
 * LICENSE.md which is distributed with this source code.
 *
 *  @copyright  Copyright (c) Pimcore GmbH (http://www.pimcore.org)
 *  @license    http://www.pimcore.org/license     GPLv3 and PCL
 */

namespace Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\OpenSearch\DataObject\FieldDefinitionAdapter;

use Exception;
use InvalidArgumentException;
use Pimcore\Bundle\GenericDataIndexBundle\Enum\SearchIndex\OpenSearch\AttributeType;
use Pimcore\Bundle\GenericDataIndexBundle\Traits\LoggerAwareTrait;
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
    use LoggerAwareTrait;

    private ServiceResolverInterface $classificationService;

    #[Required]
    public function setClassificationService(ServiceResolverInterface $serviceResolver): void
    {
        $this->classificationService = $serviceResolver;
    }

    public function getIndexMapping(): array
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
            try {
                $definition = $this->classificationService->getFieldDefinitionFromKeyConfig($key);
            } catch (Exception) {
                $this->logger->warning(
                    'Could not get field definition for type ' . $key->getType() . ' in group ' . $key->getGroupId()
                );

                continue;
            }

            if ($definition instanceof Data) {
                $adapter = $this->getFieldDefinitionService()->getFieldDefinitionAdapter($definition);

                if ($adapter) {
                    $groupMapping['default']['properties'][$key->getName()] = $adapter->getIndexMapping();
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
