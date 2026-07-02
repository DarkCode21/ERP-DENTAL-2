<?php
/**
 * Copyright (C) 2023 Daniel Fernández Giménez <hola@danielfg.es>
 */

namespace FacturaScripts\Plugins\Traducciones\Controller;

use FacturaScripts\Core\Lib\ExtendedController\ListController;
use FacturaScripts\Core\Tools;
use FacturaScripts\Dinamic\Model\Language;
use FacturaScripts\Plugins\Traducciones\Lib\LanguageTrait;

/**
 * @author Daniel Fernández Giménez <hola@danielfg.es>
 */
class ListTranslate extends ListController
{
    /** @var array */
    public $resultSearch = [];

    /** @var string */
    public $term;

    public function getPageData(): array
    {
        $data = parent::getPageData();
        $data['menu'] = 'admin';
        $data['title'] = 'translates';
        $data['icon'] = 'fas fa-language';
        return $data;
    }

    protected function copyNewLanguages(): bool
    {
        // validamos permisos
        if (false === $this->validateFormToken()) {
            return false;
        } elseif (false === $this->permissions->allowAccess) {
            Tools::log()->warning('access-denied-p');
            return false;
        } elseif (false === $this->user->can('EditLanguage', 'update')) {
            Tools::log()->warning('not-allowed-modify');
            return false;
        }

        return LanguageTrait::copyNewLanguages();
    }
    
    protected function createViews() {
        $this->createViewsTranslates();
        $this->createViewsLanguages();
        $this->createViewsSearch();
    }

    protected function createViewsLanguages(string $viewName = 'ListLanguage')
    {
        $this->addView($viewName, 'Language', 'languages', 'fas fa-globe-europe');
        $this->views[$viewName]->addSearchFields(['name', 'codicu']);
        $this->views[$viewName]->addOrderBy(['codpais', 'codicu'], 'country');
        $this->views[$viewName]->addOrderBy(['name'], 'name', 1);
        $this->views[$viewName]->addOrderBy(['codicu'], 'codeicu');

        // Filtros
        $this->views[$viewName]->addFilterAutocomplete('codpais', 'country', 'codpais', 'paises', 'codpais', 'nombre');

        // Botones
        $this->addButton($viewName, [
            'action' => 'copy-new-languages',
            'color' => 'info',
            'icon' => 'fas fa-magic btn-spin-action',
            'title' => 'copy-new-languages',
            'type' => 'action'
        ]);
        $this->addButton($viewName, [
            'action' => 'generate-json',
            'color' => 'warning',
            'icon' => 'fas fa-file-alt',
            'title' => 'generate-file-language',
            'type' => 'action'
        ]);
    }

    protected function createViewsSearch(string $viewName = 'ListTranslate-2')
    {
        $this->addView($viewName, 'Translate', 'search', 'fas fa-search');
        $this->views[$viewName]->template = 'SearchTranslate.html.twig';
    }

    protected function createViewsTranslates(string $viewName = 'ListTranslate')
    {
        $this->addView($viewName, 'Translate', 'translates', 'fas fa-language');
        $this->views[$viewName]->addSearchFields(['keytrans', 'valuetrans']);
        $this->views[$viewName]->addOrderBy(['idlang', 'keytrans'], 'language');
        $this->views[$viewName]->addOrderBy(['keytrans'], 'value', 1);

        // Filtros
        $languages = $this->codeModel->all('languages', 'id', 'name');
        $this->views[$viewName]->addFilterSelect('idlang', 'language', 'idlang', $languages);
    }

    protected function execPreviousAction($action): bool
    {
        switch ($action) {
            case 'copy-new-languages':
                return $this->copyNewLanguages();

            case 'generate-json':
                return $this->generateJson();

            case 'search':
                $this->searchKeyOrValue();
                return true;
        }

        return parent::execPreviousAction($action);
    }

    protected function generateJson(): bool
    {
        // validamos permisos
        if (false === $this->validateFormToken()) {
            return false;
        } elseif (false === $this->permissions->allowAccess) {
            Tools::log()->warning('access-denied-p');
            return false;
        } elseif (false === $this->user->can('EditLanguage', 'update')) {
            Tools::log()->warning('not-allowed-modify');
            return false;
        }

        // recogemos los idiomas seleccionados
        $languages = $this->request->request->get('code', []);
        if (empty($languages)) {
            return true;
        }

        // recorremos los idiomas y construimos el JSON
        foreach ($languages as $lang) {
            $langModel = new Language();
            if (false === $langModel->loadFromCode($lang)) {
                continue;
            }
            LanguageTrait::generateJson($langModel);
        }

        LanguageTrait::deploy();
        return true;
    }

    protected function loadData($viewName, $view)
    {
        switch ($viewName) {
            case 'ListTranslate':
            case 'ListLanguage':
                parent::loadData($viewName, $view);
                break;
        }
    }

    protected function searchKeyOrValue()
    {
        $this->term = $this->request->get('term', '');
        if (empty($this->term)) {
            return;
        }

        foreach (LanguageTrait::getAvailableLanguages(false) as $lang) {
            // obtenemos el json del archivo
            $json = file_get_contents( $lang['path']);

            // convertimos el json en array
            $jsonArray = json_decode($json, true);

            // buscamos la palabra tanto en la key como en el value del jsonArray
            $result = [];
            foreach ($jsonArray as $key => $value) {
                if (false !== stripos($key, $this->term) || false !== stripos($value, $this->term)) {
                    // si encuentra la palabra en el array $result, coloremos el fondo de la palabra
                    $key = str_ireplace($this->term, '<span class="bg-warning">' . $this->term . '</span>', $key);
                    $value = str_ireplace($this->term, '<span class="bg-warning">' . $this->term . '</span>', $value);
                    $result[$key] = $value;
                }
            }

            // si el array $result no está vacío, lo añadimos al array $resultSearch
            if (false === empty($result)) {
                $this->resultSearch[$lang['codicu']] = $result;
            }
        }
    }
}