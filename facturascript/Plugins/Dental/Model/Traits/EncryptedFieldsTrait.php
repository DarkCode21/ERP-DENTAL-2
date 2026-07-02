<?php
/**
 * EncryptedFieldsTrait
 * 
 * Trait para cifrar/decifrar campos automaticamente
 */

namespace FacturaScripts\Plugins\Dental\Model\Traits;

trait EncryptedFieldsTrait
{
    public function save(): bool
    {
        foreach (static::$encryptedFields as $field) {
            if (isset($this->$field) && !empty($this->$field)) {
                $this->$field = \FacturaScripts\Plugins\Dental\Lib\DentalCrypto::encrypt($this->$field);
            }
        }
        return parent::save();
    }

    public function loadFromData(array $data = [], array $exclude = [])
    {
        parent::loadFromData($data, $exclude);
        foreach (static::$encryptedFields as $field) {
            if (isset($this->$field) && !empty($this->$field)) {
                $this->$field = \FacturaScripts\Plugins\Dental\Lib\DentalCrypto::decrypt($this->$field);
            }
        }
    }
}
