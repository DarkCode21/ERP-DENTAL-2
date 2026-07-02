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

class FSReportsGroups extends \FacturaScripts\Core\Model\Base\ModelClass
{

    use \FacturaScripts\Core\Model\Base\ModelTrait;

    public $id;
    public $name;

    public static function tableName()
    {
        return 'fsreports_groups';
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
        $this->id = null;
        $this->name = '';
    }
}
