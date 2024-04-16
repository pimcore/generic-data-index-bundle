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

namespace Pimcore\Bundle\GenericDataIndexBundle\Tests\Helper;

// here you can define custom actions
// all public methods declared in helper class will be available in $I

use Pimcore\Model\Asset;
use Pimcore\Model\DataObject\ClassDefinition;
use Pimcore\Model\DataObject\Simple;
use Pimcore\Model\DataObject\Unittest;
use Pimcore\Tests\Support\Helper\ClassManager;
use Pimcore\Tests\Support\Helper\DataType\TestDataHelper;
use Pimcore\Tests\Support\Helper\Model;
use Pimcore\Tests\Support\Util\TestHelper;

class Index extends Model
{
    public function initializeDefinitions(): void
    {
        $this->setupFieldcollection_Unittestfieldcollection();
        $this->setupPimcoreClass_Unittest();
        $this->setupObjectbrick_UnittestBrick();
        $this->setupPimcoreClass_Simple();
        $this->setupPimcoreClass_Simple('mappingTest');
    }

    /**
     * Set up a class used for Link Test.
     *
     * @param string $name
     * @param string $filename
     *
     * @return ClassDefinition|null
     *
     * @throws \Exception
     */
    public function setupPimcoreClass_Simple($name = 'simple', $filename = 'simple-import.json'): ?ClassDefinition
    {
        /** @var ClassManager $cm */
        $cm = $this->getModule('\\' . ClassManager::class);

        if (!$class = $cm->getClass($name)) {
            $root = new ClassDefinition\Layout\Panel();
            $panel = (new ClassDefinition\Layout\Panel())->setName('MyLayout');
            $rootPanel = (new ClassDefinition\Layout\Tabpanel())->setName('Layout');
            $rootPanel->addChild($panel);

            $nameField = new ClassDefinition\Data\Input();
            $nameField->setName('name');
            $textareaField = new ClassDefinition\Data\Textarea();
            $textareaField->setName('textarea');

            $lFields = new \Pimcore\Model\DataObject\ClassDefinition\Data\Localizedfields();
            $lFields->setName('localizedfields');

            $lnameField = new ClassDefinition\Data\Input();
            $lnameField->setName('loc_name');

            $lFields->addChild($lnameField);

            $panel->addChild($nameField);
            $panel->addChild($textareaField);
            $panel->addChild($lFields);
            $root->addChild($rootPanel);
            $class = $this->createClass($name, $root, $filename, true, null, false);
        }

        return $class;
    }

    /**
     * Set up a class which (hopefully) contains all data types
     *
     * @param string $name
     * @param string $filename
     *
     * @return ClassDefinition|null
     *
     * @throws \Exception
     */
    public function setupPimcoreClass_Unittest($name = 'unittest', $filename = 'class-import.json'): ?ClassDefinition
    {

        /** @var ClassManager $cm */
        $cm = $this->getModule('\\' . ClassManager::class);

        if (!$class = $cm->getClass($name)) {
            $root = new \Pimcore\Model\DataObject\ClassDefinition\Layout\Panel('root');
            $panel = (new \Pimcore\Model\DataObject\ClassDefinition\Layout\Panel())->setName('MyLayout');
            $rootPanel = (new \Pimcore\Model\DataObject\ClassDefinition\Layout\Tabpanel())->setName('Layout');
            $rootPanel->addChild($panel);

            $calculatedValue = $this->createDataChild('calculatedValue');
            $calculatedValue->setCalculatorClass('@test.calculatorservice');
            $panel->addChild($calculatedValue);

            $calculatedValueExpression = $this->createDataChild('calculatedValue', 'calculatedValueExpression');
            $calculatedValueExpression->setCalculatorExpression("object.getFirstname() ~ ' some calc'");
            $calculatedValueExpression->setCalculatorType(ClassDefinition\Data\CalculatedValue::CALCULATOR_TYPE_EXPRESSION);
            $panel->addChild($calculatedValueExpression);

            $calculatedValueExpressionConstant = $this->createDataChild('calculatedValue', 'calculatedValueExpressionConstant');
            $calculatedValueExpressionConstant->setCalculatorExpression("constant('PIMCORE_PROJECT_ROOT')");
            $calculatedValueExpressionConstant->setCalculatorType(ClassDefinition\Data\CalculatedValue::CALCULATOR_TYPE_EXPRESSION);
            $panel->addChild($calculatedValueExpressionConstant);

            $panel->addChild($this->createDataChild('consent'));

            $panel->addChild($this->createDataChild('country'));
            $panel->addChild($this->createDataChild('countrymultiselect', 'countries'));

            $panel->addChild($this->createDataChild('date'));
            $panel->addChild($this->createDataChild('datetime'));

            $panel->addChild($this->createDataChild('email'));

            /** @var ClassDefinition\Data\EncryptedField $encryptedField */
            $encryptedField = $this->createDataChild('encryptedField');

            $encryptedField->setDelegateDatatype('input');
            $panel->addChild($encryptedField);

            $panel->addChild($this->createDataChild('externalImage'));

            $panel->addChild($this->createDataChild('firstname'));

            $panel->addChild($this->createDataChild('gender'));

            $panel->addChild($this->createDataChild('geopoint', 'point', false, false));
            $panel->addChild($this->createDataChild('geobounds', 'bounds', false, false));
            $panel->addChild($this->createDataChild('geopolygon', 'polygon', false, false));
            $panel->addChild($this->createDataChild('geopolyline', 'polyline', false, false));

            //            $panel->addChild($this->createDataChild('indexFieldSelection', 'indexFieldSelection', false, false));
            //            $panel->addChild($this->createDataChild('indexFieldSelectionCombo', 'indexFieldSelectionCombo', false, false));
            //            $panel->addChild($this->createDataChild('indexFieldSelectionField', 'indexFieldSelectionField', false, false));

            $panel->addChild($this->createDataChild('imageGallery'));
            $panel->addChild($this->createDataChild('input'));
            /** @var ClassDefinition\Data\Input $inputWithDefault */
            $inputWithDefault = $this->createDataChild('input', 'inputWithDefault');
            $inputWithDefault->setDefaultValue('default');
            $panel->addChild($inputWithDefault);

            $panel->addChild($this->createDataChild('manyToOneRelation', 'lazyHref')
                ->setDocumentTypes([])->setAssetTypes([])->setClasses([])
                ->setDocumentsAllowed(true)->setAssetsAllowed(true)->setObjectsAllowed(true));

            $panel->addChild($this->createDataChild('manyToManyRelation', 'lazyMultihref')
                ->setDocumentTypes([])->setAssetTypes([])->setClasses([])
                ->setDocumentsAllowed(true)->setAssetsAllowed(true)->setObjectsAllowed(true));

            $panel->addChild($this->createDataChild('manyToManyObjectRelation', 'lazyObjects')
                ->setClasses([]));

            $panel->addChild($this->createDataChild('manyToOneRelation', 'href')
                ->setDocumentTypes([])->setAssetTypes([])->setClasses([])
                ->setDocumentsAllowed(true)->setAssetsAllowed(true)->setObjectsAllowed(true));

            $panel->addChild($this->createDataChild('manyToManyRelation', 'multihref')
                ->setDocumentTypes([])->setAssetTypes([])->setClasses([])
                ->setDocumentsAllowed(true)->setAssetsAllowed(true)->setObjectsAllowed(true));

            $panel->addChild($this->createDataChild('manyToManyObjectRelation', 'objects')
                ->setClasses([]));

            $panel->addChild($this->createDataChild('inputQuantityValue'));
            $panel->addChild($this->createDataChild('quantityValue'));

            $panel->addChild($this->createDataChild('advancedManyToManyObjectRelation', 'objectswithmetadata')
                ->setAllowedClassId($name)
                ->setClasses([])
                ->setColumns([ ['position' => 1, 'key' => 'meta1', 'type' => 'text', 'label' => 'label1'],
                    ['position' => 2, 'key' => 'meta2', 'type' => 'text', 'label' => 'label2'], ]));

            $panel->addChild($this->createDataChild('lastname'));

            $panel->addChild($this->createDataChild('numeric', 'number'));

            $passwordField = $this->createDataChild('password');
            $passwordField->setAlgorithm(ClassDefinition\Data\Password::HASH_FUNCTION_PASSWORD_HASH);
            $panel->addChild($passwordField);

            $panel->addChild($this->createDataChild('rgbaColor', 'rgbaColor', false, false));

            $panel->addChild($this->createDataChild('select')->setOptions([
                ['key' => 'Selection 1', 'value' => '1'],
                ['key' => 'Selection 2', 'value' => '2'], ]));

            $panel->addChild($this->createDataChild('slider'));

            $panel->addChild($this->createDataChild('textarea'));
            $panel->addChild($this->createDataChild('time'));

            $panel->addChild($this->createDataChild('wysiwyg'));

            $panel->addChild($this->createDataChild('video', 'video', false, false));

            $panel->addChild($this->createDataChild('multiselect')->setOptions([
                ['key' => 'Katze', 'value' => 'cat'],
                ['key' => 'Kuh', 'value' => 'cow'],
                ['key' => 'Tiger', 'value' => 'tiger'],
                ['key' => 'Schwein', 'value' => 'pig'],
                ['key' => 'Esel', 'value' => 'donkey'],
                ['key' => 'Affe', 'value' => 'monkey'],
                ['key' => 'Huhn', 'value' => 'chicken'],
            ]));

            $panel->addChild($this->createDataChild('language', 'languagex'));
            $panel->addChild($this->createDataChild('languagemultiselect', 'languages'));
            $panel->addChild($this->createDataChild('user'));
            $panel->addChild($this->createDataChild('link'));
            $panel->addChild($this->createDataChild('image'));
            $panel->addChild($this->createDataChild('hotspotimage'));
            $panel->addChild($this->createDataChild('checkbox'));
            $panel->addChild($this->createDataChild('booleanSelect'));
            $panel->addChild($this->createDataChild('table'));
            $panel->addChild($this->createDataChild('structuredTable', 'structuredtable', false, false)
                ->setCols([
                    ['position' => 1, 'key' => 'col1', 'type' => 'number', 'label' => 'collabel1'],
                    ['position' => 2, 'key' => 'col2', 'type' => 'text', 'label' => 'collabel2'],
                ])
                ->setRows([
                    ['position' => 1, 'key' => 'row1', 'label' => 'rowlabel1'],
                    ['position' => 2, 'key' => 'row2', 'label' => 'rowlabel2'],
                    ['position' => 3, 'key' => 'row3', 'label' => 'rowlabel3'],
                ])
            );
            $panel->addChild($this->createDataChild('fieldcollections', 'fieldcollection')
                ->setAllowedTypes(['unittestfieldcollection']));
            $panel->addChild($this->createDataChild('reverseObjectRelation', 'nonowner')->setOwnerClassName($name)->setOwnerFieldName('objects'));
            $panel->addChild($this->createDataChild('fieldcollections', 'myfieldcollection')
                ->setAllowedTypes(['unittestfieldcollection']));

            $panel->addChild($this->createDataChild('urlSlug')->setAction('MyController::myAction'));
            $panel->addChild($this->createDataChild('urlSlug', 'urlSlug2')->setAction('MyController::myAction'));

            $lFields = new \Pimcore\Model\DataObject\ClassDefinition\Data\Localizedfields();
            $lFields->setName('localizedfields');
            $lFields->addChild($this->createDataChild('input', 'linput'));
            $lFields->addChild($this->createDataChild('textarea', 'ltextarea'));
            $lFields->addChild($this->createDataChild('wysiwyg', 'lwysiwyg'));
            $lFields->addChild($this->createDataChild('numeric', 'lnumber'));
            $lFields->addChild($this->createDataChild('slider', 'lslider'));
            $lFields->addChild($this->createDataChild('date', 'ldate'));
            $lFields->addChild($this->createDataChild('datetime', 'ldatetime'));
            $lFields->addChild($this->createDataChild('time', 'ltime'));
            $lFields->addChild($this->createDataChild('select', 'lselect')->setOptions([
                ['key' => 'one', 'value' => '1'],
                ['key' => 'two', 'value' => '2'], ]));

            $lFields->addChild($this->createDataChild('multiselect', 'lmultiselect')->setOptions([
                ['key' => 'one', 'value' => '1'],
                ['key' => 'two', 'value' => '2'], ]));
            $lFields->addChild($this->createDataChild('countrymultiselect', 'lcountries'));
            $lFields->addChild($this->createDataChild('languagemultiselect', 'llanguages'));
            $lFields->addChild($this->createDataChild('table', 'ltable'));
            $lFields->addChild($this->createDataChild('image', 'limage'));
            $lFields->addChild($this->createDataChild('checkbox', 'lcheckbox'));
            $lFields->addChild($this->createDataChild('link', 'llink'));
            $lFields->addChild($this->createDataChild('manyToManyObjectRelation', 'lobjects')
                ->setClasses([]));

            $lFields->addChild($this->createDataChild('manyToManyRelation', 'lmultihrefLazy')
                ->setDocumentTypes([])->setAssetTypes([])->setClasses([])
                ->setDocumentsAllowed(true)->setAssetsAllowed(true)->setObjectsAllowed(true));

            $lFields->addChild($this->createDataChild('urlSlug', 'lurlSlug')->setAction('MyController::myLocalizedAction'));

            $panel->addChild($lFields);
            $panel->addChild($this->createDataChild('objectbricks', 'mybricks'));

            $root->addChild($rootPanel);
            $class = $this->createClass($name, $root, $filename, false, $name);
        }

        return $class;
    }

    public function createFullyFledgedObjectSimple($keyPrefix = '', $save = true, $publish = true, $seed = 1)
    {
        $testDataHelper = $this->getModule('\\' . TestDataHelper::class);

        $object = new Simple();
        $object->setOmitMandatoryCheck(true);
        $object->setParentId(1);
        $object->setUserOwner(1);
        $object->setUserModification(1);
        $object->setCreationDate(time());
        $object->setKey($keyPrefix . uniqid() . rand(10, 99));

        if ($publish) {
            $object->setPublished(true);
        }

        $testDataHelper->fillInput($object, 'name', $seed);
        $testDataHelper->fillTextarea($object, 'textarea', $seed);
        $testDataHelper->fillInput($object, 'loc_name', $seed, 'de');

        if ($save) {
            $object->save();
        }

        return $object;
    }

    /**
     * @param string $keyPrefix
     * @param bool $save
     * @param bool $publish
     * @param int $seed
     *
     * @return Unittest
     */
    public function createFullyFledgedObjectUnittest($keyPrefix = '', $save = true, $publish = true, $seed = 1)
    {
        $testDataHelper = $this->getModule('\\' . TestDataHelper::class);

        if (null === $keyPrefix) {
            $keyPrefix = '';
        }

        $object = new Unittest();
        $object->setOmitMandatoryCheck(true);
        $object->setParentId(1);
        $object->setUserOwner(1);
        $object->setUserModification(1);
        $object->setCreationDate(time());
        $object->setKey($keyPrefix . uniqid() . rand(10, 99));

        if ($publish) {
            $object->setPublished(true);
        }

        $testDataHelper->fillInput($object, 'input', $seed);
        $testDataHelper->fillNumber($object, 'number', $seed);
        $testDataHelper->fillTextarea($object, 'textarea', $seed);
        $testDataHelper->fillSlider($object, 'slider', $seed);
        $testDataHelper->fillHref($object, 'href', $seed);
        $testDataHelper->fillMultihref($object, 'multihref', $seed);
        $testDataHelper->fillImage($object, 'image', $seed);
        $testDataHelper->fillHotspotImage($object, 'hotspotimage', $seed);
        $testDataHelper->fillLanguage($object, 'languagex', $seed);
        $testDataHelper->fillCountry($object, 'country', $seed);
        $testDataHelper->fillDate($object, 'date', $seed);
        $testDataHelper->fillDate($object, 'datetime', $seed);
        $testDataHelper->fillTime($object, 'time', $seed);
        $testDataHelper->fillSelect($object, 'select', $seed);
        $testDataHelper->fillMultiSelect($object, 'multiselect', $seed);
        $testDataHelper->fillUser($object, 'user', $seed);
        $testDataHelper->fillCheckbox($object, 'checkbox', $seed);
        $testDataHelper->fillBooleanSelect($object, 'booleanSelect', $seed);
        $testDataHelper->fillWysiwyg($object, 'wysiwyg', $seed);
        $testDataHelper->fillPassword($object, 'password', $seed);
        $testDataHelper->fillMultiSelect($object, 'countries', $seed);
        $testDataHelper->fillMultiSelect($object, 'languages', $seed);
        $testDataHelper->fillGeoCoordinates($object, 'point', $seed);
        $testDataHelper->fillGeobounds($object, 'bounds', $seed);
        $testDataHelper->fillGeopolygon($object, 'polygon', $seed);
        $testDataHelper->fillTable($object, 'table', $seed);
        $testDataHelper->fillLink($object, 'link', $seed);
        $testDataHelper->fillStructuredTable($object, 'structuredtable', $seed);

        $testDataHelper->fillInput($object, 'linput', $seed, 'de');
        $testDataHelper->fillInput($object, 'linput', $seed, 'en');

        $testDataHelper->fillBricks($object, 'mybricks', $seed);
        $testDataHelper->fillFieldCollection($object, 'myfieldcollection', $seed);

        $testDataHelper->fillObjects($object, 'objects', $seed);
        $testDataHelper->fillObjects($object, 'lobjects', $seed, 'de');
        $testDataHelper->fillObjects($object, 'lobjects', $seed, 'en');
        $testDataHelper->fillObjectsWithMetadata($object, 'objectswithmetadata', $seed);

        if ($save) {
            $object->save();
        }

        return $object;
    }

    public function initSimpleDataObjectData(): array
    {

        $ids = [];

        $object1 = $this->createFullyFledgedObjectSimple('first_');
        $object1->setName('first input');
        $object1->setTextarea('foo');
        $object1->save();
        $ids[1] = $object1->getId();

        $object2 = $this->createFullyFledgedObjectSimple('second_');
        $object2->setName('second input');
        $object2->setTextarea('foo');
        $object2->save();
        $ids[2] = $object2->getId();

        $object3 = $this->createFullyFledgedObjectSimple('third_');
        $object3->setParent(\Pimcore\Model\DataObject\Service::createFolderByPath('/subfolder'));
        $object3->setName('third input');
        $object3->save();
        $ids[3] = $object3->getId();

        $object4 = $this->createFullyFledgedObjectSimple('forth_');
        $object4->setParent(\Pimcore\Model\DataObject\Service::createFolderByPath('/subfolder'));
        $object4->setName('forth input');
        $object4->setTextarea('foo');
        $object4->save();
        $ids[4] = $object4->getId();

        return $ids;
    }

    public function applyAssetMetadata(Asset $asset, array $data)
    {

    }

    public function initAssetData(): array
    {

        $ids = [];

        $asset1 = TestHelper::createImageAsset();
        $metadata = [
            'License.license' => 'image license',
        ];
        $this->applyAssetMetadata($asset1, $metadata);
        $ids[1] = $asset1->getId();

        $asset2 = TestHelper::createDocumentAsset();
        $metadata = [
            'License.license' => 'document license',
        ];
        $this->applyAssetMetadata($asset2, $metadata);
        $ids[2] = $asset2->getId();

        $asset3 = TestHelper::createVideoAsset();
        $metadata = [
            'License.license' => 'video license',
        ];
        $folder = Asset\Service::createFolderByPath('/subfolder');
        $asset3->setParent($folder);
        $this->applyAssetMetadata($asset3, $metadata);
        $ids[3] = $asset3->getId();

        return $ids;

    }

    public function initImageAssetData(): array
    {

        $ids = [];
        $folder = Asset\Service::createFolderByPath('/testfolder');
        $subFolder = Asset\Service::createFolderByPath('/testfolder/subfolder');

        $ownerId = 1;
        $asset1 = TestHelper::createImageAsset('asset1', null, false);
        $asset1->setParent($folder);
        $asset1->save();
        $ids[0] = $asset1->getId();

        $asset2 = TestHelper::createImageAsset('asset2', null, false);
        $asset2->setParent($folder);
        $asset2->setUserOwner($ownerId);
        $asset2->save();
        $ids[1] = $asset2->getId();

        $asset3 = TestHelper::createImageAsset('asset3', null, false);
        $asset3->setParent($subFolder);
        $asset3->save();
        $ids[2] = $asset3->getId();

        $asset4 = TestHelper::createImageAsset('asset4', null, false);
        $asset4->setParent($subFolder);
        $asset4->setUserOwner($ownerId);
        $asset4->save();
        $ids[3] = $asset4->getId();

        return $ids;
    }
}
