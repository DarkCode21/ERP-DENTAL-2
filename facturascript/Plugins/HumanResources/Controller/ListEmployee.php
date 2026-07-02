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
use FacturaScripts\Core\Lib\ExtendedController\BaseView;
use FacturaScripts\Core\Lib\ExtendedController\ListController;
use FacturaScripts\Core\Base\DataBase\DataBaseWhere;
use FacturaScripts\Core\Model\CodeModel;
use FacturaScripts\Dinamic\Model\EmployeeHoliday;
use FacturaScripts\Core\Tools;
use FacturaScripts\Dinamic\Model\Empresa;
use FacturaScripts\Dinamic\Model\Employee;
use FacturaScripts\Dinamic\Model\Department;
use FacturaScripts\Plugins\HumanResources\Lib\DateTimeTools;

/**
 * Controller to list the items in the Employee model
 *
 * @author Jose Antonio Cuello Principal <yopli2000@gmail.com>
 */
class ListEmployee extends ListController
{

    private const VIEW_EXPIRE_DOCUMENTS = 'ListEmployeeDocument';

    /**
     *
     * @var CodeModel[]
     */
    private array $companyList = [];

    /**
     * Returns basic page attributes
     *
     * @return array
     */
    public function getPageData(): array
    {
        $pagedata = parent::getPageData();
        $pagedata['title'] = 'employees';
        $pagedata['icon'] = 'fa-solid fa-id-card';
        $pagedata['menu'] = 'rrhh';

        return $pagedata;
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
            case 'authorized-holidays':
                $this->execActionAuthorized();
                return true;

            case 'discharge':
                return $this->execActionDischargeEmployee();

            default:
                return parent::execPreviousAction($action);
        }
    }

    /**
     * Load views
     *
     * @throws Exception
     */
    protected function createViews()
    {
        $this->companyList = $this->codeModel->all(Empresa::tableName(), Empresa::primaryColumn(), 'nombrecorto');
        $this->createViewEmployee();
        $this->createViewPayRoll();
        $this->createViewEmployeeVoucher();
        $this->createViewEmployeeHoliday();
        $this->createViewEmployeeExpireDocs();
    }

    /**
     * @param string $viewName
     * @param BaseView $view
     */
    protected function loadData($viewName, $view)
    {
        if ($viewName === self::VIEW_EXPIRE_DOCUMENTS) {
            $where = [
                new DataBaseWhere('expires', null, 'IS NOT'),
                new DataBaseWhere('expires', DateTimeTools::dateFromCurrent('+7 day'), '<='),
            ];
            $view->loadData('', $where);
            if ($view->count === 0) {
                unset($this->views[$viewName]);
                return;
            }
            Tools::log()->info('there-are-expired-docs');
            return;
        }
        parent::loadData($viewName, $view);
        if ($viewName === 'ListEmployee') {
            $view->model->discharge_date = date(Employee::DATE_STYLE);
        }
    }

    /**
     * Authorizes the selected employee holidays.
     *
     * @return void
     */
    private function execActionAuthorized(): void
    {
        if (false === $this->validateFormToken()) {
            return;
        }
        $ids = $this->request->request->get('code', []);

        $count = 0;
        foreach($ids as $idemployeeholiday){
            $employeeholiday = new EmployeeHoliday();
            if (false === $employeeholiday->loadFromCode($idemployeeholiday) || $employeeholiday->authorized) {
                continue;
            }
            $employeeholiday->authorized = true;
            if ($employeeholiday->save()) {
                $count++;
            }
        }

        if ($count > 0) {
            Tools::log()->info('authorized-records', ['%count%'=>$count]);
        }
    }

    /**
     *
     * @param string $viewName
     * @return void
     * @throws Exception
     */
    private function createViewEmployee(string $viewName = 'ListEmployee'): void
    {
        /// View
        $this->addView($viewName, 'Employee', 'employees', 'fa-solid fa-id-card');
        $this->addSearchFields($viewName, ['nombre', 'cifnif', 'insuranceid', 'credentialid']);

        /// Order By
        $this->addOrderBy($viewName, ['nombre'], 'name', 1);
        $this->addOrderBy($viewName, ['credentialid'], 'credential');
        $this->addOrderBy($viewName, ['idcompany', 'nombre'], 'company');
        $this->addOrderBy($viewName, ['fechaalta'], 'discharge-date');

        /// Filters
        $this->addFilterSelect($viewName, 'idcompany', 'company', 'idcompany', $this->companyList);

        $department = $this->codeModel->all(Department::tableName(), Department::primaryColumn(), 'name');
        $this->addFilterSelect($viewName, 'iddepartment', 'department', 'iddepartment', $department);

        $values = [
            ['label' => Tools::lang()->trans('only-active'), 'where' => [new DataBaseWhere('dischargedate', null, 'IS'), new DataBaseWhere('dischargedate', date('Y-m-d'), '>=', 'OR')]],
            ['label' => Tools::lang()->trans('only-suspended'), 'where' => [new DataBaseWhere('dischargedate', null, '!=')]],
            ['label' => Tools::lang()->trans('all'), 'where' => []]
        ];
        $this->addFilterSelectWhere($viewName, 'status', $values);

        $this->addButton($viewName, [
            'type' => 'modal',
            'action' => 'discharge',
            'label' => 'discharge',
            'color' => 'warning',
            'icon' => 'fas fa-sign-out-alt',
        ]);   
    }

    private function createViewEmployeeExpireDocs(string $viewName = self::VIEW_EXPIRE_DOCUMENTS): void
    {
        $this->addView($viewName, 'Join\EmployeeDocument', 'expired', 'far fa-calendar-times');
    }

    /**
     * @param string $viewName
     * @return void
     * @throws Exception
     */
    private function createViewEmployeeHoliday(string $viewName = 'ListEmployeeHoliday'): void
    {
        /// View
        $this->addView($viewName, 'Join\EmployeeHoliday', 'holidays', 'fa-solid fa-sun');
        $this->addSearchFields($viewName, ['startdate', 'note']);

        /// Order By
        $this->addOrderBy($viewName, ['holidays.authorized', 'holidays.startdate','startdate'], 'state', 1);
        $this->addOrderBy($viewName, ['holidays.startdate', 'employees.nombre'], 'date');
        $this->addOrderBy($viewName, ['employees.nombre', 'holidays.startdate'], 'name');
        $this->addOrderBy($viewName, ['holidays.idemployee'], 'employee');

        /// Filters
        $this->addFilterAutocomplete($viewName, 'employee', 'employee', 'idemployee', 'Employee', 'id', 'nombre');
        $this->addFilterPeriod($viewName, 'date', 'date', 'startdate');

        $values = [
            ['label' => Tools::lang()->trans('all'), 'where' => []],
            ['label' => Tools::lang()->trans('only-pending'), 'where' => [new DataBaseWhere('authorized', false)]],
        ];
        $this->addFilterSelectWhere($viewName, 'paid', $values);

        $this->addButton($viewName, [
            'action' => 'authorized-holidays',
            'icon' => 'fa-solid fa-check-circle',
            'label' => 'authorized',
            'type' => 'action',
            'color' => 'success',
        ]); 
    }

    private function createViewEmployeeVoucher(string $viewName = 'ListEmployeeVoucher'): void
    {
        /// View
        $this->addView($viewName, 'EmployeeVoucher', 'vouchers', 'fa-solid fa-hand-holding-usd');
        $this->addSearchFields($viewName, ['name', 'CAST(amount AS CHAR(50))']);

        /// Order By
        $this->addOrderBy($viewName, ['startdate'], 'date');
        $this->addOrderBy($viewName, ['name'], 'description');
        $this->addOrderBy($viewName, ['idemployee'], 'employee');

        /// Filters
        $this->addFilterAutocomplete($viewName, 'employee', 'employee', 'idemployee', 'Employee', 'id', 'nombre');
        $this->addFilterPeriod($viewName, 'date', 'date', 'startdate');

        $values = [
            ['label' => Tools::lang()->trans('only-pending'), 'where' => [new DataBaseWhere('paid', false)]],
            ['label' => Tools::lang()->trans('only-liquidated'), 'where' => [new DataBaseWhere('paid', true)]],
            ['label' => Tools::lang()->trans('all'), 'where' => []]
        ];
        $this->addFilterSelectWhere($viewName, 'paid', $values);

    }

    private function createViewPayRoll(string $viewName = 'ListPayRoll'): void
    {
        /// View
        $this->addView($viewName, 'PayRoll', 'payrolls', 'fa-solid fa-file-invoice-dollar');
        $this->addSearchFields($viewName, ['name', 'creationdate', 'startdate', 'enddate']);

        /// Order By
        $this->addOrderBy($viewName, ['creationdate', 'idcompany'], 'date', 2);
        $this->addOrderBy($viewName, ['idcompany', 'creationdate'], 'company');
        $this->addOrderBy($viewName, ['name', 'idcompany'], 'name');
        $this->addOrderBy($viewName, ['id'], 'code');

        /// Filters
        $this->addFilterSelect($viewName, 'idcompany', 'company', 'idcompany', $this->companyList);
        $this->addFilterPeriod($viewName, 'creationdate', 'date', 'creationdate');
    }

          /**
     * Import new attendances from biometric device.
     *
     * @return bool
     */
    private function execActionDischargeEmployee(): bool
    {

        $data = $this->request->request->all(); 
        if(empty($data['code'])
         || empty($data['discharge_date'])
         || empty($data['discharge_description'])
         || false === $this->validateFormToken()
        ){
            return true;
        }
        $employee = new Employee();
        $ids = explode(',', $data['code']);
        foreach ($ids as $idemployee) {
            
            if(false === $employee->loadFromCode($idemployee)){                                          
               continue;                
            }

            $employee->dischargeEmployee(
                $data['discharge_date'],
                $data['discharge_description']
            );
        }
        return true;

    }  
}