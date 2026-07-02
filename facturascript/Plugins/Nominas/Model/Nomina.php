<?php

namespace FacturaScripts\Plugins\Nominas\Model;
use FacturaScripts\Core\Model\Base\ModelClass;
use FacturaScripts\Core\Model\Base\ModelTrait;

class Nomina extends ModelClass
{
	use ModelTrait;
	
	public $idnomina;	
	public $codempleado;
	public $nombretrabajador;
	public $nif;
	public $direccion;
	public $localidad;
	public $cif;
	public $grupoprofesional;
	public $naf;
	public $cccss;
	public $grupocotizacion;
	public $inicioliquidacion;
	public $finliquidacion;
	public $totaldias;
	public $salariobase;
	public $complementossalariales;
	public $extrasfuerzamayor;
	public $extrasresto;
	public $horascomplementarias;
	public $gratificacionesextraordinarias;
	public $salarioespecie;
	public $indemnizaciones;
	public $seguridadsocial;
	public $despidos;
	public $otraspercepciones;
	public $totaldevengado;
	public $porc_contingencias;
	public $monto_contingencias;
	public $porc_desempleo;
	public $monto_desempleo;
	public $porc_profesional;
	public $monto_profesional;
	public $porc_extras_fuerza_mayor;
	public $monto_extras_fuerza_mayor;
	public $porc_extras_resto;
	public $monto_extras_resto;
	public $total_aportaciones;
	public $porc_impuesto_renta;
	public $monto_impuesto_renta;
	public $monto_anticipos;
	public $monto_productos_especies;
	public $monto_deducciones;
	public $totaldeducir;
	public $total_liquido_percibir;
	public $remuneracion_mensual_cotizacion;
	public $prorratas_pagas_extras;
	public $total_remuneracion;
	public $base_cotizacion_horas_extras_fuerza_mayor;
	public $base_cotizacion_horas_extras_resto;
	public $base_retencion_irpf;
	public $base;
	public $porc_base;
	public $monto_base;
	public $porc_at_ep;
	public $monto_at_ep;
	public $porc_desempleo_c;
	public $monto_desempleo_c;
	public $porc_formacion_empresarial;
	public $bccp;
	public $monto_formacion_empresarial;
	public $porc_base_cot_horas_extras_fuerza_mayor;
	public $monto_base_cot_horas_extras_fuerza_mayor;
	public $porc_base_cot_horas_extras_resto;
	public $monto_base_cot_horas_extras_resto;
	public $activo;
	public $recibido_el;
	
    public function clear()
    {
        parent::clear();
        $this->activo = 1;
    }

	public static function primaryColumn(): string
    {
        return 'idnomina';
    }
	
	public static function tableName(): string
    {
        return 'nominas';
    }

	public function delete(): bool {
		#parent::delete();
		return self::$dataBase->exec("UPDATE nominas SET activo = '0' WHERE idnomina = " . 
									 self::$dataBase->var2str($this->idnomina) . ";");
	}

}


?>