<?php
declare(strict_types=1);

/**
 * Pimcore
 *
 * This source file is available under following license:
 * - Pimcore Commercial License (PCL)
 *
 *  @copyright  Copyright (c) Pimcore GmbH (http://www.pimcore.org)
 *  @license    http://www.pimcore.org/license     PCL
 */

namespace Pimcore\Bundle\GenericDataIndexBundle\Exception\QueryLanguage;

final class ParsingException extends \Exception
{
    public function __construct(string $expected, string $found)
    {
        $message = sprintf('Expected %s, found %s.', $expected, $found);

        parent::__construct($message, 0, null);
    }
}
