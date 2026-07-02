<?php
/**
 * Copyright (C) 2019-2022 Carlos Garcia Gomez <carlos@facturascripts.com>
 */

namespace FacturaScripts\Plugins\CRM\Model;

use FacturaScripts\Core\Model\Contacto as ParentModel;

/**
 * Description of Contacto
 *
 * @author Carlos Garcia Gomez <carlos@facturascripts.com>
 */
class Contacto extends ParentModel
{

    /**
     * @var int
     */
    public $idfuente;

    public function getFuente(): CrmFuente
    {
        $fuente = new CrmFuente();
        $fuente->loadFromCode($this->idfuente);
        return $fuente;
    }

    public function delete(): bool
    {
        if (false === parent::delete()) {
            return false;
        }

        if (!empty($this->idfuente)) {
            // update source update
            $this->getFuente()->save();
        }

        return true;
    }

    public function url(string $type = 'auto', string $list = 'List'): string
    {
        return parent::url($type, $list);
    }

    protected function saveInsert(array $values = []): bool
    {
        if (false === parent::saveInsert($values)) {
            return false;
        }

        if (!empty($this->idfuente)) {
            // update source update
            $this->getFuente()->save();
        }

        return true;
    }
}
