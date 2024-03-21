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
