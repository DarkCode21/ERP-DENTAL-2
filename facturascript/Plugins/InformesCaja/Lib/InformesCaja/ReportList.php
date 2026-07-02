<?php
/**
 * Copyright (C) 2022 Carlos Garcia Gomez <carlos@facturascripts.com>
 */

namespace FacturaScripts\Plugins\InformesCaja\Lib\InformesCaja;

use FacturaScripts\Core\Base\DataBase;
use FacturaScripts\Core\Base\DataBase\DataBaseWhere;
use FacturaScripts\Core\Base\DivisaTools;
use FacturaScripts\Core\Base\ToolBox;
use FacturaScripts\Core\Session; #MOD Miller 
use FacturaScripts\Dinamic\Model\AttachedFile;
use FacturaScripts\Dinamic\Model\FacturaProveedor;
use FacturaScripts\Dinamic\Model\FacturaCliente;

/**
 *
 * @author Carlos Garcia Gomez      <carlos@facturascripts.com>
 * @author Daniel Fernández Giménez <hola@danielfg.es>
 */
class ReportList
{
    /**
     * @var string
     */
    protected static $f_inicio = '';

    /**
     * @var string
     */
    protected static $f_fin = '';
	protected static $s_ventas = false;
	protected static $nick = '';
	protected static $codserie = '';
    /**
     * @var int
     */
    protected static $limit = 0;
	protected static $level = 0;
    /**
     * @var string
     */
    protected static $cod_pago_cont = 'CONT';
	protected static $cod_pago_tarjeta = 'TARJETA';
	protected static $cod_pago_trans = 'TRANS';
	
	protected static $user;
	
    public static function apply(array $formData)
    {
   
    }
	
	protected static function init(): void
	{
		self::$user = Session::user();
		self::$nick = mb_strtolower(self::$user->nick);
		self::$level = self::$user->level;
	}

    public static function render(string $f_inicio, string $f_fin, bool $s_ventas, string $nick, string $codserie = ''): string
    {
        self::$limit = -1;
		self::$f_inicio = $f_inicio;
		self::$f_fin = $f_fin;
		self::$s_ventas = $s_ventas;
		self::$nick = $nick;
		self::$codserie = $codserie;
		
		self::init();
		
        return self::getReportList();
    }

	protected static function serieFilterSql(DataBase $dataBase): string
	{
		if (empty(self::$codserie)) {
			return '';
		}

		return ' AND fcli.codserie = ' . $dataBase->var2str(self::$codserie);
	}

	protected static function terminalSerieFilterSql(DataBase $dataBase): string
	{
		if (empty(self::$codserie)) {
			return '';
		}

		return ' AND tpv.codserie = ' . $dataBase->var2str(self::$codserie);
	}

	protected static function getCashMovementsSummary(): array
	{
		$dataBase = new DataBase();

		$sqlTpvneo = 'SELECT '
			. 'SUM(CASE WHEN m.tipo = ' . $dataBase->var2str('in') . ' THEN m.importe ELSE 0 END) AS entradas, '
			. 'SUM(CASE WHEN m.tipo = ' . $dataBase->var2str('out') . ' THEN m.importe ELSE 0 END) AS salidas '
			. 'FROM tpvsneo_caja_movimientos m '
			. 'LEFT JOIN tpvsneo tpv ON tpv.idtpv = m.idtpv '
			. 'WHERE DATE(m.fecha) BETWEEN ' . $dataBase->var2str(self::$f_inicio)
			. ' AND ' . $dataBase->var2str(self::$f_fin);

		if (self::$level != 99) {
			$sqlTpvneo .= ' AND LOWER(SUBSTRING_INDEX(tpv.name, " ", 1)) = ' . $dataBase->var2str(self::$nick);
		}

		$sqlTpvneo .= self::terminalSerieFilterSql($dataBase) . ';';

		$resultTpvneo = $dataBase->select($sqlTpvneo);
		$entradasTpvneo = isset($resultTpvneo[0]['entradas']) ? (float) $resultTpvneo[0]['entradas'] : 0.0;
		$salidasTpvneo = isset($resultTpvneo[0]['salidas']) ? (float) $resultTpvneo[0]['salidas'] : 0.0;

		$sqlRestaurante = 'SELECT '
			. 'SUM(CASE WHEN m.tipo = ' . $dataBase->var2str('in') . ' THEN m.importe ELSE 0 END) AS entradas, '
			. 'SUM(CASE WHEN m.tipo = ' . $dataBase->var2str('out') . ' THEN m.importe ELSE 0 END) AS salidas '
			. 'FROM rest_caja_movimientos m '
			. 'LEFT JOIN rest_cajas c ON c.idcaja = m.idcaja '
			. 'WHERE DATE(m.fecha) BETWEEN ' . $dataBase->var2str(self::$f_inicio)
			. ' AND ' . $dataBase->var2str(self::$f_fin);

		if (self::$level != 99) {
			$sqlRestaurante .= ' AND LOWER(m.nick) = ' . $dataBase->var2str(self::$nick);
		}

		if (!empty(self::$codserie)) {
			$sqlRestaurante .= ' AND c.codserie = ' . $dataBase->var2str(self::$codserie);
		}

		$sqlRestaurante .= ';';

		$resultRestaurante = $dataBase->select($sqlRestaurante);
		$entradasRestaurante = isset($resultRestaurante[0]['entradas']) ? (float) $resultRestaurante[0]['entradas'] : 0.0;
		$salidasRestaurante = isset($resultRestaurante[0]['salidas']) ? (float) $resultRestaurante[0]['salidas'] : 0.0;

		$entradasTotal = $entradasTpvneo + $entradasRestaurante;
		$salidasTotal = $salidasTpvneo + $salidasRestaurante;


		return [
			'entradas_tpvneo' => $entradasTpvneo,
			'salidas_tpvneo' => $salidasTpvneo,
			'entradas_restaurante' => $entradasRestaurante,
			'salidas_restaurante' => $salidasRestaurante,
			'entradas' => $entradasTotal,
			'salidas' => $salidasTotal,
			'balance' => $entradasTotal - $salidasTotal,
		];
	}

    protected static function getFacturasCliente(): array
	{
		
		$dataBase = new DataBase();
		$sql = 'SELECT SUM(fcli.totaleuros) total, COUNT(fcli.pagada) cantidad, fcli.pagada tipo'
			. ' FROM facturascli as fcli'
			. ' LEFT JOIN recibospagoscli as rcli ON rcli.idfactura = fcli.idfactura'
			. ' LEFT JOIN tpvsneo as tpv ON tpv.idtpv = fcli.idtpv'
			. ' WHERE ((rcli.idrecibo IS NULL AND fcli.fecha BETWEEN ' . $dataBase->var2str(self::$f_inicio)
			. ' AND ' . $dataBase->var2str(self::$f_fin) . ')'
			. ' OR (rcli.idrecibo IS NOT NULL AND rcli.fecha BETWEEN ' . $dataBase->var2str(self::$f_inicio)
			. ' AND ' . $dataBase->var2str(self::$f_fin)
			. ' AND rcli.codpago = ' . $dataBase->var2str(self::$cod_pago_cont) . '))';

		if (self::$level != 99) {
			$sql .= ' AND LOWER(SUBSTRING_INDEX(tpv.name, " ", 1)) = ' . $dataBase->var2str(self::$nick);
		}

		$sql .= self::serieFilterSql($dataBase);

		$sql .= ' GROUP BY fcli.pagada'
			 .  ' ORDER BY fcli.pagada ASC';

		if (self::$limit > 0) {
			return $dataBase->selectLimit($sql, self::$limit);
		}

		$sql .= ';';
		return $dataBase->select($sql);
	}
	
	/*PARA USUARIO */
	protected static function getFacturasUsuarioCliente(): array
    {
		
        $dataBase = new DataBase();
        $sql = 'SELECT SUM(fcli.totaleuros) total, COUNT(fcli.pagada) cantidad, fcli.pagada tipo'
            . ' FROM facturascli as fcli'
            . ' LEFT JOIN recibospagoscli as rcli ON rcli.idfactura = fcli.idfactura'
			. ' LEFT JOIN tpvsneo as tpv ON tpv.idtpv = fcli.idtpv'
	    		. ' WHERE ((rcli.idrecibo IS NULL AND fcli.fecha BETWEEN '. $dataBase->var2str(self::$f_inicio)
			. ' AND '.$dataBase->var2str(self::$f_fin).')  OR (rcli.idrecibo IS NOT NULL AND'
			. ' rcli.fecha BETWEEN '. $dataBase->var2str(self::$f_inicio) .' AND '.$dataBase->var2str(self::$f_fin)
			. ' AND rcli.codpago = '. $dataBase->var2str(self::$cod_pago_cont).'))'
			. ' AND LOWER(SUBSTRING_INDEX(tpv.name, " ", 1)) = '. $dataBase->var2str(self::$nick)
			. self::serieFilterSql($dataBase)
			. ' GROUP BY fcli.pagada'
			. ' ORDER BY fcli.pagada ASC';

	 
        if (self::$limit > 0) {
            return $dataBase->selectLimit($sql, self::$limit);
        }

        $sql .= ';';
        return $dataBase->select($sql);
    }

	
	/**/
	protected static function getFacturasProveedor(): array
    {
		
        $dataBase = new DataBase();
        $sql = 'SELECT SUM(fprov.totaleuros) total, COUNT(fprov.pagada) cantidad,  fprov.pagada tipo'
            . ' FROM facturasprov as fprov'
            . ' LEFT JOIN recibospagosprov as rprov ON rprov.idfactura = fprov.idfactura'
            . ' WHERE (rprov.idrecibo IS NULL AND fprov.fecha BETWEEN '. $dataBase->var2str(self::$f_inicio)
			. ' AND '.$dataBase->var2str(self::$f_fin).')  OR (rprov.idrecibo IS NOT NULL AND'
			. ' rprov.fecha BETWEEN '. $dataBase->var2str(self::$f_inicio) .' AND '.$dataBase->var2str(self::$f_fin)
			. ' AND rprov.codpago = '. $dataBase->var2str(self::$cod_pago_cont).')';
		
		if (self::$level != 99) {
			$sql .= ' AND fprov.nick = ' . $dataBase->var2str(self::$nick);
		}

		$sql .= ' GROUP BY fprov.pagada'
			 .  ' ORDER BY fprov.pagada ASC';

     
        if (self::$limit > 0) {
            return $dataBase->selectLimit($sql, self::$limit);
        }

        $sql .= ';';
	
        return $dataBase->select($sql);
    }

	protected static function getFacturasClienteTarjeta(): array
    {
		
        $dataBase = new DataBase();
        $sql = 'SELECT SUM(fcli.totaleuros) total, COUNT(fcli.pagada) cantidad, fcli.pagada tipo'
            . ' FROM facturascli as fcli'
            . ' LEFT JOIN recibospagoscli as rcli ON rcli.idfactura = fcli.idfactura'
			. ' LEFT JOIN tpvsneo as tpv ON tpv.idtpv = fcli.idtpv'
			. ' WHERE (rcli.idrecibo IS NULL AND fcli.fecha BETWEEN '. $dataBase->var2str(self::$f_inicio)
			. ' AND '.$dataBase->var2str(self::$f_fin).')  OR (rcli.idrecibo IS NOT NULL AND'
			. ' rcli.fecha BETWEEN '. $dataBase->var2str(self::$f_inicio) .' AND '.$dataBase->var2str(self::$f_fin)
			. ' AND rcli.codpago = '. $dataBase->var2str(self::$cod_pago_tarjeta).')';
			
			if (self::$level != 99) {
				$sql .= ' AND LOWER(SUBSTRING_INDEX(tpv.name, " ", 1)) = ' . $dataBase->var2str(self::$nick);
			}

			$sql .= self::serieFilterSql($dataBase);

			$sql .= ' GROUP BY fcli.pagada'
			 	 .  ' ORDER BY fcli.pagada ASC';

	 
        if (self::$limit > 0) {
            return $dataBase->selectLimit($sql, self::$limit);
        }

        $sql .= ';';
        return $dataBase->select($sql);
    }

	protected static function getFacturasUsuarioClienteTarjeta(): array
    {
		
        $dataBase = new DataBase();
        $sql = 'SELECT SUM(fcli.totaleuros) total, COUNT(fcli.pagada) cantidad, fcli.pagada tipo'
            . ' FROM facturascli as fcli'
            . ' LEFT JOIN recibospagoscli as rcli ON rcli.idfactura = fcli.idfactura'
			. ' LEFT JOIN tpvsneo as tpv ON tpv.idtpv = fcli.idtpv'
	    	    . ' WHERE ((rcli.idrecibo IS NULL AND fcli.fecha BETWEEN '. $dataBase->var2str(self::$f_inicio)
			. ' AND '.$dataBase->var2str(self::$f_fin).')  OR (rcli.idrecibo IS NOT NULL AND'
			. ' rcli.fecha BETWEEN '. $dataBase->var2str(self::$f_inicio) .' AND '.$dataBase->var2str(self::$f_fin)
			. ' AND rcli.codpago = '. $dataBase->var2str(self::$cod_pago_tarjeta).'))'
			. ' AND LOWER(SUBSTRING_INDEX(tpv.name, " ", 1)) = '. $dataBase->var2str(self::$nick)
			. self::serieFilterSql($dataBase)
			. ' GROUP BY fcli.pagada'
			. ' ORDER BY fcli.pagada ASC';

	 
        if (self::$limit > 0) {
            return $dataBase->selectLimit($sql, self::$limit);
        }

        $sql .= ';';
        return $dataBase->select($sql);
    }

	protected static function getFacturasProveedorTarjeta(): array
    {
		
        $dataBase = new DataBase();
        $sql = 'SELECT SUM(fprov.totaleuros) total, COUNT(fprov.pagada) cantidad, fprov.pagada tipo'
            . ' FROM facturasprov as fprov'
            . ' LEFT JOIN recibospagosprov as rprov ON rprov.idfactura = fprov.idfactura'
            . ' WHERE (rprov.idrecibo IS NULL AND fprov.fecha BETWEEN '. $dataBase->var2str(self::$f_inicio)
			. ' AND '.$dataBase->var2str(self::$f_fin).')  OR (rprov.idrecibo IS NOT NULL AND'
			. ' rprov.fecha BETWEEN '. $dataBase->var2str(self::$f_inicio) .' AND '.$dataBase->var2str(self::$f_fin)
			. ' AND rprov.codpago = '. $dataBase->var2str(self::$cod_pago_tarjeta).')';
			
		if (self::$level != 99) {
			$sql .= ' AND fprov.nick = ' . $dataBase->var2str(self::$nick);
		}

		$sql .= ' GROUP BY fprov.pagada'
			 .  ' ORDER BY fprov.pagada ASC';

        if (self::$limit > 0) {
            return $dataBase->selectLimit($sql, self::$limit);
        }

        $sql .= ';';
	
        return $dataBase->select($sql);
    }

	protected static function getFacturasClienteTrans(): array
    {
		
        $dataBase = new DataBase();
        $sql = 'SELECT SUM(fcli.totaleuros) total, COUNT(fcli.pagada) cantidad, fcli.pagada tipo'
            . ' FROM facturascli as fcli'
            . ' LEFT JOIN recibospagoscli as rcli ON rcli.idfactura = fcli.idfactura'
			. ' LEFT JOIN tpvsneo as tpv ON tpv.idtpv = fcli.idtpv'
            . ' WHERE (rcli.idrecibo IS NULL AND fcli.fecha BETWEEN '. $dataBase->var2str(self::$f_inicio)
			. ' AND '.$dataBase->var2str(self::$f_fin).')  OR (rcli.idrecibo IS NOT NULL AND'
			. ' rcli.fecha BETWEEN '. $dataBase->var2str(self::$f_inicio) .' AND '.$dataBase->var2str(self::$f_fin)
			. ' AND rcli.codpago = '. $dataBase->var2str(self::$cod_pago_trans).')';
			
			if (self::$level != 99) {
				$sql .= ' AND LOWER(SUBSTRING_INDEX(tpv.name, " ", 1)) = ' . $dataBase->var2str(self::$nick);
			}

			$sql .= self::serieFilterSql($dataBase);

			$sql .= ' GROUP BY fcli.pagada'
			 	 .  ' ORDER BY fcli.pagada ASC';

	 
        if (self::$limit > 0) {
            return $dataBase->selectLimit($sql, self::$limit);
        }

        $sql .= ';';
        return $dataBase->select($sql);
    }
	
	protected static function getFacturasUsuarioClienteTrans(): array
    {
		
        $dataBase = new DataBase();
        $sql = 'SELECT SUM(fcli.totaleuros) total, COUNT(fcli.pagada) cantidad, fcli.pagada tipo'
            . ' FROM facturascli as fcli'
            . ' LEFT JOIN recibospagoscli as rcli ON rcli.idfactura = fcli.idfactura'
			. ' LEFT JOIN tpvsneo as tpv ON tpv.idtpv = fcli.idtpv'
			. ' WHERE ((rcli.idrecibo IS NULL AND fcli.fecha BETWEEN '. $dataBase->var2str(self::$f_inicio)
			. ' AND '.$dataBase->var2str(self::$f_fin).')  OR (rcli.idrecibo IS NOT NULL AND'
			. ' rcli.fecha BETWEEN '. $dataBase->var2str(self::$f_inicio) .' AND '.$dataBase->var2str(self::$f_fin)
			. ' AND rcli.codpago = '. $dataBase->var2str(self::$cod_pago_trans).'))'
			. ' AND LOWER(SUBSTRING_INDEX(tpv.name, " ", 1)) = '. $dataBase->var2str(self::$nick)
			. self::serieFilterSql($dataBase)
			. ' GROUP BY fcli.pagada'
			. ' ORDER BY fcli.pagada ASC';

	 
        if (self::$limit > 0) {
            return $dataBase->selectLimit($sql, self::$limit);
        }

        $sql .= ';';
        return $dataBase->select($sql);
    }
	
	protected static function getFacturasProveedorTrans(): array
    {
		
        $dataBase = new DataBase();
        $sql = 'SELECT SUM(fprov.totaleuros) total, COUNT(fprov.pagada) cantidad, fprov.pagada tipo'
            . ' FROM facturasprov as fprov'
            . ' LEFT JOIN recibospagosprov as rprov ON rprov.idfactura = fprov.idfactura'
            . ' WHERE (rprov.idrecibo IS NULL AND fprov.fecha BETWEEN '. $dataBase->var2str(self::$f_inicio)
			. ' AND '.$dataBase->var2str(self::$f_fin).')  OR (rprov.idrecibo IS NOT NULL AND'
			. ' rprov.fecha BETWEEN '. $dataBase->var2str(self::$f_inicio) .' AND '.$dataBase->var2str(self::$f_fin)
			. ' AND rprov.codpago = '. $dataBase->var2str(self::$cod_pago_trans).')';
			
		if (self::$level != 99) {
			$sql .= ' AND fprov.nick = ' . $dataBase->var2str(self::$nick);
		}

		$sql .= ' GROUP BY fprov.pagada'
			 .  ' ORDER BY fprov.pagada ASC';
     
        if (self::$limit > 0) {
            return $dataBase->selectLimit($sql, self::$limit);
        }

        $sql .= ';';
	
        return $dataBase->select($sql);
    }

	protected static function getReportList(): string
	{
		$movimientosCaja = self::getCashMovementsSummary();
		$serieTexto = empty(self::$codserie) ? 'Todas' : self::$codserie;

		$html = '<div class="form-row d-flex justify-content-center mt-5" id="rFechas">'
				.'<div class="col-3">'
				.'<span><strong>Inicio: </strong>'. date('d/m/Y', strtotime(self::$f_inicio)).'</span>'
				.'</div>'
				.'<div class="col-3">'
				.'<span><strong>Fin: </strong>'. date('d/m/Y', strtotime(self::$f_fin)).'</span>'
				.'</div>'
				.'<div class="col-2">'
				.'<span><strong>Serie: </strong>'. $serieTexto .'</span>'
				.'</div>'
				.'<div class="col-4">'
				.'<span><strong>Generado el: </strong>'. date('d/m/Y h:i:s a').'</span>'
				.'</div>'
				.'</div>';
		
		$html .= '<div class="form-row d-flex justify-content-center mt-5">'
				.'<div class="col-sm-4">'
				.'<div class="card border-info shadow mb-3">'
				.'<div class="card-header bg-info text-white text-center">'
				.'<strong>VENTAS - CONTADO</strong>'
                .'</div>'
                .'<div class="table-responsive">'
				.'<table class="table table-striped mb-0">'
				.'<thead>'
				.'<tr>'
				.'<th style="text-transform: uppercase;"></th>'	
				.'<th class="money" style="text-transform: uppercase;">'. self::$nick . '</th>'
				.'<th class="money" style="text-transform: uppercase;">Total</th>'
				.'</tr>'
				.'</thead>'
				.'<tbody>';
		$flagVacio = true;
		$flagPendientes = 0;
		$flagPagados = 0;
		$resumenPendiente = 0;
		$resumenPagado = 0;
		
		$resumenVentasPd = 0;
		$resumenVentasPg = 0;
		$resumenCantVentasPd = 0;
		$resumenCantVentasPg = 0;
		
		$resumenComprasPd = 0;
		$resumenCantComprasPd = 0;
		$resumenComprasPg = 0;
		$resumenCantComprasPg = 0;
		
		$contado = self::getFacturasCliente();
		foreach($contado as $itemcli) {
			$flagVacio = false;
			if ($itemcli['tipo'] != '1') {
				$flagPendientes++;	
				$resumenPendiente += $itemcli['total'];
				$resumenVentasPd += $itemcli['total'];
				$resumenCantVentasPd += $itemcli['cantidad'];
			} elseif ($itemcli['tipo'] == '1') {
				$flagPagados++;	
				$resumenPagado += $itemcli['total'];
				$resumenVentasPg += $itemcli['total'];
				$resumenCantVentasPg += $itemcli['cantidad'];
			}
		}
		
		$contadoUser = self::getFacturasUsuarioCliente();
		$resumenVentasUPd = 0;
		$resumenVentasUPg = 0;
		
		foreach($contadoUser as $itemcli) {
			if ($itemcli['tipo'] != '1') {
				$resumenVentasUPd += $itemcli['total'];
			} elseif ($itemcli['tipo'] == '1') {
				$resumenVentasUPg += $itemcli['total'];
			}
		}
		
		
		if ($flagVacio) {
			$html .= '<tr>'
				  .'<td>PENDIENTES (0)</td>'
				  .'<td class="money">'.number_format($resumenVentasUPd,2,'.',',') .' €</td>'
				  .'<td class="money">'.number_format(0,2,'.',',') .' €</td>'
				  .'</tr>' 
				  .'<tr>'
				  .'<td>PAGADAS (0)</td>'
				  .'<td class="money">'.number_format($resumenVentasUPg,2,'.',',') .' €</td>'
				  .'<td class="money">'.number_format(0,2,'.',',') .' €</td>'
				  .'</tr>';
		} elseif ($flagPagados == 0 || $flagPendientes == 0) {
			if ($flagPendientes == 0) {
				$html .=  '<tr>'
				  .'<td>PENDIENTES (0)</td>'
				  .'<td class="money">'.number_format($resumenVentasUPd,2,'.',',') .' €</td>'
				  .'<td class="money">'.number_format(0,2,'.',',') .' €</td>'
				  .'</tr>';	
				
				foreach($contado as $itemcli) {
					$html .= '<tr>'
					  .'<td>'.($itemcli['tipo'] == '1'?'PAGADAS': 'PENDIENTES').' ('.$itemcli['cantidad'].')</td>'
					  .'<td class="money">'.number_format(($itemcli['tipo'] == '1'?$resumenVentasUPg:$resumenVentasUPd),2,'.',',') .' €</td>'
					  .'<td class="money">'.number_format($itemcli['total'],2,'.',',') .' €</td>'
					  .'</tr>';
				}
			}
			
			if ($flagPagados == 0) {
				foreach($contado as $itemcli) {
					$html .= '<tr>'
					  .'<td>'.($itemcli['tipo'] == '1'?'PAGADAS': 'PENDIENTES').' ('.$itemcli['cantidad'].')</td>'
					  .'<td class="money">'.number_format(($itemcli['tipo'] == '1'?$resumenVentasUPg:$resumenVentasUPd),2,'.',',') .' €</td>'
					  .'<td class="money">'.number_format($itemcli['total'],2,'.',',') .' €</td>'
					  .'</tr>';
				}
				
				$html .= '<tr>'
				  .'<td>PAGADAS (0)</td>'
				  .'<td class="money">'.number_format($resumenVentasUPg,2,'.',',') .' €</td>'
				  .'<td class="money">'.number_format(0,2,'.',',') .' €</td>'
				  .'</tr>';
			} 
		} else {
			foreach($contado as $itemcli) {
				$html .= '<tr>'
					  .'<td>'.($itemcli['tipo'] == '1'?'PAGADAS': 'PENDIENTES').' ('.$itemcli['cantidad'].')</td>'
					  .'<td class="money">'.number_format(($itemcli['tipo'] == '1'?$resumenVentasUPg:$resumenVentasUPd),2,'.',',') .' €</td>'
					  .'<td class="money">'.number_format($itemcli['total'],2,'.',',') .' €</td>'
					  .'</tr>';
			}
		}
		
		$html .= '</tbody>'
			  .'</table>'
			  .'</div>'
			  .'</div>'
			  .'</div>';
		
		if (!self::$s_ventas) {
			$html .= '<div class="col-sm-4">'
					.'<div class="card border-danger shadow mb-3">'
					.'<div class="card-header bg-danger text-white text-center">'
					.'<strong>COMPRAS - CONTADO</strong>'
					.'</div>'
					.'<div class="table-responsive">'
					.'<table class="table table-striped mb-0">'
					.'<tbody>';
			$flagVacio = true;
			$flagPendientes = 0;
			$flagPagados = 0;
			$contadoCompras = self::getFacturasProveedor();
			foreach($contadoCompras as $itemcli) {
				$flagVacio = false;

				if ($itemcli['tipo'] != '1') {
					$flagPendientes++;	
					$resumenPendiente -= $itemcli['total'];
					$resumenComprasPd += $itemcli['total'];
					$resumenCantComprasPd += $itemcli['cantidad'];
				} elseif ($itemcli['tipo'] == '1') {
					$flagPagados++;	
					$resumenPagado -= $itemcli['total'];
					$resumenComprasPg += $itemcli['total'];
					$resumenCantComprasPg += $itemcli['cantidad'];
				}
			}

			if ($flagVacio) {
				$html .= '<tr>'
					  .'<td>PENDIENTES (0)</td>'
					  .'<td class="money">'.number_format(0,2,'.',',') .' €</td>'
					  .'</tr>' 
					  .'<tr>'
					  .'<td>PAGADAS (0)</td>'
					  .'<td class="money">'.number_format(0,2,'.',',') .' €</td>'
					  .'</tr>';
			} elseif ($flagPagados == 0 || $flagPendientes == 0) {
				if ($flagPendientes == 0) {
					$html .=  '<tr>'
					  .'<td>PENDIENTES (0)</td>'
					  .'<td class="money">'.number_format(0,2,'.',',') .'</td>'
					  .'</tr>';
					foreach($contadoCompras as $itemcli) {
						$html .= '<tr>'
							.'<td>'.($itemcli['tipo'] == '1'?'PAGADAS': 'PENDIENTES').' ('.$itemcli['cantidad'].')</td>'
							.'<td class="money">'.number_format($itemcli['total'],2,'.',',') .' €</td>'
							.'</tr>';
					}
				}
				if ($flagPagados == 0) {
					foreach($contadoCompras as $itemcli) {
						$html .= '<tr>'
							.'<td>'.($itemcli['tipo'] == '1'?'PAGADAS': 'PENDIENTES').' ('.$itemcli['cantidad'].')</td>'
							.'<td>'.number_format($itemcli['cantidad'],2,'.',',').'</td>'
							.'<td class="money">'.number_format($itemcli['total'],2,'.',',') .' €</td>'
							.'</tr>';
					}
					$html .= '<tr>'
					  .'<td>PAGADAS</td>'
					  .'<td>'.number_format(0,2,'.',',') .'</td>'
					  .'<td class="money">'.number_format(0,2,'.',',') .' €</td>'
					  .'</tr>';
				} 
			} else {
				foreach($contadoCompras as $itemcli) {
					$html .= '<tr>'
						.'<td>'.($itemcli['tipo'] == '1'?'PAGADAS': 'PENDIENTES').' ('.$itemcli['cantidad'].')</td>'
						.'<td class="money">'.number_format($itemcli['total'],2,'.',',') .' €</td>'
						.'</tr>';
				}
			}
			$html .= '</tbody>'
				  .'</table>'
				  .'</div>'
				  .'</div>'
				.'</div>';
		
			$html .= '<div class="col-sm-4">'
					.'<div class="card border-success shadow mb-3">'
					.'<div class="card-header bg-success text-white text-center">'
					.'<strong>RESUMEN - CONTADO</strong>'
					.'</div>'
					.'<div class="table-responsive">'
					.'<table class="table table-striped mb-0">'
					.'<tbody>'
					.'<tr>'
					  .'<td>PENDIENTES</td>'
					  .'<td class="money">'.number_format($resumenPendiente,2,'.',',') .' €</td>'
					.'</tr>'
					.'<tr>'
					  .'<td>PAGADAS</td>'
					  .'<td class="money">'.number_format($resumenPagado,2,'.',',') .' €</td>'
					.'</tr>'
					.'</tbody>'
				  .'</table>'
				  .'</div>'
				  .'</div>'
				.'</div>'
			.'</div>';
		}
		
		#PARA PAGOS CON TARJETA
		if (self::$s_ventas) {
			$html .= '</div>';
		}
		
		$html .= '<div class="form-row d-flex justify-content-center mt-5">'
				.'<div class="col-sm-4">'
				.'<div class="card border-info shadow mb-3">'
				.'<div class="card-header bg-info text-white text-center">'
				.'<strong>VENTAS - TARJETA</strong>'
                .'</div>'
                .'<div class="table-responsive">'
				.'<table class="table table-striped mb-0">'
				.'<thead>'
				.'<tr>'
				.'<th style="text-transform: uppercase;"></th>'	
				.'<th class="money" style="text-transform: uppercase;">'. self::$nick . '</th>'
				.'<th class="money" style="text-transform: uppercase;">Total</th>'
				.'</tr>'
				.'</thead>'
				.'<tbody>';
		$flagVacio = true;
		$flagPendientes = 0;
		$flagPagados = 0;
		$resumenPendiente = 0;
		$resumenPagado = 0;
		
		$tarjeta = self::getFacturasClienteTarjeta();
		foreach($tarjeta as $itemcli) {
			$flagVacio = false;
			if ($itemcli['tipo'] != '1') {
				$flagPendientes++;	
				$resumenPendiente += $itemcli['total'];
				$resumenVentasPd += $itemcli['total'];
				$resumenCantVentasPd += $itemcli['cantidad'];
			} elseif ($itemcli['tipo'] == '1') {
				$flagPagados++;	
				$resumenPagado += $itemcli['total'];
				$resumenVentasPg += $itemcli['total'];
				$resumenCantVentasPg += $itemcli['cantidad'];
			}
		}
		
		$tarjetaU = self::getFacturasUsuarioClienteTarjeta();
		$resumenVentasTUPd = 0;
		$resumenVentasTUPg = 0;
		
		foreach($tarjetaU as $itemcli) {
			if ($itemcli['tipo'] != '1') {
				$resumenVentasTUPd += $itemcli['total'];
			} elseif ($itemcli['tipo'] == '1') {
				$resumenVentasTUPg += $itemcli['total'];
			}
		}

		
		if ($flagVacio) {
			$html .= '<tr>'
				  .'<td>PENDIENTES (0)</td>'
				  .'<td class="money">'.number_format($resumenVentasTUPd,2,'.',',') .' €</td>'
				  .'<td class="money">'.number_format(0,2,'.',',') .' €</td>'
				  .'</tr>' 
				  .'<tr>'
				  .'<td>PAGADAS (0)</td>'
				  .'<td class="money">'.number_format($resumenVentasTUPg,2,'.',',') .' €</td>'
				  .'<td class="money">'.number_format(0,2,'.',',') .' €</td>'
				  .'</tr>';
		} elseif ($flagPagados == 0 || $flagPendientes == 0) {
			if ($flagPendientes == 0) {
				$html .=  '<tr>'
				  .'<td>PENDIENTES (0)</td>'
				  .'<td class="money">'.number_format($resumenVentasTUPd,2,'.',',') .' €</td>'
				  .'<td class="money">'.number_format(0,2,'.',',') .' €</td>'
				  .'</tr>';	
				
				foreach($tarjeta as $itemcli) {
					$html .= '<tr>'
					.'<td>'.($itemcli['tipo'] == '1'?'PAGADAS': 'PENDIENTES').' ('.$itemcli['cantidad'].')</td>'
					.'<td class="money">'.number_format(($itemcli['tipo'] == '1'?$resumenVentasTUPg:$resumenVentasTUPd),2,'.',',') .' €</td>'
				 	.'<td class="money">'.number_format($itemcli['total'],2,'.',',') .' €</td>'
					.'</tr>';
				}
			}
			
			if ($flagPagados == 0) {
				foreach($tarjeta as $itemcli) {
					$html .= '<tr>'
					.'<td>'.($itemcli['tipo'] == '1'?'PAGADAS': 'PENDIENTES').' ('.$itemcli['cantidad'].')</td>'
					.'<td class="money">'.number_format(($itemcli['tipo'] == '1'?$resumenVentasTUPg:$resumenVentasTUPd),2,'.',',') .' €</td>'
					.'<td class="money">'.number_format($itemcli['total'],2,'.',',') .' €</td>'
					.'</tr>';
				}
				
				$html .= '<tr>'
				  .'<td>PAGADAS (0)</td>'
				  .'<td class="money">'.number_format($resumenVentasTUPg,2,'.',',') .' €</td>'
				  .'<td class="money">'.number_format(0,2,'.',',') .'</td>'
				  .'</tr>';
			} 
		} else {
			foreach($tarjeta as $itemcli) {
				$html .= '<tr>'
				.'<td>'.($itemcli['tipo'] == '1'?'PAGADAS': 'PENDIENTES').' ('.$itemcli['cantidad'].')</td>'
				.'<td class="money">'.number_format(($itemcli['tipo'] == '1'?$resumenVentasTUPg:$resumenVentasTUPd),2,'.',',') .' €</td>'
			 	.'<td class="money">'.number_format($itemcli['total'],2,'.',',') .' €</td>'
				.'</tr>';
			}
		}
		
		$html .= '</tbody>'
			  .'</table>'
			  .'</div>'
			  .'</div>'
			  .'</div>';
		
		if (!self::$s_ventas) {
			$html .= '<div class="col-sm-4">'
					.'<div class="card border-danger shadow mb-3">'
					.'<div class="card-header bg-danger text-white text-center">'
					.'<strong>COMPRAS - TARJETA</strong>'
					.'</div>'
					.'<div class="table-responsive">'
					.'<table class="table table-striped mb-0">'
					.'<tbody>';
			$flagVacio = true;
			$flagPendientes = 0;
			$flagPagados = 0;
			$comprasTarjeta = self::getFacturasProveedorTarjeta(); 
			foreach($comprasTarjeta as $itemcli) {
				$flagVacio = false;

				if ($itemcli['tipo'] != '1') {
					$flagPendientes++;	
					$resumenPendiente -= $itemcli['total'];
					$resumenComprasPd += $itemcli['total'];
					$resumenCantComprasPd += $itemcli['cantidad'];
			
				} elseif ($itemcli['tipo'] == '1') {
					$flagPagados++;	
					$resumenPagado -= $itemcli['total'];
					$resumenComprasPg += $itemcli['total'];
					$resumenCantComprasPg += $itemcli['cantidad'];
				}
			}

			if ($flagVacio) {
				$html .= '<tr>'
					  .'<td>PENDIENTES (0)</td>'
					  .'<td class="money">'.number_format(0,2,'.',',') .' €</td>'
					  .'</tr>' 
					  .'<tr>'
					  .'<td>PAGADAS (0)</td>'
					  .'<td class="money">'.number_format(0,2,'.',',') .' €</td>'
					  .'</tr>';
			} elseif ($flagPagados == 0 || $flagPendientes == 0) {
				if ($flagPendientes == 0) {
					$html .=  '<tr>'
					  .'<td>PENDIENTES (0)</td>'
					  .'<td class="money">'.number_format(0,2,'.',',') .' €</td>'
					  .'</tr>';
					foreach($comprasTarjeta as $itemcli) {
						$html .= '<tr>'
							.'<td>'.($itemcli['tipo'] == '1'?'PAGADAS': 'PENDIENTES').' ('.$itemcli['cantidad'].')</td>'
							.'<td class="money">'.number_format($itemcli['total'],2,'.',',') .' €</td>'
							.'</tr>';
					}
				}
				if ($flagPagados == 0) {
					foreach($comprasTarjeta as $itemcli) {
						$html .= '<tr>'
							.'<td>'.($itemcli['tipo'] == '1'?'PAGADAS': 'PENDIENTES').' ('.$itemcli['cantidad'].')</td>'
							.'<td class="money">'.number_format($itemcli['total'],2,'.',',') .' €</td>'
							.'</tr>';
					}
					$html .= '<tr>'
					  .'<td>PAGADAS (0)</td>'
					  .'<td class="money">'.number_format(0,2,'.',',') .' €</td>'
					  .'</tr>';
				} 
			} else {
				foreach($comprasTarjeta as $itemcli) {
					$html .= '<tr>'
						.'<td>'.($itemcli['tipo'] == '1'?'PAGADAS': 'PENDIENTES').' ('.$itemcli['cantidad'].')</td>'
						.'<td class="money">'.number_format($itemcli['total'],2,'.',',') .' €</td>'
						.'</tr>';
				}	
			}
			$html .= '</tbody>'
				  .'</table>'
				  .'</div>'
				  .'</div>'
				.'</div>';
		
		
			$html .= '<div class="col-sm-4">'
					.'<div class="card border-success shadow mb-3">'
					.'<div class="card-header bg-success text-white text-center">'
					.'<strong>RESUMEN - TARJETA</strong>'
					.'</div>'
					.'<div class="table-responsive">'
					.'<table class="table table-striped mb-0">'
					.'<tbody>'
					.'<tr>'
					  .'<td>PENDIENTES</td>'
					  .'<td class="money">'.number_format($resumenPendiente,2,'.',',') .' €</td>'
					.'</tr>'
					.'<tr>'
					  .'<td>PAGADAS</td>'
					  .'<td class="money">'.number_format($resumenPagado,2,'.',',') .' €</td>'
					.'</tr>'
					.'</tbody>'
				  .'</table>'
				  .'</div>'
				  .'</div>'
				.'</div>'
			.'</div>';
		}
		
		#PARA PAGOS POR TRANSFERENCIAS
		if (self::$s_ventas) {
			$html .= '</div>';
		}
		
		$html .= '<div class="form-row d-flex justify-content-center mt-5">'
				.'<div class="col-sm-4">'
				.'<div class="card border-info shadow mb-3">'
				.'<div class="card-header bg-info text-white text-center">'
				.'<strong>VENTAS - TRANSFERENCIAS</strong>'
                .'</div>'
                .'<div class="table-responsive">'
				.'<table class="table table-striped mb-0">'
				.'<thead>'
				.'<tr>'
				.'<th style="text-transform: uppercase;"></th>'	
				.'<th class="money" style="text-transform: uppercase;">'. self::$nick . '</th>'
				.'<th class="money" style="text-transform: uppercase;">Total</th>'
				.'</tr>'
				.'</thead>'
				.'<tbody>';
		$flagVacio = true;
		$flagPendientes = 0;
		$flagPagados = 0;
		$resumenPendiente = 0;
		$resumenPagado = 0;
		
		$transf = self::getFacturasClienteTrans();
		foreach($transf as $itemcli) {
			$flagVacio = false;
			if ($itemcli['tipo'] != '1') {
				$flagPendientes++;	
				$resumenPendiente += $itemcli['total'];
				$resumenVentasPd += $itemcli['total'];
				$resumenCantVentasPd += $itemcli['cantidad'];
			} elseif ($itemcli['tipo'] == '1') {
				$flagPagados++;	
				$resumenPagado += $itemcli['total'];
				$resumenVentasPg += $itemcli['total'];
				$resumenCantVentasPg += $itemcli['cantidad'];
			}
		}
		
		$transfU = self::getFacturasUsuarioClienteTrans();
		$resumenVentasTransUPd = 0;
		$resumenVentasTransUPg = 0;
		
		foreach($transfU as $itemcli) {
			if ($itemcli['tipo'] != '1') {
				$resumenVentasTransUPd += $itemcli['total'];
			} elseif ($itemcli['tipo'] == '1') {
				$resumenVentasTransUPg += $itemcli['total'];
			}
		}
		
		
		
		if ($flagVacio) {
			$html .= '<tr>'
				  .'<td>PENDIENTES (0)</td>'
				  .'<td class="money">'.number_format($resumenVentasTransUPd,2,'.',',') .' €</td>'
				  .'<td class="money">'.number_format(0,2,'.',',') .' €</td>'
				  .'</tr>' 
				  .'<tr>'
				  .'<td>PAGADAS (0)</td>'
				  .'<td class="money">'.number_format($resumenVentasTransUPg,2,'.',',') .' €</td>'
		  		  .'<td class="money">'.number_format(0,2,'.',',') .' €</td>'
				  .'</tr>';
		} elseif ($flagPagados == 0 || $flagPendientes == 0) {
			if ($flagPendientes == 0) {
				$html .=  '<tr>'
				  .'<td>PENDIENTES (0)</td>'
				  .'<td class="money">'.number_format($resumenVentasTransUPd,2,'.',',') .' €</td>'
				  .'<td class="money">'.number_format(0,2,'.',',') .' €</td>'
				  .'</tr>';	
				
				foreach($transf as $itemcli) {
					$html .= '<tr>'
					.'<td>'.($itemcli['tipo'] == '1'?'PAGADAS': 'PENDIENTES').' ('.$itemcli['cantidad'].')</td>'
					.'<td class="money">'.number_format(($itemcli['tipo'] == '1'?$resumenVentasTransUPg:$resumenVentasTransUPd),2,'.',',') 
						.' €</td>'
					.'<td class="money">'.number_format($itemcli['total'],2,'.',',') .' €</td>'
					.'</tr>';
				}
			}
			
			if ($flagPagados == 0) {
				foreach($transf as $itemcli) {
					$html .= '<tr>'
					  .'<td>'.($itemcli['tipo'] == '1'?'PAGADAS': 'PENDIENTES').' ('.$itemcli['cantidad'].')</td>'
					  .'<td class="money">'.number_format(($itemcli['tipo'] == '1'?$resumenVentasTransUPg:$resumenVentasTransUPd),2,'.',',') 
						.' €</td>'
				      .'<td class="money">'.number_format($itemcli['total'],2,'.',',') .' €</td>'
					  .'</tr>';
				}
				
				$html .= '<tr>'
				  .'<td>PAGADAS (0)</td>'
				  .'<td class="money">'.number_format($resumenVentasTransUPg,2,'.',',') .' €</td>'
				  .'<td class="money">'.number_format(0,2,'.',',') .' €</td>'
				  .'</tr>';
			} 
		} else {
			foreach($transf as $itemcli) {
				$html .= '<tr>'
					  .'<td>'.($itemcli['tipo'] == '1'?'PAGADAS': 'PENDIENTES').' ('.$itemcli['cantidad'].')</td>'
					  .'<td class="money">'.number_format(($itemcli['tipo'] == '1'?$resumenVentasTransUPg:$resumenVentasTransUPd),2,'.',',') 
						.' €</td>'
					  .'<td class="money">'.number_format($itemcli['total'],2,'.',',') .' €</td>'
					  .'</tr>';
			}
		}
		
		$html .= '</tbody>'
			  .'</table>'
			  .'</div>'
			  .'</div>'
			  .'</div>';
		
		if (!self::$s_ventas) {
			$html .= '<div class="col-sm-4">'
					.'<div class="card border-danger shadow mb-3">'
					.'<div class="card-header bg-danger text-white text-center">'
					.'<strong>COMPRAS - TRANSFERENCIAS</strong>'
					.'</div>'
					.'<div class="table-responsive">'
					.'<table class="table table-striped mb-0">'
					.'<tbody>';
			$flagVacio = true;
			$flagPendientes = 0;
			$flagPagados = 0;
			$comprasTransf = self::getFacturasProveedorTrans(); 
			foreach($comprasTransf as $itemcli) {
				$flagVacio = false;

				if ($itemcli['tipo'] != '1') {
					$flagPendientes++;	
					$resumenPendiente -= $itemcli['total'];
					$resumenComprasPd += $itemcli['total'];
					$resumenCantComprasPd += $itemcli['cantidad'];
			
				} elseif ($itemcli['tipo'] == '1') {
					$flagPagados++;	
					$resumenPagado -= $itemcli['total'];
					$resumenComprasPg += $itemcli['total'];
					$resumenCantComprasPg += $itemcli['cantidad'];
				}
			}

			if ($flagVacio) {
				$html .= '<tr>'
					  .'<td>PENDIENTES (0)</td>'
					  .'<td class="money">'.number_format(0,2,'.',',') .' €</td>'
					  .'</tr>' 
					  .'<tr>'
					  .'<td>PAGADAS (0)</td>'
					  .'<td class="money">'.number_format(0,2,'.',',') .' €</td>'
					  .'</tr>';
			} elseif ($flagPagados == 0 || $flagPendientes == 0) {
				if ($flagPendientes == 0) {
					$html .=  '<tr>'
					  .'<td>PENDIENTES (0)</td>'
					  .'<td class="money">'.number_format(0,2,'.',',') .' €</td>'
					  .'</tr>';
					foreach($comprasTransf as $itemcli) {
						$html .= '<tr>'
							.'<td>'.($itemcli['tipo'] == '1'?'PAGADAS': 'PENDIENTES').' ('.$itemcli['cantidad'].')</td>'
							.'<td class="money">'.number_format($itemcli['total'],2,'.',',') .' €</td>'
							.'</tr>';
					}
				}
				if ($flagPagados == 0) {
					foreach($comprasTransf as $itemcli) {
						$html .= '<tr>'
							.'<td>'.($itemcli['tipo'] == '1'?'PAGADAS': 'PENDIENTES').' ('.$itemcli['cantidad'].')</td>'
							.'<td class="money">'.number_format($itemcli['total'],2,'.',',') .' €</td>'
							.'</tr>';
					}
					$html .= '<tr>'
					  .'<td>PAGADAS (0)</td>'
					  .'<td class="money">'.number_format(0,2,'.',',') .' €</td>'
					  .'</tr>';
				} 
			} else {
				foreach($comprasTransf as $itemcli) {
					$html .= '<tr>'
						.'<td>'.($itemcli['tipo'] == '1'?'PAGADAS': 'PENDIENTES').' ('.$itemcli['cantidad'].')</td>'
						.'<td>'.number_format($itemcli['cantidad'],2,'.',',') .'</td>'
						.'<td class="money">'.number_format($itemcli['total'],2,'.',',') .' €</td>'
						.'</tr>';
				}	
			}
			$html .= '</tbody>'
				  .'</table>'
				  .'</div>'
				  .'</div>'
				.'</div>';
		
			$html .= '<div class="col-sm-4">'
					.'<div class="card border-success shadow mb-3">'
					.'<div class="card-header bg-success text-white text-center">'
					.'<strong>RESUMEN - TRANSFERENCIAS</strong>'
					.'</div>'
					.'<div class="table-responsive">'
					.'<table class="table table-striped mb-0">'
					.'<tbody>'
					.'<tr>'
					  .'<td>PENDIENTES</td>'
					  .'<td class="money">'.number_format($resumenPendiente,2,'.',',') .' €</td>'
					.'</tr>'
					.'<tr>'
					  .'<td>PAGADAS</td>'
					  .'<td class="money">'.number_format($resumenPagado,2,'.',',') .' €</td>'
					.'</tr>'
					.'</tbody>'
				  .'</table>'
				  .'</div>'
				  .'</div>'
				.'</div>'
			.'</div>';		
		}
		
		#PARA RESUMEN
		if (self::$s_ventas) {
			$html .= '</div>';
		}
		
		$html .= '<div class="form-row d-flex justify-content-center mt-5">'
				.'<div class="col-sm-4">'
				.'<div class="card border-warning shadow mb-3">'
				.'<div class="card-header bg-warning text-white text-center">'
				.'<strong>VENTAS - RESUMEN</strong>'
                .'</div>'
                .'<div class="table-responsive">'
				.'<table class="table table-striped mb-0">'
				.'<thead>'
				.'<tr>'
				.'<th style="text-transform: uppercase;"></th>'	
				.'<th class="money" style="text-transform: uppercase;">'. self::$nick . '</th>'
				.'<th class="money" style="text-transform: uppercase;">Total</th>'
				.'</tr>'
				.'</thead>'
				.'<tbody>'
				.'<tr>'
					.'<td>PENDIENTES ('.$resumenCantVentasPd.')</td>'
					.'<td class="money">'.number_format($resumenVentasUPd+$resumenVentasTUPd+$resumenVentasTransUPd,2,'.',',') .' €</td>'
					.'<td class="money">'.number_format($resumenVentasPd,2,'.',',') .' €</td>'
				.'</tr>'
				.'<tr>'
					.'<td>PAGADAS ('.$resumenCantVentasPg.')</td>'
					.'<td class="money">'.number_format($resumenVentasUPg+$resumenVentasTUPg+$resumenVentasTransUPg,2,'.',',') .' €</td>'
					.'<td class="money">'.number_format($resumenVentasPg,2,'.',',') .' €</td>'
				.'</tr>'
				.'</tbody>'
				.'</table>'
				.'</div>'
				.'</div>'
				.'</div>';
		if (!self::$s_ventas) {
				$html .= '<div class="col-sm-4">'
				.'<div class="card border-warning shadow mb-3">'
				.'<div class="card-header bg-warning text-white text-center">'
				.'<strong>COMPRAS - RESUMEN</strong>'
                .'</div>'
                .'<div class="table-responsive">'
				.'<table class="table table-striped mb-0">'
				.'<tbody>'
				.'<tr>'
					.'<td>PENDIENTES ('.$resumenCantComprasPd.')</td>'
					.'<td class="money">'.number_format($resumenComprasPd,2,'.',',') .' €</td>'
				.'</tr>'
				.'<tr>'
					.'<td>PAGADAS ('.$resumenCantComprasPg.')</td>'
					.'<td class="money">'.number_format($resumenComprasPg,2,'.',',') .' €</td>'
				.'</tr>'
				.'</tbody>'
				.'</table>'
				.'</div>'
				.'</div>';
		}
		
		#PARA RESUMEN
		if (self::$s_ventas) {
			$html .= '</div>';
		}

		$html .= '<div class="form-row d-flex justify-content-center mt-4">'
				.'<div class="col-sm-6">'
				.'<div class="card border-primary shadow mb-3">'
				.'<div class="card-header bg-primary text-white text-center">'
				.'<strong>MOVIMIENTOS DE CAJA</strong>'
				.'</div>'
				.'<div class="table-responsive">'
				.'<table class="table table-striped mb-0">'
				.'<tbody>'
				.'<tr>'
				.'<td>ENTRADAS TPVNEO</td>'
				.'<td class="money">'.number_format($movimientosCaja['entradas_tpvneo'],2,'.',',') .' €</td>'
				.'</tr>'
				.'<tr>'
				.'<td>SALIDAS TPVNEO</td>'
				.'<td class="money">'.number_format($movimientosCaja['salidas_tpvneo'],2,'.',',') .' €</td>'
				.'</tr>'
				.'<tr>'
				.'<td>ENTRADAS RESTAURANTE</td>'
				.'<td class="money">'.number_format($movimientosCaja['entradas_restaurante'],2,'.',',') .' €</td>'
				.'</tr>'
				.'<tr>'
				.'<td>SALIDAS RESTAURANTE</td>'
				.'<td class="money">'.number_format($movimientosCaja['salidas_restaurante'],2,'.',',') .' €</td>'
				.'</tr>'
				.'<tr>'
				.'<td><strong>ENTRADAS TOTALES</strong></td>'
				.'<td class="money"><strong>'.number_format($movimientosCaja['entradas'],2,'.',',') .' €</strong></td>'
				.'</tr>'
				.'<tr>'
				.'<td><strong>SALIDAS TOTALES</strong></td>'
				.'<td class="money"><strong>'.number_format($movimientosCaja['salidas'],2,'.',',') .' €</strong></td>'
				.'</tr>'
				.'<tr>'
				.'<td><strong>BALANCE MOVIMIENTOS</strong></td>'
				.'<td class="money"><strong>'.number_format($movimientosCaja['balance'],2,'.',',') .' €</strong></td>'
				.'</tr>'
				.'</tbody>'
				.'</table>'
				.'</div>'
				.'</div>'
				.'</div>'
				.'</div>';
		
		return $html;
	}	
}