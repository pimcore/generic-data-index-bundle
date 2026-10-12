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

namespace Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\DefaultSearch\Search\Processor;

/**
 * Runs the tagged search body processors in registration order — the single owner of the chain's
 * ordering and error semantics for both the search and the count path.
 *
 * @internal
 */
final readonly class SearchBodyProcessorPipeline
{
    /**
     * @param iterable<SearchBodyProcessorInterface> $searchBodyProcessors
     */
    public function __construct(
        private iterable $searchBodyProcessors = [],
    ) {
    }

    /**
     * @param array<string, mixed> $body
     *
     * @return array<string, mixed>
     */
    public function process(array $body, string $indexName): array
    {
        foreach ($this->searchBodyProcessors as $searchBodyProcessor) {
            $body = $searchBodyProcessor->process($body, $indexName);
        }

        return $body;
    }
}
