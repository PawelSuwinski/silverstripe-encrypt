<?php

namespace LeKoala\Encrypt;

use Exception;
use SilverStripe\ORM\DataObject;
use ParagonIE\CipherSweet\CipherSweet;
use ParagonIE\CipherSweet\EncryptedRow;
use ParagonIE\CipherSweet\Exception\InvalidCiphertextException;
use ParagonIE\CipherSweet\KeyRotation\RowRotator;
use SilverStripe\ORM\Queries\SQLSelect;
use SilverStripe\ORM\Queries\SQLUpdate;
use SodiumException;

/**
 * This trait helps to override the default getField method in order to return
 * the value of a field directly instead of the db object instance
 *
 * Simply define this in your code
 *
 * public function getField($field)
 * {
 *    return $this->getEncryptedField($field);
 * }
 *
 * public function setField($fieldName, $val)
 * {
 *     return $this->setEncryptedField($fieldName, $val);
 * }
 */
trait HasEncryptedFields
{
    /**
     * Check if the record needs to be reencrypted with a new key or algo
     * @param CipherSweet $old
     * @return bool
     */
    public function needsToRotateEncryption(CipherSweet $old)
    {
        $class = get_class($this);
        $tableName = DataObject::getSchema()->tableName($class);
        $columnIdentifier = DataObject::getSchema()->sqlColumnForField($class, 'ID');

        $new = EncryptHelper::getCipherSweet();

        $oldRow = $this->getEncryptedRow($old);
        $newRow = $this->getEncryptedRow($new);

        $rotator = new RowRotator($oldRow, $newRow);
        $query = new SQLSelect("*", $tableName, [$columnIdentifier => $this->ID]);
        $ciphertext = $query->execute()->first();
        $ciphertext = EncryptHelper::removeNulls($ciphertext);
        if ($rotator->needsReEncrypt($ciphertext)) {
            return true;
        }
        return false;
    }

    /**
     * Rotate encryption with current engine without using orm
     * @param CipherSweet $old
     * @return bool
     * @throws SodiumException
     * @throws InvalidCiphertextException
     */
    public function rotateEncryption(CipherSweet $old)
    {
        $class = get_class($this);
        $tableName = DataObject::getSchema()->tableName($class);
        $columnIdentifier = DataObject::getSchema()->sqlColumnForField($class, 'ID');

        $new = EncryptHelper::getCipherSweet();

        $oldRow = $this->getEncryptedRow($old);
        $newRow = $this->getEncryptedRow($new);

        $rotator = new RowRotator($oldRow, $newRow);
        $query = new SQLSelect("*", $tableName, [$columnIdentifier => $this->ID]);
        $ciphertext = $query->execute()->first();
        $ciphertext = EncryptHelper::removeNulls($ciphertext);
        $indices = null;
        if ($rotator->needsReEncrypt($ciphertext)) {
            list($ciphertext, $indices) = $rotator->prepareForUpdate($ciphertext);
            $assignment = array_merge($ciphertext, $indices);
            $update = new SQLUpdate($tableName, $assignment, ["ID" => $this->ID]);
            return $update->execute();
        }
        return false;
    }

    /**
     * @param CipherSweet $engine
     * @return EncryptedRow
     */
    public function getEncryptedRow(CipherSweet $engine = null)
    {
        if ($engine === null) {
            $engine = EncryptHelper::getCipherSweet();
        }
        $tableName = DataObject::getSchema()->tableName(get_class($this));
        $encryptedRow = new EncryptedRow($engine, $tableName);
        $fields = EncryptHelper::getEncryptedFields(get_class($this));
        foreach ($fields as $field) {
            /** @var EncryptedField $encryptedField */
            $encryptedField = $this->dbObject($field)->getEncryptedField($engine);
            $blindIndexes = $encryptedField->getBlindIndexObjects();
            if (count($blindIndexes)) {
                $encryptedRow->addField($field . "Value");
                foreach ($encryptedField->getBlindIndexObjects() as $blindIndex) {
                    $encryptedRow->addBlindIndex($field, $blindIndex);
                }
            } else {
                $encryptedRow->addField($field);
            }
        }
        return $encryptedRow;
    }

    /**
     * Extend getField to support retrieving encrypted value transparently
     * @param string $field The name of the field
     * @return mixed The field value
     */
    public function getEncryptedField($field)
    {
        // If it's an encrypted field
        if ($this->hasEncryptedField($field)) {
            $fieldObj = $this->dbObject($field);
            // Set decrypted value directly on the record for later use
            $this->record[$field] = $fieldObj->getValue();
        }
        return parent::getField($field);
    }

    /**
     * Extend setField to support setting encrypted value transparently
     * @param string $field
     * @param mixed $val
     * @return $this
     */
    public function setEncryptedField($field, $val)
    {
        // If it's an encrypted field
        if ($this->hasEncryptedField($field) && $val && is_scalar($val)) {
            $schema = static::getSchema();

            // In case of composite fields, return the DBField object
            if ($schema->compositeField(static::class, $field)) {
                $fieldObj = $this->dbObject($field);
                $fieldObj->setValue($val);
                // Keep a reference for isChange checks
                $this->record[$field] = $fieldObj;
                // Proceed with DBField instance, that will call saveInto
                // and call this method again for distinct fields
                $val = $fieldObj;
            }
        }
        return parent::setField($field, $val);
    }

    /**
     * @param string $field
     * @return boolean
     */
    public function hasEncryptedField($field)
    {
        return EncryptHelper::isEncryptedField(get_class($this), $field);
    }
}
