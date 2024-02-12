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

use Exception;
use InvalidArgumentException;
use Pimcore\Bundle\GenericDataIndexBundle\Enum\SearchIndex\OpenSearch\AttributeType;
use Pimcore\Model\DataObject\ClassDefinition\Data\Fieldcollections;
use Pimcore\Model\DataObject\Fieldcollection\Definition as FieldCollectionDefinition;

/**
 * @internal
 */
final class FieldCollectionAdapter extends AbstractAdapter
{
    /**
     * @throws Exception
     */
    public function getOpenSearchMapping(): array
    {
        $fieldDefinition = $this->getFieldDefinition();
        if (!$fieldDefinition instanceof Fieldcollections) {
            throw new InvalidArgumentException('FieldDefinition must be of type Fieldcollections');
        }

        $mapping = [];
        $allowedTypes = $fieldDefinition->getAllowedTypes();

        foreach ($allowedTypes as $allowedType) {
            $fieldCollectionDefinition = FieldCollectionDefinition::getByKey($allowedType);
            if (!$fieldCollectionDefinition) {
                continue;
            }
            foreach ($fieldCollectionDefinition->getFieldDefinitions() as $fieldDefinition) {
                $fieldDefinitionAdapter = $this->getFieldDefinitionService()
                    ->getFieldDefinitionAdapter($fieldDefinition);
                if ($fieldDefinitionAdapter) {
                    $mapping[$fieldDefinition->getName()] = $fieldDefinitionAdapter->getOpenSearchMapping();
                }
            }
        }

        // Add type mapping
        $mapping['type'] = [
            'type' => AttributeType::TEXT,
        ];

        return [
                'type' => 'nested',
                'properties' => $mapping,
            ];
    }
}
