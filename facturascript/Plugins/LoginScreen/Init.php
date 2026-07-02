<?php

namespace FacturaScripts\Plugins\LoginScreen;

use FacturaScripts\Core\Template\InitClass;

class Init extends InitClass
{

    /**
     * @inheritDoc
     */
    public function init(): void
    {
        $this->loadExtension(new Extension\Controller\EditSettings());
    }

    /**
     * @inheritDoc
     */
    public function uninstall(): void
    {
        // TODO: Implement uninstall() method.
    }

    /**
     * @inheritDoc
     */
    public function update(): void
    {
        // TODO: Implement update() method.
    }
}
