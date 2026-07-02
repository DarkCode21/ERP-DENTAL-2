<?php
/**
 * Copyright (C) 2020-2024 Carlos Garcia Gomez <carlos@facturascripts.com>
 */

namespace FacturaScripts\Plugins\CSVimport\Lib\AutoTemplates;

use FacturaScripts\Core\Base\DataBase\DataBaseWhere;
use FacturaScripts\Dinamic\Model\Contacto;
use FacturaScripts\Plugins\CSVimport\Contract\AutoTemplateInterface;
use FacturaScripts\Plugins\CSVimport\Lib\CsvFileTools;

/**
 * @author Carlos Garcia Gomez      <carlos@facturascripts.com>
 * @author Daniel Fernandez Giménez <hola@danielfg.es>
 */
class OutlookContacts implements AutoTemplateInterface
{
    const LIMIT_IMPORT = 500;

    /** @var bool */
    private $continue = false;

    /** @var int */
    private $start = 0;

    /** @var int */
    private $total_lines = 0;

    public function continue(): bool
    {
        return $this->continue;
    }

    public function getTotalLines(): int
    {
        return $this->total_lines;
    }

    public function isValid(string $filePath, string $profile): bool
    {
        if ($profile !== 'contacts') {
            return false;
        }

        // probamos empezando desde las líneas 0 a la 6
        foreach (range(0, 6) as $start) {
            $this->start = $start;
            $csv = CsvFileTools::read($filePath, $start, 0, 1);
            $this->total_lines = CsvFileTools::getTotalLines();

            if (empty($csv['titles'])) {
                continue;
            } elseif ($csv['titles'][0] === 'First Name' && $csv['titles'][1] === 'Middle Name') {
                return true;
            }
        }

        return false;
    }

    public function run(string $filePath, string $profile, string $mode, int &$offset, int &$saveLines): bool
    {
        $csv = CsvFileTools::read($filePath, $this->start, $offset, static::LIMIT_IMPORT);
        $this->total_lines = CsvFileTools::getTotalLines();
        $this->continue = false;

        foreach ($csv['data'] as $row) {
            // extract emails and phones
            $emails = static::findValues($row['E-mail Address'] ?? '');
            $emails = static::findValues($row['E-mail 2 Address'] ?? '', $emails);
            $emails = static::findValues($row['E-mail 3 Address'] ?? '', $emails);
            $phones = static::findValues($row['Primary Phone'] ?? '');
            $phones = static::findValues($row['Home Phone'] ?? '', $phones);
            $phones = static::findValues($row['Home Phone 2'] ?? '', $phones);
            $phones = static::findValues($row['Mobile Phone'] ?? '', $phones);
            $phones = static::findValues($row['Business Phone'] ?? '', $phones);
            $phones = static::findValues($row['Business Phone 2'] ?? '', $phones);
            $phones = static::findValues($row['Other Phone'] ?? '', $phones);

            // find contact
            $contacto = new Contacto();
            $where = [];
            if (!empty($emails)) {
                $where[] = new DataBaseWhere('email', $emails[0]);
            }
            if (!empty($phones)) {
                $where[] = new DataBaseWhere('telefono1', $phones[0]);
            }
            if (empty($where) || ($contacto->loadFromCode('', $where) && $mode === CsvFileTools::INSERT_MODE)) {
                continue;
            }

            // save new contact
            if (isset($row['Home Address PO Box'])) {
                $contacto->apartado = static::findOne($row['Home Address PO Box'], $row['Business Address PO Box'], $row['Other Address PO Box']);
            }
            $contacto->apellidos = empty($row['Middle Name']) ? $row['Last Name'] : $row['Middle Name'] . ' ' . $row['Last Name'];
            $contacto->ciudad = static::findOne($row['Home City'], $row['Business City'], $row['Other City'] ?? '');
            if (isset($row['Home Country'])) {
                $contacto->codpais = static::findOne($row['Home Country'], $row['Business Country'], $row['Other Country'] ?? '');
            }
            if (isset($row['Home Address'])) {
                $contacto->codpostal = static::findOne($row['Home Address'], $row['Business Address'], $row['Other Address']);
            }
            if (isset($row['Home Postal Code'])) {
                $contacto->direccion = static::findOne($row['Home Postal Code'], $row['Business Postal Code'], $row['Other Postal Code'] ?? '');
            }
            $contacto->email = $emails[0] ?? '';
            $contacto->provincia = static::findOne($row['Home State'], $row['Business State'], $row['Other State'] ?? '');
            $contacto->telefono1 = $phones[0] ?? '';
            $contacto->telefono2 = $phones[1] ?? '';
            $contacto->nombre = static::findOne($row['First Name'], $contacto->email, $contacto->telefono1);
            if (false === $contacto->save()) {
                continue;
            }

            $this->continue = true;
            $offset++;
            $saveLines++;
        }

        return true;
    }

    protected static function findOne($first, $second, $third): string
    {
        if (!empty($first)) {
            return $first;
        }

        if (!empty($second)) {
            return $second;
        }

        if (!empty($third)) {
            return $third;
        }

        return 'contacts';
    }

    protected static function findValues($txt, array $values = []): array
    {
        foreach (explode(' ::: ', $txt) as $part) {
            $value = trim($part);
            if (!empty($value)) {
                $values[] = $value;
            }
        }

        return $values;
    }
}
