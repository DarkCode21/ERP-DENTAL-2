<?php
namespace FacturaScripts\Plugins\Nominas\Controller;

use FacturaScripts\Core\Base\DataBase\DataBaseWhere;
use FacturaScripts\Core\Lib\ExtendedController\BaseView;
use FacturaScripts\Core\Lib\ExtendedController\DocFilesTrait;
use FacturaScripts\Core\Lib\ExtendedController\EditController;
#use FacturaScripts\Core\Model\Empleado;
use FacturaScripts\Dinamic\Model\Empleado;
use FacturaScripts\Dinamic\Model\Nomina;

use FacturaScripts\Dinamic\Lib\ServiceToInvoice;

/**
 * Description of EditServicioAT
 *
 * @author Erick Lizana
 */
class EditNomina extends EditController
{
	use DocFilesTrait;
	public $nomina;
	
	public function getModelClassName(): string
    {
        return 'Nomina';
    }

	public function getPageData(): array
    {
        $data = parent::getPageData();
        $data['menu'] = 'admin';
        $data['title'] = 'nominas';
        $data['icon'] = 'fas fa-users';
        $data['showonmenu'] = false;
        return $data;
    }

	protected function createViews()
    {
		$this->setTemplate("EditNomina");	
    }


	public function privateCore(&$response, $user, $permissions)
	{
		parent::privateCore($response, $user, $permissions);
		$action = $this->request->request->get('action');
		$this->nomina = new Nomina();
		$code = $this->request->query->get('code');
		if (!empty($code)) {
			$this->nomina->loadFromCode($code);
			$this->nomina->recibido_el_f = !is_null($this->nomina->recibido_el)?date('Y-m-d', strtotime($this->nomina->recibido_el)):null;
		}
		switch ($action) {
			case 'autocomplete-employee':
				$this->autocompleteEmployee();
				break;
			case 'autocomplete-data-employee':
				$this->autocompleteDataEmployee();
				break;
			case 'save-data':
				$this->saveData();
				break;
			default:
		        $this->setTemplate('EditNomina');
				break;			
		}
    }
		
	protected function saveData ()	
	{
        $this->setTemplate(false);
		$i18n = $this->toolBox()->i18n();
		$list = [];
		$nomina = new Nomina();
		$code = $this->request->request->get('idnomina');
		if (!empty($code)) { $nomina->loadFromCode($code); }
		
		if (empty($this->request->request->get('codempleado'))) {
			$list =  ['status' => 'error', 'message' => $i18n->trans('invalid-codempleado'), 'type' => 'error'];		
			$this->response->setContent(\json_encode($list));
			return;
		}
		
		if (empty($this->request->request->get('inicioliquidacion'))) {
			$list = ['status' => 'error', 'message' => $i18n->trans('invalid-inicioliquidacion'), 'type' => 'error'];		
			$this->response->setContent(\json_encode($list));
			return;
		}

		if (empty($this->request->request->get('finliquidacion'))) {
			$list = ['status' => 'error', 'message' => $i18n->trans('invalid-finliquidacion'), 'type' => 'error'];	
			$this->response->setContent(\json_encode($list));
			return;
		}

		if (empty($this->request->request->get('salariobase')) || $this->request->request->get('salariobase') <= 0) {
			$list = ['status' => 'error', 'message' => $i18n->trans('invalid-salariobase'), 'type' => 'error'];	
			$this->response->setContent(\json_encode($list));
			return;
		}

		$recibido_el = $this->request->request->get('recibido_el');
		$nomina->codempleado = $this->request->request->get('codempleado');
		$nomina->nombretrabajador = $this->request->request->get('query');
		$nomina->nif = $this->request->request->get('cifnif');
		$nomina->direccion = $this->request->request->get('direccion');
		$nomina->localidad = $this->request->request->get('localidad');
		$nomina->cif = $this->request->request->get('cif');
		$nomina->grupoprofesional = $this->request->request->get('grupoprofesional');
		$nomina->naf = $this->request->request->get('naf');
		$nomina->cccss = $this->request->request->get('cccss');
		$nomina->grupocotizacion = $this->request->request->get('grupocotizacion');
		$nomina->inicioliquidacion = $this->request->request->get('inicioliquidacion');
		$nomina->finliquidacion = $this->request->request->get('finliquidacion');
		$nomina->totaldias = $this->request->request->get('totaldias');
		$nomina->salariobase = $this->request->request->get('salariobase');
		$nomina->complementossalariales = !empty($this->request->request->get('complementossalariales'))?$this->request->request->get('complementossalariales'):0;
		$nomina->extrasfuerzamayor = !empty($this->request->request->get('extrasfuerzamayor'))?$this->request->request->get('extrasfuerzamayor'):0;
		$nomina->extrasresto = !empty($this->request->request->get('extrasresto'))?$this->request->request->get('extrasresto'):0;
		$nomina->horascomplementarias = !empty($this->request->request->get('horascomplementarias'))?$this->request->request->get('horascomplementarias'):0;
		$nomina->gratificacionesextraordinarias = !empty($this->request->request->get('gratificacionesextraordinarias'))?$this->request->request->get('gratificacionesextraordinarias'):0;
		$nomina->salarioespecie = !empty($this->request->request->get('salarioespecie'))?$this->request->request->get('salarioespecie'):0;
		$nomina->indemnizaciones = !empty($this->request->request->get('indemnizaciones'))?$this->request->request->get('indemnizaciones'):0;
		$nomina->seguridadsocial = !empty($this->request->request->get('seguridadsocial'))?$this->request->request->get('seguridadsocial'):0;
		$nomina->despidos = !empty($this->request->request->get('despidos'))?$this->request->request->get('despidos'):0;
		$nomina->otraspercepciones = !empty($this->request->request->get('otraspercepciones'))?$this->request->request->get('otraspercepciones'):0;
		$nomina->totaldevengado = !empty($this->request->request->get('totaldevengado'))?$this->request->request->get('totaldevengado'):0;
		$nomina->porc_contingencias = !empty($this->request->request->get('porc_contingencias'))?$this->request->request->get('porc_contingencias'):0;
		$nomina->monto_contingencias = !empty($this->request->request->get('monto_contingencias'))?$this->request->request->get('monto_contingencias'):0;
		$nomina->porc_desempleo = !empty($this->request->request->get('porc_desempleo'))?$this->request->request->get('porc_desempleo'):0;
		$nomina->monto_desempleo = !empty($this->request->request->get('monto_desempleo'))?$this->request->request->get('monto_desempleo'):0;
		$nomina->porc_profesional = !empty($this->request->request->get('porc_profesional'))?$this->request->request->get('porc_profesional'):0;
		$nomina->monto_profesional = !empty($this->request->request->get('monto_profesional'))?$this->request->request->get('monto_profesional'):0;
		$nomina->porc_extras_fuerza_mayor = !empty($this->request->request->get('porc_extras_fuerza_mayor'))?$this->request->request->get('porc_extras_fuerza_mayor'):0;
		$nomina->monto_extras_fuerza_mayor = !empty($this->request->request->get('monto_extras_fuerza_mayor'))?$this->request->request->get('monto_extras_fuerza_mayor'):0;
		$nomina->porc_extras_resto = !empty($this->request->request->get('porc_extras_resto'))?$this->request->request->get('porc_extras_resto'):0;
		$nomina->monto_extras_resto = !empty($this->request->request->get('monto_extras_resto'))?$this->request->request->get('monto_extras_resto'):0;
		$nomina->total_aportaciones = !empty($this->request->request->get('total_aportaciones'))?$this->request->request->get('total_aportaciones'):0;
		$nomina->porc_impuesto_renta = !empty($this->request->request->get('porc_impuesto_renta'))?$this->request->request->get('porc_impuesto_renta'):0;
		$nomina->monto_impuesto_renta = !empty($this->request->request->get('monto_impuesto_renta'))?$this->request->request->get('monto_impuesto_renta'):0;
		$nomina->monto_anticipos = !empty($this->request->request->get('monto_anticipos'))?$this->request->request->get('monto_anticipos'):0;
		$nomina->monto_productos_especies = !empty($this->request->request->get('monto_productos_especies'))?$this->request->request->get('monto_productos_especies'):0;
		$nomina->monto_deducciones = !empty($this->request->request->get('monto_deducciones'))?$this->request->request->get('monto_deducciones'):0;
		$nomina->totaldeducir = !empty($this->request->request->get('totaldeducir'))?$this->request->request->get('totaldeducir'):0;
		$nomina->total_liquido_percibir = !empty($this->request->request->get('total_liquido_percibir'))?$this->request->request->get('total_liquido_percibir'):0;
		$nomina->remuneracion_mensual_cotizacion = !empty($this->request->request->get('remuneracion_mensual_cotizacion'))?$this->request->request->get('remuneracion_mensual_cotizacion'):0;
		$nomina->prorratas_pagas_extras = !empty($this->request->request->get('prorratas_pagas_extras'))?$this->request->request->get('prorratas_pagas_extras'):0;
		$nomina->total_remuneracion = !empty($this->request->request->get('total_remuneracion'))?$this->request->request->get('total_remuneracion'):0;
		$nomina->base_cotizacion_horas_extras_fuerza_mayor = !empty($this->request->request->get('base_cotizacion_horas_extras_fuerza_mayor'))?$this->request->request->get('base_cotizacion_horas_extras_fuerza_mayor'):0;
		$nomina->base_cotizacion_horas_extras_resto = !empty($this->request->request->get('base_cotizacion_horas_extras_resto'))?$this->request->request->get('base_cotizacion_horas_extras_resto'):0;
		$nomina->base_retencion_irpf = !empty($this->request->request->get('base_retencion_irpf'))?$this->request->request->get('base_retencion_irpf'):0;
		$nomina->base = !empty($this->request->request->get('base'))?$this->request->request->get('base'):0;
		$nomina->porc_base = !empty($this->request->request->get('porc_base'))?$this->request->request->get('porc_base'):0;
		$nomina->monto_base = !empty($this->request->request->get('monto_base'))?$this->request->request->get('monto_base'):0;
		$nomina->porc_at_ep = !empty($this->request->request->get('porc_at_ep'))?$this->request->request->get('porc_at_ep'):0;
		$nomina->monto_at_ep = !empty($this->request->request->get('monto_at_ep'))?$this->request->request->get('monto_at_ep'):0;
		$nomina->porc_desempleo_c = !empty($this->request->request->get('porc_desempleo_c'))?$this->request->request->get('porc_desempleo_c'):0;
		$nomina->monto_desempleo_c = !empty($this->request->request->get('monto_desempleo_c'))?$this->request->request->get('monto_desempleo_c'):0;
		$nomina->porc_formacion_empresarial = !empty($this->request->request->get('porc_formacion_empresarial'))?$this->request->request->get('porc_formacion_empresarial'):0;
		$nomina->bccp = !empty($this->request->request->get('bccp'))?$this->request->request->get('bccp'):0;
		$nomina->monto_formacion_empresarial = !empty($this->request->request->get('monto_formacion_empresarial'))?$this->request->request->get('monto_formacion_empresarial'):0;
		$nomina->porc_base_cot_horas_extras_fuerza_mayor = !empty($this->request->request->get('porc_base_cot_horas_extras_fuerza_mayor'))?$this->request->request->get('porc_base_cot_horas_extras_fuerza_mayor'):0;
		$nomina->monto_base_cot_horas_extras_fuerza_mayor = !empty($this->request->request->get('monto_base_cot_horas_extras_fuerza_mayor'))?$this->request->request->get('monto_base_cot_horas_extras_fuerza_mayor'):0;
		$nomina->porc_base_cot_horas_extras_resto = !empty($this->request->request->get('porc_base_cot_horas_extras_resto'))?$this->request->request->get('porc_base_cot_horas_extras_resto'):0;
		$nomina->monto_base_cot_horas_extras_resto = !empty($this->request->request->get('monto_base_cot_horas_extras_resto'))?$this->request->request->get('monto_base_cot_horas_extras_resto'):0;
		$nomina->recibido_el = (!empty($recibido_el)?$recibido_el:null);
		$status = 'error';
		$type = 'warning';
		#die(var_dump($nomina->save()));
    	if($nomina->save()) {
			$message = $i18n->trans('record-updated-correctly');
			$status = 'success';
			$type = 'success';
		} else {
			$message =  $i18n->trans('not-allowed-modify');	
		}
	
		$list = ['status' => $status, 'message' => $message, 'type' => $type];		
		$this->response->setContent(\json_encode($list));
		
		#die(var_dump($this->request->request));
	}
	
	protected function autocompleteEmployee()
    {
        $this->setTemplate(false);

        $list = [];
        $empleado = new Empleado();
        $query = $this->request->get('query');
	    foreach ($empleado->codeModelSearch($query, 'idempleado') as $value) {
			$list[] = [
                'key' => $this->toolBox()->utils()->fixHtml($value->code),
                'value' => $this->toolBox()->utils()->fixHtml($value->description)
            ];
        }

        if (empty($list)) {
            $list[] = ['key' => null, 'value' => $this->toolBox()->i18n()->trans('no-data')];
        }
		$this->response->setContent(\json_encode($list));
    }
	
	protected function autocompleteDataEmployee()
	{
		$this->setTemplate(false);
        $list = [];
		$code = $this->request->get('codempleado');
	    $empleado = new Empleado();
		if($empleado->loadFromCode($code)) {
			$list[] = ['key'=>'empleado', 'value' => $empleado];
		}		
		$this->response->setContent(\json_encode($list));
	}
	
	protected function execAfterAction($action)
    {
	    if ($action === 'export') {
            $option = $this->request->get('option', 'show');
            if ($option === 'show') {
                return;
            }
        }
		$this->setTemplate(false);
        $codes = $this->request->request->get('code');
        $this->exportManager->newDoc(
            $this->request->get('option', ''),
            'Nomina',
            0,
            ''
        );
		#$this->exportManager->addBusinessDocPage('Nomina');
		#$this->exportManager->show($this->response);

       #$this->views[$this->active]->export($this->exportManager, $codes);
        #$this->exportManager->show($this->response);
    
		parent::execAfterAction($action);
    }

}