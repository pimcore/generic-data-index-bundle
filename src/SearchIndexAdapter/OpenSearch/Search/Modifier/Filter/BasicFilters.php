<?php
declare(strict_types=1);

namespace Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\OpenSearch\Search\Modifier\Filter;

use Pimcore\Bundle\GenericDataIndexBundle\Attribute\OpenSearch\AsSearchModifierHandler;
use Pimcore\Bundle\GenericDataIndexBundle\Enum\SearchIndex\FieldCategory\SystemField;
use Pimcore\Bundle\GenericDataIndexBundle\Model\OpenSearch\Modifier\SearchModifierContextInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Model\OpenSearch\Query\TermFilter;
use Pimcore\Bundle\GenericDataIndexBundle\Model\OpenSearch\Query\TermsFilter;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Modifier\Filter\Basic\IdFilter;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Modifier\Filter\Basic\IdsFilter;

final class BasicFilters
{
    #[AsSearchModifierHandler]
    public function handleIdFilter(IdFilter $idFilter, SearchModifierContextInterface $context): void
    {
        $context->getSearch()->addQuery(
            new TermFilter(
                field: SystemField::ID->getPath(),
                term: $idFilter->getId(),
            )
        );
    }

    #[AsSearchModifierHandler]
    public function handleIdsFilter(IdsFilter $idsFilter, SearchModifierContextInterface $context): void
    {
        $context->getSearch()->addQuery(
            new TermsFilter(
                field: SystemField::ID->getPath(),
                terms: $idsFilter->getIds(),
            )
        );
    }
}