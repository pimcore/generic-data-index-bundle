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
