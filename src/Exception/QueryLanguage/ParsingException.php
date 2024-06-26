<?php
declare(strict_types=1);

/**
 * Pimcore
 *
 * This source file is available under two different licenses:
 * - GNU General Public License version 3 (GPLv3)
 * - Pimcore Commercial License (PCL)
 * Full copyright and license information is available in
 * LICENSE.md which is distributed with this source code.
 *
 *  @copyright  Copyright (c) Pimcore GmbH (http://www.pimcore.org)
 *  @license    http://www.pimcore.org/license     GPLv3 and PCL
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
        Exception $previous = null
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
