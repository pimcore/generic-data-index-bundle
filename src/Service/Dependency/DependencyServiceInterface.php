<?php
declare(strict_types=1);

namespace Pimcore\Bundle\GenericDataIndexBundle\Service\Dependency;

use Pimcore\Model\Element\ElementInterface;

/**
 * @internal
 */
interface DependencyServiceInterface
{
    public function getRequiresDependencies(ElementInterface $element): array;
}