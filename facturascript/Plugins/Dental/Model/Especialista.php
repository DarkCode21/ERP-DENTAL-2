<?php

namespace FacturaScripts\Plugins\Dental\Model;

use FacturaScripts\Core\Base\DataBase\DataBaseWhere;
use FacturaScripts\Core\Model\Base\ModelClass;
use FacturaScripts\Core\Model\User;
use FacturaScripts\Dinamic\Model\Especialidad AS DinEspecialidad;

class Especialista extends ModelClass
{
    use \FacturaScripts\Core\Model\Base\ModelTrait;

    public $id;
    public $codusuario;
    public $nombre;
    public $apellidos;
    public $numero_colegiado;
    public $idespecialidad;
    public $telefono;
    public $email;
    public $color_agenda;
    public $salon_assistant_id;
    public $estado;
    public $observaciones;
    public $created_at;
    public $updated_at;

    public static function primaryColumn(): string
    {
        return 'id';
    }

    public static function tableName(): string
    {
        return 'dental_especialistas';
    }

    public function clear()
    {
        parent::clear();
        $this->estado = 'activo';
        $this->color_agenda = '#3b82f6';
    }

    public function primaryDescriptionColumn(): string
    {
        return 'nombre';
    }

    public function getEspecialidad(): ?DinEspecialidad
    {
        if (empty($this->idespecialidad)) {
            return null;
        }

        $especialidad = new DinEspecialidad();
        if ($especialidad->loadFromCode($this->idespecialidad)) {
            return $especialidad;
        }
        return null;
    }

    public function getUser(): ?User
    {
        if (empty($this->codusuario)) {
            return null;
        }

        $user = new User();
        if ($user->loadFromCode($this->codusuario)) {
            return $user;
        }
        return null;
    }

    public static function getFromUser(User $user): ?self
    {
        if (empty($user->nick)) {
            return null;
        }

        $especialista = new self();
        $where = [new DataBaseWhere('codusuario', $user->nick)];
        if ($especialista->loadFromCode('', $where)) {
            return $especialista;
        }
        return null;
    }

    public function isActive(): bool
    {
        return $this->estado === 'activo';
    }

    public function getFullName(): string
    {
        return $this->nombre . ' ' . $this->apellidos;
    }

    public function getInitials(): string
    {
        $nombre = mb_substr($this->nombre, 0, 1);
        $apellidos = mb_substr($this->apellidos, 0, 1);
        return mb_strtoupper($nombre . $apellidos);
    }

    public function test(): bool
    {
        $this->nombre = trim($this->nombre);
        $this->apellidos = trim($this->apellidos);
        if (empty($this->nombre) || empty($this->apellidos)) {
            return false;
        }

        if (!empty($this->codusuario)) {
            $user = new User();
            if (!$user->loadFromCode($this->codusuario)) {
                $this->codusuario = null;
            }
        }

        return parent::test();
    }
}
