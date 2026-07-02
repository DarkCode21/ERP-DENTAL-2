<?php
/**
 * Copyright (C) 2022 Carlos Garcia Gomez <carlos@facturascripts.com>
 */

namespace FacturaScripts\Plugins\TPVneo\Lib\TPVneo;

use FacturaScripts\Core\Base\ToolBox;
use FacturaScripts\Dinamic\Model\Agente;
use FacturaScripts\Dinamic\Model\User;
use FacturaScripts\Dinamic\Model\TpvTerminal;
use FacturaScripts\Dinamic\Model\Ticket;

/**
 *
 * @author Carlos Garcia Gomez <carlos@facturascripts.com>
 * @author Daniel Fernández Giménez <hola@danielfg.es>
 */
class SaleTicket
{
    public static function loadFormats(): array
    {
        $formats = [];
        foreach (ToolBox::files()->scanFolder(FS_FOLDER . '/Dinamic/Lib/Tickets') as $fileName) {
            $formats[] = substr($fileName, 0, -4);
        }
		$formats[] = "Previsualizar Ticket";  #MOD ERICK
	
        return $formats;
    }

    public static function openDrawer(TpvTerminal $tpv, User $user, Agente $agente): Ticket
    {
        $printer = $tpv->getPrinter();
        $ticket = new Ticket();
        $ticket->idprinter = $printer->id;
        $ticket->nick = $user->nick;
        $ticket->codagente = $agente->codagente;
        $ticket->title = ToolBox::i18n()->trans('open-drawer');
        $ticket->body = $printer->getCommandStr('open');
        $ticket->save();
        return $ticket;
    }
}