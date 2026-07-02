<?php
/**
 * Copyright (C) 2022 Carlos Garcia Gomez <carlos@facturascripts.com>
 */

namespace FacturaScripts\Plugins\RemesasSEPA\Lib\Accounting;

use PHP_IBAN\IBAN;

class CalculateSwift
{
    public static function getSwift(string $iban, string $swift): string
    {
        if (false === empty($swift)) {
            return $swift;
        }

        if (false === verify_iban($iban)) {
            return '';
        }

        $myIban = new IBAN($iban);
        $bankCode = $myIban->Country() . $myIban->Bank();

        // READ CSV
        $swift = '';
        $filePath = FS_FOLDER . '/Plugins/RemesasSEPA/bancos_swift.csv';
        $handle = fopen($filePath, "r");
        while (($data = fgetcsv($handle, 0, ";")) !== FALSE) {
            if (empty($swift) && $bankCode == $data[0]) {
                $swift = $data[2];
                break;
            }
        }
        fclose($handle);

        return $swift;
    }
}
