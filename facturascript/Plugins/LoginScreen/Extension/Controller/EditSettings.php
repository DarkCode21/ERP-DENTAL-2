<?php

namespace FacturaScripts\Plugins\LoginScreen\Extension\Controller;

use Closure;
use Exception;
use FacturaScripts\Core\Model\AttachedFile;
use FacturaScripts\Core\Tools;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;

/**
 * @property Request $request
 */
class EditSettings
{
    public function execAfterAction(): Closure
    {
        return function () {
            /** @var UploadedFile $favicon */
            $request = $this->request->request;

            if ($request->get('activetab') !== 'SettingsLoginScreen')
                return;

            $faviconID = $request->get('login_favicon_id');

            $attachedFavicon = new AttachedFile();
            if ($attachedFavicon->loadFromCode($faviconID)) {

                try {
                    $faviconPath = FS_FOLDER . DIRECTORY_SEPARATOR . $attachedFavicon->path;
                    $assetsPath = FS_FOLDER . DIRECTORY_SEPARATOR . 'Plugins/LoginScreen/Assets/Images/favicon.ico';

                    if (!copy($faviconPath, $assetsPath)) {
                        Tools::log()->warning('cant-copy: ' . $assetsPath);
                        //throw new Exception('No se pudo copiar el favicon de ' . $faviconPath . ' a ' . $assetsPath);
                        return;
                    }
                    Tools::log()->info('favicon-installed');
                } catch (Exception $e) {
                    Tools::log()->warning('error-saving-favicon: ' . $e->getMessage());
                }
            }
        };
    }
}
