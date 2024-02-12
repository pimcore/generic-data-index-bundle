<?php
declare(strict_types=1);

namespace Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\OpenSearch\Search\Modifier\Filter\Tree;

use Pimcore\Bundle\GenericDataIndexBundle\Attribute\OpenSearch\AsSearchModifierHandler;
use Pimcore\Bundle\GenericDataIndexBundle\Enum\SearchIndex\FieldCategory;
use Pimcore\Bundle\GenericDataIndexBundle\Model\OpenSearch\Modifier\SearchModifierContextInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Model\OpenSearch\Query\BoolQuery;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Modifier\Filter\Tree\ParentIdFilter;
use Pimcore\Bundle\GenericDataIndexBundle\Model\OpenSearch\Modifier\SearchModifierContext;

#[AsSearchModifierHandler]
final class ParentIdFilterModifier
{
    public function __invoke(ParentIdFilter $parentIdFilter, SearchModifierContextInterface $context): void
    {
        $parentIdAttribute = FieldCategory::SYSTEM_FIELDS->value . '.' . FieldCategory\SystemField::PARENT_ID->value;

        $context->getSearch()->addQuery(new BoolQuery([
            'filter' => [
                'term' => [
                    $parentIdAttribute => $parentIdFilter->getParentId(),
                ],
            ],
        ]));
    }
}