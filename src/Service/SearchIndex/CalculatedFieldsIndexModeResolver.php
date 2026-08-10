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
use Pimcore\Bundle\GenericDataIndexBundle\Traits\LoggerAwareTrait;

/**
 * @internal
 */
final class CalculatedFieldsIndexModeResolver implements CalculatedFieldsIndexModeResolverInterface
{
    use LoggerAwareTrait;

    private ?CalculatedFieldsIndexMode $override = null;

    private bool $invalidEnvValueLogged = false;

    public function __construct(
        private readonly string $configuredMode = CalculatedFieldsIndexMode::LIVE->value,
    ) {
    }

    public function getMode(): CalculatedFieldsIndexMode
    {
        if ($this->override !== null) {
            return $this->override;
        }

        $envValue = getenv(self::ENV_VAR);
        if ($envValue !== false && $envValue !== '') {
            $envMode = CalculatedFieldsIndexMode::tryFrom($envValue);
            if ($envMode !== null) {
                return $envMode;
            }

            if (!$this->invalidEnvValueLogged) {
                $this->invalidEnvValueLogged = true;
                $this->logger->warning(sprintf(
                    'Ignoring invalid value "%s" of %s, falling back to the configured mode. Valid values: %s',
                    $envValue,
                    self::ENV_VAR,
                    implode(', ', array_column(CalculatedFieldsIndexMode::cases(), 'value'))
                ));
            }
        }

        return CalculatedFieldsIndexMode::from($this->configuredMode);
    }

    public function overrideMode(?CalculatedFieldsIndexMode $mode): void
    {
        $this->override = $mode;
    }
}
