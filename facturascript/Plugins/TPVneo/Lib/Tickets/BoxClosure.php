<?php
/**
 * Copyright (C) 2022-2023 Carlos Garcia Gomez <carlos@facturascripts.com>
 */

namespace FacturaScripts\Plugins\TPVneo\Lib\Tickets;

use FacturaScripts\Core\Base\ToolBox;
use FacturaScripts\Core\Session;
use FacturaScripts\Dinamic\Model\Divisa;
use FacturaScripts\Plugins\Tickets\Model\Ticket;
use FacturaScripts\Plugins\TPVneo\Model\TpvCaja;

class BoxClosure
{
    public static function print(TpvCaja $caja): bool
    {
        $terminal = $caja->getTerminal();
        $printer = $terminal->getPrinter();
        if (false === $printer->exists() || empty($caja->fechafin)) {
            return false;
        }

        $i18n = ToolBox::i18n();

        $ticket = new Ticket();
        $ticket->idprinter = $printer->id;
        $ticket->nick = Session::get('user')->nick;
        $ticket->title = $i18n->trans('box-closure');

        $divisa = new Divisa();
        $divisa->loadFromCode($terminal->coddivisa);
        ToolBox::coins()->findDivisa($divisa);

        $ticket->body = self::getBigText($i18n->trans('box-closure'), $printer->linelen) . "\n"
            . $i18n->trans('pos-terminal') . ': ' . $terminal->name . "\n"
            . $i18n->trans('date') . ': ' . $caja->fechafin . "\n"
            . $i18n->trans('user') . ': ' . $caja->nick . "\n"
            . $i18n->trans('start-money') . ': ' . ToolBox::coins()::format($caja->dineroini) . "\n"
            . $i18n->trans('income') . ': ' . ToolBox::coins()::format($caja->ingresos) . "\n"
            . 'Entrada caja: ' . ToolBox::coins()::format($caja->getManualIncomes()) . "\n"
            . 'Salida caja: ' . ToolBox::coins()::format($caja->getManualOutcomes()) . "\n"
            . $i18n->trans('end-money') . ': ' . ToolBox::coins()::format($caja->dinerofin) . "\n"
            . $i18n->trans('difference') . ': ' . ToolBox::coins()::format($caja->diferencia) . "\n"
            . $i18n->trans('tickets') . ': ' . $caja->numtickets . "\n\n";

        foreach ($caja->getPaymentBreakdown() as $payment) {
            $ticket->body .= $payment['descripcion'] . ': ' . ToolBox::coins()::format($payment['total']) . "\n";
        }

        $ticket->body .= "\n" . $i18n->trans('observations') . ":\n" . $caja->observaciones
            . "\n\n\n\n\n\n"
            . "\n\n\n\n\n\n"
            . $printer->getCommandStr('cut') . "\n";
        return $ticket->save();
    }

    protected static function getBigText(string $text, int $lineLength): string
    {
        $bigLine = '';
        $bigLineLength = 0;
        $bigLineMax = intval($lineLength / 2);
        $words = explode(' ', $text);
        foreach ($words as $word) {
            if ($bigLineLength === 0) {
                $bigLine .= $word;
                $bigLineLength += strlen($word);
                continue;
            }

            $bigLineLength += strlen($word) + 1;
            if ($bigLineLength <= $bigLineMax) {
                $bigLine .= ' ' . $word;
                continue;
            }

            $bigLine .= "\n" . $word;
            $bigLineLength = strlen($word);
        }

        return "\x1B" . "!" . "\x38" . $bigLine . "\n" . "\x1B" . "!" . "\x00";
    }
}