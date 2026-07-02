<?php
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
namespace FacturaScripts\Plugins\FSReports\Model;

/**
 * Description of FSReportsFilters
 *
 * @author Usuario
 */
class FSReportsFilters extends \FacturaScripts\Core\Model\Base\ModelClass
{

    use \FacturaScripts\Core\Model\Base\ModelTrait;

    public $id;
    public $idreport;
    public $type;
    public $operator;
    public $label;
    public $default_value;

    /**
     * Devuelve el nombre de la tabla que usa este modelo.
     *
     * @return string
     */
    public static function tableName()
    {
        return 'frreports_filters';
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
        $this->idreport = NULL;
        $this->type = '';
        $this->operator = '';
        $this->label = '';
        $this->default_value = '';
    }
}
