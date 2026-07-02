<?php
/**
 * Copyright (C) 2022-2025 Carlos Garcia Gomez <carlos@facturascripts.com>
 */

namespace FacturaScripts\Plugins\CSVimport\Lib;

use DateTime;
use Exception;
use FacturaScripts\Core\Base\DataBase\DataBaseWhere;
use FacturaScripts\Core\Model\Base\ModelCore;
use FacturaScripts\Core\Tools;
use FacturaScripts\Dinamic\Model\CSVfile;
use OpenSpout\Reader\Common\Creator\ReaderEntityFactory;
use ParseCsv\Csv;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * @author Carlos Garcia Gomez      <carlos@facturascripts.com>
 * @author Daniel Fernandez Giménez <hola@danielfg.es>
 */
class CsvFileTools
{
    const AUTOMATIC = 'automatic';
    const INSERT_MODE = 'insert';
    const NEW_TEMPLATE = 'new-template';
    const UPDATE_MODE = 'update';

    /** @var int */
    private static $total_lines = 0;

    public static function convertFileToCsv(string $filePath): string
    {
        if (empty($filePath) || false === file_exists($filePath)) {
            return '';
        }

        // obtenemos la extensión del archivo
        $ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));

        $mime = mime_content_type($filePath);
        switch ($mime) {
            case 'application/csv':
            case 'text/csv':
            case 'text/plain':
            case 'text/x-Algol68':
                $finalPath = $filePath;
                break;

            case 'application/vnd.ms-excel':
            case 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet':
            case 'application/vnd.oasis.opendocument.spreadsheet':
                $finalPath = ($ext === 'xls') ?
                    self::convertXlsToCsv($filePath) :
                    self::convertOtherToCsv($filePath);
                break;

            default:
                return '';
        }

        // obtenemos la extensión del archivo final
        $finalExt = strtolower(pathinfo($finalPath, PATHINFO_EXTENSION));

        // si no es csv, lo renombramos
        if ($finalExt !== 'csv') {
            $newPath = pathinfo($finalPath, PATHINFO_DIRNAME) . DIRECTORY_SEPARATOR
                . pathinfo($finalPath, PATHINFO_FILENAME) . '.csv';
            return rename($finalPath, $newPath) ? $newPath : $finalPath;
        }

        return $finalPath;
    }

    public static function formatBool(string $txt): bool
    {
        return in_array(strtolower($txt), ['1', 'true', 't', 'si', 's', 'yes', 'y', 'on', 'activo']);
    }

    public static function formatDate(?string $text): ?string
    {
        if (empty($text)) {
            return null;
        }

        // eliminamos comillas simples y dobles
        $text = str_replace(['"', "'"], '', $text);

        // si la fecha tiene un espacio, nos quedamos con la primera parte
        $text = explode(' ', $text)[0];

        // comprobamos si el separador de fecha es / o -
        $sep = strpos($text, '/') !== false ? '/' : '-';

        // partimos la fecha, si no hay 3 trozos, devolvemos null
        $parts = explode($sep, $text);
        if (count($parts) !== 3) {
            return null;
        }

        // si el primer y tercer valor tiene 2 dígitos y es mayor que 80, le sumamos 1900, si no 2000
        if (strlen($parts[0]) === 2 && strlen($parts[2]) === 2) {
            $parts[2] = (int)$parts[2] > 80 ? '19' . $parts[2] : '20' . $parts[2];
        }

        // si el separador es '-' y el segundo valor es mayor que 12, lanzamos una excepción
        if ($sep === '-' && (int)$parts[1] > 12) {
            throw new Exception('Invalid date: ' . $text);
        }

        // componemos la nueva fecha
        $newText = strlen($parts[2]) > strlen($parts[0]) ?
            $parts[2] . '-' . $parts[1] . '-' . $parts[0] :
            $parts[0] . '-' . $parts[1] . '-' . $parts[2];
        return date(ModelCore::DATE_STYLE, strtotime($newText));
    }

    public static function formatEmail($text): string
    {
        if (empty($text)) {
            return '';
        }

        foreach (explode(' ', $text) as $part) {
            if (filter_var($part, FILTER_VALIDATE_EMAIL)) {
                return $part;
            }
        }

        return '';
    }

    public static function formatFloat(string $value): string
    {
        // eliminamos todos los caracteres que no sean números, guión, coma o punto
        $value = preg_replace('/[^0-9\-\.,]/', '', $value);

        if (empty($value)) {
            return '0.0';
        }

        // reemplazamos las comas por puntos para manejar el formato decimal
        $value = str_replace(',', '.', $value);

        // si el número tiene más de un punto, eliminamos todos los puntos menos el último
        if (substr_count($value, '.') > 1) {
            return preg_replace('/\.(?=.*\.)/', '', $value);
        }

        // contamos cuantos caracteres hay después del punto
        $decimals = strlen(substr(strrchr($value, '.'), 1));

        // sí son 3 decimales y no tenemos 3 decimales en la configuración, eliminamos el punto
        if ($decimals == 3 && Tools::settings('default', 'decimals') != 3) {
            return str_replace('.', '', $value);
        }

        return $value;
    }

    public static function formatString(string $txt, int $length, int $offset = 0): string
    {
        return substr($txt, $offset, $length);
    }

    public static function generateCsvTitles(string $filePath, int $startLine): void
    {
        if (false === file_exists($filePath)) {
            return;
        }

        // cargamos el csv
        $csv = new Csv();
        $csv->heading = false;
        if (false === $csv->auto($filePath)) {
            return;
        }

        // obtenemos el número de columnas
        $numColumns = count($csv->data[$startLine]);

        // construir los títulos con columnas "Column1", "Column2", ..., "ColumnN"
        $titles = [];
        for ($i = 1; $i <= $numColumns; $i++) {
            $titles[] = "Column$i";
        }

        // insertar la nueva fila en la posición deseada
        if ($startLine > 2) {
            array_splice($csv->data, $startLine - 1, 0, [$titles]);
        } elseif ($startLine === 1) {
            array_splice($csv->data, $startLine, 0, [$titles]);
        } else {
            array_unshift($csv->data, $titles);
        }

        // guardamos el csv
        $csv->save($filePath);
    }

    public static function getFilePath($fileName): string
    {
        $filePath = FS_FOLDER . DIRECTORY_SEPARATOR . 'MyFiles' . DIRECTORY_SEPARATOR . $fileName;
        if (false === file_exists($filePath)) {
            Tools::log()->warning('file-not-found', ['%fileName%' => $filePath]);
            return '';
        }

        return $filePath;
    }

    public static function getFileTemplate(string $filePath, int $idtemplate = 0, ?string $profile = null): ?CSVfile
    {
        $templateModel = new CSVfile();

        $where = $idtemplate > 0 ?
            [new DataBaseWhere('id', $idtemplate)] :
            [new DataBaseWhere('profile', $profile)];
        $where[] = new DataBaseWhere('options', null, 'IS NOT');

        foreach ($templateModel->all($where, [], 0, 0) as $template) {
            $csv = $template->getProfile(0, 0, $filePath)->getCsv();
            if (empty($csv->titles)) {
                // si la plantilla no tiene títulos, la excluimos, no está configurada
                continue;
            }

            $cont = 0;
            $csvColumns = $template->getCsvColumns();
            foreach ($csvColumns as $key => $value) {
                $index = str_replace('field', '', $key);
                if (isset($csv->titles[$index]) && $csv->titles[$index] === $value) {
                    $cont++;
                }
            }

            if (count($csvColumns) === $cont) {
                return $template;
            }
        }

        return null;
    }

    public static function getTotalLines(): int
    {
        return self::$total_lines;
    }

    public static function isBigFile(UploadedFile $uploadFile): bool
    {
        if ($uploadFile->getSize() > 10485760) {
            Tools::log()->warning('file-too-big');
            return true;
        }

        return false;
    }

    public static function isValidFile(string $filePath): bool
    {
        $mime = mime_content_type($filePath);
        switch ($mime) {
            case 'text/csv':
            case 'text/plain':
            case 'text/x-Algol68':
            case 'application/csv':
            case 'application/vnd.ms-excel':
            case 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet':
            case 'application/vnd.oasis.opendocument.spreadsheet':
                return true;

            default:
                $ext = ['csv', 'ods', 'xls', 'xlsx'];
                Tools::log()->warning('unsupported-file-ext', ['%ext%' => implode(', ', $ext)]);
                Tools::log()->warning($mime);
                return false;
        }
    }

    public static function read(string $filePath, int $start = 0, $offset = 0, $limit = 0): array
    {
        $csv = ['titles' => [], 'data' => []];
        $file = fopen($filePath, 'r');

        // Eliminar BOM si existe
        $firstLine = fgets($file);
        $bom = pack('H*', 'EFBBBF');
        if (strpos($firstLine, $bom) === 0) {
            // si tiene BOM, lo eliminamos y reescribimos la primera línea en el stream
            $firstLine = substr($firstLine, 3);
        }
        // Regresamos el puntero del archivo y escribimos la línea corregida en un stream temporal
        $temp = fopen('php://temp', 'r+');
        fwrite($temp, $firstLine);
        stream_copy_to_stream($file, $temp);
        rewind($temp);

        // Usamos el stream temporal en lugar del archivo original
        $file = $temp;

        // detectamos el delimitador
        $delimiters = [';' => 0, ',' => 0, '|' => 0, "\t" => 0];
        foreach ($delimiters as $item => &$count) {
            $row = fgetcsv($file, 0, $item);
            $count = count($row);
            rewind($file);
        }

        // ordenamos los delimitadores por número de columnas
        arsort($delimiters);
        $delimiter = key($delimiters);

        // leemos el csv
        $line = 0;
        while (false !== ($row = fgetcsv($file, 0, $delimiter))) {
            if ($line < $start) {
                $line++;
                continue;
            }

            if ($line === $start) {
                $csv['titles'] = $row;

                // recorremos los títulos, si alguno está vacío, lo rellenamos
                foreach ($csv['titles'] as $key => $value) {
                    if (empty($value)) {
                        $csv['titles'][$key] = 'Column' . ($key + 1);
                    }
                }

                $line++;
                continue;
            }

            if ($line < $start + $offset + 1) {
                $line++;
                continue;
            }

            if ($limit > 0 && count($csv['data']) >= $limit) {
                $line++;
                continue;
            }

            // si el número de columnas de $row es mayor que el de $csv['titles']
            // recortamos $row para que tenga el mismo número de columnas
            if (count($row) > count($csv['titles'])) {
                $row = array_slice($row, 0, count($csv['titles']));
            }

            // si el número de columnas de $row es menor que el de $csv['titles']
            // saltamos la fila
            if (count($row) < count($csv['titles'])) {
                $line++;
                continue;
            }

            $csv['data'][] = array_combine($csv['titles'], $row);

            $line++;
        }

        fclose($file);
        self::$total_lines = $line;

        return $csv;
    }

    public static function saveUploadFile(UploadedFile $uploadFile): string
    {
        $folder = Tools::folder('MyFiles');
        return $uploadFile->move($folder, $uploadFile->getClientOriginalName())->getRealPath();
    }

    protected static function convertOtherToCsv(string $filePath): string
    {
        // leemos el excel
        $reader = ReaderEntityFactory::createReaderFromFile($filePath);
        $reader->open($filePath);

        // abrimos el csv
        $pathCsv = Tools::folder('MyFiles', pathinfo($filePath, PATHINFO_FILENAME) . '.csv');
        $file = fopen($pathCsv, 'w');

        // recorremos las filas
        foreach ($reader->getSheetIterator() as $sheet) {
            foreach ($sheet->getRowIterator() as $row) {
                $newRow = [];
                foreach ($row->getCells() as $cell) {
                    // si el valor es dateTime, lo convertimos a string
                    $value = $cell->getValue();
                    if ($value instanceof DateTime) {
                        $newRow[] = $value->format('d-m-Y');
                        continue;
                    }

                    $newRow[] = $value;
                }

                fputcsv($file, $newRow);
            }
            break;
        }

        // cerramos los archivos
        $reader->close();
        fclose($file);

        // eliminamos el archivo original
        unlink($filePath);

        return $pathCsv;
    }

    protected static function convertXlsToCsv(string $filePath): string
    {
        $reader = IOFactory::createReader(IOFactory::identify($filePath));
        $reader->setReadEmptyCells(false);

        // recorremos todas las columnas y cambiamos las de tipo fecha a texto
        $spreadsheet = $reader->load($filePath);
        foreach ($spreadsheet->getActiveSheet()->getRowIterator() as $row) {
            foreach ($row->getCellIterator() as $cell) {
                if ($cell->getDataType() === DataType::TYPE_NUMERIC) {
                    $cell->setValueExplicit($cell->getValue(), DataType::TYPE_STRING);
                }
            }
            break;
        }

        // convertimos el excel a csv
        $pathCsv = Tools::folder('MyFiles', pathinfo($filePath, PATHINFO_FILENAME) . '.csv');
        $writer = IOFactory::createWriter($spreadsheet, 'Csv');
        $writer->setSheetIndex(0);
        $writer->setDelimiter(';');
        $writer->save($pathCsv);

        // liberamos memoria
        $spreadsheet->disconnectWorksheets();
        $spreadsheet->garbageCollect();
        unset($spreadsheet);

        // eliminamos el excel
        unlink($filePath);

        // leemos el csv para comprobar si tiene fechas no válidas
        $file = fopen($pathCsv, 'r');
        $invalidDate = false;
        while (false !== ($line = fgetcsv($file, 0, ';'))) {
            foreach ($line as $value) {
                // comprobamos si hay fechas
                $matches = [];
                if (!preg_match('/^(\d{1,2})\/(\d{1,2})\/(\d{4})$/', $value, $matches)) {
                    continue;
                }

                $date = DateTime::createFromFormat('d/m/Y', $value);
                if ($date === false) {
                    $invalidDate = true;
                    break 2;
                }
            }
        }

        // si hay fechas no válidas, las corregimos
        if ($invalidDate) {
            rewind($file);
            $csv = '';
            while (false !== ($line = fgetcsv($file, 0, ';'))) {
                $csv .= self::fixDateOnCsvLine($line) . PHP_EOL;
            }
            fclose($file);
            file_put_contents($pathCsv, $csv);
        }

        return $pathCsv;
    }

    protected static function fixDateOnCsvLine(array $line): string
    {
        $result = [];
        foreach ($line as $value) {
            if (preg_match('/^(\d{1,2})\/(\d{1,2})\/(\d{4})$/', $value, $matches)) {
                $result[] = str_pad($matches[2], 2, '0', STR_PAD_LEFT)
                    . '-' . str_pad($matches[1], 2, '0', STR_PAD_LEFT)
                    . '-' . $matches[3];
                continue;
            }

            $result[] = $value;
        }

        return implode(';', $result);
    }
}
