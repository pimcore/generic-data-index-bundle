<?php
declare(strict_types=1);

namespace Pimcore\Bundle\GenericDataIndexBundle\Model\OpenSearch\Query;

interface AsSubQueryInterface
{
    public function toArrayAsSubQuery(): array;
}