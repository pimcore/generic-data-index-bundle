<?php
declare(strict_types=1);

namespace Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Modifier\Filter\Basic;

use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Modifier\SearchModifierInterface;
use Symfony\Component\Validator\Constraints as Assert;

final class IdsFilter implements SearchModifierInterface
{
    public function __construct(
        /** @var int[] $ids */
        #[Assert\All([
            new Assert\Type('int'),
            new Assert\Positive(),
        ])]
        private readonly array $ids = []
    ) {
    }

    public function getIds(): array
    {
        return $this->ids;
    }
}