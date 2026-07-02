<?php
namespace FacturaScripts\Plugins\LogsFields;

use FacturaScripts\Core\Base\DataBase;
use FacturaScripts\Core\Base\InitClass;

class Init extends InitClass
{
    public function init() {
		// se ejecutara cada vez que carga FacturaScripts (si este plugin está activado).
      $this->loadExtension(new Extension\Model\LogMessage());
	  #$this->loadExtension(new Extension\Controller\ListLogMessage());
	  $dataBase = new DataBase();
	  $sql = "UPDATE logs JOIN users ON users.nick = logs.nick
	  			SET logs.email = users.email WHERE logs.email IS NULL";
	
	  $dataBase->exec($sql);
    }

    public function update() {
        // se ejecutara cada vez que se instala o actualiza el plugin.
    }
}