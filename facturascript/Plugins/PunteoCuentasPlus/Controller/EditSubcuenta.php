<?php
/**
 * This file is part of PunteoCuentasPlus plugin for FacturaScripts.
 * FacturaScripts    Copyright (C) 2015-2024 Carlos Garcia Gomez <carlos@facturascripts.com>
 * PunteoCuentasPlus Copyright (C) 2023-2024 Jose Antonio Cuello Principal <yopli2000@gmail.com>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Lesser General Public License for more details.
 *
 * You should have received a copy of the GNU Lesser General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 */
namespace FacturaScripts\Plugins\PunteoCuentasPlus\Controller;

use Exception;
use FacturaScripts\Core\Base\DataBase\DataBaseWhere;
use FacturaScripts\Core\Base\DivisaTools;
use FacturaScripts\Core\Tools;
use FacturaScripts\Core\Controller\EditSubcuenta as ParentController;
use FacturaScripts\Core\Lib\ExtendedController\BaseView;
use FacturaScripts\Core\Lib\ExtendedController\DocFilesTrait;
use FacturaScripts\Dinamic\Model\Partida;
use FacturaScripts\Dinamic\Model\TotalModel;

/**
 * Add to EditSubcuenta:
 *   - additional filters
 *   - buttons statistics.
 *   - massive change of the subaccount.
 *   - add attached files.
 *
 * @author Jose Antonio Cuello Principal <yopli2000@gmail.com>
 */
class EditSubcuenta extends ParentController
{
    use DocFilesTrait;

    /**
     * Returns the total of the checked partidas.
     *
     * @return string
     */
    public function getChecked(): string
    {
        return DivisaTools::format($this->getTotal(true));
    }

    /**
     * Returns the total of the unchecked partidas.
     *
     * @return string
     */
    public function getUnChecked(): string
    {
        return DivisaTools::format($this->getTotal(false));
    }

    /**
     * Load views
     */
    protected function createViews()
    {
        parent::createViews();
        $this->addHtmlView('docfiles', 'Tab/PreviewFiles', 'AttachedFileRelation', 'files', 'fas fa-paperclip');
    }

    /**
     * Add new functions to the lines view.
     *
     * @param string $viewName
     * @return void
     * @throws Exception
     */
    protected function createViewsLines(string $viewName = 'ListPartidaAsiento'): void
    {
        parent::createViewsLines();
        $this->setSettings($viewName, 'btnPrint', true);
        $i18n = Tools::lang();
        $this->views[$viewName]->addFilterSelectWhere('status', [
            ['label' => $i18n->trans('all'), 'where' => []],
            ['label' => $i18n->trans('unchecked'), 'where' => [new DataBaseWhere('punteada', false)]],
            ['label' => $i18n->trans('checked'), 'where' => [new DataBaseWhere('punteada', true)]],
        ]);

        $this->addButton($viewName, [
            'action' => 'change-account',
            'label' => 'change',
            'color' => 'danger',
            'icon' => 'fas fa-recycle',
            'type' => 'modal',
        ]);
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
                return $this->addFileAction();

            case 'delete-file':
                return $this->deleteFileAction();

            case 'edit-file':
                return $this->editFileAction();

            case 'unlink-file':
                return $this->unlinkFileAction();

            case 'change-account':
                $this->changeAccountAction();
                return true;

            default:
                return parent::execPreviousAction($action);
        }
    }

    /**
     * Load view data procedure
     *
     * @param string $viewName
     * @param BaseView $view
     */
    protected function loadData($viewName, $view)
    {
        switch ($viewName) {
            case 'docfiles':
                $this->loadDataDocFiles($view, $this->getModelClassName(), $this->getModel()->primaryColumnValue());
                break;

            default:
                parent::loadData($viewName, $view);
        }
    }

    /**
     * Change the account of the selected partidas.
     *   - Check the request data.
     *   - For each selected partida change the account.
     *   - If the account doesn't exist into the exercise, show a warning.
     */
    private function changeAccountAction(): void
    {
        $data = $this->request->request->all();
        $codes = $data['code'] ?? '';
        if (empty($codes) || empty($data['new_code']) || empty($data['codsubcuenta'])) {
            Tools::log()->warning('change-subaccount-data-error');
            return;
        }

        $this->dataBase->beginTransaction();
        try {
            foreach (explode(',', $codes) as $idline) {
                $line = new Partida();
                if (false === $line->loadFromCode($idline)) {
                    continue;
                }

                $subAccount = $line->getSubcuenta($data['new_code']);
                if (empty($subAccount->idsubcuenta)) {
                    Tools::log()->warning('subaccount-not-found', ['%subAccountCode%' => $data['new_code']]);
                    continue;
                }

                // make the entry editable.
                $entry = $line->getAccountingEntry();
                $isEditable = $entry->editable;
                if (false === $isEditable) {
                    $entry->editable = true;
                    if (false === $entry->save()) {
                        Tools::log()->warning('entry-save-error', ['%code%' => $entry->numero]);
                        continue;
                    }
                }

                // change the subaccount.
                $line->idsubcuenta = $subAccount->idsubcuenta;
                $line->codsubcuenta = $subAccount->codsubcuenta;
                if (false === $line->save()) {
                    throw new Exception(
                        Tools::lang()->trans('partida-save-error', ['%code%' => $line->idpartida])
                    );
                }

                // Restore the editable status.
                if (false === $isEditable) {
                    $entry->editable = false;
                    $entry->save();
                }
            }
            $this->dataBase->commit();
            Tools::log()->notice('record-updated-correctly');
        } catch (Exception $exc) {
            $this->dataBase->rollback();
            Tools::log()->error($exc->getMessage());
        }
    }

    /**
     * get the total of the partidas checked or not.
     *
     * @param bool $checked
     * @return float
     */
    private function getTotal(bool $checked): float
    {
        $idsubcuenta = $this->request->query->get('code');
        $where = [
            new DataBaseWhere('idsubcuenta', $idsubcuenta),
            new DataBaseWhere('punteada', $checked),
        ];
        $fields = ['debe' => 'SUM(debe)', 'haber' => 'SUM(haber)'];
        $result = 0.00;
        foreach (TotalModel::all('partidas', $where, $fields) as $row) {
            $result += $row->totals['debe'] - $row->totals['haber'];
        }
        return $result;
    }
}
