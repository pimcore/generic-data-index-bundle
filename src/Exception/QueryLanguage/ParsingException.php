<?php
declare(strict_types=1);

/**
 * This source file is available under the terms of the
 * Pimcore Open Core License (POCL)
 * Full copyright and license information is available in
 * LICENSE.md which is distributed with this source code.
 *
 *  @copyright  Copyright (c) Pimcore GmbH (https://www.pimcore.com)
 *  @license    Pimcore Open Core License (POCL)
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
        ?string $message = null,
        private readonly ?int $position = null,
        ?Exception $previous = null
    ) {
        $message = $message ?? sprintf('Expected %s, found %s.', $expected, $found);

        parent::__construct($message, 0, $previous);
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
        return $this->position ?? $this->token->position ?? strlen($this->query);
    }
}
