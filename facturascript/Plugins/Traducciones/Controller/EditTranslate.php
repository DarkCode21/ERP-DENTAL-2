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
class EditTranslate extends EditController
{
    public function getModelClassName(): string
    {
        return 'Translate';
    }

    public function getPageData(): array
    {
        $pagedata = parent::getPageData();
        $pagedata['menu'] = 'translates';
        $pagedata['title'] = 'translate';
        $pagedata['icon'] = 'fas fa-language';
        return $pagedata;
    }

    protected function copyTranslateTo(): bool
    {
        // validamos permisos
        if (false === $this->validateFormToken()) {
            return false;
        } elseif (false === $this->permissions->allowUpdate) {
            Tools::log()->warning('not-allowed-modify');
            return false;
        }

        // obtenemos la traducción original
        $transOrig = new Translate();
        if (false === $transOrig->loadFromCode($this->request->get('code'))) {
            Tools::log()->warning('record-not-found');
            return false;
        }

        $languages = [];
        $copyLang = $this->request->get('copylanguage', '');
        if (empty($copyLang)) {
            return true;
        }

        if ($copyLang === 'all') {
            // selecciono copiar a todos los idiomas
            $langModel = new Language();
            $where = [new DataBaseWhere('id', $transOrig->idlang, '!=')];
            $languages = $langModel->all($where, [], 0, 0);
        } else {
            // selecciono copiar 1 solo idioma, obtenemos el idioma seleccionado hacia donde copiar
            $langModel = new Language();
            if (false === $langModel->loadFromCode($copyLang)) {
                Tools::log()->warning('record-not-found');
                return false;
            }
            $languages[] = $langModel;
        }

        LanguageTrait::$deploy = false;
        LanguageTrait::$json = false;
        foreach ($languages as $lang) {
            // buscamos si existe la traducción en el idioma seleccionado, si no existe la creamos
            $transCopy = new Translate();
            $where = [
                new DataBaseWhere('idlang', $lang->id),
                new DataBaseWhere('keytrans', $transOrig->keytrans)
            ];

            if ($transCopy->loadFromCode('', $where)) {
                Tools::log()->info('copy-translate-exists', ['%lang%' => $lang->name]);
                continue;
            }

            $transCopy->idlang = $lang->id;
            $transCopy->keytrans = $transOrig->keytrans;
            $transCopy->valuetrans = $transOrig->valuetrans;
            if (false === $transCopy->save()) {
                Tools::log()->error('copy-translate-error', ['%lang%' => $lang->name]);
                continue;
            }

            Tools::log()->notice('copy-translate-ok', ['%lang%' => $lang->name]);
        }

        LanguageTrait::$deploy = true;
        LanguageTrait::$json = true;
        foreach ($languages as $lang) {
            LanguageTrait::generateJson($lang);
        }
        LanguageTrait::deploy();

        return true;
    }

    protected function createViews()
    {
        parent::createViews();
        $this->setTabsPosition('bottom');
        $this->createViewsOtherTranslates();
    }

    protected function createViewsOtherTranslates(string $viewName = 'ListTranslate')
    {
        $this->addListView($viewName, 'Translate', 'translates');
        $this->views[$viewName]->addOrderBy(['idlang'], 'language', 1);

        // desactivamos columnas
        $this->views[$viewName]->disableColumn('value');

        // desactivamos botones
        $this->setSettings($viewName, 'btnNew', false);
        $this->setSettings($viewName, 'btnDelete', false);
        $this->setSettings($viewName, 'checkBoxes', false);
    }

    protected function execPreviousAction($action): bool
    {
        if ($action === 'copy-translate-to') {
            return $this->copyTranslateTo();
        }

        return parent::execPreviousAction($action);
    }

    protected function loadData($viewName, $view)
    {
        $mvn = $this->getMainViewName();
        switch ($viewName) {
            case 'ListTranslate':
                $where = [
                    new DataBaseWhere('idlang', $this->getViewModelValue($mvn, 'idlang'), '!='),
                    new DataBaseWhere('keytrans', $this->getViewModelValue($mvn, 'keytrans'))
                ];
                $view->loadData('', $where);
                break;

            case $mvn:
                parent::loadData($viewName, $view);
                $this->loadTranslateCopy($view, 'language-copy');

                if ($view->model->exists()) {
                    $this->addButton($viewName, [
                        'action' => 'copy-translate-to',
                        'color' => 'info',
                        'icon' => 'fas fa-copy',
                        'label' => 'copy-translate-to',
                        'title' => 'copy-translate-to-desc',
                        'type' => 'modal'
                    ]);
                }
                break;
        }
    }

    protected function loadTranslateCopy($view, $columnName)
    {
        $column = $view->columnModalForName($columnName);
        if (empty($column) || $column->widget->getType() !== 'select') {
            return;
        }

        // obtenemos el idioma actual
        $transOrig = new Translate();
        $transOrig->loadFromCode($this->request->get('code'));
        $langOrig = $transOrig->getLanguage();

        $languages = [['value' => 'all', 'title' => Tools::lang()->trans('all-languages')]];
        $langModel = new Language();
        foreach ($langModel->all([], ['name' => 'ASC'], 0, 0) as $lang) {
            if ($langOrig->id !== $lang->id) {
                $languages[] = ['value' => $lang->id, 'title' => $lang->name];
            }
        }
        $column->widget->setValuesFromArray($languages);
    }
}