<?php
/**
 * Copyright (C) 2024 Carlos Garcia Gomez <carlos@facturascripts.com>
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
class GoogleContacts implements AutoTemplateInterface
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

            if ($csv['titles'][0] === 'Name' && $csv['titles'][1] === 'Given Name') {
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
            $emails = static::findValues($row['E-mail 1 - Value'] ?? '');
            $emails = static::findValues($row['E-mail 2 - Value'] ?? '', $emails);
            $emails = static::findValues($row['E-mail 3 - Value'] ?? '', $emails);
            $emails = static::findValues($row['E-mail 4 - Value'] ?? '', $emails);
            $phones = static::findValues($row['Phone 1 - Value'] ?? '');
            $phones = static::findValues($row['Phone 2 - Value'] ?? '', $phones);
            $phones = static::findValues($row['Phone 3 - Value'] ?? '', $phones);

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
            $contacto->apellidos = $row['Family Name'];
            $contacto->email = $emails[0] ?? '';
            $contacto->telefono1 = $phones[0] ?? '';
            $contacto->telefono2 = $phones[1] ?? '';
            $contacto->nombre = static::findOne($row['Name'], $contacto->email, $contacto->telefono1);
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
