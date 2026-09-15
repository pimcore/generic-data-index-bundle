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
 * @internal
 */
final class CalculatedFieldsIndexModeResolver implements CalculatedFieldsIndexModeResolverInterface
{
    public function __construct(
        private readonly string $configuredMode = CalculatedFieldsIndexMode::LIVE->value,
    ) {
    }

    public function getMode(): CalculatedFieldsIndexMode
    {
        return CalculatedFieldsIndexMode::from($this->configuredMode);
    }
}
