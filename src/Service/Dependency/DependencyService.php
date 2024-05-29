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
}
