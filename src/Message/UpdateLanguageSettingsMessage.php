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

namespace Pimcore\Bundle\GenericDataIndexBundle\Message;

/**
 * @internal
 */
final readonly class UpdateLanguageSettingsMessage
{
    public function __construct(
        private array $currentLanguages,
        private array $validLanguages,
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
}