<?php
declare(strict_types=1);

namespace Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex;

use Doctrine\DBAL\Connection;
use OpenSearch\Client;
use Pimcore\Bundle\GenericDataIndexBundle\Enum\SearchIndex\IndexName;
use Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\IndexAliasServiceInterface;

/**
 * @internal
 */
final readonly class GlobalIndexAliasService implements GlobalIndexAliasServiceInterface
{
    public function __construct(
        private Connection $connection,
        private SearchIndexConfigServiceInterface $searchIndexConfigService,
        private IndexAliasServiceInterface $indexAliasService,
    )
    {
    }

    public function updateDataObjectAlias(): void
    {
        $aliases = $this->indexAliasService->getAllAliases();

        $existingClassAliases = $this->filterClassAliases($aliases);
        $existingIndicesInDataObjectAlias = $this->filterByAliasName(
            $aliases,
            $this->getDataObjectAliasName()
        );

        $this->indexAliasService->updateAliases(
            $this->getDataObjectAliasName(),
            $this->getIndexNamesFromAliases($existingClassAliases),
            $this->getIndexNamesFromAliases($existingIndicesInDataObjectAlias)
        );

    }

    public function updateElementSearchAlias(): void
    {
        $aliases = $this->indexAliasService->getAllAliases();

        $elementSearchIndexAliases = $this->filterClassAliases($aliases);

        $elementSearchIndexAliases = array_merge(
            $elementSearchIndexAliases,
            $this->filterByAliasName($aliases, $this->searchIndexConfigService->getIndexName(IndexName::ASSET->value))
        );

        $elementSearchIndexAliases = array_merge(
            $elementSearchIndexAliases,
            $this->filterByAliasName($aliases, $this->searchIndexConfigService->getIndexName(IndexName::DOCUMENT->value))
        );

        $existingIndicesInElementSearchAlias =  $this->filterByAliasName(
            $aliases,
            $this->getElementSearchAliasName()
        );

        $this->indexAliasService->updateAliases(
            $this->getElementSearchAliasName(),
            $this->getIndexNamesFromAliases($elementSearchIndexAliases),
            $this->getIndexNamesFromAliases($existingIndicesInElementSearchAlias)
        );
    }

    public function addToDataObjectAlias(string $indexName): void
    {
        $this->indexAliasService->addAlias(
            $this->getDataObjectAliasName(),
            $indexName
        );
    }

    public function addToElementSearchAlias(string $indexName): void
    {
        $this->indexAliasService->addAlias(
            $this->getElementSearchAliasName(),
            $indexName
        );
    }

    public function getDataObjectAliasName(): string
    {
        return $this->searchIndexConfigService->getIndexName(IndexName::DATA_OBJECT->value);
    }

    public function getElementSearchAliasName(): string
    {
        return $this->searchIndexConfigService->getIndexName(IndexName::ELEMENT_SEARCH->value);
    }

    private function filterClassAliases(array $aliases): array
    {
        $classAliases = $this->getAliasesForAllClasses();

        return array_values(array_filter($aliases, static function(array $alias) use ($classAliases) {
            return in_array($alias['alias'], $classAliases, true);
        }));
    }

    private function filterByAliasName(array $aliases, string $aliasName): array
    {
        return array_values(array_filter($aliases, static function(array $alias) use ($aliasName) {
            return $alias['alias'] === $aliasName;
        }));
    }

    private function getAliasesForAllClasses(): array
    {
        $classes = $this->connection->fetchFirstColumn('select name from classes');
        return array_map(function(string $class) {
            return $this->searchIndexConfigService->getIndexName($class);
        }, $classes);
    }

    private function getIndexNamesFromAliases(array $aliases): array
    {
        return array_map(static function(array $alias) {
            return $alias['index'];
        }, $aliases);
    }
}