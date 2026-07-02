<?php
/**
 * This file is part of the Produccion plugin for FacturaScripts.
 * FacturaScripts  Copyright (C) 2015-2026 Carlos Garcia Gomez <carlos@facturascripts.com>
 * Produccion      Copyright (C) 2020-2026 Jose Antonio Cuello Principal <yopli2000@gmail.com>
 *
 * This program and its files are under the terms of the license specified in the LICENSE file.
 * All Rights Reserved.
 */
namespace FacturaScripts\Plugins\Produccion\Lib\Produccion;

use FacturaScripts\Core\Html;
use FacturaScripts\Core\Tools;
use FacturaScripts\Plugins\Produccion\Controller\EditOrdenProduccion;
use FacturaScripts\Plugins\Produccion\Model\OrdenNumSerie;
use FacturaScripts\Plugins\Produccion\Model\OrdenProduccion;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;

/**
 * Description of EditOrdenProduccionNumSerieAction
 *
 * @author Jose Antonio Cuello  <yopli2000@gmail.com>
 */
class EditOrdenProduccionNumSerieAction
{
    private EditOrdenProduccion $controller;

    /**
     * Class constructor.
     * Sets the document to manage.
     *
     * @param EditOrdenProduccion $controller
     */
    public function __construct(EditOrdenProduccion $controller)
    {
        $this->controller = $controller;
    }

    /**
     * Exec indicated action.
     *
     * @param string $action
     * @return array
     * @throws LoaderError
     * @throws RuntimeError
     * @throws SyntaxError
     */
    public function exec(string $action): array
    {
        $data = $this->controller->request->request->all();
        return match ($action) {
            'consume-numserie' => $this->consumeNumserieAction($data),
            'release-numserie' => $this->releaseNumserieAction($data),

            'save-numserie',
            'verify-numserie' => $this->saveNumserieAction($data),

            default => ['ok' => false],
        };
    }

    /**
     * Assign a numserie to ingredient.
     *
     * @param array $data
     * @return array
     */
    private function consumeNumserieAction(array $data): array
    {
        $result = [
            'ok' => false,
            'error' => Tools::lang()->trans('record-not-found'),
        ];

        $production = new OrdenProduccion();
        $ordenNumSerie = new OrdenNumSerie();
        if (false === $this->controller->checkParams($production, false)
            || false === $ordenNumSerie->load($data['idnumserie'])
        ) {
            return $result;
        }

        if (false === empty($ordenNumSerie->iddelivery)) {
            $result['error'] = Tools::lang()->trans('numserie-used-and-delivery');
            return $result;
        }

        if (false === empty($ordenNumSerie->idusedinorder)) {
            $result['error'] = Tools::lang()->trans('numserie-used');
            return $result;
        }

        $ordenNumSerie->setUpdatingNumSerie();
        $ordenNumSerie->idusedinorder = $production->id();
        if (false === $ordenNumSerie->save()) {
            $result['error'] = Tools::lang()->trans('record-save-error');
            return $result;
        }

        $result['ok'] = true;
        return $result;
    }

    /**
     * Release a numserie from ingredient.
     *
     * @param array $data
     * @return array
     */
    private function releaseNumserieAction(array $data): array
    {
        $result = [
            'ok' => false,
            'error' => Tools::lang()->trans('record-not-found'),
        ];

        $production = new OrdenProduccion();
        $ordenNumSerie = new OrdenNumSerie();
        if (false === $this->controller->checkParams($production, false)
            || false === $ordenNumSerie->load($data['idnumserie'])
        ) {
            return $result;
        }

        if (false === empty($ordenNumSerie->iddelivery)) {
            $result['error'] = Tools::lang()->trans('numserie-used-and-delivery');
            return $result;
        }

        if (empty($ordenNumSerie->idusedinorder)) {
            $result['error'] = Tools::lang()->trans('numserie-not-used');
            return $result;
        }

        $ordenNumSerie->setUpdatingNumSerie();
        $ordenNumSerie->idusedinorder = null;
        if (false === $ordenNumSerie->save()) {
            $result['error'] = Tools::lang()->trans('record-save-error');
            return $result;
        }

        $result['ok'] = true;
        return $result;
    }

    /**
     * @param array $data
     * @return array
     * @throws LoaderError
     * @throws RuntimeError
     * @throws SyntaxError
     */
    private function saveNumserieAction(array $data): array
    {
        $result = [
            'ok' => false,
            'error' => Tools::lang()->trans('record-not-found'),
            'post' => $data,
        ];

        $production = new OrdenProduccion();
        $ordenNumSerie = new OrdenNumSerie();
        if (false === $this->controller->checkParams($production)
            || false === $ordenNumSerie->load($data['id'])
        ) {
            return $result;
        }

        if (false === empty($data['numserie'])) {
            $ordenNumSerie->setUpdatingNumSerie();
            $ordenNumSerie->numserie = $data['numserie'];
        }

        $ordenNumSerie->verified = true;
        if (false === $ordenNumSerie->save()) {
            $result['error'] = Tools::lang()->trans('error-save');
            return $result;
        }

        $result['ok'] = true;
        $result['html'] = Html::render('Block/ProductionProducedNumSerieRow.html.twig', [
            'fsc' => $this->controller,
            'item' => $ordenNumSerie,
        ]);
        return $result;
    }
}
