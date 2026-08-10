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

namespace Pimcore\Bundle\GenericDataIndexBundle\Message;

use Pimcore\Bundle\GenericDataIndexBundle\Enum\SearchIndex\CalculatedFieldsIndexMode;

final readonly class IndexUpdateQueueMessage
{
    public function __construct(
        private array $entries,
        private ?CalculatedFieldsIndexMode $calculatedFieldsIndexMode = null,
    ) {
    }

    public function getEntries(): array
    {
        return $this->entries;
    }

    /**
     * Per-run override of the calculated-fields index mode, set when a reindex was triggered
     * with --calculated-fields-mode. Null means "use the configured mode". Carried on the
     * message so the override reaches the (separate) worker process that extracts values.
     */
    public function getCalculatedFieldsIndexMode(): ?CalculatedFieldsIndexMode
    {
        return $this->calculatedFieldsIndexMode;
    }
}
