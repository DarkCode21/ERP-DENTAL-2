<?php
/**
 * This file is part of FacturaScripts
 * Copyright (C) 2017-2022 Carlos Garcia Gomez <carlos@facturascripts.com>
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

namespace FacturaScripts\Plugins\erp_interiberica\Controller;

use FacturaScripts\Core\Base\DataBase\DataBaseWhere;
use FacturaScripts\Core\Lib\ExtendedController\BaseView;
use FacturaScripts\Core\Lib\ExtendedController\EditController;

/**
 * Controller to edit a single item from the Atributo model
 *
 * @author Carlos García Gómez      <carlos@facturascripts.com>
 * @author Jose Antonio Cuello Principal <yopli2000@gmail.com>
 * @author Ramiro Salvador Mamani   <ramiro@solsun.pe>
 * @author Carlos Jiménez Gómez     <carlos@evolunext.es>
 */
class EditVersion extends EditController
{

    /**
     * Returns the model name.
     *
     * @return string
     */
    public function getModelClassName(): string
    {
        return 'Version';
    }

    /**
     * Returns basic page attributes.
     *
     * @return array
     */
    public function getPageData(): array
    {
        $data = parent::getPageData();
      	$data['menu'] = 'admin';
        $data['title'] = 'version';
        $data['icon'] = 'fas fa-recycle';
		$data['showonmenu'] = false;
		return $data;
    }

    /**
     * Load views
     */
    protected function createViews()
    {
        parent::createViews();
        $this->createViewsAttValues();
    }

    /**
     * @param string $viewName
     */
    protected function createViewsAttValues(string $viewName = 'EditVersion')
    {
        $this->addEditView($viewName, 'Version', 'version');
        #$this->views[$viewName]->setInLine(true);

        // disable column
        #$this->views[$viewName]->disableColumn('attribute');
    }

    /**
     * Load view data procedure
     *
     * @param string $viewName
     * @param BaseView $view
     */
    protected function loadData($viewName, $view)
    {
		$mvn = $this->getMainViewName();
        switch ($viewName) {
            case 'EditVersion':
				parent::loadData($viewName, $view);
                if (empty($view->model->nick)) {
                    $view->model->nick = $this->user->nick;
                }

		    case $mvn:
                parent::loadData($viewName, $view);
                if (empty($view->model->nick)) {
                    $view->model->nick = $this->user->nick;
                }
				if (empty($view->model->nombresoftware)) {
					$view->model->nombresoftware = $this->toolBox()->appSettings()->get('default', 'nombrecrm');
				}
                break;
            default:
                parent::loadData($viewName, $view);
                break;
        }
    }
}
