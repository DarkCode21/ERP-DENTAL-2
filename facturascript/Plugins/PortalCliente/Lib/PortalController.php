<?php
/**
 * Copyright (C) 2024 Daniel Fernández Giménez <hola@danielfg.es>
 */

namespace FacturaScripts\Plugins\PortalCliente\Lib;

use FacturaScripts\Core\Base\Controller;
use FacturaScripts\Core\Base\ControllerPermissions;
use FacturaScripts\Core\KernelException;
use FacturaScripts\Core\Tools;
use FacturaScripts\Dinamic\Model\Contacto;
use FacturaScripts\Dinamic\Model\User;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * @author Daniel Fernández Giménez <hola@danielfg.es>
 */
class PortalController extends Controller
{
    const DEFAULT_TEMPLATE = 'Master/PortalTemplate';

    /** @var string */
    public $canonicalUrlWithParameters;

    /** @var Contacto */
    public $contact;

    /** @var string */
    public $description;

    /** @var string */
    public $uriParameters;

    /**
     * @param string $className
     * @param string $uri
     */
    public function __construct(string $className, string $uri = '')
    {
        parent::__construct($className, $uri);
        $uriParts = parse_url($_SERVER["REQUEST_URI"] ?? '');
        $this->uriParameters = $uriParts['query'] ?? '';
    }

    public function getPageData(): array
    {
        $pageData = parent::getPageData();
        $pageData['menu'] = 'web';
        $pageData['showonmenu'] = false;
        return $pageData;
    }

    /**
     * @param Response $response
     * @param User $user
     * @param ControllerPermissions $permissions
     * @throws KernelException
     */
    public function privateCore(&$response, $user, $permissions)
    {
        parent::privateCore($response, $user, $permissions);
        $this->commonCore();
    }

    /**
     * @param Response $response
     */
    public function publicCore(&$response)
    {
        parent::publicCore($response);
        $this->commonCore();
    }

    protected function commonCore(): void
    {
        $this->canonicalUrlWithParameters = empty($this->uriParameters) ? $this->uri : $this->uri . '?' . $this->uriParameters;

        // login del contacto
        $this->contact = new Contacto();
        $idcontacto = $this->request->cookies->get('pc_idcontacto', '');

        if (false === empty($idcontacto) &&
            $this->contact->loadFromCode($idcontacto) &&
            $this->contact->pc_active &&
            $this->contact->verifyPCLogkey((string)$this->request->cookies->get('pc_log_key'))) {

            // establecemos el idioma del contacto
            Tools::lang()->setDefaultLang($this->contact->langcode);

            if ($this->contact->updatePCActivity($this->request->headers->get('User-Agent'))) {
                // actualizamos las cookies
                $expire = time() + FS_COOKIES_EXPIRE;
                setcookie('pc_idcontacto', $this->contact->idcontacto, $expire, Tools::config('route', '/'));
                setcookie('pc_log_key', $this->contact->pc_log_key, $expire, Tools::config('route', '/'));

                $this->contact->save();
            }
        }

        $this->setTemplate(static::DEFAULT_TEMPLATE);
    }

    protected function error301(string $newUrl): void
    {
        $this->setTemplate(false);
        $this->response = new RedirectResponse($newUrl, 301);
    }

    protected function error302(string $newUrl): void
    {
        $this->setTemplate(false);
        $this->response = new RedirectResponse($newUrl, 302);
    }

    protected function error403(): void
    {
        $this->setTemplate('Error/PortalAccessDenied');
        $this->response->setStatusCode(Response::HTTP_FORBIDDEN);
    }

    protected function error404(): void
    {
        $this->setTemplate('Error/Portal404');
        $this->response->setStatusCode(Response::HTTP_NOT_FOUND);

        $this->description = Tools::lang()->trans('page-not-found-p');
        $this->title = Tools::lang()->trans('page-not-found');
    }
}
