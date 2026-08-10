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

namespace Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex;

use Pimcore\Bundle\GenericDataIndexBundle\Enum\SearchIndex\CalculatedFieldsIndexMode;

/**
 * Resolves the effective calculated-fields index mode: the process-level override if set
 * (e.g. from a reindex CLI option, carried to the worker via IndexUpdateQueueMessage),
 * otherwise the configured mode.
 *
 * @internal
 */
interface CalculatedFieldsIndexModeResolverInterface
{
    public function getMode(): CalculatedFieldsIndexMode;

    /**
     * The current process-level override, or null when the configured mode applies.
     */
    public function getOverrideMode(): ?CalculatedFieldsIndexMode;

    /**
     * Set a process-level override, or null to clear it.
     */
    public function overrideMode(?CalculatedFieldsIndexMode $mode): void;
}
