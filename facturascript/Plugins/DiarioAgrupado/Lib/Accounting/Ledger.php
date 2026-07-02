<?php
/**
 * Copyright (C) 2021-2022 Carlos Garcia Gomez <carlos@facturascripts.com>
 */

namespace FacturaScripts\Plugins\DiarioAgrupado\Lib\Accounting;

use FacturaScripts\Core\Base\DataBase\DataBaseWhere;
use FacturaScripts\Core\Base\ToolBox;
use FacturaScripts\Core\Lib\Accounting\Ledger as ParentClass;
use FacturaScripts\Dinamic\Model\Asiento;
use FacturaScripts\Dinamic\Model\Ejercicio;

/**
 * Description of Ledger
 *
 * @author Carlos Garcia Gomez <carlos@facturascripts.com>
 */
class Ledger extends ParentClass
{
    public function generate(int $idcompany, string $dateFrom, string $dateTo, array $params = []): array
    {
        if (($params['grouped'] ?? '') !== 'M') {
            return parent::generate($idcompany, $dateFrom, $dateTo, $params);
        }

        $this->exercise = new Ejercicio();
        $this->exercise->idempresa = $idcompany;
        if (false === $this->exercise->loadFromDate($dateFrom, true, false)) {
            return [];
        }

        $debe = $haber = 0.0;
        $ledger = [];
        $year = date('Y', strtotime($dateFrom));

        // desglose apertura
        $apertura = $this->getAsientoEspecial(Asiento::OPERATION_OPENING);
        if ($apertura->exists()) {
            $ledger[] = $this->generateCustomGroup($debe, $haber, $dateFrom, $dateTo, $apertura->concepto, [
                'entry-from' => $apertura->numero,
                'entry-to' => $apertura->numero
            ]);
        }

        $ledger[] = $this->generateCustomGroup($debe, $haber, '01-01-' . $year, '31-01-' . $year, 'Enero ' . $year, $params);
        $ledger[] = $this->generateCustomGroup($debe, $haber, '01-02-' . $year, '29-02-' . $year, 'Febrero ' . $year, $params);
        $ledger[] = $this->generateCustomGroup($debe, $haber, '01-03-' . $year, '31-03-' . $year, 'Marzo ' . $year, $params);
        $ledger[] = $this->generateCustomGroup($debe, $haber, '01-04-' . $year, '30-04-' . $year, 'Abril ' . $year, $params);
        $ledger[] = $this->generateCustomGroup($debe, $haber, '01-05-' . $year, '31-05-' . $year, 'Mayo ' . $year, $params);
        $ledger[] = $this->generateCustomGroup($debe, $haber, '01-06-' . $year, '30-06-' . $year, 'Junio ' . $year, $params);
        $ledger[] = $this->generateCustomGroup($debe, $haber, '01-07-' . $year, '31-07-' . $year, 'Julio ' . $year, $params);
        $ledger[] = $this->generateCustomGroup($debe, $haber, '01-08-' . $year, '31-08-' . $year, 'Agosto ' . $year, $params);
        $ledger[] = $this->generateCustomGroup($debe, $haber, '01-09-' . $year, '30-09-' . $year, 'Septiembre ' . $year, $params);
        $ledger[] = $this->generateCustomGroup($debe, $haber, '01-10-' . $year, '31-10-' . $year, 'Octubre ' . $year, $params);
        $ledger[] = $this->generateCustomGroup($debe, $haber, '01-11-' . $year, '30-11-' . $year, 'Noviembre ' . $year, $params);
        $ledger[] = $this->generateCustomGroup($debe, $haber, '01-12-' . $year, '31-12-' . $year, 'Diciembre ' . $year, $params);

        // desglose regularización
        $regularizacion = $this->getAsientoEspecial(Asiento::OPERATION_REGULARIZATION);
        if ($regularizacion->exists()) {
            $ledger[] = $this->generateCustomGroup($debe, $haber, $dateFrom, $dateTo, $regularizacion->concepto, [
                'entry-from' => $regularizacion->numero,
                'entry-to' => $regularizacion->numero
            ]);
        }

        // desglose cierre
        $closing = $this->getAsientoEspecial(Asiento::OPERATION_CLOSING);
        if ($closing->exists()) {
            $ledger[] = $this->generateCustomGroup($debe, $haber, $dateFrom, $dateTo, $closing->concepto, [
                'entry-from' => $closing->numero,
                'entry-to' => $closing->numero
            ]);
        }

        return $ledger;
    }

    private function generateCustomGroup(float &$debe, float &$haber, string $dateFrom, string $dateTo, string $label, array $params = []): array
    {
        $this->dateFrom = $dateFrom;
        $this->dateTo = $dateTo;
        $debe2 = $haber2 = 0.0;

        $table = [
            ['cuenta' => '', 'descripcion' => $label, 'debe' => '', 'haber' => '']
        ];
        foreach ($this->getDataCustom($params) as $line) {
            $table[] = [
                'cuenta' => $line['codcuenta'],
                'descripcion' => ToolBox::utils()->fixHtml($line['cuentadesc']),
                'debe' => ToolBox::coins()->format($line['debe'], FS_NF0, ''),
                'haber' => ToolBox::coins()->format($line['haber'], FS_NF0, '')
            ];

            $debe += (float)$line['debe'];
            $debe2 += (float)$line['debe'];
            $haber += (float)$line['haber'];
            $haber2 += (float)$line['haber'];
        }

        $table[] = [
            'cuenta' => '',
            'descripcion' => $label,
            'debe' => '<b>' . ToolBox::coins()->format($debe2, FS_NF0, '') . '</b>',
            'haber' => '<b>' . ToolBox::coins()->format($haber2, FS_NF0, '') . '</b>'
        ];
        if ($debe != $debe2 || $haber != $haber2) {
            $table[] = [
                'cuenta' => '',
                'descripcion' => 'Acumulado',
                'debe' => '<b>' . ToolBox::coins()->format($debe, FS_NF0, '') . '</b>',
                'haber' => '<b>' . ToolBox::coins()->format($haber, FS_NF0, '') . '</b>'
            ];
        }
        return $table;
    }

    private function getAsientoEspecial(string $operation): Asiento
    {
        $asiento = new Asiento();
        $where = [
            new DataBaseWhere('codejercicio', $this->exercise->codejercicio),
            new DataBaseWhere('operacion', $operation)
        ];
        $asiento->loadFromCode('', $where);
        return $asiento;
    }

    private function getDataCustom(array $params): array
    {
        if (false === $this->dataBase->tableExists('partidas')) {
            return [];
        }

        $sql = 'SELECT subcuentas.codcuenta, cuentas.descripcion AS cuentadesc, SUM(partidas.debe) AS debe, SUM(partidas.haber) AS haber'
            . ' FROM partidas'
            . ' LEFT JOIN asientos ON partidas.idasiento = asientos.idasiento'
            . ' LEFT JOIN subcuentas ON subcuentas.idsubcuenta = partidas.idsubcuenta'
            . ' LEFT JOIN cuentas ON cuentas.idcuenta = subcuentas.idcuenta'
            . ' WHERE ' . $this->getDataWhere($params)
            . ' GROUP BY 1, 2'
            . ' ORDER BY 1 ASC';
        return $this->dataBase->select($sql);
    }

    protected function getDataWhere(array $params = []): string
    {
        $where = 'asientos.codejercicio = ' . $this->dataBase->var2str($this->exercise->codejercicio)
            . ' AND asientos.fecha BETWEEN ' . $this->dataBase->var2str($this->dateFrom)
            . ' AND ' . $this->dataBase->var2str($this->dateTo);

        $channel = $params['channel'] ?? '';
        if (!empty($channel)) {
            $where .= ' AND asientos.canal = ' . $this->dataBase->var2str($channel);
        }

        $subaccountFrom = $params['subaccount-from'] ?? '';
        $subaccountTo = $params['subaccount-to'] ?? $subaccountFrom;
        if (!empty($subaccountFrom) || !empty($subaccountTo)) {
            $where .= ' AND partidas.codsubcuenta BETWEEN ' . $this->dataBase->var2str($subaccountFrom)
                . ' AND ' . $this->dataBase->var2str($subaccountTo);
        }

        $entryFrom = $params['entry-from'] ?? '';
        $entryTo = $params['entry-to'] ?? $entryFrom;
        if (!empty($entryFrom) || !empty($entryTo)) {
            $where .= ' AND asientos.numero BETWEEN ' . $this->dataBase->var2str($entryFrom)
                . ' AND ' . $this->dataBase->var2str($entryTo);
        }

        return $where;
    }
}
