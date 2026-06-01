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
    ) {
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

    public function isReferencedByAny(ElementInterface $element): bool
    {
        return (bool) $this->connection->fetchOne(
            'SELECT 1 FROM dependencies WHERE targetid = ? AND targettype = ? LIMIT 1',
            [$element->getId(), Service::getElementType($element)]
        );
    }
}
