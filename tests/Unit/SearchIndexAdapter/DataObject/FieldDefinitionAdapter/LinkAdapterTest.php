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

namespace Pimcore\Bundle\GenericDataIndexBundle\Tests\Unit\SearchIndexAdapter\DataObject\FieldDefinitionAdapter;

use Codeception\Test\Unit;
use Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\DataObject\FieldDefinitionServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\DefaultSearch\DataObject\FieldDefinitionAdapter\LinkAdapter;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\SearchIndexConfigServiceInterface;

/**
 * @internal
 */
final class LinkAdapterTest extends Unit
{
    public function testGetSearchIndexMapping(): void
    {
        $searchIndexConfigServiceInterfaceMock = $this->makeEmpty(SearchIndexConfigServiceInterface::class);
        $fieldDefinitionServiceInterfaceMock = $this->makeEmpty(FieldDefinitionServiceInterface::class);
        $adapter = new LinkAdapter(
            $searchIndexConfigServiceInterfaceMock,
            $fieldDefinitionServiceInterfaceMock
        );

        $mapping = $adapter->getIndexMapping();

        $this->assertSame([
            'properties' => [
                'text' => [
                    'type' => 'text',
                ],
                'internalType' => [
                    'type' => 'keyword',
                ],
                'internal' => [
                    'type' => 'long',
                ],
                'direct' => [
                    'type' => 'keyword',
                ],
                'linktype' => [
                    'type' => 'keyword',
                ],
                'target' => [
                    'type' => 'keyword',
                ],
                'parameters' => [
                    'type' => 'text',
                ],
                'anchor' => [
                    'type' => 'keyword',
                ],
                'title' => [
                    'type' => 'text',
                ],
                'accesskey' => [
                    'type' => 'keyword',
                ],
                'rel' => [
                    'type' => 'keyword',
                ],
                'tabindex' => [
                    'type' => 'keyword',
                ],
                'class' => [
                    'type' => 'keyword',
                ],
                'attributes' => [
                    'type' => 'keyword',
                ],
                '_fieldname' => [
                    'type' => 'keyword',
                ],
                '_language' => [
                    'type' => 'keyword',
                ],
            ],
        ], $mapping);
    }
}
