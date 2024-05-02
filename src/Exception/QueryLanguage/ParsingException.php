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

final class ParsingException extends \Exception
{
    public function __construct(private string $expected, private string $found, private ?Token $token, private ?int $position = null)
    {
        $message = sprintf('Expected %s, found %s.', $expected, $found);

        parent::__construct($message);
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

    public function getPosition(): ?int
    {
        return $this->position ?? $this->token->position ?? null;
    }
}
