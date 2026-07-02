<?php
/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */
/**
 * Description of FSReportsGroups
 *
 * @author Raul <raljopa@gmail.com>
 */
namespace FacturaScripts\Plugins\FSReports\Model;

use FacturaScripts\Core\App\AppSettings;
use FacturaScripts\Core\Base\Utils;

/**
 * Description of FSReportsReports
 *
 * @author Raul Jimenez <raljopa@gmail.com>
 */
class FSReportsReports extends \FacturaScripts\Core\Model\Base\ModelClass
{

    use \FacturaScripts\Core\Model\Base\ModelTrait;

    public $id;
    public $idgroup;
    public $name;
    public $description;
    public $label;
    public $template;

    /**
     * Devuelve el nombre de la tabla que usa este modelo.
     *
     * @return string
     */
    public static function tableName()
    {
        return 'fsreports_reports';
    }

    /**
     * Devuelve el nombre de la columna que es clave primaria del modelo.
     *
     * @return int
     */
    public static function primaryColumn()
    {
        return 'id';
    }

    /**
     * Resetea los valores de todas las propiedades modelo.
     */
    public function clear()
    {
        $this->id = NULL;
        $this->idgroup = NULL;
        $this->name = '';
        $this->description = '';
        $this->label = '';
        $this->template = '';
    }
}
