<?php
declare(strict_types=1);

namespace Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\IndexService\MappingHandler;

use Exception;
use Pimcore\Model\Element\ElementInterface;

interface MappingHandlerInterface#
{

    /**
     * @throws Exception
     */
    public function updateMapping(mixed $context = null, bool $forceCreateIndex = false): void;

    public function getCurrentFullIndexName(mixed $context = null): string;
}