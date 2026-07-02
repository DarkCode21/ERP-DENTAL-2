<?php
/**
 * This file is part of DocumentosRecurrentes plugin for FacturaScripts.
 * FacturaScripts         Copyright (C) 2015-2021 Carlos Garcia Gomez <carlos@facturascripts.com>
 * DocumentosRecurrentes  Copyright (C) 2020-2021 Jose Antonio Cuello Principal <yopli2000@gmail.com>
 *
 * This program and its files are under the terms of the license specified in the LICENSE file.
 */
namespace FacturaScripts\Plugins\DocumentosRecurrentes;

use FacturaScripts\Core\Base\InitClass;
use FacturaScripts\Dinamic\Model\EmailNotification;

/**
 * Description of Init
 *
 * @author Carlos Garcia Gomez <carlos@facturascripts.com>
 * @author Jose Antonio Cuello <yopli2000@gmail.com>
 */
class Init extends InitClass
{

    private const NOTIFICATION_ID = 'DocRecurring';

    public function init()
    {
        $this->loadExtension(new Extension\Controller\EditCliente());
        $this->loadExtension(new Extension\Controller\EditProveedor());
    }

    /**
     * When install or update plugin:
     *   - Check, and create if dont exists, email notification.
     */
    public function update()
    {
        $notification = new EmailNotification();
        if (false === $notification->loadFromCode(self::NOTIFICATION_ID)) {
            $notification->name = self::NOTIFICATION_ID;
            $notification->subject = $this->toolBox()->i18n()->trans('doc-recurring-mail-subject');
            $notification->body = $this->toolBox()->i18n()->trans('doc-recurring-mail-body');
            $notification->enabled = true;
            $notification->save();
        }
    }
}
