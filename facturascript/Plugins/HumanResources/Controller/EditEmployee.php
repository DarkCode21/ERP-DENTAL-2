<?php
/**
 * This file is part of HumanResources plugin for FacturaScripts.
 * FacturaScripts Copyright (C) 2015-2025 Carlos Garcia Gomez <carlos@facturascripts.com>
 * HumanResources Copyright (C) 2018-2025 Jose Antonio Cuello Principal <yopli2000@gmail.com>
 *
 * This program and its files are under the terms of the license specified in the LICENSE file.
 */
namespace FacturaScripts\Plugins\HumanResources\Controller;

use FacturaScripts\Core\Base\DataBase\DataBaseWhere;
use FacturaScripts\Core\Lib\ExtendedController\BaseView;
use FacturaScripts\Core\Lib\ExtendedController\EditController;
use FacturaScripts\Core\Tools;
use FacturaScripts\Dinamic\Model\EmployeeSalary;
use FacturaScripts\Dinamic\Model\TotalModel;
use FacturaScripts\Plugins\HumanResources\Lib\HumanResources\EmployeeControllerTrait;
use FacturaScripts\Plugins\HumanResources\Lib\HumanResources\EmployeeFilesTrait;
use FacturaScripts\Plugins\HumanResources\Model\Attendance;
use FacturaScripts\Plugins\HumanResources\Model\EmployeeHoliday;
use FacturaScripts\Dinamic\Model\Employee;


/**
 * Controler to edit Employee.
 *
 * @author Jose Antonio Cuello Principal <yopli2000@gmail.com>
 */
class EditEmployee extends EditController
{

    private const VIEW_HOLIDAY = 'ListEmployeeHoliday2';
    private const VIEW_ATTENDANCE = 'ListAttendance';
    private const VIEW_ADDRESS = 'EditDireccionContacto';
    private const VIEW_CONTRACT = 'EditEmployeeContract';
    private const VIEW_COURSES = 'EditEmployeeCourse';
    private const VIEW_LEAVE = 'EditEmployeeLeave';
    private const VIEW_NOTE = 'EditEmployeeNote';
    private const VIEW_SALARY = 'EditEmployeeSalary';
    private const VIEW_SANCTION = 'EditEmployeeSanction';
    private const VIEW_VOURCHER = 'ListEmployeeVoucher';
    private const VIEW_WORKSHIFT = 'ListEmployeeWorkShift';
    private const VIEW_EMPLOYEE = 'EditEmployee';


    use EmployeeFilesTrait;
    use EmployeeControllerTrait;

    /**
     * Gets the employee's total vacation days by years.
     *
     * @return array
     */
    public function holidaySummary(): array
    {
        $idemployee = $this->getViewModelValue($this->getMainViewName(), 'id');
        $totals = TotalModel::all(
            EmployeeHoliday::tableName(),
            [ new DataBaseWhere('idemployee', $idemployee),
              new DataBaseWhere('startdate', $this->getDate(5), '>=')],
            ['total' => 'SUM(totaldays)'],
            'EXTRACT(YEAR FROM startdate)'
        );

        $result = [];
        foreach (array_reverse($totals, true) as $year) {
            $result[$year->code] = $year->totals['total'];
        }
        return $result;
    }

    /**
     * Returns the model name
     */
    public function getModelClassName(): string
    {
        return 'Employee';
    }

    /**
     * Returns basic page attributes
     *
     * @return array
     */
    public function getPageData(): array
    {
        $pagedata = parent::getPageData();
        $pagedata['menu'] = 'rrhh';
        $pagedata['title'] = 'employee';
        $pagedata['icon'] = 'fa-solid fa-id-card';
        $pagedata['showonmenu'] = false;

        return $pagedata;
    }

    /**
     * Create views to display.
     */
    protected function createViews()
    {
        parent::createViews();
        $this->addEditView(self::VIEW_NOTE, 'Employee', 'notes', 'fa-solid fa-sticky-note');
        $this->setSettings(self::VIEW_NOTE, 'btnDelete', false);

        $this->createViewEdit(self::VIEW_ADDRESS, 'Contacto', 'addresses', 'fa-solid fa-address-book');
        $this->createViewEdit(self::VIEW_CONTRACT, 'EmployeeContract', 'contracts', 'fa-solid fa-handshake');
        $this->createViewEmployeeWorkShift();
        $this->createViewEdit(self::VIEW_SALARY, 'EmployeeSalary', 'employee-salary', 'fa-solid fa-money-bill-alt');
        $this->createViewEdit(self::VIEW_LEAVE, 'EmployeeLeave', 'employee-leaves', 'fa-solid fa-wheelchair');
        $this->createViewEmployeeHoliday();
        $this->createViewCourses();
        $this->createViewEmployeeVoucher();
        $this->createViewEdit(self::VIEW_SANCTION, 'EmployeeSanction', 'sanctions', 'fa-solid fa-gavel');
        $this->createViewAttendance();
        $this->createViewEmployeeFiles();
    }

    /**
     * Run the actions that alter data before reading it.
     *
     * @param string $action
     * @return bool
     */
    protected function execPreviousAction($action)
    {
        switch ($action) {
            case 'add-file':
            case 'delete-file':
            case 'edit-file':
            case 'unlink-file':
                return $this->execFileAction($action);
            
            case 'discharge':
                return $this->execActionDischargeEmployee();

            case 'clone-salary':
                $fromemployee = $this->request->get('fromemployee');
                $toemployee = $this->request->get('code');
                $employeeSalary = new EmployeeSalary();
                $employeeSalary->cloneSalaryToEmployee($fromemployee, $toemployee);
                return true;

            case 'justified':
                $data = $this->request->request->all();
                $attendance = new Attendance();
                $attendance->justifiedFromData($data);
                return true;
        }
        return parent::execPreviousAction($action);
    }

    /**
     * Import a CSV File with Attendance structure
     */
    protected function execActionImport()
    {
        $csv = $this->getFile();
        if (empty($csv)) {
            return;
        }

        $attendance = new Attendance();
        $attendance->importFromCSV($csv);
    }

    /**
     * Load view data procedure
     *
     * @param string $viewName
     * @param BaseView $view
     */
    protected function loadData($viewName, $view)
    {
        $mainViewName = $this->getMainViewName();
        if ($viewName === $mainViewName) {
            parent::loadData($viewName, $view);
            $this->loadAddressContact();
            if(empty($view->model->dischargedate)){
                $this->addButton($mainViewName, [
                    'type' => 'modal',
                    'action' => 'discharge',
                    'label' => 'discharge',
                    'color' => 'warning',
                    'icon' => 'fas fa-sign-out-alt',
                ]); 
                $view->model->discharge_date = date(Employee::DATE_STYLE);            
            }
            return;
        }

        $idemployee = $this->getViewModelValue($mainViewName, 'id');
        $where = [new DataBaseWhere('idemployee', $idemployee)];
        switch ($viewName) {
            case 'EmployeeFiles':
                $this->loadDataEmployeeFiles($view, $idemployee);
                break;

            case self::VIEW_NOTE:
                $view->loadData($idemployee);
                $view->count = -1;
                break;

            case self::VIEW_ATTENDANCE:
                $where[] = new DataBaseWhere('checkdate', date('Y-m-d', strtotime('-1 month')), '>');
                $view->loadData(false, $where, []);
                $this->loadDataAttendanceAfter($idemployee);
                break;

            default:            
                $view->loadData(false, $where, $this->orderByForView($viewName));
                break;
        }


    }

    /**
     * Create Employee Courses view.
     *
     * @param string $viewName
     */
    private function createViewCourses(string $viewName = self::VIEW_COURSES)
    {
        $view = $this->addEditListView(self::VIEW_COURSES, 'EmployeeCourse', 'courses', 'fa-solid fa-graduation-cap');
        $view->setInLine(true);
        $view->disableColumn('employee');
    }

    /**
     * Create Employee Holidays view.
     *
     * @param string $viewName
     */
    private function createViewEmployeeHoliday($viewName = self::VIEW_HOLIDAY)
    {
        $this->createViewList($viewName, 'EmployeeHoliday', 'holidays', 'fa-solid fa-sun');
        $this->views[$viewName]->addFilterPeriod('startdate', 'start-date', 'startdate');
        $this->views[$viewName]->addFilterNumber('totaldays', 'totaldays');

        $this->views[$viewName]->addOrderBy(['startdate'], 'start-date' ,2);

        $values = [
            ['label' => Tools::lang()->trans('this-year'), 'where' => [new DataBaseWhere('startdate', $this->getDate(), '>=')]],
            ['label' => '2 ' . Tools::lang()->trans('years'), 'where' => [new DataBaseWhere('startdate', $this->getDate(1), '>=')]],
            ['label' => Tools::lang()->trans('only-pending'), 'where' => [new DataBaseWhere('enddate', null, 'IS'), new DataBaseWhere('enddate', date('Y-m-d'), '>=', 'OR')]],
            ['label' => Tools::lang()->trans('all'), 'where' => []]
        ];
        $this->views[$viewName]->addFilterSelectWhere('status', $values);
    }

    /**
     * Create Attendance view.
     *
     * @param string $viewName
     */
    private function createViewAttendance($viewName = self::VIEW_ATTENDANCE)
    {
        $this->createViewList($viewName, 'Attendance', 'attendances-last', 'fa-solid fa-clock', ['code', 'credential', 'employee']);
        $view = $this->views[$viewName];
        $view->addSearchFields(['checkdate', 'checktime', 'note']);
        $view->addOrderBy(['checkdate', 'checktime'], 'date', 2);

        // Filters
        $view->addFilterPeriod('checkdate', 'date', 'checkdate');

        $lang = Tools::lang();
        $view->addFilterSelect('origin', 'origin', 'origin', [
            ['code' => '1', 'description' => $lang->trans('manual')],
            ['code' => '2', 'description' => $lang->trans('justified')],
            ['code' => '3', 'description' => $lang->trans('external')],
        ]);

        $view->addFilterSelect('kind', 'type', 'kind', [
            ['code' => Attendance::KIND_INPUT, 'description' => $lang->trans('input')],
            ['code' => Attendance::KIND_OUTPUT, 'description' => $lang->trans('output')],
        ]);

        $absenceConceptValues = $this->codeModel->all('rrhh_absencesconcepts', 'id', 'name');
        $view->addFilterSelect('idabsenceconcept', 'absence-concept', 'idabsenceconcept', $absenceConceptValues);
    }

    /**
     * Create Employee Voucher view.
     *
     * @param string $viewName
     */
    private function createViewEmployeeVoucher($viewName = self::VIEW_VOURCHER)
    {
        $this->createViewList($viewName, 'EmployeeVoucher', 'vouchers', 'fa-solid fa-coins');
        $view = $this->views[$viewName];
        $view->addSearchFields(['name', 'startdate', 'checktime', 'note']);
        $view->addOrderBy(['startdate', 'pending'], 'date', 2);

        // Filters
        $view->addFilterPeriod('startdate', 'start-date', 'startdate');
        $view->addFilterNumber('pending');
    }

    /**
     * Create Employee WorkShift view.
     *
     * @param string $viewName
     */
    private function createViewEmployeeWorkShift($viewName = self::VIEW_WORKSHIFT)
    {
        $this->createViewList($viewName, 'EmployeeWorkShift', 'work-shifts-short', 'fa-solid fa-business-time');
        $view = $this->views[$viewName];
        $view->addOrderBy(['idemployee', 'startdate'], 'date', 2);
    }

    /**
     * Get date from start year.
     *
     * @param int $regression
     * @return string
     */
    private function getDate(int $regression = 0): string
    {
        $year = (int) date('Y') - $regression;
        $startDate = strtotime('01-01-' . $year);
        return date('Y-m-d', $startDate);
    }

    /**
     * Set Address list to column from contact table.
     */
    private function loadAddressContact()
    {
        $viewName = $this->getMainViewName();
        $idemployee = $this->getViewModelValue($viewName, 'id');
        if (empty($idemployee)) {
            return;
        }
        $where = [new DataBaseWhere('idemployee', $idemployee)];
        $contacts = $this->codeModel->all('contactos', 'idcontacto', 'descripcion', false, $where);
        $columnAddress = $this->views[$viewName]->columnForName('address');
        if ($columnAddress) {
            $columnAddress->widget->setValuesFromCodeModel($contacts);
        }
    }

    /**
     * Set value and readonly to employee modal column.
     *
     * @param int $idemployee
     */
    private function loadDataAttendanceAfter($idemployee)
    {
        $view = $this->views[self::VIEW_ATTENDANCE];
        $view->model->employee_id = $idemployee;

        $employeeCol = $view->columnModalForName('employee');
        if (isset($employeeCol)) {
            $employeeCol->widget->readonly = 'true';
        }
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
        $firstId = $ids[0];  
            
            if(false === $employee->loadFromCode($firstId)){                                          
               return true;               
            }

            $employee->dischargeEmployee(
                $data['discharge_date'],
                $data['discharge_description']
            );
            
        return true;

    }  
}
