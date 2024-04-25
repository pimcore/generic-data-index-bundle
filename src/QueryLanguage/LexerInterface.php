<?php
declare(strict_types=1);

namespace Pimcore\Bundle\GenericDataIndexBundle\QueryLanguage;

interface LexerInterface
{
    public function setQuery(string $query): void;

    public function getTokens(): array;
}