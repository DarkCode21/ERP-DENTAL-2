<?php
/**
 * Copyright (C) 2020-2024 Carlos Garcia Gomez <carlos@facturascripts.com>
 */

namespace FacturaScripts\Plugins\CSVimport\Lib\ManualTemplates;

use Exception;
use FacturaScripts\Core\Base\DataBase;
use FacturaScripts\Core\Model\Base\ModelClass;
use FacturaScripts\Core\Tools;
use FacturaScripts\Plugins\CSVimport\Lib\CsvFileTools;
use FacturaScripts\Plugins\CSVimport\Model\CSVfile;
use ParseCsv\Csv;

/**
 * @author Carlos Garcia Gomez      <carlos@facturascripts.com>
 * @author Daniel Fernandez Giménez <hola@danielfg.es>
 */
abstract class ManualTemplateClass
{
    const LIMIT_IMPORT = 1000;
    const MAX_VALUE_LEN = 30;

    /** @var Csv */
    protected $csv;

    /** @var CSVfile */
    protected $model;

    /** @var int */
    protected $csvTotal = 0;

    /** @var int */
    protected $offset = 0;

    /** @var int */
    protected $saveLines = 0;

    public function getCsv(): Csv
    {
        return $this->csv;
    }

    public function getRows(): array
    {
        $rows = [];
        if (false === empty($this->csv->titles)) {
            foreach ($this->csv->titles as $title) {
                if (empty($title)) {
                    continue;
                }

                $rows[] = [
                    'title' => $title,
                    'value1' => '',
                    'value2' => '',
                    'value3' => '',
                    'use' => ''
                ];
            }
        }

        $this->setValues($rows);
        return $rows;
    }

    public function import(): array
    {
        $numLines = 0;
        $numSave = 0;

        // no model, no import
        if (empty($this->model)) {
            return ['save' => $numSave, 'offset' => $this->offset, 'total' => $this->csvTotal, 'options' => []];
        }

        // get transformations
        $transformations = [];
        foreach ($this->getRows() as $row) {
            if (!empty($row['use'])) {
                $transformations[$row['use']] = $row['title'];
            }
        }

        // start transaction
        $dataBase = new DataBase();
        $dataBase->beginTransaction();

        try {
            if (false === empty($this->csv->data)) {
                foreach ($this->csv->data as $line) {
                    $numLines++;
                    $item = [];
                    foreach ($transformations as $key => $field) {
                        $item[$key] = $line[$field];
                    }

                    if ($this->importItem($item)) {
                        $numSave++;
                    }
                }
            }

            // confirm data
            $dataBase->commit();
        } catch (Exception $exp) {
            Tools::log()->error($exp->getMessage() . ' ' . $exp->getFile() . ' ' . $exp->getLine());
        } finally {
            if ($dataBase->inTransaction()) {
                $dataBase->rollback();
            }
        }

        $this->offset += $numLines;
        $this->saveLines += $numSave;
        return ['save' => $this->saveLines, 'offset' => $this->offset, 'total' => $this->csvTotal, 'options' => $this->model->options];
    }

    public function load(CSVfile $model, ?int $offset, int $saveLines, ?int $limit): void
    {
        $filePath = FS_FOLDER . DIRECTORY_SEPARATOR . $model->path;
        if (false === file_exists($model->path)) {
            return;
        }

        $this->model = $model;
        $fileEncode = static::getFileEncode($filePath);
        $offset = $offset + ($model->startline > 2 ? $model->startline - 1 : $model->startline);
        $this->csv = static::createCSV($filePath, $fileEncode, $model->noutf8file, $offset, $limit);

        // volvemos a cargar el csv para los totales
        $csv = static::createCSV($filePath, $fileEncode, $model->noutf8file);

        $this->csvTotal = count($csv->data);
        $this->offset = $offset;
        $this->saveLines = $saveLines;

        // ¿Los títulos no están en la primera línea?
        if ($model->startline > 0) {
            $this->csv->titles = [];

            // asignamos los nuevos títulos
            $index = $model->startline !== 2 ? max($model->startline - 2, 0) : 1;
            if (isset($csv->data[$index])) {
                foreach ($csv->data[$index] as $value) {
                    $this->csv->titles[] = $value;
                }
            }

            // reasignamos las columnas
            foreach ($this->csv->data as $index => $row) {
                $newRow = [];
                $pos = 0;
                foreach ($row as $column) {
                    $key = $this->csv->titles[$pos];
                    $newRow[$key] = $column;
                    $pos++;
                }
                $this->csv->data[$index] = $newRow;
            }
        }
    }

    protected static function createCSV(string $filePath, string $fileEncode, bool $noUTF8file, ?int $offset = null, ?int $limit = null): Csv
    {
        if (false === is_null($offset) && false === is_null($limit)) {
            $csv = new Csv(null, $offset + 1, $limit);
        } else {
            $csv = new Csv();
        }

        $csv->convert_encoding = true;
        $csv->use_mb_convert_encoding = true;
        $csv->auto($filePath);

        if ($noUTF8file) {
            $csv->encoding(null, 'UTF-8');
        } elseif ($csv->input_encoding !== $csv->output_encoding) {
            $csv->encoding($csv->input_encoding, $csv->output_encoding);
        } else {
            $csv->encoding($fileEncode, 'UTF-8');
        }

        return $csv;
    }

    protected static function getFileEncode(string $filePath): string
    {
        // leemos los primeros 1000 bytes del archivo para determinar la codificación
        $fileContent = file_get_contents($filePath, false, null, 0, 1000);

        // intentamos detectar la codificación
        $fileEncode = mb_detect_encoding($fileContent);

        // si no se ha detectado la codificación, usamos la codificación por defecto que usa el csv
        if (empty($fileEncode)) {
            $fileEncode = 'ISO-8859-1';
        }

        return $fileEncode;
    }

    protected function setModelValues(ModelClass &$model, array $values, string $prefix): bool
    {
        foreach ($model->getModelFields() as $key => $field) {
            if (!isset($values[$prefix . $key])) {
                continue;
            }

            switch ($field['type']) {
                case 'date':
                    $model->{$key} = CsvFileTools::formatDate($values[$prefix . $key]);
                    break;

                case 'double':
                case 'double precision':
                case 'float':
                    $model->{$key} = CsvFileTools::formatFloat($values[$prefix . $key]);
                    break;

                default:
                    $model->{$key} = $values[$prefix . $key];
            }

            if ($field['name'] == 'email') {
                $model->{$key} = CsvFileTools::formatEmail($model->{$key});
            }
        }
        return true;
    }

    protected function setValues(array &$rows)
    {
        if (false === empty($this->csv->data)) {
            foreach ($this->csv->data as $num0 => $line) {
                $num = 1 + $num0;
                foreach ($rows as $key => $row) {
                    if (!isset($row['value' . $num])) {
                        break;
                    }

                    $value = $line[$row['title']] ?? null;
                    if (is_string($value) && strlen($value) > static::MAX_VALUE_LEN) {
                        $rows[$key]['value' . $num] = substr($value, 0, static::MAX_VALUE_LEN) . '...';
                        continue;
                    }

                    $rows[$key]['value' . $num] = $value;
                }
            }
        }

        $options = $this->model->getOptions();
        foreach (array_keys($rows) as $key) {
            if (isset($options['field' . $key])) {
                $rows[$key]['use'] = $options['field' . $key];
            }
        }
    }
}
