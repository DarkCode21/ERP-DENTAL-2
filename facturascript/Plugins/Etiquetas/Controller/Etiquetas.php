<?php
/**
 * Copyright (C) 2022-2023 Carlos Garcia Gomez <carlos@facturascripts.com>
 */

namespace FacturaScripts\Plugins\Etiquetas\Controller;

use FacturaScripts\Core\Base\Controller;
use FacturaScripts\Core\Base\DataBase\DataBaseWhere;
use FacturaScripts\Dinamic\Model\Variante;

/**
 * @author Carlos Garcia Gomez      <carlos@facturascripts.com>
 * @author Daniel Fernández Giménez <hola@danielfg.es>
 */
class Etiquetas extends Controller
{
    public function getPageData(): array
    {
        $pageData = parent::getPageData();
        $pageData['menu'] = 'warehouse';
        $pageData['title'] = 'tags';
        $pageData['icon'] = 'fas fa-barcode';
        return $pageData;
    }

    public function privateCore(&$response, $user, $permissions)
    {
        parent::privateCore($response, $user, $permissions);

        $action = $this->request->get('action', '');
        if ($action === 'autocomplete-product') {
            return $this->autocompleteProductAction();
        }

        $this->setTemplate('CustomEtiquetas');
    }

    protected function autocompleteProductAction(): bool
    {
        $this->setTemplate(false);

        $list = [];
        $variante = new Variante();
        $query = (string)$this->request->get('term');
        $where = [
            new DataBaseWhere('p.bloqueado', 0),
            new DataBaseWhere('p.sevende', 1)
        ];
        foreach ($variante->codeModelSearch($query, 'referencia', $where) as $value) {
            $list[] = [
                'key' => $this->toolBox()->utils()->fixHtml($value->code),
                'value' => $this->toolBox()->utils()->fixHtml($value->description)
            ];
        }

        if (empty($list)) {
            $list[] = ['key' => null, 'value' => $this->toolBox()->i18n()->trans('no-data')];
        }

        $this->response->setContent(json_encode($list));
        return false;
    }
}