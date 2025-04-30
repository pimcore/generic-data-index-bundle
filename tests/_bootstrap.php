<?php

/**
 * This source file is available under the terms of the
 * Pimcore Open Core License (POCL)
 * Full copyright and license information is available in
 * LICENSE.md which is distributed with this source code.
 *
 *  @copyright  Copyright (c) Pimcore GmbH (https://www.pimcore.com)
 *  @license    Pimcore Open Core License (POCL)
 */

use Pimcore\Tests\Support\Util\Autoloader;

if (file_exists('../../vendor/autoload.php')) {
    $vendorLocation = '../../vendor/autoload.php';
    $projectRoot = '../../';
} elseif (file_exists('../vendor/autoload.php')) {
    $vendorLocation = '../vendor/autoload.php';
    $projectRoot = '../';
} elseif (file_exists('vendor/autoload.php')) {
    $vendorLocation = 'vendor/autoload.php';
    $projectRoot = '';
} else {
    throw new \Exception('Vendor location not found! Please run composer install.');
}

include $vendorLocation;
$pimcoreTestDir =  $projectRoot . 'vendor/pimcore/pimcore/tests';

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
Autoloader::addNamespace('Pimcore\Bundle\GenericDataIndexBundle\Tests', __DIR__ . '/Support');

if (!defined('TESTS_PATH')) {
    define('TESTS_PATH', __DIR__);
}

if (!defined('PIMCORE_TEST')) {
    define('PIMCORE_TEST', true);
}
