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
        DocumentTypeAdapter $documentTypeAdapter
    ) {
        $this->adapters[] = $assetTypeAdapter;
        $this->adapters[] = $dataObjectTypeAdapter;
        $this->adapters[] = $documentTypeAdapter;
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
