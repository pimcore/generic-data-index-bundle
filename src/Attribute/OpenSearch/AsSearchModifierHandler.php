<?php
declare(strict_types=1);

namespace Pimcore\Bundle\GenericDataIndexBundle\Attribute\OpenSearch;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
class AsSearchModifierHandler
{

}