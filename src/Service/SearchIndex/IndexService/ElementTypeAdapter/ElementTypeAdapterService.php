<?php
declare(strict_types=1);

namespace Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\IndexService\ElementTypeAdapter;

use InvalidArgumentException;
use Pimcore\Model\Element\ElementInterface;

class ElementTypeAdapterService
{
    /**
     * @var AbstractElementTypeAdapter[]
     */
    private array $adapters;

    public function __construct(
        AssetTypeAdapter $assetTypeAdapter,
        DataObjectTypeAdapter $dataObjectTypeAdapter,
    )
    {
        $this->adapters[] = $assetTypeAdapter;
        $this->adapters[] = $dataObjectTypeAdapter;
    }

    /**
     * @throws InvalidArgumentException
     */
    public function getTypeAdapter(ElementInterface $element): AbstractElementTypeAdapter
    {
        foreach ($this->adapters as $adapter) {
            if ($adapter->supports($element)) {
                return $adapter;
            }
        }

        throw new InvalidArgumentException('Element type adapter not found - type: ' . $element->getType());
    }
}