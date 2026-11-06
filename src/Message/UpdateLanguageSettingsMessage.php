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

/**
 * @internal
 */
final readonly class UpdateLanguageSettingsMessage
{
    /**
     * @param int|null $dispatchedAt millisecond timestamp of the settings save that dispatched the message
     */
    public function __construct(
        private array $currentLanguages,
        private array $validLanguages,
        private ?int $dispatchedAt = null,
    ) {
    }

    public function getCurrentLanguages(): array
    {
        return $this->currentLanguages;
    }

    public function getValidLanguages(): array
    {
        return $this->validLanguages;
    }

    /**
     * Null for a message queued by a version without the timestamp.
     */
    public function getDispatchedAt(): ?int
    {
        // A message serialized by an earlier version has no such property.
        return isset($this->dispatchedAt) ? $this->dispatchedAt : null;
    }
}
