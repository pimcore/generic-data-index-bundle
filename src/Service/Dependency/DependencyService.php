<?php
declare(strict_types=1);

namespace Pimcore\Bundle\GenericDataIndexBundle\Service\Dependency;

use Doctrine\DBAL\Connection;
use Pimcore\Model\Element\ElementInterface;
use Pimcore\Model\Element\Service;

/**
 * @internal
 */
final readonly class DependencyService implements DependencyServiceInterface
{
    public function __construct(
        private Connection $connection,
    )
    {
    }

    public function getRequiresDependencies(ElementInterface $element): array
    {
        $items = $this->connection->fetchAllAssociative(
            'select * from dependencies where sourceid = ? and sourcetype = ?',
            [$element->getId(), Service::getElementType($element)]
        );

        $result = [];
        foreach ($items as $item) {
            $result[$item['targettype']] ??= [];
            $result[$item['targettype']][] = $item['targetid'];
        }

        return $result;
    }

}