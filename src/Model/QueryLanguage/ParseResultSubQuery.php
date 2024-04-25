<?php
declare(strict_types=1);

namespace Pimcore\Bundle\GenericDataIndexBundle\Model\QueryLanguage;

/**
 * @internal
 */
final readonly class ParseResultSubQuery
{

    public function __construct(
        private string $subQueryId,
        private string $relationFieldPath,
        private string $targetType,
        private string $targetQuery
    )
    {
    }

    public function getSubQueryId(): string
    {
        return $this->subQueryId;
    }

    public function getRelationFieldPath(): string
    {
        return $this->relationFieldPath;
    }

    public function getTargetType(): string
    {
        return $this->targetType;
    }

    public function getTargetQuery(): string
    {
        return $this->targetQuery;
    }
}