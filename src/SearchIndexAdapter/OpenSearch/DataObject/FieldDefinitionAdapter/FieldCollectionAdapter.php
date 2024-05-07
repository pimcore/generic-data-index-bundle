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
use Pimcore\Bundle\StaticResolverBundle\Models\DataObject\FieldCollection\DefinitionResolverInterface;
use Pimcore\Model\DataObject\ClassDefinition\Data\Fieldcollections;
use Symfony\Contracts\Service\Attribute\Required;

/**
 * @internal
 */
final class FieldCollectionAdapter extends AbstractAdapter
{
    private DefinitionResolverInterface $fieldCollectionDefinition;

    /**
     * @throws Exception
     */
    public function getIndexMapping(): array
    {
        $fieldDefinition = $this->getFieldDefinition();
        if (!$fieldDefinition instanceof Fieldcollections) {
            throw new InvalidArgumentException('FieldDefinition must be of type Fieldcollections');
        }

        $mapping = [];
        $allowedTypes = $fieldDefinition->getAllowedTypes();

        foreach ($allowedTypes as $allowedType) {
            $fieldCollectionDefinition = $this->fieldCollectionDefinition->getByKey($allowedType);
            if (!$fieldCollectionDefinition) {
                continue;
            }
            foreach ($fieldCollectionDefinition->getFieldDefinitions() as $fieldDefinition) {
                $fieldDefinitionAdapter = $this->getFieldDefinitionService()
                    ->getFieldDefinitionAdapter($fieldDefinition);
                if ($fieldDefinitionAdapter) {
                    $mapping[$fieldDefinition->getName()] = $fieldDefinitionAdapter->getIndexMapping();
                }
            }
        }

        // Add type mapping
        $mapping['type'] = [
            'type' => AttributeType::TEXT,
        ];

        return [
                'type' => AttributeType::NESTED,
                'properties' => $mapping,
            ];
    }

    #[Required]
    public function setFieldCollectionDefinition(DefinitionResolverInterface $definitionResolver): void
    {
        $this->fieldCollectionDefinition = $definitionResolver;
    }
}
