<?php
/**
 * Copyright (C) 2020-2024 Carlos Garcia Gomez <carlos@facturascripts.com>
 */

namespace FacturaScripts\Plugins\CSVimport\Model;

use Exception;
use FacturaScripts\Core\Http;
use FacturaScripts\Core\Model\Base;
use FacturaScripts\Core\Tools;
use FacturaScripts\Core\Validator;
use FacturaScripts\Dinamic\Model\AttachedFile;
use FacturaScripts\Plugins\CSVimport\Contract\AutoTemplateInterface;
use FacturaScripts\Plugins\CSVimport\Contract\ManualTemplateInterface;
use FacturaScripts\Plugins\CSVimport\Lib\CsvFileTools;
use FacturaScripts\Plugins\CSVimport\Lib\ManualTemplates\ManualTemplateClass;

/**
 * @author Carlos Garcia Gomez      <carlos@facturascripts.com>
 * @author Daniel Fernandez Giménez <hola@danielfg.es>
 */
class CSVfile extends Base\ModelClass
{
    use Base\ModelTrait;

    /** @var AutoTemplateInterface[] */
    private static $auto_templates = [];

    /** @var ManualTemplateInterface[] */
    private static $manual_templates = [];

    /** @var string */
    public $codproveedor;

    /** @var string */
    public $csvcolumns;

    /** @var string */
    public $date;

    /** @var int */
    public $expiration;

    /** @var string */
    public $expirationtype;

    /** @var int */
    public $id;

    /** @var int */
    public $idfile;

    /** @var string */
    public $mode;

    /** @var string */
    public $name;

    /** @var bool */
    public $no_titles;

    /** @var bool */
    public $noutf8file;

    /** @var string */
    public $options;

    /** @var string */
    public $path;

    /** @var string */
    public $profile;

    /** @var int */
    public $size;

    /** @var int */
    public $startline;

    /** @var string */
    public $template;

    /** @var string */
    public $url;

    public static function addAutoTemplate(string $profile, AutoTemplateInterface $template): void
    {
        if (!isset(self::$auto_templates[$profile])) {
            self::$auto_templates[$profile] = [];
        }

        self::$auto_templates[$profile][] = $template;
    }

    public static function addManualTemplate(string $profile, $template): void
    {
        if (!isset(self::$manual_templates[$profile])) {
            self::$manual_templates[$profile] = $template;
        }
    }

    public static function autoTemplate(string $filePath, string $profile): ?AutoTemplateInterface
    {
        if (!isset(self::$auto_templates[$profile])) {
            return null;
        }

        foreach (self::$auto_templates[$profile] as $template) {
            if ($template->isValid($filePath, $profile)) {
                return $template;
            }
        }

        return null;
    }

    public function clear()
    {
        parent::clear();
        $this->date = Tools::date();
        $this->expiration = 0;
        $this->mode = CsvFileTools::INSERT_MODE;
        $this->no_titles = false;
        $this->noutf8file = false;
        $this->size = 0;
        $this->startline = 0;
    }

    public function delete(): bool
    {
        $attachedFile = $this->getAttachedFile();
        if ($attachedFile && false === $attachedFile->delete()) {
            return false;
        }

        return parent::delete();
    }

    public function download(bool $save = true): bool
    {
        // si la url empieza por ftp://, entonces descargamos el fichero por FTP
        if (stripos($this->url, 'ftp://') === 0) {
            return $this->downloadFTPFile($save);
        }

        // si la url empieza por http, entonces descargamos el fichero por HTTP
        if (stripos($this->url, 'http') === 0) {
            return $this->downloadHTTPFile($save);
        }

        return false;
    }

    /**
     * Return the attached file to this build.
     *
     * @return AttachedFile
     */
    public function getAttachedFile(): ?AttachedFile
    {
        $attachedFile = new AttachedFile();
        if ($attachedFile->loadFromCode($this->idfile)) {
            return $attachedFile;
        }

        return null;
    }

    public function getCsvColumns(): array
    {
        return empty($this->csvcolumns) ? [] : json_decode($this->csvcolumns, true);
    }

    public static function getManualTemplates(): array
    {
        return self::$manual_templates;
    }

    public function getOptions(): array
    {
        return empty($this->options) ? [] : json_decode($this->options, true);
    }

    public function getProfile($offset = 0, $saveLines = 0, $filePath = null, $mode = null, bool $limit = true): ManualTemplateInterface
    {
        $limit = $limit ? ManualTemplateClass::LIMIT_IMPORT : null;
        $path = is_null($filePath) ? $this->path : $filePath;

        $model = clone $this;
        $model->mode = is_null($mode) || $mode === CsvFileTools::AUTOMATIC ? $model->mode : $mode;
        $model->path = str_replace(FS_FOLDER . DIRECTORY_SEPARATOR, '', $path);

        $manualClass = $this->getManualClass();
        $manualClass->load($model, $offset, $saveLines, $limit);

        return $manualClass;
    }

    public static function newTemplate(string $fileName, string $profile): CSVfile
    {
        $newCsvFile = new static();
        $newCsvFile->path = $fileName;
        $newCsvFile->profile = $profile;
        return $newCsvFile;
    }

    public static function primaryColumn(): string
    {
        return 'id';
    }

    public function setCsvColumns(array $values): bool
    {
        $this->csvcolumns = count($values) > 0 ? json_encode($values) : null;
        return $this->save();
    }

    public function setOptions(array $values): bool
    {
        $this->options = count($values) > 0 ? json_encode($values) : null;
        return $this->save();
    }

    public static function tableName(): string
    {
        return 'csv_files';
    }

    public function test(): bool
    {
        if (empty($this->path) && empty($this->url) && empty($this->id)) {
            Tools::log()->warning('file-or-url-required');
            return false;
        }

        if (in_array($this->mode, [CsvFileTools::AUTOMATIC, 'default'])) {
            $this->mode = CsvFileTools::INSERT_MODE;
        }

        // escapamos el html
        $this->name = Tools::noHtml($this->name);
        $this->template = empty($this->template) ? null : Tools::noHtml($this->template);
        $this->url = Tools::noHtml($this->url);

        // si al guardar el modelo por primera vez no hemos subido un archivo,
        // pero si hemos puesto una url, entonces descargamos el archivo
        if (empty($this->primaryColumnValue()) && empty($this->path) && !empty($this->url)) {
            $this->download(false);
        }

        // si tenemos path, pero el path no tiene MyFiles,
        // entonces es un archivo nuevo para guardar
        if ($this->path && false === strpos($this->path, 'MyFiles')) {
            return $this->saveFile() && parent::test();
        }

        return parent::test();
    }

    public function url(string $type = 'auto', string $list = 'ListAttachedFile?activetab=List'): string
    {
        return parent::url($type, $list);
    }

    protected function downloadFTPFile(bool $save): bool
    {
        // tenemos una url de ejemplo: ftp:://usuario:clave@servidor|archivo
        // debemos obtener por separado el usuario, la clave, el servidor y el archivo
        $urlParts = explode('@', str_replace('ftp://', '', $this->url));
        if (count($urlParts) !== 2) {
            Tools::log()->warning('invalid-ftp', ['%ftp%' => Tools::noHtml($this->url)]);
            return false;
        }

        $userParts = explode(':', $urlParts[0]);
        if (count($userParts) !== 2) {
            Tools::log()->warning('invalid-ftp', ['%ftp%' => Tools::noHtml($this->url)]);
            return false;
        }

        $user = $userParts[0];
        $password = $userParts[1];

        $serverParts = explode('|', $urlParts[1]);
        if (count($serverParts) !== 2) {
            Tools::log()->warning('invalid-ftp', ['%ftp%' => Tools::noHtml($this->url)]);
            return false;
        }

        $server = $serverParts[0];
        $file = $serverParts[1];

        // Establecer una conexión básica
        $conn_id = ftp_connect($server);
        if (false === $conn_id) {
            Tools::log()->warning('ftp-connect-error', ['%server%' => Tools::noHtml($server)]);
            return false;
        }

        // Iniciar sesión con nombre de usuario y contraseña
        $login_result = ftp_login($conn_id, $user, $password);
        if (false === $login_result) {
            Tools::log()->warning('ftp-login-error', ['%user%' => Tools::noHtml($user)]);
            ftp_close($conn_id);
            return false;
        }

        // Habilitar el modo pasivo
        ftp_pasv($conn_id, true);

        // Descargamos el archivo
        if (false === ftp_get($conn_id, Tools::folder('MyFiles', $file), $file)) {
            Tools::log()->warning('ftp-download-error', ['%file%' => Tools::noHtml($file)]);
            ftp_close($conn_id);
            return false;
        }

        // Cerrar la conexión FTP
        ftp_close($conn_id);

        $this->path = $file;
        return !$save || $this->save();
    }

    protected function downloadHTTPFile(bool $save): bool
    {
        if (Validator::url($this->url) === false) {
            Tools::log()->warning('invalid-web', ['%web%' => Tools::noHtml($this->url)]);
            return false;
        }

        // creamos un nombre temporal para el fichero
        $fileName = 'csv_file_' . $this->primaryColumnValue();

        // si la url tiene extensión al final, la añadimos al nombre temporal del fichero
        $urlParts = explode('.', $this->url);
        if (count($urlParts) > 1 && strlen(end($urlParts)) < 5) {
            $fileName .= '.' . end($urlParts);
        }

        $filePath = Tools::folder('MyFiles', $fileName);
        if (false === Http::get($this->url)->saveAs($filePath)) {
            Tools::log()->warning('download-file-error: ' . $this->url);
            return false;
        }

        if (false === CsvFileTools::isValidFile($filePath)) {
            unlink($filePath);
            return false;
        }

        try {
            $filePath = CsvFileTools::convertFileToCSV($filePath);
            $this->path = basename($filePath);
        } catch (Exception $exc) {
            unlink($filePath);
            Tools::log()->warning('convert-file-error');
            return false;
        }

        return !$save || $this->save();
    }

    protected function getManualClass(): ManualTemplateInterface
    {
        if (isset(self::$manual_templates[$this->profile])) {
            return self::$manual_templates[$this->profile];
        }

        return self::$manual_templates['customers'];
    }

    protected function saveFile(): bool
    {
        $filePath = CsvFileTools::getFilePath($this->path);
        if (empty($filePath)) {
            return false;
        }

        $attachedFile = new AttachedFile();
        $attachedFile->path = $this->path;
        if (false === $attachedFile->save()) {
            unlink($filePath);
            return false;
        }

        // eliminamos el fichero antiguo
        if ($this->idfile) {
            $oldAttachedFile = new AttachedFile();
            $oldAttachedFile->loadFromCode($this->idfile);
            $oldAttachedFile->delete();
        }

        $this->idfile = $attachedFile->idfile;
        $this->name = $attachedFile->filename;
        $this->path = $attachedFile->path;
        $this->size = $attachedFile->size;

        if ($this->no_titles) {
            $filePath = FS_FOLDER . DIRECTORY_SEPARATOR . $this->path;
            CsvFileTools::generateCsvTitles($filePath, $this->startline);
        }

        return true;
    }
}
