<?php

namespace FacturaScripts\Plugins\ImportFacEmail\Model;

use FacturaScripts\Core\Model\Base;
use FacturaScripts\Core\Tools;

class CorreoInformacion extends Base\ModelClass
{
	use Base\ModelTrait;
	
	/** @var int */
    public $idcorreo;

    /** @var string */
    public $remitente;

    /** @var string */
    public $asunto;

    /** @var string */
    public $fecha;

    /** @var string */
    public $contenido;

    /** @var string */
    public $adjunto;

    /** @var string */
    public $message_id;
	
	public function clear()
    {
        parent::clear();
    }
	
	public function test(): bool
    {
        $fields = ['remitente', 'asunto', 'contenido', 'adjunto', 'message_id'];
        foreach ($fields as $field) {
            $this->{$field} = Tools::noHtml($this->{$field});
        }

        return parent::test();
    }

    /**
     * Verifica si ya existe un correo con este message_id
     */
    public function existeCorreo($messageId): bool
    {
        $sql = 'SELECT COUNT(*) as total FROM ' . static::tableName() . 
               ' WHERE message_id = ' . self::$dataBase->var2str($messageId);
        $data = self::$dataBase->select($sql);
        return !empty($data) && $data[0]['total'] > 0;
    }
	
	public static function primaryColumn(): string
    {
        return 'idcorreo';
    }
	
	public static function tableName(): string
    {
        return 'correos_informacions';
    }
}