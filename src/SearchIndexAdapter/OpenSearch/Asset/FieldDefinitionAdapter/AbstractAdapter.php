<?php
declare(strict_types=1);

namespace Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\OpenSearch\Asset\FieldDefinitionAdapter;

use Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\Asset\AdapterInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\SearchIndexConfigServiceInterface;

abstract class AbstractAdapter implements AdapterInterface
{
    public function __construct(
        protected readonly SearchIndexConfigServiceInterface $searchIndexConfigService,
    )
    {
    }

    private string $type;

    public function setType(string $type): self
    {
        $this->type = $type;

        return $this;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getIndexMapping(): array
    {
        return [];
    }

    public function normalize(mixed $value): mixed
    {
        return $value;
    }

}