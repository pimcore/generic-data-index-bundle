<?php

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
