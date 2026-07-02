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

use Exception;
use FacturaScripts\Core\Base\DataBase\DataBaseWhere;
use FacturaScripts\Core\Model\TotalModel;
use FacturaScripts\Core\Tools;
use FacturaScripts\Dinamic\Lib\ExtendedController\EditController;
use FacturaScripts\Plugins\Produccion\Model\Join\LineaProduccion;
use FacturaScripts\Plugins\Produccion\Model\OrdenNumSerie;
use FacturaScripts\Plugins\Produccion\Model\OrdenProduccion;

/**
 * Description of EditOrdenProduccionAction
 *
 * @author Jose Antonio Cuello  <yopli2000@gmail.com>
 */
class EditOrdenProduccionAction
{
    /** @var OrdenProduccion */
    private OrdenProduccion $document;

    /**
     * Class constructor.
     * Sets the document to manage.
     *
     * @param OrdenProduccion $document
     */
    public function __construct(OrdenProduccion $document)
    {
        $this->document = $document;
    }

    /**
     * Add buttons depending on the status of the document.
     *
     * @param EditController $controller
     * @param string $viewName
     * @throws Exception
     */
    public function addStatusButton(EditController $controller, string $viewName): void
    {
        if (empty($this->document->id)) {
            return;
        }

        $this->addCommonButtons($controller, $viewName);

        switch ($this->document->estado) {
            case OrdenProduccion::STATUS_PENDING:
                $controller->addButton($viewName, [
                    'action' => 'start',
                    'color' => 'info',
                    'confirm' => true,
                    'icon' => 'fa-solid fa-cogs',
                    'label' => 'produce-start',
                ]);
                break;

            case OrdenProduccion::STATUS_STARTED:
                $controller->addButton($viewName, [
                    'action' => 'confirm',
                    'color' => 'warning',
                    'confirm' => true,
                    'icon' => 'fa-solid fa-check-circle',
                    'label' => 'produce-confirm',
                ]);
                break;

            case OrdenProduccion::STATUS_VERIFYING:
                $controller->addButton($viewName, [
                    'action' => 'verified',
                    'color' => 'warning',
                    'confirm' => true,
                    'icon' => 'fa-solid fa-check-circle',
                    'label' => 'produce-verified',
                ]);
                break;
        }
    }

    /**
     * Exec indicated action.
     *
     * @param string $action
     * @param array $data
     */
    public function exec(string $action, array $data): void
    {
        switch ($action) {
            case 'back':
                $this->document->estado = OrdenProduccion::STATUS_PENDING;
                $this->document->save();
                break;

            case 'cancel':
                if (false === empty($data['cancelnotes'])) {
                    $this->document->observaciones = (empty($this->document->observaciones))
                        ? $data['cancelnotes']
                        : $this->document->observaciones . "\n" . $data['cancelnotes'];
                }
                $this->document->estado = OrdenProduccion::STATUS_CANCELLED;
                $this->document->save();
                break;

            case 'confirm':
                $this->produce();
                break;

            case 'start':
                if ($this->document->confirmar) {
                    $this->produce();
                    return;
                }
                $this->document->estado = OrdenProduccion::STATUS_STARTED;
                $this->document->save();
                break;

            case 'verified':
                $this->verified();
                break;
        }
    }

    /**
     *
     * @param EditController $controller
     * @param string $viewName
     * @throws Exception
     */
    private function addCommonButtons(EditController $controller, string $viewName): void
    {
        if (in_array($this->document->estado, [
            OrdenProduccion::STATUS_STARTED,
            OrdenProduccion::STATUS_CANCELLED]))
        {
            $controller->addButton($viewName, [
                'action' => 'back',
                'color' => 'info',
                'confirm' => true,
                'icon' => 'fa-solid fa-angle-double-left',
                'label' => 'pending',
            ]);
        }

        if (in_array($this->document->estado, [
            OrdenProduccion::STATUS_PENDING,
            OrdenProduccion::STATUS_STARTED]))
        {
            $controller->addButton($viewName, [
                'action' => 'cancel',
                'color' => 'danger',
                'icon' => 'fa-solid fa-ban',
                'label' => 'cancel',
                'type' => 'modal',
            ]);
        }
    }

    private function produce(): void
    {
        $manager = new OrderManager();
        if ($manager->produce($this->document->id)) {
            Tools::log()->notice('order-produce-ok', ['%order%' => $this->document->id]);
        }
    }

    private function verified(): void
    {
        if ($this->hasPendingRawNumSeries() || $this->hasProducedNumSeries()) {
            return;
        }

        $this->document->estado = OrdenProduccion::STATUS_FINISHED;
        if ($this->document->save()) {
            Tools::log()->notice('order-produce-complete');
        }
    }

    /**
     * Return if exists raw products without numseries.
     * @return bool
     */
    private function hasPendingRawNumSeries(): bool
    {
        $requiredByReference = [];
        $whereIngredients = [
            new DataBaseWhere('lineas.idorden', $this->document->id()),
            new DataBaseWhere('productos.numserie', true),
        ];

        // Calculate total for reference for duplicate references
        $lineaProduccion = new LineaProduccion();
        foreach ($lineaProduccion->all($whereIngredients) as $linea) {
            $reference = $linea->referencia;
            $requiredByReference[$reference] = ($requiredByReference[$reference] ?? 0) + (int) $linea->cantidad;
        }

        if (empty($requiredByReference)) {
            return false;
        }

        // Check for assigned
        $whereAssigned = [ new DataBaseWhere('idusedinorder', $this->document->id()) ];
        foreach (TotalModel::all('produccion_ordenesnumseries', $whereAssigned, ['total' => 'COUNT(id)'], 'reference') as $row) {
            $required = $requiredByReference[$row->code] ?? 0;
            $assigned = (int) ($row->totals['total'] ?? 0);
            if ($required > $assigned) {
                return true;
            }
            unset($requiredByReference[$row->code]);
        }

        return false === empty($requiredByReference);
    }

    /**
     * Return if exists produced num-series without assigned
     *
     * @return bool
     */
    private function hasProducedNumSeries(): bool
    {
        $numSerie = new OrdenNumSerie();
        $whereProduced = [
            new DataBaseWhere('idorden', $this->document->id()),
            new DataBaseWhere('verified', false),
        ];
        if ($numSerie->loadWhere($whereProduced)) {
            Tools::log()->error('pending-verification');
            return true;
        }

        return false;
    }
}
