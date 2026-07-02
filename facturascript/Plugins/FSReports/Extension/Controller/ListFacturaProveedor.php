<?php
namespace FacturaScripts\Plugins\FSReports\Extension\Controller;

use FacturaScripts\Core\Lib\ExtendedController\ListController;
use FacturaScripts\Core\Base\DataBase\DataBaseWhere;
use ZipArchive;
use Symfony\Component\HttpFoundation\Response;
use Closure;

/**
 * Description of ListFacturaProveedor
 *
 * @author Raúl <raljopa@gmail.com>
 */
class ListFacturaProveedor {
    public function createViews(): Closure {
        return function () {
            
            $this->addButton('ListFacturaProveedor', [
                'action' => 'exportzip',
                'color' => 'info',
                'icon' => 'fas fa-file-archive',
                'label' => 'Exportar adjuntos',
                'type' => 'action'
            ]);
            $modelName = 'FacturaProveedor';
        };
    }

    public function execPreviousAction(): Closure {
        return function ($action) {
            switch ($action) {
                case 'exportzip':
                    $option = $this->request->get('option', '');
                    $files = [];
                    $params = explode("&", $option);
                    if ($action == 'exportzip') {
                        $files = $this->generateDocList();
                        $zipFileName = $this->compressFiles($files);
                        
                        $zipFileName = FS_ROUTE . '/MyFiles/Public/' . 'adjuntos.zip';
                        $msg = 'Fichero listo para descarga. Botón derecho y guardar como . . . en <a target="_blank" href="' . $zipFileName . '"> Descarga de adjuntos </a>';
                        $this->toolBox()->log()->notice(utf8_encode($msg));
                        break;
                        
                    }
            }
        };
    }
    public function generateDocList(): Closure {
        return function () {
            
            $document = new \FacturaScripts\Dinamic\Model\AttachedFile();

            $files = [];
            
            $codes = $this->request->get('code', []);
            
            $codeString = implode(',', $codes);

          
            foreach ($codes as $code) {
                $whereRelations = [
                    new DataBaseWhere('modelid', $code)
                    , new DataBaseWhere('model', 'FacturaProveedor')
                ];
                $relations = new \FacturaScripts\Dinamic\Model\AttachedFileRelation();
                $relations = $relations->all($whereRelations, [], 0, 0);
                foreach ($relations as $relation) {
                    $document->loadFromCode($relation->idfile);
                    if ($document) {
                        $register['filename'] = $document->filename;
                        $register['path'] = $document->path;
                        $files[] = $register;
                    }
                }
            }
            return $files;
        };
    }

    public function compressFiles(): Closure {
        return function ($files) {
            $zipFile = new ZipArchive();
            $path = FS_FOLDER;

            $zipFilename = FS_FOLDER . '/MyFiles/Public/' . 'adjuntos.zip';
            if (file_exists($zipFilename)) {
                unlink($zipFilename);
            }
            if ($zipFile->open($zipFilename, ZipArchive::CREATE) === TRUE) {
                foreach ($files as $file) {
                    if (file_exists(FS_FOLDER . DIRECTORY_SEPARATOR . $file['path'])) {
                        $zipFile->addFile(FS_FOLDER . DIRECTORY_SEPARATOR . $file['path'], $file['filename']);
                    }
                    //
                }
                $result = $zipFile->close();
                
            }
            return $zipFilename;
        };
    }

}
