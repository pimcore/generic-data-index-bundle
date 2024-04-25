<?php
declare(strict_types=1);

namespace Pimcore\Bundle\GenericDataIndexBundle\Exception\QueryLanguage;

final class ParsingException extends \Exception
{

    public function __construct(string $expected, string $found)
    {
        $message = sprintf('Expected %s, found %s.', $expected, $found);

        parent::__construct($message, 0, null);
    }

}