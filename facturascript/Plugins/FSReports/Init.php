<?php

namespace FacturaScripts\Plugins\FSReports;

use FacturaScripts\Core\Base\InitClass;

require_once __DIR__ . '/vendor/autoload.php';
require_once FS_FOLDER . '/Plugins/FSReports/vendor/autoload.php';

/**
 * Description of Init
 *
 * @author Raul Jimenez <raljopa@gmail.com>
 */
class Init extends InitClass {

    public function init() {
        
        $this->loadExtension(new Extension\Controller\ListFacturaProveedor());
    }

    public function update() {
        ;
    }

}
