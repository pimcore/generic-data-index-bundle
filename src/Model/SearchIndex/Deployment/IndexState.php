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

namespace Pimcore\Bundle\GenericDataIndexBundle\Model\SearchIndex\Deployment;

/**
 * Mapping checksum, settings hash and data version of an index, either as currently configured
 * or as stored after the last successful update. A null value was never stored.
 *
 * @internal
 */
final readonly class IndexState
{
    public function __construct(
        private ?string $mappingChecksum,
        private ?string $settingsHash,
        private ?string $dataVersion,
    ) {
    }

    public function getMappingChecksum(): ?string
    {
        return $this->mappingChecksum;
    }

    public function getSettingsHash(): ?string
    {
        return $this->settingsHash;
    }

    public function getDataVersion(): ?string
    {
        return $this->dataVersion;
    }
}
