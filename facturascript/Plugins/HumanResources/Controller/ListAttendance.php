<?php
/**
 * This file is part of HumanResources plugin for FacturaScripts.
 * FacturaScripts Copyright (C) 2015-2025 Carlos Garcia Gomez <carlos@facturascripts.com>
 * HumanResources Copyright (C) 2018-2025 Jose Antonio Cuello Principal <yopli2000@gmail.com>
 *
 * This program and its files are under the terms of the license specified in the LICENSE file.
 */
namespace FacturaScripts\Plugins\HumanResources\Controller;

use Exception;
use FacturaScripts\Core\Base\DataBase\DataBaseWhere;
use FacturaScripts\Core\Lib\ExtendedController\ListController;
use FacturaScripts\Core\Tools;
use FacturaScripts\Dinamic\Model\Attendance;
use FacturaScripts\Dinamic\Model\BiometricDevice;
use FacturaScripts\Plugins\HumanResources\Lib\HumanResources\BiometricAnviz;
use FacturaScripts\Plugins\HumanResources\Model\AttendanceAudit;
use ParseCsv\Csv;

/**
 * Controller to list the items in the Attendance model
 *
 * @author Jose Antonio Cuello Principal <yopli2000@gmail.com>
 */
class ListAttendance extends ListController
{
    private const VIEW_ATTENDANCES = 'ListAttendance';
    private const VIEW_DEVICES = 'ListBiometricDevice';
    private const VIEW_OVERTIMES = 'ListOvertimeClosing';
    private const VIEW_AUDIT = 'ListAttendanceAudit';

    /**
     * Returns basic page attributes
     *
     * @return array
     */
    public function getPageData(): array
    {
        $pagedata = parent::getPageData();
        $pagedata['title'] = 'attendances';
        $pagedata['icon'] = 'fa-solid fa-clock';
        $pagedata['menu'] = 'rrhh';

        return $pagedata;
    }

    /**
     * Load views
     *
     * @throws Exception
     */
    protected function createViews()
    {
        $this->createViewAttendances();
        $this->createViewOvertimes();
        if (Tools::settings('rrhh', 'biodevice', 0) == 1) {
            $this->createViewDevices();
        }
        $this->createViewAudit();
    }

    /**
     * Runs the actions that alter the data before reading it.
     *
     * @param string $action
     * @return bool
     */
    protected function execPreviousAction($action)
    {
        switch ($action) {
            case 'device':
                return $this->execActionDevice();

            case 'import':
                return $this->execActionImport();

            case 'justified':
                $this->execActionJustified();
                return true;

            case 'authorized-attendance':
                $this->execActionAuthorized();
                return true;

            default:
                return parent::execPreviousAction($action);
        }
    }

    /**
     * Add attendances view.
     *
     * @param string $viewName
     * @throws Exception
     */
    private function createViewAttendances(string $viewName = self::VIEW_ATTENDANCES): void
    {
        /// Views
        $view = $this->addView($viewName, 'Join\AttendanceUser', 'attendances', 'fa-solid fa-clock')
            ->setSettings('btnDelete', false)
            /// Search Fields
            ->addSearchFields([
                'attendances.idemployee', 'attendances.credentialid',
                'attendances.checkdate', 'attendances.checktime',
                'attendances.note'
            ])
            /// Order By
            ->addOrderBy(['attendances.checkdate', 'attendances.checktime'], 'date', 2)
            ->addOrderBy(['attendances.idemployee'], 'employee')
            ->addOrderBy(['attendances.credentialid'], 'credential');

        /// Filters
        $filterCheckDate = $this->request->get('filtercheckdate');
        if (false === isset($filterCheckDate)) {
            $this->request->request->set('filtercheckdate', Tools::settings('rrhh', 'filtercheckdate', ''));
        }
        $view->addFilterPeriod('checkdate', 'date', 'checkdate');
        $view->addFilterAutocomplete('employee', 'employee', 'idemployee', 'Employee', 'id', 'nombre');

        $absenceConceptValues = $this->codeModel->all('rrhh_absencesconcepts', 'id', 'name');
        $view->addFilterSelect('idabsenceconcept', 'absence-concept', 'idabsenceconcept', $absenceConceptValues);
        $view->addFilterAutocomplete('idabsenceconcept', 'absence-concept', 'idabsenceconcept', 'rrhh_absencesconcepts', 'id', 'name');

        $view->addFilterSelect('origin', 'origin', 'origin', [
            ['code' => Attendance::ORIGIN_MANUAL, 'description' => Tools::lang()->trans('manual')],
            ['code' => Attendance::ORIGIN_JUSTIFIED, 'description' => Tools::lang()->trans('justified')],
            ['code' => Attendance::ORIGIN_EXTERNAL, 'description' => Tools::lang()->trans('external')],
        ]);

        $view->addFilterSelect('kind', 'type', 'kind', [
            ['code' => Attendance::KIND_INPUT, 'description' => Tools::lang()->trans('input')],
            ['code' => Attendance::KIND_OUTPUT, 'description' => Tools::lang()->trans('output')],
        ]);

        $view->addFilterSelectWhere('paid', [
            ['label' => Tools::lang()->trans('all'), 'where' => []],
            ['label' => Tools::lang()->trans('only-pending'), 'where' => [new DataBaseWhere('authorized', false)]],
        ]);

        $this->addButton($viewName, [
            'type' => 'modal',
            'action' => 'import',
            'label' => 'import',
            'color' => 'warning',
            'icon' => 'fa-solid fa-file-import',
        ]);

         $this->addButton($viewName, [
            'action' => 'authorized-attendance',
            'icon' => 'fa-solid fa-check-circle',
            'label' => 'authorized',
            'type' => 'action',
            'color' => 'success',
        ]); 

        // modal employee list
        $this->setModalEmployeeSelect();
    }

    private function createViewAudit(): void
    {
        $this->addView(self::VIEW_AUDIT, 'AttendanceAudit', 'audit', 'fa-solid fa-file-alt')
            ->setSettings('btnNew', false)
            ->setSettings('btnDelete', false)
            ->addOrderBy(['dateaction', 'id'], 'date', 2)
            ->addOrderBy(['id'], 'code')
            ->addFilterPeriod('dateaction', 'date', 'dateaction', true)
            ->addFilterAutocomplete('nick', 'user', 'nick', 'users', 'nick')
            ->addFilterSelectWhere('action', [
                ['label' => Tools::lang()->trans('all'), 'where' => []],
                ['label' => Tools::lang()->trans('only-audit-update'), 'where' => [new DataBaseWhere('action', AttendanceAudit::AUDIT_ACTION_UPDATE)]],
                ['label' => Tools::lang()->trans('only-audit-delete'), 'where' => [new DataBaseWhere('action', AttendanceAudit::AUDIT_ACTION_DELETE)]],
            ]);
    }

    /**
     * Add biometric devices view.
     *
     * @throws Exception
     */
    private function createViewDevices(): void
    {
        $this->addView(self::VIEW_DEVICES, 'BiometricDevice', 'devices', 'fa-solid fa-fingerprint')
            ->addSearchFields(['name', 'note'])
            ->addOrderBy(['name'], 'name');

        $this->addButton(self::VIEW_DEVICES, [
            'type' => 'modal',
            'action' => 'device',
            'label' => 'import',
            'color' => 'warning',
            'icon' => 'fa-solid fa-fingerprint',
        ]);
    }

    /**
     * @return void
     */
    private function createViewOvertimes(): void
    {
        $this->addView(self::VIEW_OVERTIMES, 'OvertimeClosing', 'closures', 'fa-solid fa-user-lock')
            ->addOrderBy(['startdate'], 'starting-date', 2)
            ->addFilterPeriod('startdate', 'starting-date', 'startdate')
            ->addFilterAutocomplete('employee', 'employee', 'idemployee', 'Employee', 'id', 'nombre');
    }

    /**
     * Check if a Csv file is empty or field list is empty
     *
     * @param Csv $csv
     * @return bool
     */
    private function errorCSVFile(&$csv): bool
    {
        $error = false;
        if (empty($csv->data)) {
            Tools::log()->error('import-empty-error');
            $error = true;
        }

        if (empty($csv->titles)) {
            Tools::log()->error('import-fields-error');
            $error = true;
        }

        return $error;
    }

    /**
     * Import new attendances from biometric device.
     *
     * @return bool
     */
    private function execActionDevice(): bool
    {
        $deviceID = $this->request->get('device_id', 0);
        $device = new BiometricDevice();
        if (false === $device->loadFromCode($deviceID)) {
            return true;
        }
        if ($device->type == BiometricDevice::DEVICE_TYPE_ANVIZ) {
            $biometric = new BiometricAnviz();
            $count = $biometric->import($device);
            Tools::log()->notice('attendance-import-ok', ['%count%' => $count]);
        }
        return true;
    }

    /**
     * Import csv file with attendances list.
     *
     * @return bool
     */
    private function execActionImport(): bool
    {
        $csv = $this->getFile();
        if (empty($csv)) {
            return true;
        }

        $attendance = new Attendance();
        $attendance->importFromCSV($csv);
        return true;
    }

    /**
     * Create a justified attendance.
     *
     * @return void
     */
    private function execActionJustified(): void
    {
        $data = $this->request->request->all();
        $attendance = new Attendance();
        $attendance->justifiedFromData($data);
    }

    /**
     * Authorize attendance records.
     * Use direct SQL query to update the authorized field.
     * Simplifies the process by avoiding audit, because don't change the attendance data.
     *
     * @return void
     */
    private function execActionAuthorized(): void
    {
        if (false === $this->validateFormToken()) {
            return;
        }

        $count = 0;
        $ids = $this->request->request->get('code', []);
        foreach ($ids as $idattendance) {
            $sql = 'UPDATE ' . Attendance::tableName() . ' SET authorized = true WHERE id = ' . $idattendance;
            if ($this->dataBase->exec($sql)) {
                $count++;
            }
        }

        if ($count > 0) {
            Tools::log()->info('authorized-records', ['%count%'=>$count]);
        }
    }

    /**
     *
     * @return Csv|null
     */
    private function getFile()
    {
        $uploadFile = $this->request->files->get('filedata');
        if (empty($uploadFile)) {
            return null;
        }

        $csv = new Csv();
        $csv->auto($uploadFile->getPathname());
        if ($this->errorCSVFile($csv)) {
            return null;
        }
        return $csv;
    }

    /**
     * Load active employee list to widget select into modal view
     */
    private function setModalEmployeeSelect(): void
    {
        $columnEmployee = $this->views[self::VIEW_ATTENDANCES]->columnModalForName('employee');
        if (isset($columnEmployee) && $columnEmployee->widget->getType() === 'select') {
            $where = [
                new DataBaseWhere('dischargedate', null, 'IS'),
                new DataBaseWhere('dischargedate', date('Y-m-d'), '>=', 'OR'),
            ];
            $rows = $this->codeModel->all('rrhh_employees', 'id', 'nombre', false, $where);
            $columnEmployee->widget->setValuesFromCodeModel($rows);
        }
    }
}
