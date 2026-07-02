<?php
/**
 * Copyright (C) 2023 Daniel Fernández Giménez <hola@danielfg.es>
 */

namespace FacturaScripts\Plugins\Traducciones\Model;

use FacturaScripts\Core\Model\Base;
use FacturaScripts\Core\Session;
use FacturaScripts\Core\Tools;
use FacturaScripts\Plugins\Traducciones\Lib\LanguageTrait;

/**
 * @author Daniel Fernández Giménez <hola@danielfg.es>
 */
class Translate extends Base\ModelOnChangeClass
{
    use Base\ModelTrait;

    /** @var string */
    public $creationdate;

    /** @var int */
    public $id;

    /** @var int */
    public $idlang;
    
    /** @var string */
    public $keytrans;

    /** @var string */
    public $lastnick;

    /** @var string */
    public $lastupdate;

    /** @var string */
    public $nick;

    /** @var string */
    public $valuetrans;

    public function delete(): bool
    {
        if (false === parent::delete()) {
            return false;
        }

        LanguageTrait::generateJson($this->getLanguage());
        LanguageTrait::deploy();
        return true;
    }

    public function getLanguage(): Language
    {
        $lang = new Language();
        $lang->loadFromCode($this->idlang);
        return $lang;
    }

    public function save(): bool
    {
        if (false === parent::save()) {
            return false;
        }
		
		LanguageTrait::generateJson($this->getLanguage());
        LanguageTrait::deploy();
        return true;
    }
    
    public static function primaryColumn(): string
    {
        return 'id';
    }

    public function primaryDescription(): string
    {
        return $this->keytrans ?? Tools::lang()->trans('new');
    }

    public static function tableName(): string
    {
        return 'translates';
    }

    public function test(): bool
    {
        if ($this->exists()) {
            $this->lastnick = Session::get('user') ? Session::get('user')->nick : null;
            $this->lastupdate = Tools::dateTime();
        } else {
            $this->creationdate = Tools::dateTime();
            $this->lastnick = null;
            $this->lastupdate = null;
            $this->nick = Session::get('user') ? Session::get('user')->nick : null;
        }

        return parent::test();
    }

    public function install(): string
    {
        new Language();
        return parent::install();
    }
}