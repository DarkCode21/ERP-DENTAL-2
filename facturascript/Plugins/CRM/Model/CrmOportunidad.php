<?php
/**
 * Copyright (C) 2019-2022 Carlos Garcia Gomez <carlos@facturascripts.com>
 */

namespace FacturaScripts\Plugins\CRM\Model;

use FacturaScripts\Core\Base\DataBase\DataBaseWhere;
use FacturaScripts\Core\Model\Base;
use FacturaScripts\Dinamic\Model\PresupuestoCliente;

/**
 * Description of CrmOportunidad
 *
 * @author Carlos Garcia Gomez <carlos@facturascripts.com>
 */
class CrmOportunidad extends Base\ModelOnChangeClass
{

    use Base\ModelTrait;

    /**
     * @var string
     */
    public $codagente;

    /**
     * @var string
     */
    public $coddivisa;

    /**
     * @var string
     */
    public $descripcion;

    /**
     * @var bool
     */
    public $editable;

    /**
     * @var string
     */
    public $fecha;

    /**
     * @var string
     */
    public $fechamod;

    /**
     * @var string
     */
    public $fecha_cierre;

    /**
     * @var string
     */
    public $hora;

    /**
     * @var int
     */
    public $id;

    /**
     * @var int
     */
    public $idcontacto;

    /**
     * @var int
     */
    public $idestado;

    /**
     * @var int
     */
    public $idfuente;

    /**
     * @var int
     */
    public $idinteres;

    /**
     * @var int
     */
    public $idpresupuesto;

    /**
     * @var float
     */
    public $neto;

    /**
     * @var float
     */
    public $netoeuros;

    /**
     * @var string
     */
    public $nick;

    /**
     * @var string
     */
    public $observaciones;

    /**
     * @var bool
     */
    public $rechazado;

    /**
     * @var float
     */
    public $tasaconv;

    public function clear()
    {
        parent::clear();
        $this->fecha = date(self::DATE_STYLE);
        $this->fechamod = date(self::DATETIME_STYLE);
        $this->hora = date(self::HOUR_STYLE);
        $this->neto = 0.0;
        $this->netoeuros = 0.0;
        $this->tasaconv = 1.0;

        // set estado
        $estadoModel = new CrmOportunidadEstado();
        foreach ($estadoModel->all([], [], 0, 0) as $estado) {
            if ($estado->predeterminado) {
                $this->editable = $estado->editable;
                $this->idestado = $estado->id;
                $this->rechazado = $estado->rechazado;
                break;
            }
        }
    }

    /**
     * @return Contacto
     */
    public function getContacto()
    {
        $contact = new Contacto();
        $contact->loadFromCode($this->idcontacto);
        return $contact;
    }

    /**
     * @return CrmOportunidadEstado
     */
    public function getEstado()
    {
        $estado = new CrmOportunidadEstado();
        $estado->loadFromCode($this->idestado);
        return $estado;
    }

    /**
     * @return CrmNota[]
     */
    public function getNotas(): array
    {
        $noteModel = new CrmNota();
        $where = [new DataBaseWhere('idoportunidad', $this->id)];
        $order = ['fecha' => 'DESC', 'hora' => 'DESC'];
        return $noteModel->all($where, $order, 0, 0);
    }

    /**
     * @return PresupuestoCliente
     */
    public function getPresupuesto()
    {
        $presupuesto = new PresupuestoCliente();
        $presupuesto->loadFromCode($this->idpresupuesto);
        return $presupuesto;
    }

    public function install(): string
    {
        // needed dependency
        new CrmOportunidadEstado();
        new PresupuestoCliente();

        return parent::install();
    }

    public static function primaryColumn(): string
    {
        return 'id';
    }

    public function primaryDescriptionColumn(): string
    {
        return 'id';
    }

    public function save(): bool
    {
        if (false === parent::save()) {
            return false;
        }

        // save contact interest
        if ($this->idinteres && $this->idcontacto) {
            $interesContacto = new CrmInteresContacto();
            $where = [
                new DataBaseWhere('idcontacto', $this->idcontacto),
                new DataBaseWhere('idinteres', $this->idinteres)
            ];
            if (false === $interesContacto->loadFromCode('', $where)) {
                $interesContacto->idcontacto = $this->idcontacto;
                $interesContacto->idinteres = $this->idinteres;
                $interesContacto->save();
            }
        }

        return true;
    }

    public static function tableName(): string
    {
        return 'crm_oportunidades';
    }

    public function test(): bool
    {
        $utils = $this->toolBox()->utils();
        $this->descripcion = $utils->noHtml($this->descripcion);
        $this->observaciones = $utils->noHtml($this->observaciones);

        return parent::test();
    }

    /**
     * @param string $field
     *
     * @return bool
     */
    protected function onChange($field)
    {
        if ($field === 'idestado') {
            $estado = $this->getEstado();
            $this->editable = $estado->editable;
            $this->idestado = $estado->id;
            $this->rechazado = $estado->rechazado;
            $this->fecha_cierre = $estado->editable ? null : \date(self::DATE_STYLE);
        }

        return parent::onChange($field);
    }

    protected function saveUpdate(array $values = []): bool
    {
        $this->fechamod = date(self::DATETIME_STYLE);
        return parent::saveUpdate($values);
    }

    protected function setPreviousData(array $fields = [])
    {
        $more = ['idestado'];
        parent::setPreviousData(array_merge($more, $fields));
    }
}
