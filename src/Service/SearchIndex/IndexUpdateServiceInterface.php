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

    public function setReCreateIndex(bool $reCreateIndex): self;
}
