<?php
declare(strict_types=1);

namespace Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Modifier\Filter\Basic;

use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Modifier\SearchModifierInterface;
use Symfony\Component\Validator\Constraints as Assert;

final class IdFilter implements SearchModifierInterface
{
    public function __construct(
        #[Assert\Positive]
        private readonly int $id = 1
    ) {
    }

    public function getId(): int
    {
        return $this->id;
    }
}