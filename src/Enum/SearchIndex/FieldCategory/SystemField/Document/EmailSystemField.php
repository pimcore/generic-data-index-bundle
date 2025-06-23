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

enum EmailSystemField: string
{
    use SystemFieldTrait;

    case SUBJECT = 'subject';
    case FROM = 'from';
    case REPLY_TO = 'replyTo';
    case TO = 'to';
    case CC = 'cc';
    case BCC = 'bcc';
}
