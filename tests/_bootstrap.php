<?php

/**
 * Pimcore
 *
 * This source file is available under following license:
 * - Pimcore Commercial License (PCL)
 *
 *  @copyright  Copyright (c) Pimcore GmbH (http://www.pimcore.org)
 *  @license    http://www.pimcore.org/license     PCL
 */

use Pimcore\Tests\Support\Util\Autoloader;

if (getenv('PIMCORE_PROJECT_ROOT') == '') {
    define('PIMCORE_PROJECT_ROOT', dirname(__DIR__));
}

if (file_exists(getenv('PIMCORE_PROJECT_ROOT') . '/vendor/autoload.php')) {
    include realpath(getenv('PIMCORE_PROJECT_ROOT')) . '/vendor/autoload.php';
    $pimcoreTestDir = realpath(getenv('PIMCORE_PROJECT_ROOT')) . '/vendor/pimcore/pimcore/tests';
} else {
    throw new \Exception('Invalid Pimcore project root "' . getenv('PIMCORE_PROJECT_ROOT') . '"');
}

$pimcoreTestsSupportDir = $pimcoreTestDir . '/Support';
include $pimcoreTestsSupportDir . '/Util/Autoloader.php';

\Pimcore\Bootstrap::setProjectRoot();
\Pimcore\Bootstrap::bootstrap();

//error_reporting(E_ALL & ~E_NOTICE & ~E_STRICT & ~E_WARNING);

//Codeception\Util\Autoload::addNamespace();
Autoloader::addNamespace('Pimcore\Tests', $pimcoreTestsSupportDir);
Autoloader::addNamespace('Pimcore\Tests\Support', $pimcoreTestsSupportDir);

//Autoloader::addNamespace('Pimcore\Model\DataObject', __DIR__ . '/_output/var/classes/DataObject');
Autoloader::addNamespace('Pimcore\Model\DataObject', PIMCORE_CLASS_DIRECTORY . '/DataObject');
Autoloader::addNamespace('Pimcore\Bundle\GenericDataIndexBundle\Tests', __DIR__);
Autoloader::addNamespace('Pimcore\Bundle\GenericDataIndexBundle\Tests', __DIR__ . '/_support');

if (!defined('TESTS_PATH')) {
    define('TESTS_PATH', __DIR__);
}

if (!defined('PIMCORE_TEST')) {
    define('PIMCORE_TEST', true);
}
