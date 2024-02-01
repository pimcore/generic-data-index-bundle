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

namespace Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex;

/**
 * @internal
 */
interface LanguageServiceInterface
{
    public function setValidLanguages(array $argLanguages): void;

    public function getValidLanguages(): array;

    public function getNewLanguages(array $validLanguages): array;
}
