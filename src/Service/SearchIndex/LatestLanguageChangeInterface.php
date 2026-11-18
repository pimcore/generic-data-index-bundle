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

/**
 * Remembers the languages of the latest system settings save that dispatched a language message, so that an older
 * message retried after it does not reinstall the language list of its own save. The record lives in the settings
 * store, which all nodes and workers share, whatever version they run.
 *
 * @internal
 */
interface LatestLanguageChangeInterface
{
    /**
     * @param string[] $validLanguages
     */
    public function record(array $validLanguages): void;

    /**
     * True when the given languages are the ones of the latest recorded save, or when no save was recorded yet.
     *
     * @param string[] $validLanguages
     */
    public function isLatest(array $validLanguages): bool;
}
