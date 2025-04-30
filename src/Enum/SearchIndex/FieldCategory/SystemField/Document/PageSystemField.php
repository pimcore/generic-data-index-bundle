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

namespace Pimcore\Bundle\GenericDataIndexBundle\Enum\SearchIndex\FieldCategory\SystemField\Document;

use Pimcore\Bundle\GenericDataIndexBundle\Enum\SearchIndex\FieldCategory\SystemField\SystemFieldTrait;

enum PageSystemField: string
{
    use SystemFieldTrait;

    case TITLE = 'title';
    case DESCRIPTION = 'description';
    case PRETTY_URL = 'prettyUrl';
    case CONTROLLER = 'controller';
    case TEMPLATE = 'template';
    case CONTENT_MAIN_DOCUMENT_ID = 'contentMainDocumentId';
    case SUPPORTS_CONTENT_MAIN = 'supportsContentMain';
    case MISSING_REQUIRED_EDITABLE = 'missingRequiredEditable';
    case STATIC_GENERATOR_ENABLED = 'staticGeneratorEnabled';
    case STATIC_GENERATOR_LIFETIME = 'staticGeneratorLifetime';
}
