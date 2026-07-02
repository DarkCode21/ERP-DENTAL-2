<?php
/**
 * Copyright (C) 2019-2022 Carlos Garcia Gomez <carlos@facturascripts.com>
 */

namespace FacturaScripts\Plugins\erp_interiberica\Model;

use FacturaScripts\Core\Model\Base;

/**
 * Description of CrmInteresContacto
 *
 * @author Carlos Garcia Gomez <carlos@facturascripts.com>
 */
class Version extends Base\ModelClass
{

    use Base\ModelTrait;

    /**
     * @var string
     */
    public $fecha;

    /**
     * @var int
     */
    public $id;

    /**
     * @var string
     */
    public $nombresoftware;

    /**
     * @var string
     */
    public $version;

	/**
     * @var int
	 */
    public $estado;

	/**
     * @var string
     */
    public $nick;
	
    public function clear()
    {
        parent::clear();
        $this->fecha = date(self::DATE_STYLE);
    }

    public function delete(): bool
    {
        if (parent::delete()) {
            // force interest update
            #$this->getInteres()->save();

            return true;
        }

        return false;
    }

	public static function primaryColumn(): string
    {
        return 'id';
    }

    public static function tableName(): string
    {
        return 'erp_versiones';
    }


	protected function updateAll (): bool {
		return self::$dataBase->exec('UPDATE ' . static::tableName() . ' SET estado = 0;');
	}
	
    protected function saveInsert(array $values = []): bool
    {
		$this->updateAll();
        if (parent::saveInsert($values)) {
            // force interest update
            #$this->getInteres()->save();
            return true;
        }

        return false;
    }
}
