<?php

namespace FacturaScripts\Plugins\ImportFacEmail\Controller;

use FacturaScripts\Core\Base\Controller;
use FacturaScripts\Core\Base\DataBase;
use FacturaScripts\Core\Base\ExtensionsTrait;
use FacturaScripts\Core\Tools;

class ListCorreoInformacion extends Controller
{
    use ExtensionsTrait;

    public function getPageData(): array
    {
        $pageData = parent::getPageData();
        $pageData['title'] = 'Correos Facturas';
        $pageData['menu']  = 'purchases';
        $pageData['icon'] = 'fas fa-th-list';
        return $pageData;
    }

    protected function loadData($viewName, $view) {}

    public function privateCore(&$response, $user, $permissions)
    {
        parent::privateCore($response, $user, $permissions);

        $action = $this->request->request->get('action', '');
        if ($action === 'delete') {
            $this->deleteAction();
        }

        $this->listarCorreos();
    }

    protected function createViews()
    {
        $this->setTemplate('ListCorreoInformacion');
    }

    public function listarCorreos()
    {
        $dataBase = new DataBase();

        $sql = 'SELECT 
					idcorreo AS idcorreo,
					remitente AS remitente, 
					asunto AS asunto, 
					fecha AS fecha, 
					contenido AS contenido, 
					adjunto AS adjunto 
				FROM correos_informacions
				ORDER BY fecha DESC';

        $data = $dataBase->selectLimit($sql);

        return $data;
    }

    protected function deleteAction()
    {
        $ids = $this->request->request->get('ids', []);

        if (empty($ids)) {
            Tools::log()->warning('no-items-selected');
            return false;
        }

        $dataBase = new DataBase();
        $deleted = 0;

        foreach ($ids as $id) {
            $sql = 'DELETE FROM correos_informacions WHERE idcorreo = ' . $dataBase->var2str($id);
            if ($dataBase->exec($sql)) {
                $deleted++;
            }
        }

        if ($deleted > 0) {
            Tools::log()->info('items-deleted', ['%count%' => $deleted]);
        }

        return true;
    }
}
