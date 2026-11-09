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

namespace Pimcore\Bundle\GenericDataIndexBundle\Tests\Unit\Service\Search\SearchService;

use Codeception\Test\Unit;
use Error;
use Exception;
use LogicException;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Interfaces\SearchInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\Search\SearchService\IndexNameResolverInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\Search\SearchService\MappedFieldPathService;
use Pimcore\Bundle\GenericDataIndexBundle\Service\Search\SearchService\SearchPqlFieldNameTransformationServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\CachedSearchIndexMappingServiceInterface;
use RuntimeException;

/**
 * @internal
 */
final class MappedFieldPathServiceTest extends Unit
{
    private const FIELD_PATH = 'standard_fields.store.group.default.material';

    private const INDEX_WITH_MULTI_FIELD = 'withmultifield';

    private const INDEX_WITHOUT_MULTI_FIELD = 'withoutmultifield';

    /**
     * Field name resolution decides over all the indices behind the alias at once, so one index
     * carrying the `keyword` multi-field moves the query onto `<path>.keyword` for every index. The
     * declaration has to be about that path: the index that declares the key without the multi-field
     * does not answer the query, and reporting it as declaring would hand a missing-value filter an
     * absent path - which `must_not exists` satisfies for every document of that index.
     *
     * @throws Exception
     */
    public function testTheDeclarationIsAnsweredAboutTheResolvedPath(): void
    {
        $declaration = $this->getService(self::FIELD_PATH . '.keyword')->resolveDeclaration(
            $this->makeEmpty(SearchInterface::class),
            self::FIELD_PATH,
            pqlFieldNameResolutionEnabled: true,
        );

        $this->assertSame([self::INDEX_WITH_MULTI_FIELD], $declaration->getDeclaringIndices());
        $this->assertTrue($declaration->isDeclaredByPartOfTheIndices());
        $this->assertFalse($declaration->isUndeclaredEverywhere());
    }

    /**
     * The control: with resolution off the query reads the path as it was written, both indices
     * declare it, and nothing is narrowed. The transformation service is not reached at all - the
     * stub throws if it is.
     *
     * @throws Exception
     */
    public function testTheDeclarationIsAnsweredAboutThePathAsGivenWhenResolutionIsOff(): void
    {
        $declaration = $this->getService()->resolveDeclaration(
            $this->makeEmpty(SearchInterface::class),
            self::FIELD_PATH,
        );

        $this->assertSame(
            [self::INDEX_WITH_MULTI_FIELD, self::INDEX_WITHOUT_MULTI_FIELD],
            $declaration->getDeclaringIndices()
        );
        $this->assertFalse($declaration->isDeclaredByPartOfTheIndices());
        $this->assertFalse($declaration->isUndeclaredEverywhere());
    }

    /**
     * @throws Exception
     */
    public function testAMappingThatCouldNotBeReadIsUnanswered(): void
    {
        $service = new MappedFieldPathService(
            $this->makeEmpty(IndexNameResolverInterface::class, ['resolveIndexName' => 'alias']),
            $this->makeEmpty(CachedSearchIndexMappingServiceInterface::class, [
                'getMapping' => static fn () => throw new RuntimeException('the cluster did not answer'),
            ]),
            $this->makeEmpty(SearchPqlFieldNameTransformationServiceInterface::class),
        );

        $declaration = $service->resolveDeclaration(
            $this->makeEmpty(SearchInterface::class),
            self::FIELD_PATH,
            pqlFieldNameResolutionEnabled: true,
        );

        $this->assertSame([], $declaration->getDeclaringIndices());
        $this->assertFalse($declaration->isDeclaredByPartOfTheIndices());
        $this->assertFalse($declaration->isUndeclaredEverywhere());
    }

    /**
     * A programming error is not a mapping that could not be read. Swallowing it would answer
     * "unanswered", and the caller's fallback for that is to build the query it would have built
     * without the guard - so the bug would show up as a filter that matches everything.
     *
     * @throws Exception
     */
    public function testAProgrammingErrorIsNotReportedAsUnanswered(): void
    {
        $service = new MappedFieldPathService(
            $this->makeEmpty(IndexNameResolverInterface::class, [
                'resolveIndexName' => static fn () => throw new Error('typed property is not initialized'),
            ]),
            $this->makeEmpty(CachedSearchIndexMappingServiceInterface::class),
            $this->makeEmpty(SearchPqlFieldNameTransformationServiceInterface::class),
        );

        $this->expectException(Error::class);

        $service->resolveDeclaration($this->makeEmpty(SearchInterface::class), self::FIELD_PATH);
    }

    /**
     * @throws Exception
     */
    private function getService(?string $resolvedFieldPath = null): MappedFieldPathService
    {
        return new MappedFieldPathService(
            $this->makeEmpty(IndexNameResolverInterface::class, ['resolveIndexName' => 'alias']),
            $this->makeEmpty(CachedSearchIndexMappingServiceInterface::class, [
                'getMapping' => $this->getIndexMappings(),
            ]),
            $this->makeEmpty(SearchPqlFieldNameTransformationServiceInterface::class, [
                'transformFieldnameForSearch' => $resolvedFieldPath
                    ?? static fn () => throw new LogicException('the field name must not be resolved'),
            ]),
        );
    }

    /**
     * Two indices behind one alias, declaring the same key: one as a text key with the `keyword` and
     * `sort` multi-fields this bundle gives a text key, one without them - which is what an index that
     * was built when the key had another type still holds.
     */
    private function getIndexMappings(): array
    {
        return [
            self::INDEX_WITH_MULTI_FIELD => $this->mappingForKey([
                'type' => 'text',
                'fields' => [
                    'keyword' => ['type' => 'keyword', 'ignore_above' => 1024],
                    'sort' => ['type' => 'keyword', 'ignore_above' => 1024],
                ],
            ]),
            self::INDEX_WITHOUT_MULTI_FIELD => $this->mappingForKey(['type' => 'keyword']),
        ];
    }

    /**
     * The mapping of one index declaring self::FIELD_PATH, one container level per path segment.
     */
    private function mappingForKey(array $keyMapping): array
    {
        $mapping = $keyMapping;
        foreach (array_reverse(explode('.', self::FIELD_PATH)) as $segment) {
            $mapping = ['properties' => [$segment => $mapping]];
        }

        return ['mappings' => $mapping];
    }
}
