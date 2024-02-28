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

namespace Pimcore\Bundle\GenericDataIndexBundle\Tests\Unit\Service\SearchIndex\DataObject\FieldDefinitionAdapter;

use Codeception\Test\Unit;
use Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\DataObject\FieldDefinitionServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\OpenSearch\DataObject\FieldDefinitionAdapter\LinkAdapter;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\SearchIndexConfigServiceInterface;

/**
 * @internal
 */
final class LinkAdapterTest extends Unit
{
    public function testGetOpenSearchMapping(): void
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
