<?php
/**
 * Copyright (C) 2019-2022 Carlos Garcia Gomez <carlos@facturascripts.com>
 */

namespace FacturaScripts\Plugins\CRM\Extension\Controller;

use Closure;
use FacturaScripts\Core\Base\DataBase\DataBaseWhere;
use FacturaScripts\Dinamic\Model\PresupuestoCliente;
use FacturaScripts\Plugins\CRM\Model\CrmNota;
use FacturaScripts\Plugins\CRM\Model\CrmOportunidad;

/**
 * Description of EditPresupuestoCliente
 *
 * @author Carlos Garcia Gomez <carlos@facturascripts.com>
 */
class EditPresupuestoCliente
{

    public function getCrmNotes(): Closure
    {
        return function () {
            // direct notes
            $crmNote = new CrmNota();
            $where = [
                new DataBaseWhere('documento', $this->getViewModelValue($this->getMainViewName(), 'codigo')),
                new DataBaseWhere('idoportunidad', null, 'IS'),
                new DataBaseWhere('tipodocumento', 'presupuesto de cliente')
            ];
            $order = ['fecha' => 'DESC', 'hora' => 'DESC'];
            $notes = $crmNote->all($where, $order, 0, 0);

            // opportunity notes
            foreach ($this->views['crm']->cursor as $oportunity) {
                foreach ($oportunity->getNotas() as $note) {
                    $notes[] = $note;
                }
            }

            return $notes;
        };
    }

    protected function createViews(): Closure
    {
        return function () {
            $viewName = 'crm';
            $this->addHTMLView($viewName, 'Tab/CrmOportunidad', 'CrmOportunidad', 'crm', 'far fa-sticky-note');
        };
    }

    protected function editCrmNoteAction(): Closure
    {
        return function () {
            $nota = new CrmNota();
            $id = $this->request->request->get('id');
            if (false === $nota->loadFromCode($id)) {
                return;
            }

            $nota->observaciones = $this->request->request->get('observaciones');
            $nota->fechaaviso = $this->request->request->get('fechaaviso');
            if (empty($nota->fechaaviso)) {
                $nota->fechaaviso = null;
            }

            if ($nota->save()) {
                $this->toolBox()->i18nLog()->notice('record-updated-correctly');
                return;
            }

            $this->toolBox()->i18nLog()->warning('record-updated-error');
        };
    }

    protected function execPreviousAction(): Closure
    {
        return function ($action) {
            switch ($action) {
                case 'edit-crm-note':
                    $this->editCrmNoteAction();
                    break;

                case 'new-crm-note':
                    $this->newCrmNoteAction();
                    break;
            }
        };
    }

    protected function loadData(): Closure
    {
        return function ($viewName, $view) {
            if ($viewName === 'crm') {
                $code = $this->getViewModelValue($this->getMainViewName(), 'idpresupuesto');
                $where = [new DataBaseWhere('idpresupuesto', $code)];
                $view->loadData('', $where);
            }
        };
    }

    protected function newCrmNoteAction(): Closure
    {
        return function () {
            $presupuesto = new PresupuestoCliente();
            if (false === $presupuesto->loadFromCode($this->request->query->get('code'))) {
                $this->toolBox()->i18nLog()->warning('record-not-found');
                return;
            }

            $oportunidad = new CrmOportunidad();
            $idoportunidad = $this->request->request->get('idoportunidad');
            if (empty($idoportunidad) || false === $oportunidad->loadFromCode($idoportunidad)) {
                $oportunidad->codagente = $presupuesto->codagente;
                $oportunidad->coddivisa = $presupuesto->coddivisa;
                $oportunidad->descripcion = $this->toolBox()->i18n()->trans('estimation') . ' ' . $presupuesto->codigo;
                $oportunidad->idcontacto = $presupuesto->idcontactofact;
                $oportunidad->idpresupuesto = $presupuesto->idpresupuesto;
                $oportunidad->nick = $this->user->nick;
                $oportunidad->tasaconv = $presupuesto->tasaconv;
                $oportunidad->save();
            }

            $nota = new CrmNota();
            $nota->idcontacto = $oportunidad->idcontacto;
            $nota->idoportunidad = $oportunidad->id;
            $nota->nick = $this->user->nick;
            $nota->observaciones = $this->request->request->get('observaciones');
            $nota->fechaaviso = $this->request->request->get('fechaaviso');
            if (empty($nota->fechaaviso)) {
                $nota->fechaaviso = null;
            }

            if ($nota->save()) {
                $this->toolBox()->i18nLog()->notice('record-updated-correctly');
                return;
            }

            $this->toolBox()->i18nLog()->warning('record-updated-error');
        };
    }
}
