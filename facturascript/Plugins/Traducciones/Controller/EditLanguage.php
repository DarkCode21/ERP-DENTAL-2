<?php
/**
 * Copyright (C) 2023 Daniel Fernández Giménez <hola@danielfg.es>
 */

namespace FacturaScripts\Plugins\Traducciones\Controller;

use FacturaScripts\Core\Base\DataBase\DataBaseWhere;
use FacturaScripts\Core\Lib\ExtendedController\EditController;
use FacturaScripts\Core\Tools;
use FacturaScripts\Dinamic\Model\Language;
use FacturaScripts\Dinamic\Model\Translate;
use FacturaScripts\Plugins\Traducciones\Lib\LanguageTrait;

/**
 * @author Daniel Fernández Giménez <hola@danielfg.es>
 */
class EditLanguage extends EditController
{
    public function getModelClassName(): string
    {
        return 'Language';
    }

    public function getPageData(): array
    {
        $pagedata = parent::getPageData();
        $pagedata['menu'] = 'translates';
        $pagedata['title'] = 'language';
        $pagedata['icon'] = 'fas fa-globe-europe';
        return $pagedata;
    }

    protected function copyCorePluginTranslateFrom(): bool
    {
        // validamos permisos
        if (false === $this->validateFormToken()) {
            return false;
        } elseif (false === $this->permissions->allowUpdate) {
            Tools::log()->warning('not-allowed-modify');
            return false;
        }

        // obtenemos el idioma de origen
        $langOrig = new Language();
        $langOrig->loadFromCode($this->request->get('code'));
        if (false === $langOrig->exists()) {
            Tools::log()->warning('record-not-found');
            return false;
        }

        // obtenemos el idioma seleccionado desde donde copiar
        $langCopy = $this->request->get('copylanguage', '');

        $cont = 0;
        $found = 0;
        LanguageTrait::$deploy = false;
        LanguageTrait::$json = false;

        // obtenemos todos los archivos de idioma del core y de los plugins activos
        foreach (LanguageTrait::getAvailableLanguages(false, true, true, false) as $lang) {
            // comprobamos si el idioma seleccionado es igual que el idioma a copiar, si no continuamos
            if ($lang['codicu'] !== $langCopy) {
                continue;
            }

            // obtenemos el json del idioma a copiar
            $jsonCopy = json_decode(file_get_contents($lang['path']), true);

            // comprobamos si existe la traducción en el idioma original, si no existe la creamos
            foreach ($jsonCopy as $key => $value) {
                $transModel = new Translate();
                $where = [
                    new DataBaseWhere('idlang', $langOrig->id),
                    new DataBaseWhere('keytrans', $key)
                ];

                if ($transModel->loadFromCode('', $where)) {
                    $found++;
                    continue;
                }

                // creamos la traducción
                $transModel->idlang = $langOrig->id;
                $transModel->keytrans = $key;
                $transModel->valuetrans = $value;

                if ($transModel->save()) {
                    $cont++;
                    continue;
                }

                if ($cont > 0) {
                    Tools::log()->notice('copy-translates-ok', ['%count%' => $cont]);
                }

                if ($found > 0) {
                    Tools::log()->warning('copy-translates-found', ['%found%' => $found]);
                }

                Tools::log()->warning('copy-translates-error', ['%keytrans%' => $key]);
            }
        }

        if ($cont > 0) {
            Tools::log()->notice('copy-translates-ok', ['%count%' => $cont]);
        } else {
            Tools::log()->warning('copy-translates-no-translations', ['%lang%' => $langCopy]);
        }

        LanguageTrait::$deploy = true;
        LanguageTrait::$json = true;
        LanguageTrait::generateJson($langOrig);
        LanguageTrait::deploy();
        return true;
    }

    protected function copyCustomTranslateFrom(): bool
    {
        // validamos permisos
        if (false === $this->validateFormToken()) {
            return false;
        } elseif (false === $this->permissions->allowUpdate) {
            Tools::log()->warning('not-allowed-modify');
            return false;
        }

        // obtenemos el idioma de origen
        $langOrig = new Language();
        $langOrig->loadFromCode($this->request->get('code'));
        if (false === $langOrig->exists()) {
            Tools::log()->warning('record-not-found');
            return false;
        }

        // obtenemos el idioma seleccionado desde donde copiar
        $langCopy = new Language();
        $langCopy->loadFromCode($this->request->get('copylanguage'));
        if (false === $langCopy->exists()) {
            Tools::log()->warning('record-not-found');
            return false;
        }

        $cont = 0;
        $found = 0;

        // obtenemos las traducciones del idioma seleccionado desde donde copiar
        $translations = $langCopy->getTranslations();

        if (empty($translations)) {
            Tools::log()->warning('copy-translates-no-translations', ['%lang%' => $langCopy->name]);
            return true;
        }

        LanguageTrait::$deploy = false;
        LanguageTrait::$json = false;
        foreach ($translations as $trans) {
            // si existe una traducción para la misma clave en el idioma original, continuamos
            $transModel = new Translate();
            $where = [
                new DataBaseWhere('idlang', $langOrig->id),
                new DataBaseWhere('keytrans', $trans->keytrans)
            ];

            if ($transModel->loadFromCode('', $where)) {
                $found++;
                continue;
            }

            // creamos la traducción
            $transModel->idlang = $langOrig->id;
            $transModel->keytrans = $trans->keytrans;
            $transModel->valuetrans = $trans->valuetrans;

            if ($transModel->save()) {
                $cont++;
                continue;
            }

            if ($cont > 0) {
                Tools::log()->notice('copy-translates-ok', ['%count%' => $cont]);
            }

            if ($found > 0) {
                Tools::log()->warning('copy-translates-found', ['%found%' => $found]);
            }

            Tools::log()->warning('copy-translates-error', ['%keytrans%' => $trans->keytrans]);

            LanguageTrait::$deploy = true;
            LanguageTrait::$json = true;
            LanguageTrait::generateJson($langOrig);
            LanguageTrait::deploy();
            return false;
        }

        if ($cont > 0) {
            Tools::log()->notice('copy-translates-ok', ['%count%' => $cont]);
        } else {
            Tools::log()->warning('copy-translates-no-translations', ['%lang%' => $langCopy->name]);
        }

        LanguageTrait::$deploy = true;
        LanguageTrait::$json = true;
        LanguageTrait::generateJson($langOrig);
        LanguageTrait::deploy();
        return true;
    }

    protected function createViews()
    {
        parent::createViews();
        $this->setTabsPosition('bottom');
        $this->createViewsTranslates();
    }

    protected function createViewsTranslates(string $viewName = 'ListTranslate')
    {
        $this->addListView($viewName, 'Translate', 'translates');
        $this->views[$viewName]->addSearchFields(['keytrans', 'valuetrans']);
        $this->views[$viewName]->addOrderBy(['idlang', 'keytrans'], 'language');
        $this->views[$viewName]->addOrderBy(['keytrans'], 'value', 1);

        // desactivamos columnas
        $this->views[$viewName]->disableColumn('language');
    }

    protected function execPreviousAction($action): bool
    {
        switch ($action) {
            case 'copy-custom-translates-from':
                return $this->copyCustomTranslateFrom();

            case 'copy-core-plugin-translates-from':
                return $this->copyCorePluginTranslateFrom();
        }

        return parent::execPreviousAction($action);
    }

    protected function loadData($viewName, $view)
    {
        $mvn = $this->getMainViewName();
        switch ($viewName) {
            case 'ListTranslate':
                $where = [new DataBaseWhere('idlang', $this->getViewModelValue($mvn, 'id'))];
                $view->loadData('', $where);
                break;

            case $mvn:
                parent::loadData($viewName, $view);
                $this->loadImageFiles($view, 'flag');
                $this->loadLanguageCorePlugin($view, 'language-core-plugin');
                $this->loadLanguageCustom($view, 'language-custom');

                if ($view->model->exists()) {
                    $this->addButton($viewName, [
                        'action' => 'copy-core-plugin-translates-from',
                        'color' => 'info',
                        'icon' => 'fas fa-copy',
                        'label' => 'copy-core-plugin-translates-from',
                        'title' => 'copy-core-plugin-translates-from-desc',
                        'type' => 'modal'
                    ]);
                    $this->addButton($viewName, [
                        'action' => 'copy-custom-translates-from',
                        'color' => 'info',
                        'icon' => 'fas fa-copy',
                        'label' => 'copy-custom-translates-from',
                        'title' => 'copy-custom-translates-from-desc',
                        'type' => 'modal'
                    ]);
                }
                break;
        }
    }

    protected function loadLanguageCorePlugin($view, $columnName)
    {
        $column = $view->columnModalForName($columnName);
        if (empty($column) || $column->widget->getType() !== 'select') {
            return;
        }

        $languages = [];
        foreach (LanguageTrait::getAvailableLanguages(true, true, true, false) as $lang) {
            $languages[] = ['value' => $lang['codicu'], 'title' => $lang['title']];
        }
        $column->widget->setValuesFromArray($languages);
    }

    protected function loadLanguageCustom($view, $columnName)
    {
        $column = $view->columnModalForName($columnName);
        if (empty($column) || $column->widget->getType() !== 'select') {
            return;
        }

        // obtenemos la traducción actual
        $langOrig = new Language();
        $langOrig->loadFromCode($this->request->get('code'));

        $languages = [];
        $langModel = new Language();
        foreach ($langModel->all([], ['name' => 'ASC'], 0, 0) as $lang) {
            if ($langOrig->id !== $lang->id) {
                $languages[] = ['value' => $lang->id, 'title' => $lang->name];
            }
        }
        $column->widget->setValuesFromArray($languages);
    }

    protected function loadImageFiles($view, $columnName, $format = 'image/gif,image/jpeg,image/jpg,image/png,image/svg,image/svg+xml')
    {
        $column = $view->columnForName($columnName);
        if ($column && $column->widget->getType() === 'select') {
            $images = $this->codeModel->all('attached_files', 'idfile', 'filename', true, [
                new DataBaseWhere('mimetype', $format, 'IN')
            ]);
            $column->widget->setValuesFromCodeModel($images);
        }
    }
}