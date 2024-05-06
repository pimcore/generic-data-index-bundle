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

namespace Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\IndexService\ElementTypeAdapter;

use Pimcore\Bundle\GenericDataIndexBundle\Exception\InvalidElementTypeException;
use Pimcore\Model\Element\ElementInterface;

/**
 * @internal
 */
final class AdapterService implements AdapterServiceInterface
{
    /**
     * @var AbstractElementTypeAdapter[]
     */
    private array $adapters;

    public function __construct(
        AssetTypeAdapter $assetTypeAdapter,
        DataObjectTypeAdapter $dataObjectTypeAdapter,
    ) {
        $this->adapters[] = $assetTypeAdapter;
        $this->adapters[] = $dataObjectTypeAdapter;
    }

    /**
     * @throws InvalidElementTypeException
     */
    public function getTypeAdapter(ElementInterface $element): AbstractElementTypeAdapter
    {
        foreach ($this->adapters as $adapter) {
            if ($adapter->supports($element)) {
                return $adapter;
            }
        }

        throw new InvalidElementTypeException(
            'Element type adapter not found - type: ' . $element->getType()
        );
    }
}
