<?php
/**
 * Copyright (C) 2023 Daniel Fernández Giménez <hola@danielfg.es>
 */

namespace FacturaScripts\Plugins\Traducciones\Lib;

use FacturaScripts\Core\Base\DataBase\DataBaseWhere;
use FacturaScripts\Core\Cache;
use FacturaScripts\Core\Plugins;
use FacturaScripts\Core\Tools;
use FacturaScripts\Dinamic\Model\AttachedFile;
use FacturaScripts\Dinamic\Model\Language;

/**
 * @author Daniel Fernández Giménez <hola@danielfg.es>
 */
trait LanguageTrait
{
    public static $deploy = true;

    public static $json = true;
    public static $languagesCore = [
        [
            'codpais' => 'ESP',
            'name' => 'Spanish (Spain)',
            'codicu' => 'es_ES',
            'icon' => 'es_es.svg',
        ],
        [
            'codpais' => 'ESP',
            'name' => 'Spanish (Catalan)',
            'codicu' => 'ca_ES',
            'icon' => 'ca_es.svg',
        ],
        [
            'codpais' => 'ARG',
            'name' => 'Spanish (Argentina)',
            'codicu' => 'es_AR',
            'icon' => 'es_ar.svg',
        ],
        [
            'codpais' => 'CHL',
            'name' => 'Spanish (Chile)',
            'codicu' => 'es_CL',
            'icon' => 'es_cl.svg',
        ],
        [
            'codpais' => 'COL',
            'name' => 'Spanish (Colombia)',
            'codicu' => 'es_CO',
            'icon' => 'es_co.svg',
        ],
        [
            'codpais' => 'CRI',
            'name' => 'Spanish (Costa Rica)',
            'codicu' => 'es_CR',
            'icon' => 'es_cr.svg',
        ],
        [
            'codpais' => 'DOM',
            'name' => 'Spanish (República Dominicana)',
            'codicu' => 'es_DO',
            'icon' => 'es_do.svg',
        ],
        [
            'codpais' => 'ECU',
            'name' => 'Spanish (Ecuador)',
            'codicu' => 'es_EC',
            'icon' => 'es_ec.svg',
        ],
        [
            'codpais' => 'GTM',
            'name' => 'Spanish (Guatemala)',
            'codicu' => 'es_GT',
            'icon' => 'es_gt.svg',
        ],
        [
            'codpais' => 'MEX',
            'name' => 'Spanish (Mexico)',
            'codicu' => 'es_MX',
            'icon' => 'es_mx.svg',
        ],
        [
            'codpais' => 'PER',
            'name' => 'Spanish (Peru)',
            'codicu' => 'es_PE',
            'icon' => 'es_pe.svg',
        ],
        [
            'codpais' => 'URY',
            'name' => 'Spanish (Uruguay)',
            'codicu' => 'es_UY',
            'icon' => 'es_uy.svg',
        ],
        [
            'codpais' => 'ESP',
            'name' => 'Spanish (Galician)',
            'codicu' => 'gl_ES',
            'icon' => 'gl_es.svg',
        ],
        [
            'codpais' => 'ESP',
            'name' => 'Spanish (Valencian)',
            'codicu' => 'va_ES',
            'icon' => 'va_es.svg',
        ],
        [
            'codpais' => 'ITA',
            'name' => 'Italian',
            'codicu' => 'it_IT',
            'icon' => 'it_it.svg',
        ],
        [
            'codpais' => 'PRT',
            'name' => 'Portuguese',
            'codicu' => 'pt_PT',
            'icon' => 'pt_pt.svg',
        ],
        [
            'codpais' => 'ESP',
            'name' => 'Spanish (Basque)',
            'codicu' => 'eu_ES',
            'icon' => 'eu_es.svg',
        ],
        [
            'codpais' => 'GBR',
            'name' => 'English (United Kingdom)',
            'codicu' => 'en_GB',
            'icon' => 'en_gb.svg',
        ],
        [
            'codpais' => 'USA',
            'name' => 'English (United States)',
            'codicu' => 'en_US',
            'icon' => 'en_us.svg',
        ],
        [
            'codpais' => 'FRA',
            'name' => 'French',
            'codicu' => 'fr_FR',
            'icon' => 'fr_fr.svg',
        ],
        [
            'codpais' => 'DEU',
            'name' => 'German',
            'codicu' => 'de_DE',
            'icon' => 'de_de.svg',
        ],
        [
            'codpais' => 'GBR',
            'name' => 'English',
            'codicu' => 'en_EN',
            'icon' => 'en_gb.svg',
        ],
        [
            'codpais' => 'PAN',
            'name' => 'Panamanian (Panama)',
            'codicu' => 'es_PA',
            'icon' => 'es_pa.svg',
        ]
    ];

    public static function copyNewLanguages(): bool
    {
        self::$deploy = false;
        foreach (self::getAvailableLanguages(true, true, true, false) as $lang) {
            // comprobamos que no existe el idioma, si existe continuamos
            $languageModel = new Language();
            $where = [new DataBaseWhere('codicu', $lang['codicu'])];
            if ($languageModel->loadFromCode('', $where)) {
                continue;
            }

            // si el idioma no tiene país, continuamos
            $languageModel->codpais = $lang['codpais'];
            if (empty($languageModel->codpais)) {
                continue;
            }

            // buscamos si no existe la imagen en la biblioteca
            $file = new AttachedFile();
            $imgFlag = $lang['flag'];
            $where = [new DataBaseWhere('filename', $imgFlag)];
            if (false === $file->loadFromCode('', $where)) {
                // copiamos la imagen de la bandera a la carpeta MyFiles
                // añadimos la imagen de la bandera si se puedo copiar y guardar
                $file->path = $imgFlag;
                $filePath = FS_FOLDER . '/Plugins/Traducciones/MyFiles/' . $imgFlag;
                if (file_exists($filePath)
                    && copy($filePath, FS_FOLDER . '/MyFiles/' . $imgFlag)
                    && $file->save()) {
                    $languageModel->idflag = $file->idfile;
                }
            } else {
                $languageModel->idflag = $file->idfile;
            }

            // creamos el idioma
            $languageModel->codicu = $lang['codicu'];
            $languageModel->name = Tools::lang()->trans('languages-' . $lang['codicu']);
            if (false === $languageModel->save()) {
                return false;
            }
        }

        self::$deploy = true;
        self::deploy();
        return true;
    }

    public static function deploy()
    {
        if (false === self::$deploy) {
            return;
        }

        $i18n = Tools::lang();
        if (method_exists($i18n, 'reload')) {
            $i18n::reload();
        }

        Cache::clear();
    }

    public static function generateJson(Language $lang): bool
    {
		if (false === self::$json) {
            return true;
        }
		
        // creamos el directorio de traducciones
        $dir = FS_FOLDER . '/MyFiles/Translation/';
        if (false === file_exists($dir)) {
            mkdir($dir, 0777, true);
        }
	    $translations = [];
        foreach ($lang->getTranslations() as $trans) {
            $translations[$trans->keytrans] = $trans->valuetrans;
        }

        $filePath = FS_FOLDER . '/MyFiles/Translation/' . $lang->codicu . '.json';
        $jsonTranslations = json_encode($translations, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (false === file_put_contents($filePath, $jsonTranslations)) {
            Tools::log()->error('cant-save-file', ['%filePath%' => $filePath]);
            return false;
        }

        Tools::log()->notice('file-saved-correctly', ['%filePath%' => $filePath]);
        return true;
    }

    public static function getAvailableLanguages(bool $strict = true, bool $core = true, bool $plugin = true, bool $custom = true): array
    {
        $languages = [];

        if ($core) {
            foreach (self::getCoreLanguages() as $lang) {
                if (false === $strict) {
                    $languages[] = $lang;
                } elseif (false === in_array($lang['codicu'], array_column($languages, 'key'))) {
                    $languages[] = $lang;
                }
            }
        }

        if ($plugin) {
            foreach (self::getPluginsLanguages() as $pluginName => $pluginLanguages) {
                foreach ($pluginLanguages as $lang) {
                    if (false === $strict) {
                        $languages[] = $lang;
                    } elseif (false === in_array($lang['codicu'], array_column($languages, 'codicu'))) {
                        $languages[] = $lang;
                    }
                }
            }
        }

        if ($custom) {
            foreach (self::getCustomLanguages() as $lang) {
                if (false === $strict) {
                    $languages[] = $lang;
                } elseif (false === in_array($lang['codicu'], array_column($languages, 'codicu'))) {
                    $languages[] = $lang;
                }
            }
        }

        // ordenamos el array por el campo title
        usort($languages, function ($a, $b) {
            return strcasecmp($a['title'], $b['title']);
        });

        return $languages;
    }

    public static function getCoreLanguages(): array
    {
        $languages = [];
        $dir = FS_FOLDER . '/Core/Translation';
        if (false === file_exists($dir) || false === is_dir($dir)) {
            return $languages;
        }

        foreach (scandir($dir, SCANDIR_SORT_ASCENDING) as $fileName) {
            if ($fileName !== '.' && $fileName !== '..' && !is_dir($fileName) && substr($fileName, -5) === '.json') {
                $codicu = substr($fileName, 0, -5);
                $languages[] = [
                    'codicu' => $codicu,
                    'codpais' => self::setCountry($codicu),
                    'flag' => self::setFlag($codicu),
                    'file' => $fileName,
                    'folder' => FS_FOLDER . '/Core/Translation',
                    'path' => FS_FOLDER . '/Core/Translation/' . $fileName,
                    'title' => Tools::lang()->trans('languages-' . $codicu)
                ];
            }
        }

        return $languages;
    }

    public static function getCustomLanguages(): array
    {
        $languages = [];
        $dir = FS_FOLDER . '/MyFiles/Translation';
        if (false === file_exists($dir) || false === is_dir($dir)) {
            return $languages;
        }

        foreach (scandir($dir, SCANDIR_SORT_ASCENDING) as $fileName) {
            if ($fileName !== '.' && $fileName !== '..' && !is_dir($fileName) && substr($fileName, -5) === '.json') {
                $codicu = substr($fileName, 0, -5);

                // obtenemos el idioma si existe
                $langModel = new Language();
                $where = [new DataBaseWhere('codicu', $codicu)];
                $langModel->loadFromCode('', $where);

                $languages[] = [
                    'codicu' => $codicu,
                    'codpais' => $langModel->codpais,
                    'flag' => self::setFlag($codicu),
                    'file' => $fileName,
                    'folder' => FS_FOLDER . '/MyFiles/Translation',
                    'path' => FS_FOLDER . '/MyFiles/Translation/' . $fileName,
                    'title' => Tools::lang()->trans('languages-' . $codicu)
                ];
            }
        }

        return $languages;
    }

    public static function getLanguages(): array
    {
        $langModel = new Language();
        return $langModel->all([], ['name' => 'ASC'], 0, 0);
    }

    public static function getPluginsLanguages(): array
    {
        $languages = [];
        foreach (Plugins::list() as $plugin) {
            if (false === $plugin->enabled) {
                continue;
            }

            $dir = FS_FOLDER . '/Plugins/' . $plugin->name . '/Translation';
            if (false === file_exists($dir) || false === is_dir($dir)) {
                continue;
            }

            $pluginLanguages = [];
            foreach (scandir($dir, SCANDIR_SORT_ASCENDING) as $fileName) {
                if ($fileName !== '.' && $fileName !== '..' && !is_dir($fileName) && substr($fileName, -5) === '.json') {
                    $codicu = substr($fileName, 0, -5);
                    $pluginLanguages[] = [
                        'codicu' => $codicu,
                        'codpais' => self::setCountry($codicu),
                        'flag' => self::setFlag($codicu),
                        'file' => $fileName,
                        'folder' => FS_FOLDER . '/Plugins/' . $plugin->name . '/Translation',
                        'path' => FS_FOLDER . '/Plugins/' . $plugin->name . '/Translation/' . $fileName,
                        'title' => Tools::lang()->trans('languages-' . $codicu)
                    ];
                }
            }

            $languages[$plugin->name] = $pluginLanguages;
        }

        return $languages;
    }

    protected static function setCountry(string $codicu): ?string
    {
        foreach (self::$languagesCore as $lang) {
            if ($lang['codicu'] === $codicu) {
                return $lang['codpais'];
            }
        }
        return null;
    }

    protected static function setFlag(string $codicu): ?string
    {
        foreach (self::$languagesCore as $lang) {
            if ($lang['codicu'] === $codicu) {
                return $lang['icon'];
            }
        }
        return null;
    }
}