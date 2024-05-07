<?php
declare(strict_types=1);

/**
 * Pimcore
 *
 * This source file is available under two different licenses:
 * - GNU General Public License version 3 (GPLv3)
 * - Pimcore Commercial License (PCL)
 * Full copyright and license information is available in
 * LICENSE.md which is distributed with this source code.
 *
 *  @copyright  Copyright (c) Pimcore GmbH (http://www.pimcore.org)
 *  @license    http://www.pimcore.org/license     GPLv3 and PCL
 */

namespace Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex;

use Exception;
use Pimcore\Model\DataObject\ClassDefinition;

/**
 * @internal
 */
interface IndexUpdateServiceInterface
{
    /**
     * @throws Exception
     */
    public function updateAll(): self;

    /**
     * @throws Exception
     */
    public function updateClassDefinitions(): self;

    /**
     * @throws Exception
     */
    public function updateClassDefinition(ClassDefinition $classDefinition): self;

    /**
     * @throws Exception
     */
    public function updateAssets(): self;

    /**
     * @throws Exception
     */
    public function updateDocuments(): self;

    public function setReCreateIndex(bool $reCreateIndex): self;
}
