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

namespace Pimcore\Bundle\GenericDataIndexBundle\Tests\Unit\DependencyInjection;

use Codeception\Test\Unit;
use Pimcore\Bundle\GenericDataIndexBundle\Enum\SearchIndex\FieldCategory\SystemField;
use Symfony\Component\Yaml\Yaml;

/**
 * @internal
 */
final class SystemFieldsMappingConfigTest extends Unit
{
    /**
     * @dataProvider writtenDataObjectFieldProvider
     */
    public function testDataObjectSystemFieldsWrittenByTheNormalizerAreMapped(SystemField $field): void
    {
        $settings = $this->getSystemFieldsSettings();
        $declared = array_merge($settings['general'], $settings['data_object']);

        $this->assertArrayHasKey(
            $field->value,
            $declared,
            sprintf('System field "%s" is indexed for data objects but has no configured mapping', $field->value)
        );
        $this->assertSame(
            'keyword',
            $declared[$field->value]['fields']['keyword']['type'] ?? null,
            'Filters of this and other bundles address the ".keyword" sub field'
        );
    }

    public static function writtenDataObjectFieldProvider(): array
    {
        return [
            'classId' => [SystemField::CLASS_ID],
            'className' => [SystemField::CLASS_NAME],
        ];
    }

    private function getSystemFieldsSettings(): array
    {
        $config = Yaml::parseFile(__DIR__ . '/../../../config/pimcore/config.yaml');

        return $config['pimcore_generic_data_index']['index_service']['system_fields_settings'];
    }
}
