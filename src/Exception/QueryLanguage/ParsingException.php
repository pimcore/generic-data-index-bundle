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

use Doctrine\Common\Lexer\Token;
use Exception;

final class ParsingException extends Exception
{
    public function __construct(
        private readonly string $query,
        private readonly string $expected,
        private readonly string $found,
        private readonly ?Token $token,
    ) {
        $message = sprintf('Expected %s, found %s.', $expected, $found);

        parent::__construct($message);
    }

    public function getQuery(): string
    {
        return $this->query;
    }

    public function getExpected(): string
    {
        return $this->expected;
    }

    public function getFound(): string
    {
        return $this->found;
    }

    public function getToken(): ?Token
    {
        return $this->token;
    }

    public function getPosition(): int
    {
        return $this->token->position ?? strlen($this->query);
    }
}
