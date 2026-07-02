<?php
/**
 * Copyright (C) 2023 Daniel Fernández Giménez <hola@danielfg.es>
 */

namespace FacturaScripts\Plugins\Traducciones\Model;

use FacturaScripts\Core\Base\DataBase\DataBaseWhere;
use FacturaScripts\Core\Model\Base;
use FacturaScripts\Core\Session;
use FacturaScripts\Core\Tools;
use FacturaScripts\Plugins\Traducciones\Lib\LanguageTrait;

/**
 * @author Daniel Fernández Giménez <hola@danielfg.es>
 */
class Language extends Base\ModelClass
{
    use Base\ModelTrait;
    
    /** @var string */
    public $codicu;

    /** @var string */
    public $codpais;

    /** @var string */
    public $creationdate;

    /** @var int */
    public $id;

    /** @var int */
    public $idflag;

    /** @var string */
    public $lastnick;

    /** @var string */
    public $lastupdate;
    
    /** @var string */
    public $name;

    /** @var string */
    public $nick;

    public function delete(): bool
    {
        if (false === parent::delete()) {
            return false;
        }

        // eliminamos el archivo json
        $myFile = $this->url('json');
        if (file_exists($myFile)) {
            unlink($myFile);
        }

        LanguageTrait::deploy();
        return true;
    }

    public function getTranslations(): array
    {
        $transModel = new Translate();
        $where = [new DataBaseWhere('idlang', $this->id)];
        return $transModel->all($where, ['keytrans' => 'ASC'], 0, 0);
    }

    public static function primaryColumn(): string
    {
        return 'id';
    }

    public function primaryDescription(): string
    {
        return $this->codicu ?? Tools::lang()->trans('new');
    }

    public function save(): bool
    {
        if (false === parent::save()) {
            return false;
        }

        LanguageTrait::generateJson($this);
        LanguageTrait::deploy();
        return true;
    }

    public static function tableName(): string
    {
        return 'languages';
    }

    public function test(): bool
    {
        $utils = $this->toolBox()->utils();
        $this->codicu = str_replace([' ', '-'], '_', $utils->noHtml($this->codicu));

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

    public function url(string $type = 'auto', string $list = 'ListTranslate?activetab=List'): string
    {
        if ($type === 'json') {
            $path = FS_FOLDER . '/MyFiles/Translation/' . $this->codicu . '.json';
            return file_exists($path) ? $path : '';
        }

        return parent::url($type, $list);
    }
}