<?php
/**
 * EditCita
 */
namespace FacturaScripts\Plugins\Dental\Controller;

use FacturaScripts\Core\Lib\ExtendedController\EditController;
use FacturaScripts\Core\Model\CodeModel;
use FacturaScripts\Core\Tools;
use FacturaScripts\Plugins\Dental\Lib\SalonBookingClient;
use FacturaScripts\Plugins\Dental\Model\Cita;

class EditCita extends EditController
{
    public function getPageData(): array
    {
        $data = parent::getPageData();
        $data['showonmenu'] = false;
        $data['title'] = 'appointment';
        $data['icon'] = 'fas fa-calendar';
        return $data;
    }

    public function getModelClassName(): string
    {
        return 'Cita';
    }

    protected function createViews()
    {
        parent::createViews();
        $this->loadSelectValues('EditCita');
    }

    protected function loadSelectValues(string $viewName)
    {
        $columnPatient = $this->tab($viewName)->columnForName('patient');
        if ($columnPatient && $columnPatient->widget) {
            $sql = "SELECT p.id AS code, c.razonsocial AS description "
                . "FROM dental_pacientes p INNER JOIN clientes c ON c.codcliente = p.codcliente "
                . "ORDER BY 2 ASC";
            $results = [];
            foreach ($this->dataBase->select($sql) as $row) {
                $results[] = new CodeModel($row);
            }
            $columnPatient->widget->setValuesFromCodeModel($results);
        }
    }

    protected function editAction()
    {
        $saved = parent::editAction();
        if ($saved) {
            $this->syncSalonBooking($this->views[$this->active]->model ?? null);
        }

        return $saved;
    }

    protected function insertAction()
    {
        if (false === $this->permissions->allowUpdate) {
            Tools::log()->warning('not-allowed-modify');
            return false;
        } elseif (false === $this->validateFormToken()) {
            return false;
        }

        $view = $this->views[$this->active];
        $view->processFormData($this->request, 'edit');
        if ($view->model->exists()) {
            Tools::log()->error('duplicate-record');
            return false;
        }

        if (false === $view->model->save()) {
            Tools::log()->error('record-save-error');
            return false;
        }

        $this->syncSalonBooking($view->model);

        if ($this->active === $this->getMainViewName()) {
            $this->redirect($view->model->url() . '&action=save-ok');
        }

        $view->newCode = $view->model->primaryColumnValue();
        Tools::log()->notice('record-updated-correctly');
        return true;
    }

    private function syncSalonBooking($model): void
    {
        if (!$model instanceof Cita) {
            Tools::log('dental-salon')->warning('Salon sync omitida: el modelo activo no es una cita.');
            return;
        }

        $result = (new SalonBookingClient())->syncCita($model);
        if (!empty($result['success'])) {
            return;
        }

        if (($result['status'] ?? '') !== 'skipped') {
            Tools::log()->warning('No se pudo sincronizar con Salon Booking: ' . ($result['message'] ?? 'sin detalle'));
        }
    }
}
