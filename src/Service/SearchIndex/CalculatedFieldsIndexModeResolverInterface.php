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
 * Resolves the effective calculated-fields index mode. Precedence:
 *
 *   1. a process-level override (set e.g. by a CLI option),
 *   2. the GENERIC_DATA_INDEX_CALCULATED_FIELDS_MODE environment variable — value
 *      extraction happens in the queue worker processes, so overriding the mode for a
 *      reindex cycle means setting the environment variable on the workers,
 *   3. the configured `calculated_fields_index_mode`.
 *
 * @internal
 */
interface CalculatedFieldsIndexModeResolverInterface
{
    public const ENV_VAR = 'GENERIC_DATA_INDEX_CALCULATED_FIELDS_MODE';

    public function getMode(): CalculatedFieldsIndexMode;

    /**
     * Process-level override, e.g. from a CLI option. Pass null to clear.
     */
    public function overrideMode(?CalculatedFieldsIndexMode $mode): void;
}
