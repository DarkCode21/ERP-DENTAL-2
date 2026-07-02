<?php
namespace FacturaScripts\Plugins\Nominas\Lib\Export;

use FacturaScripts\Core\Base\Utils;
use FacturaScripts\Plugins\Nominas\Model\Nomina;
use FacturaScripts\Plugins\Nominas\Model\Empleado;
use FacturaScripts\Core\App\AppSettings;
use FacturaScripts\Dinamic\Model\Empresa;
use FacturaScripts\Dinamic\Model\AttachedFile;

class PDFnominaExport extends \FacturaScripts\Core\Lib\Export\PDFExport
{
	/**
     * @param Nomina $model
     * @param array $columns
     * @param string $title
     *
     * @return bool
     */

	protected function insertHeaderNomina($idempresa = null)
    {
        if ($this->insertedHeader) {
            return;
        }

        $this->insertedHeader = true;
        $code = $idempresa ?? AppSettings::get('default', 'idempresa', '');
        $company = new Empresa();
        if (false === $company->loadFromCode($code)) {
            return;
        }

        $size = mb_strlen($company->nombre) > 20 ? self::FONT_SIZE + 2 : self::FONT_SIZE + 7;
        $this->pdf->ezText(Utils::fixHtml($company->nombre), $size, ['justification' => 'right']);
        $address = $company->direccion;
        $address .= empty($company->codpostal) ? "\n" : "\n" . $company->codpostal . ', ';
        $address .= empty($company->ciudad) ? '' : $company->ciudad;
        $address .= empty($company->provincia) ? '' : ' (' . $company->provincia . ') ' . $this->getCountryName($company->codpais);

        $contactData = [];
        foreach (['telefono1', 'telefono2', 'email', 'web'] as $field) {
            if (!empty($company->{$field})) {
                $contactData[] = $company->{$field};
            }
        }

        $lineText = $company->cifnif . ' - ' . Utils::fixHtml($address); //. "\n\n" . implode(' · ', $contactData);
        $this->pdf->ezText($lineText, self::FONT_SIZE, ['justification' => 'right']);

        $idlogo = $this->format->idlogo ?? $company->idlogo;
        $this->insertCompanyNominaLogo($idlogo);
    }
	
	/**
     * Inserts company logo to PDF document or dies with a message to try to solve the problem.
     *
     * @param int $idfile
     */
    protected function insertCompanyNominaLogo($idfile = 0)
    {
        if (!function_exists('imagecreatefromstring')) {
            die('ERROR: function imagecreatefromstring() not found. '
                . ' Do you have installed php-gd package and enabled support to allow us render images? .'
                . 'Note that the package name can differ between operating system or PHP version.');
        }

        $xPos = $this->pdf->ez['leftMargin'];

        $logoFile = new AttachedFile();
        if ($idfile !== 0 && $logoFile->loadFromCode($idfile) && file_exists($logoFile->path)) {
            $logoSize = $this->calcImageSize($logoFile->path);
            $yPos = $this->pdf->ez['pageHeight'] - $logoSize['height'] - 10; //- $this->pdf->ez['topMargin'];
            $this->addImageFromAttachedFile($logoFile, $xPos, $yPos, $logoSize['width'], $logoSize['height']);
        } else {
            $logoPath = FS_FOLDER . '/Dinamic/Assets/Images/horizontal-logo.png';
            $logoSize = $this->calcImageSize($logoPath);
            $yPos = $this->pdf->ez['pageHeight'] - $logoSize['height'] - $this->pdf->ez['topMargin'];
            $this->addImageFromFile($logoPath, $xPos, $yPos, $logoSize['width'], $logoSize['height']);
        }

        // add some margin
        $this->pdf->y -= 1;
    }



    public function addModelPage($model, $columns, $title = ''): bool
    {
		$this->newPage();
		#$this->newDoc($title, 0, static::$selectedLang);
		$empleado = new Empleado;
		$empleado->loadFromCode($model->codempleado);
		
		$this->insertHeaderNomina($empleado->idempresa);
		#$this->printTableSection('nomina', $model->idnomina);
		$this->pdf->ezText("\n" . $this->i18n->trans('nomina') . ': ' . str_pad($model->idnomina,8, "0", STR_PAD_LEFT) . "\n", 
						   self::FONT_SIZE + 6);
        $this->newLine();
		$this->insertParallelTable($this->nominaData($model), '', $this->tableOptions());
        $this->pdf->ezText('');
		
	    $this->pdf->ezTable($this->bodyData($model), '', '', $this->tableOptions(1));
		$this->pdf->ezTable($this->getDataFirma($model), '', '', $this->tableFirmaOptions());
		$this->pdf->ezText("\n   DETERMINACION DE LAS BASES DE COTIZA. A LA S.S. Y CONCEPTOS DE RECAUDACION CONJUNTA Y DE LA BASE SUJETA A RETENCION DEL I.R.P.F.\n",  self::FONT_SIZE-2.5);
	    #$this->newLine();
		
		$this->pdf->ezTable($this->getCuadroData($model), '','', $this->tableCuadroOptions());
	
        #$this->pdf->ezText('');
			
		return false;
	}
	
	protected function printTextSection($title, $text, $addLine = true)
    {
        if (empty($text)) {
            return;
        }

        $this->pdf->ezText("\n" . $this->i18n->trans($title) . "\n", self::FONT_SIZE + 4);
        if ($addLine) {
            $this->newLine();
        }
        $this->pdf->ezText(\nl2br($text) . "\n", self::FONT_SIZE + 2);
    }

	/**
     * @param ServicioAT $model
     * @param Cliente $subject
     *
     * @return array
     */
    private function nominaData(&$model): array
    {
	   return [
            ['key' => $this->i18n->trans('employee'), 'value' => Utils::fixHtml($model->nombretrabajador)],
            ['key' => $this->i18n->trans('cifnif'), 'value' => Utils::fixHtml((empty($model->cif)?$model->nif:$model->cif))],
			['key' => $this->i18n->trans('naf'), 'value' => Utils::fixHtml($model->naf)],
			['key' => $this->i18n->trans('address'), 'value' => Utils::fixHtml($model->direccion)],
			['key' => $this->i18n->trans('home'), 'value' => Utils::fixHtml($model->localidad)],
			['key' => $this->i18n->trans('group-professional'), 'value' => Utils::fixHtml($model->grupoprofesional)],
			['key' => $this->i18n->trans('group-cotizacion'), 'value' => Utils::fixHtml($model->grupocotizacion)],
			['key' => $this->i18n->trans('cccss'), 'value' => Utils::fixHtml($model->cccss)],
			['key' => $this->i18n->trans('of'), 'value' => Utils::fixHtml($model->inicioliquidacion.' al '.$model->finliquidacion)],
			['key' => $this->i18n->trans('total-days'), 'value' => Utils::fixHtml($model->totaldias)]
	  ];
    }
	
	private function getCuadroData (&$model): array
	{
	   return [
            [
				'key' => "1. Base de Cotización por Contingencias Comunes:", 
			 	'value' => '',
				'skype' => '',
				'key2' => 'BASE',
				'value2' =>  number_format($model->base,2,'.',','),
				'porc2' => ''
			],
            [
				'key' => "Remuneración mensual sujeta a cotización", 
			 	'value' => number_format($model->remuneracion_mensual_cotizacion,2,'.',','),
				'skype' => '',
				'key2' => 'BCCC',
				'value2' =>  number_format($model->monto_base,2,'.',','),
				'porc2' => '('.number_format($model->porc_base,2,'.',','). '%)'
		
			],
			[	
				'key' => "Prorrata pagas extras", 
				'value' => number_format($model->prorratas_pagas_extras,2,'.',','),
				'skype' => '',
				'key2' => 'AT y EP ( Aportación empresa)',
				'value2' =>  number_format($model->monto_at_ep,2,'.',','),
				'porc2' =>  '('.number_format($model->porc_at_ep,2,'.',','). '%)'
		
			],
			[
				'key' => "TOTAL", 
				'value' => number_format($model->total_remuneracion,2,'.',','),
				'skype' => '',
				'key2' => 'Desempleo',
				'value2' =>  number_format($model->monto_desempleo_c,2,'.',','),
				'porc2' =>  '('.number_format($model->porc_desempleo_c,2,'.',','). '%)'
			],
			[
				'key' => "2. Base de Cotización Horas Extras Fuerza Mayor", 
			 	'value' => number_format($model->base_cotizacion_horas_extras_fuerza_mayor,2,'.',','),
				'skype' => '',
				'key2' => 'Formación Profesional (BCCP)',
				'value2' =>  number_format($model->monto_formacion_empresarial,2,'.',','),
				'porc2' =>  '('.number_format($model->porc_formacion_empresarial,2,'.',','). '%)'
			],
			[
				'key' => "3. Base de Cotización Horas Extras Resto", 
			 	'value' => number_format($model->base_cotizacion_horas_extras_resto,2,'.',','),
				'skype' => '',
				'key2' => 'Base de Cotización Horas Extras Fuerza Mayor',
				'value2' =>  number_format($model->monto_base_cot_horas_extras_fuerza_mayor,2,'.',','),
				'porc2' =>  '('.number_format($model->porc_base_cot_horas_extras_fuerza_mayor,2,'.',','). '%)'	
			],
			[
				'key' => "4. Base sujeta a retención del I.R.P.F",
				'value' => number_format($model->base_retencion_irpf,2,'.',','),
				'skype' => '',
				'key2' => 'Base de Cotización Horas Extras Resto',
				'value2' =>  number_format($model->monto_base_cot_horas_extras_resto,2,'.',','),
				'porc2' =>  '('.number_format($model->porc_base_cot_horas_extras_resto,2,'.',','). '%)'	
			]
	  ];
	}

	private function bodyData($model): array
	{
		$result = [];
		$result[] = [
			$this->i18n->trans('description-pdf') => 'I. DEVENGOS',
			$this->i18n->trans('sub-total-pdf') => '',
			$this->i18n->trans('total-pdf') => ''
		];
		
		$result[] = [
			$this->i18n->trans('description-pdf') => '1. Percepciones Salariales (Sujetas a cotización)',
			$this->i18n->trans('sub-total-pdf') => '',
			$this->i18n->trans('total-pdf') => ''
		];

		$result[] = [
			$this->i18n->trans('description') => 'Salario Base',
			$this->i18n->trans('sub-total-pdf') => number_format($model->salariobase,2,'.',','),
			$this->i18n->trans('total-pdf') => ''
		];

		$result[] = [
			$this->i18n->trans('description') => 'Complementos Salariales',
			$this->i18n->trans('sub-total-pdf') => number_format($model->complementossalariales,2,'.',','),
			$this->i18n->trans('total-pdf') => ''
		];

		$result[] = [
			$this->i18n->trans('description') => 'Horas Extras Fuerza Mayor',
			$this->i18n->trans('sub-total-pdf') => number_format($model->extrasfuerzamayor,2,'.',','),
			$this->i18n->trans('total-pdf') => ''
		];

		$result[] = [
			$this->i18n->trans('description') => 'Horas Extras Resto',
			$this->i18n->trans('sub-total-pdf') => number_format($model->extrasresto,2,'.',','),
			$this->i18n->trans('total-pdf') => ''
		];
		
		$result[] = [
			$this->i18n->trans('description') => 'Horas Complementarias (contratos a tiempo parcial)',
			$this->i18n->trans('sub-total-pdf') => number_format($model->horascomplementarias,2,'.',','),
			$this->i18n->trans('total-pdf') => ''
		];
		
		$result[] = [
			$this->i18n->trans('description') => 'Gratificaciones extraordinarias',
			$this->i18n->trans('sub-total-pdf') => number_format($model->gratificacionesextraordinarias,2,'.',','),
			$this->i18n->trans('total-pdf') => ''
		];
		
		$result[] = [
			$this->i18n->trans('description') => 'Salario en especie',
			$this->i18n->trans('sub-total-pdf') => number_format($model->salarioespecie,2,'.',','),
			$this->i18n->trans('total-pdf') => ''
		];

		$result[] = [
			$this->i18n->trans('description') => '',
			$this->i18n->trans('sub-total-pdf') => '',
			$this->i18n->trans('total-pdf') => ''
		];

		$result[] = [
			$this->i18n->trans('description') => '2. Percepciones no salariales (Excluidas de cotización)',
			$this->i18n->trans('sub-total-pdf') => '',
			$this->i18n->trans('total-pdf') => ''
		];
		
		$result[] = [
			$this->i18n->trans('description') => 'Indemnizaciones o suplidos',
			$this->i18n->trans('sub-total-pdf') => number_format($model->indemnizaciones,2,'.',','),
			$this->i18n->trans('total-pdf') => ''
		];
		
		$result[] = [
			$this->i18n->trans('description') => 'Prestaciones e indemnizaciones de la Seguridad Social',
			$this->i18n->trans('sub-total-pdf') => number_format($model->seguridadsocial,2,'.',','),
			$this->i18n->trans('total-pdf') => ''
		];
		
		$result[] = [
			$this->i18n->trans('description') => 'Indemnizaciones por traslados, suspensiones o despidos',
			$this->i18n->trans('sub-total-pdf') => number_format($model->despidos,2,'.',','),
			$this->i18n->trans('total-pdf') => ''
		];
		
		$result[] = [
			$this->i18n->trans('description') => 'Otras percepciones salariales',
			$this->i18n->trans('sub-total-pdf') => number_format($model->otraspercepciones,2,'.',','),
			$this->i18n->trans('total-pdf') => ''
		];
		
		$result[] = [
			$this->i18n->trans('description') => 'A. TOTAL DEVENGADO',
			$this->i18n->trans('sub-total-pdf') => '',
			$this->i18n->trans('total-pdf') => number_format($model->totaldevengado,2,'.',','),
		];

		$result[] = [
			$this->i18n->trans('description') => '',
			$this->i18n->trans('sub-total-pdf') => '',
			$this->i18n->trans('total-pdf') => ''
		];

		$result[] = [
			$this->i18n->trans('description-pdf') => 'II. DEDUCCIONES',
			$this->i18n->trans('sub-total-pdf') => '',
			$this->i18n->trans('total-pdf') => ''
		];

		$result[] = [
			$this->i18n->trans('description-pdf') => '1. Aportaciones del trabajador a las cotizaciones a la S.S. Y conceptos de recaudación conjunta:',
			$this->i18n->trans('sub-total-pdf') => '',
			$this->i18n->trans('total-pdf') => ''
		];

		$result[] = [
			$this->i18n->trans('description-pdf') => 'Contingencias Comunes',
			$this->i18n->trans('sub-total-pdf') => number_format($model->monto_contingencias,2,'.',','),
			$this->i18n->trans('total-pdf') => '('.number_format($model->porc_contingencias,2,'.',',').'%)'
		];

		$result[] = [
			$this->i18n->trans('description-pdf') => 'Desempleo',
			$this->i18n->trans('sub-total-pdf') => number_format($model->monto_desempleo,2,'.',',') ,
			$this->i18n->trans('total-pdf') => '('.number_format($model->porc_desempleo,2,'.',',').'%)'
		];

		$result[] = [
			$this->i18n->trans('description-pdf') => 'Formación Profesional',
			$this->i18n->trans('sub-total-pdf') => number_format($model->monto_profesional,2,'.',','), 
			$this->i18n->trans('total-pdf') => '('.number_format($model->porc_profesional,2,'.',',').'%)'
		];
		
		$result[] = [
			$this->i18n->trans('description-pdf') => 'Horas Extras Fuerza Mayor',
			$this->i18n->trans('sub-total-pdf') => number_format($model->monto_extras_fuerza_mayor,2,'.',','),
			$this->i18n->trans('total-pdf') => '('.number_format($model->porc_extras_fuerza_mayor,2,'.',',').'%)'
		];

		$result[] = [
			$this->i18n->trans('description-pdf') => 'Horas Extras Resto',
			$this->i18n->trans('sub-total-pdf') => number_format($model->monto_extras_resto,2,'.',','),
			$this->i18n->trans('total-pdf') => '('.number_format($model->porc_extras_resto,2,'.',',').'%)'
		];

		$result[] = [
			$this->i18n->trans('description-pdf') => 'TOTAL APORTACIONES',
			$this->i18n->trans('sub-total-pdf') => number_format($model->total_aportaciones,2,'.',','),
			$this->i18n->trans('total-pdf') => ''
		];

		$result[] = [
			$this->i18n->trans('description') => '',
			$this->i18n->trans('sub-total-pdf') => '',
			$this->i18n->trans('total-pdf') => ''
		];

		$result[] = [
			$this->i18n->trans('description-pdf') => '2. Impuesto sobre la Renta de las Personas Físicas',
			$this->i18n->trans('sub-total-pdf') => number_format($model->monto_impuesto_renta,2,'.',','),
			$this->i18n->trans('total-pdf') => '('.number_format($model->porc_impuesto_renta,2,'.',',').'%)'
		];

		$result[] = [
			$this->i18n->trans('description-pdf') => '3. Anticipos',
			$this->i18n->trans('sub-total-pdf') => number_format($model->monto_anticipos,2,'.',','),
			$this->i18n->trans('total-pdf') => ''
		];

		$result[] = [
			$this->i18n->trans('description-pdf') => '4. Valor de los productos recibidos en especie',
			$this->i18n->trans('sub-total-pdf') => number_format($model->monto_productos_especies,2,'.',','),
			$this->i18n->trans('total-pdf') => ''
		];

		$result[] = [
			$this->i18n->trans('description-pdf') => '5. Otras deducciones',
			$this->i18n->trans('sub-total-pdf') => number_format($model->monto_deducciones,2,'.',','),
			$this->i18n->trans('total-pdf') => ''
		];

		$result[] = [
			$this->i18n->trans('description') => 'B. TOTAL A DEDUCIR',
			$this->i18n->trans('sub-total-pdf') => '',
			$this->i18n->trans('total-pdf') => number_format($model->totaldeducir,2,'.',','),
		];

		$result[] = [
			$this->i18n->trans('description') => '',
			$this->i18n->trans('sub-total-pdf') => '',
			$this->i18n->trans('total-pdf') => ''
		];

		$result[] = [
			$this->i18n->trans('description') => 'LIQUIDO TOTAL A PERCIBIR (A-B)',
			$this->i18n->trans('sub-total-pdf') => '',
			$this->i18n->trans('total-pdf') => number_format($model->total_liquido_percibir,2,'.',','),
		];

		return $result;
	}

	private function getDataFirma($model) {
		$result = [];
		
			$result[] = [
			$this->i18n->trans('description') => '',
			$this->i18n->trans('sub-total-pdf') => '',
			$this->i18n->trans('total-pdf') => ''
		];

		$result[] = [
			$this->i18n->trans('description') => '',
			$this->i18n->trans('sub-total-pdf') => '',
			$this->i18n->trans('total-pdf') => ''
		];

		$result[] = [
			$this->i18n->trans('description') => '',
			$this->i18n->trans('sub-total-pdf') => '',
			$this->i18n->trans('total-pdf') => ''
		];

		$result[] = [
			$this->i18n->trans('description') => '',
			$this->i18n->trans('sub-total-pdf') => 'RECIBÍ EL',
			$this->i18n->trans('total-pdf') => ''
		];

		$result[] = [
			$this->i18n->trans('description') => '',
			$this->i18n->trans('sub-total-pdf') => '',
			$this->i18n->trans('total-pdf') => ''
		];

		$result[] = [
			$this->i18n->trans('description') => 'Firma y sello de la empresa',
			$this->i18n->trans('sub-total-pdf') => '',
			$this->i18n->trans('total-pdf') => (!is_null($model->recibido_el)?date('d-m-Y', strtotime($model->recibido_el)):''),
		];
	
		
		return $result;
	}

	/**
     * Print a section with an array of data.
     *
     * @param string $title
     * @param array $data
     */
    protected function printTableSection($title, $data)
    {
        $this->pdf->ezText("\n" . $this->i18n->trans($title) . "\n", self::FONT_SIZE + 4);
        $this->newLine();
        $this->pdf->ezTable($data, '', '', $this->tableOptions(1));
        $this->pdf->ezText('');
	}

	protected function tableOptions($headings = 0): array
    {
        return [
            'width' => $this->tableWidth,
            'showHeadings' => $headings,
            'shaded' => 0,
			'shadeCol' => [0.95, 0.95, 0.95],
			'shadeHeadingCol' => [0.95, 0.95, 0.95],
       		'lineCol' => [0,0,0],
            'cols' => [
				$this->i18n->trans('description') => ['justification' => 'left'],
				$this->i18n->trans('sub-total-pdf') => ['justification' => 'right'],
				$this->i18n->trans('total-pdf') => ['justification' => 'right']
			],
			'fontSize' => 7.5
        ];
    }

	protected function tableFirmaOptions($headings = 0): array
    {
        return [
            'width' => $this->tableWidth,
            'showHeadings' => $headings,
            'shaded' => 0,
			'shadeCol' => [0.95, 0.95, 0.95],
			'shadeHeadingCol' => [0.95, 0.95, 0.95],
       		'lineCol' => [0,0,0],
            'cols' => [
				$this->i18n->trans('description') => ['justification' => 'left'],
				$this->i18n->trans('sub-total-pdf') => ['justification' => 'right'],
				$this->i18n->trans('total-pdf') => ['justification' => 'center']
			],
			'fontSize' => 7.5
        ];
    }
	
	protected function tableCuadroOptions($headings = 0): array
    {
        return [
            'width' => $this->tableWidth,
            'showHeadings' => $headings,
            'shaded' => 0,
    		'shadeCol' => [1, 0, 0],
            'lineCol' => [1,1,2,1,1],
     		'cols' => [
				'key' => ['justification' => 'left'],
				'value' => ['justification' => 'right'],
				'sype' => ['justification' => 'center'],
				'key2' => ['justification' => 'left'],
				'value2' => ['justification' => 'right']
			],
			'fontSize' => 6.5
        
        ];
    }
	
}
?>