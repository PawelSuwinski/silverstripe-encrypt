<?php

namespace LeKoala\Encrypt\Test;

use Exception;
use SilverStripe\Assets\File;
use SilverStripe\ORM\DataObject;
use SilverStripe\Security\Member;
use LeKoala\Encrypt\EncryptHelper;
use SilverStripe\Core\Environment;
use SilverStripe\Dev\SapphireTest;
use LeKoala\Encrypt\EncryptedDBField;
use LeKoala\Encrypt\HasEncryptedFields;
use SilverStripe\ORM\Queries\SQLSelect;

/**
 * Test for Encrypt
 *
 * Run with the following command : ./vendor/bin/phpunit ./encrypt/tests/EncryptTest.php
 *
 * You may need to run:
 * php ./framework/cli-script.php dev/build ?flush=all
 * before (remember manifest for cli is not the same...)
 *
 * @group Encrypt
 */
class EncryptTest extends SapphireTest
{
    /**
     * Defines the fixture file to use for this test class
     * @var string
     */
    protected static $fixture_file = 'EncryptTest.yml';

    protected static $extra_dataobjects = [
        Test_EncryptedModel::class,
    ];

    public function setUp()
    {
        Environment::setEnv('ENCRYPTION_KEY', '502370dfc69fd6179e1911707e8a5fb798c915900655dea16370d64404be04e5');
        parent::setUp();

        // test extension is available
        if (!extension_loaded('sodium')) {
            throw new Exception("You must load sodium extension for this");
        }
    }

    public function tearDown()
    {
        parent::tearDown();
    }

    /**
     * @return Test_EncryptedModel
     */
    public function getTestModel()
    {
        return $this->objFromFixture(Test_EncryptedModel::class, 'demo');
    }

    /**
     * @return Member
     */
    public function getAdminMember()
    {
        return $this->objFromFixture(Member::class, 'admin');
    }

    /**
     * @return File
     */
    public function getRegularFile()
    {
        return $this->objFromFixture(File::class, 'regular');
    }

    /**
     * @return File
     */
    public function getEncryptedFile()
    {
        return $this->objFromFixture(File::class, 'encrypted');
    }

    /**
     * @param string $class
     * @param int $id
     * @return array
     */
    protected function fetchRawData($class, $id)
    {
        $tableName = DataObject::getSchema()->tableName($class);
        $columnIdentifier = DataObject::getSchema()->sqlColumnForField($class, 'ID');
        $sql = new SQLSelect('*', [$tableName], [$columnIdentifier => $id]);
        $dbRecord = $sql->firstRow()->execute()->first();
        return $dbRecord;
    }

    public function testEncryption()
    {
        $someText = 'some text';
        $encrypt = EncryptHelper::encrypt($someText);
        $decryptedValue = EncryptHelper::decrypt($encrypt);

        $this->assertEquals($someText, $decryptedValue);
    }

    public function testIndexes()
    {
        $indexes = DataObject::getSchema()->databaseIndexes(Test_EncryptedModel::class);
        $keys = array_keys($indexes);
        $this->assertContains('MyIndexedVarcharBlindIndex', $keys, "Index is not defined in : " . implode(", ", $keys));
        $this->assertContains('MyNumberLastFourBlindIndex', $keys, "Index is not defined in : " . implode(", ", $keys));
    }

    public function testSearch()
    {
        $singl = singleton(Test_EncryptedModel::class);
        $obj = $singl->dbObject('MyIndexedVarchar');
        $record = $obj->fetchRecord('some_searchable_value');

        $this->assertNotEmpty($record);
        $this->assertEquals(1, $record->ID);
        $this->assertNotEquals(2, $record->ID);

        $record = $obj->fetchRecord('some_unset_value');
        $this->assertEmpty($record);

        // Let's try our four digits index
        $obj = $singl->dbObject('MyNumber');
        $record = $obj->fetchRecord('6789', 'LastFourBlindIndex');
        $searchValue = $obj->getSearchValue('6789', 'LastFourBlindIndex');
        // $searchParams = $obj->getSearchParams('6789', 'LastFourBlindIndex');
        // print_r($searchParams);
        $this->assertNotEmpty($record, "Nothing found for $searchValue");
        $this->assertEquals(1, $record->ID);
    }

    public function testSearchFilter()
    {
        $record = Test_EncryptedModel::get()->filter('MyIndexedVarchar:Encrypted', 'some_searchable_value')->first();
        $this->assertNotEmpty($record);
        $this->assertEquals(1, $record->ID);
        $this->assertNotEquals(2, $record->ID);

        $record = Test_EncryptedModel::get()->filter('MyIndexedVarchar:Encrypted', 'some_unset_value')->first();
        $this->assertEmpty($record);
    }

    public function testFixture()
    {
        $model = $this->getTestModel();

        // Ensure we have our blind indexes
        $this->assertTrue($model->hasDatabaseField('MyIndexedVarcharValue'));
        $this->assertTrue($model->hasDatabaseField('MyIndexedVarcharBlindIndex'));
        $this->assertTrue($model->hasDatabaseField('MyNumberValue'));
        $this->assertTrue($model->hasDatabaseField('MyNumberBlindIndex'));
        $this->assertTrue($model->hasDatabaseField('MyNumberLastFourBlindIndex'));

        if (class_uses($model, HasEncryptedFields::class)) {
            $this->assertTrue($model->hasEncryptedField('MyVarchar'));
            $this->assertTrue($model->hasEncryptedField('MyIndexedVarchar'));
        }


        // print_r($model);
        /*
         [record:protected] => Array
        (
            [ClassName] => LeKoala\Encrypt\Test\Test_EncryptedModel
            [LastEdited] => 2020-12-15 10:09:47
            [Created] => 2020-12-15 10:09:47
            [Name] => demo
            [MyText] => nacl:mQ1g5ugjYSWjFd-erM6-xlB_EbWp1bOAUPbL4fa3Ce5SX6LP7sFCczkFx_lRABvZioWJXx-L
            [MyHTMLText] => nacl:836In6YCaEf3_mRJR7NOC_s0P8gIFESgmPnHCefTe6ycY_6CLKVmT0_9KWHgnin-WGXMJawkS1hS87xwQw==
            [MyVarchar] => nacl:ZeOw8-dcBdFemtGm-MRJ5pCSipOtAO5-zBRms8F5Elex08GuoL_JKbdN-CiOP-u009MJfvGZUkx9Ru5Zn0_y
            [RegularFileID] => 2
            [EncryptedFileID] => 3
            [MyIndexedVarcharBlindIndex] => 04bb6edd
            [ID] => 1
            [RecordClassName] => LeKoala\Encrypt\Test\Test_EncryptedModel
        )
        */

        $varcharValue = 'encrypted varchar value';
        $varcharWithIndexValue = 'some_searchable_value';
        // regular fields are not affected
        $this->assertEquals('demo', $model->Name);

        // get value
        $this->assertEquals($varcharValue, $model->dbObject('MyVarchar')->getValue());
        // encrypted fields work transparently when using trait
        $this->assertEquals($varcharValue, $model->MyVarchar);


        $this->assertTrue($model->dbObject('MyIndexedVarchar') instanceof EncryptedDBField);
        $this->assertTrue($model->dbObject('MyIndexedVarchar')->hasField('Value'));

        $model->MyIndexedVarchar = $varcharWithIndexValue;
        $model->write();
        $this->assertEquals($varcharWithIndexValue, $model->MyIndexedVarchar);

        $dbRecord = $this->fetchRawData(get_class($model), $model->ID);
        // print_r($dbRecord);
        /*
        Array
(
    [ID] => 1
    [ClassName] => LeKoala\Encrypt\Test\Test_EncryptedModel
    [LastEdited] => 2020-12-15 10:10:27
    [Created] => 2020-12-15 10:10:27
    [Name] => demo
    [MyText] => nacl:aDplmA9hs7naqiPwWdNRMcYNUltf4mOs8KslRQZ4vCdnJylnbjAJYChtVH7wiiygsAHWqbM6
    [MyHTMLText] => nacl:dMvk5Miux0bsSP1SjaXQRlbGogNTu7UD3p6AlNHFMAEGXOQz03hkBx43C-WelCS0KUdAN9ewuwuXZqMmRA==
    [MyVarchar] => nacl:sZRenCG6En7Sg_HmsUHkNy_1MXOstly7eHm0i2iq83kTFH40UsQj-HTqxxYfx0ghuWSKbcqHQ7_OAEy4pcPm
    [RegularFileID] => 2
    [EncryptedFileID] => 3
    [MyNumberValue] =>
    [MyNumberBlindIndex] =>
    [MyNumberLastFourBlindIndex] =>
    [MyIndexedVarcharValue] =>
    [MyIndexedVarcharBlindIndex] => 04bb6edd
)
*/
        $this->assertNotEquals($varcharValue, $dbRecord['MyVarchar']);
        $this->assertNotEmpty($dbRecord['MyVarchar']);
        $this->assertTrue(EncryptHelper::isEncrypted($dbRecord['MyVarchar']));
    }

    public function testRecordIsEncrypted()
    {
        $model = new Test_EncryptedModel();

        // Let's write some stuff
        $someText = 'some text';
        $model->MyText = $someText . ' text';
        $model->MyHTMLText = '<p>' . $someText . ' html</p>';
        $model->MyVarchar = 'encrypted varchar value';
        $model->MyIndexedVarchar = "some_searchable_value";
        $model->MyNumber = "0123456789";
        // echo '<pre>';
        // print_r(array_keys($model->getChangedFields()));
        // die();
        $id = $model->write();

        $this->assertNotEmpty($id);

        // For the model, its the same
        $this->assertEquals($someText . ' text', $model->MyText);
        $this->assertEquals('<p>' . $someText . ' html</p>', $model->MyHTMLText);

        // In the db, it's not the same
        $dbRecord = $this->fetchRawData(get_class($model), $model->ID);

        if (!EncryptHelper::isEncrypted($dbRecord['MyIndexedVarcharValue'])) {
            print_r($dbRecord);
        }

        /*
(
    [ID] => 2
    [ClassName] => LeKoala\Encrypt\Test\Test_EncryptedModel
    [LastEdited] => 2020-12-15 10:20:39
    [Created] => 2020-12-15 10:20:39
    [Name] =>
    [MyText] => nacl:yA3XhjUpxE6cS3VMOVI4eqpolP1vRZDYjFySULZiazi9V3HSugC3t8KgImnGV5jP1VzEytVX
    [MyHTMLText] => nacl:F3D33dZ2O7qtlmkX-fiaYwSjAo6RC03aiAWRTkfSJOZikcSfezjwmi9DPJ4EO0hYeVc9faRgA3RmTDajRA==
    [MyVarchar] => nacl:POmdt3mTUSgPJw3ttfi2G9HgHAE4FRX4FQ5CSBicj4JsEwyPwrP-JKYGcs5drFYLId3cMVf6m8daUY7Ao4Cz
    [RegularFileID] => 0
    [EncryptedFileID] => 0
    [MyNumberValue] => nacl:2wFOX_qahm-HmzQPXvcBFhWCG1TaGQgeM7vkebLxRXDfMpzAxhxkExVgBi8caPYrwvA=
    [MyNumberBlindIndex] => 5e0bd888
    [MyNumberLastFourBlindIndex] => 276b
    [MyIndexedVarcharValue] => nacl:BLi-zF02t0Zet-ADP3RT8v5RTsM11WKIyjlJ1EVHIai2HwjxCIq92gfsay5zqiLic14dXtwigb1kI179QQ==
    [MyIndexedVarcharBlindIndex] => 04bb6edd
)
        */
        $text = isset($dbRecord['MyText']) ? $dbRecord['MyText'] : null;
        $this->assertNotEmpty($text);
        $this->assertNotEquals($someText, $text, "Data is not encrypted in the database");
        // Composite fields should work as well
        $this->assertNotEmpty($dbRecord['MyIndexedVarcharValue']);
        $this->assertNotEmpty($dbRecord['MyIndexedVarcharBlindIndex']);

        // Test save into
        $modelFieldsBefore = $model->getQueriedDatabaseFields();
        $model->MyIndexedVarchar = 'new_value';
        $dbObj = $model->dbObject('MyIndexedVarchar');
        // $dbObj->setValue('new_value', $model);
        // $dbObj->saveInto($model);
        $modelFields = $model->getQueriedDatabaseFields();
        // print_r($modelFields);
        $this->assertTrue($dbObj->isChanged());
        $changed = implode(", ", array_keys($model->getChangedFields()));
        $this->assertNotEquals($modelFieldsBefore['MyIndexedVarchar'], $modelFields['MyIndexedVarchar'], "It should not have the same value internally anymore");
        $this->assertTrue($model->isChanged('MyIndexedVarchar'), "Field is not properly marked as changed, only have : " . $changed);
        $this->assertEquals('new_value', $dbObj->getValue());
        $this->assertNotEquals('new_value', $modelFields['MyIndexedVarcharValue'], "Unencrypted value is not set on value field");

        // Somehow this is not working on travis? composite fields don't save encrypted data although it works locally
        $this->assertNotEquals("some_searchable_value", $dbRecord['MyIndexedVarcharValue'], "Data is not encrypted in the database");

        // if we load again ?
        // it should work thanks to our trait
        // by default, data will be loaded encrypted if we don't use the trait and call getField directly
        $model2 = $model::get()->byID($model->ID);
        $this->assertEquals($someText . ' text', $model2->MyText, "Data does not load properly");
        $this->assertEquals('<p>' . $someText . ' html</p>', $model2->MyHTMLText, "Data does not load properly");
    }

    public function testFileEncryption()
    {
        $regularFile = $this->getRegularFile();
        $encryptedFile = $this->getEncryptedFile();

        $this->assertEquals(0, $regularFile->Encrypted);
        $this->assertEquals(1, $encryptedFile->Encrypted);

        // test encryption

        $string = 'Some content';

        $stream = fopen('php://memory', 'r+');
        fwrite($stream, $string);
        rewind($stream);

        $encryptedFile->setFromStream($stream, 'secret.doc');
        $encryptedFile->write();

        $this->assertFalse($encryptedFile->isEncrypted());

        $encryptedFile->encryptFileIfNeeded();

        $this->assertTrue($encryptedFile->isEncrypted());
    }
}
