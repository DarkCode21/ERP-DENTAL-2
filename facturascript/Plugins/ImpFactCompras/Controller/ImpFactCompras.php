<?php
namespace FacturaScripts\Plugins\ImpFactCompras\Controller;
use FacturaScripts\Core\Base\DataBase;
use FacturaScripts\Core\Base\DataBase\DataBaseWhere;
use FacturaScripts\Dinamic\Model\FacturaProveedor;
use FacturaScripts\Dinamic\Model\Proveedor;
use FacturaScripts\Dinamic\Model\Producto;
use FacturaScripts\Dinamic\Model\Contacto;
use FacturaScripts\Dinamic\Model\ReciboProveedor;
use FacturaScripts\Dinamic\Model\Asiento;

class ImpFactCompras extends \FacturaScripts\Core\Lib\ExtendedController\PanelController {
	public $urlRootFile = FS_FOLDER."/MyFiles/XMLFacturas/";
	
	public function getPageData(): array
    {
        #die("OK");
		$pageData = parent::getPageData();
        $pageData['title'] = 'Importar Facturas';
        $pageData['menu']  = 'purchases';
        $pageData['icon'] = 'fa fa-file-code';
        return $pageData;
    }
	
	protected function loadData($viewName, $view) {
		
    }	
	
    public function privateCore(&$response, $user, $permissions)
    {
        parent::privateCore($response, $user, $permissions);
        // tu código aquí
    }	
	
	protected function createViews() {
		$this->addHtmlView('ImpFactCompras', 'ImpFactCompras', 'FacturaProveedor', 'order','fas fa-code-branch');
    }

	protected function execPreviousAction($action) {
	   switch ($action) {
		   case 'import': 
			   return $this->importAction();
		   default:
               return parent::execPreviousAction($action);
	   }	
	}
	
	private function importAction() {
		set_time_limit(0);		
    	if (false === $this->permissions->allowUpdate) {
            $this->toolBox()->i18nLog()->warning('not-allowed-update');
            return true;
        }
		
		$uploadFile = $this->request->files->get('doc');
		#die(var_dump($uploadFile));
		if ($uploadFile->getMimeType() == 'text/xml') {
			$nameFile = date('Ymd_His').'__'.$uploadFile->getClientOriginalName();
			$destino  = $this->urlRootFile;
			$uploadFile->move($destino, $nameFile);
			$this->proccessFile($nameFile);
		}
	}
	
	private function proccessFile($name) {
		$urlRead =$this->urlRootFile.$name;
		
		$file = fopen($urlRead,'r');
    	if ($file) {
			$filesize = filesize($urlRead);
			$filetext = fread($file, $filesize);
			fclose($file);
			$xml = new \SimpleXMLElement("$filetext");
			$xmlEncode = json_encode($xml);
			$xmlDecode = json_decode($xmlEncode, true);
			$headerDoc = $xmlDecode['FileHeader']['Batch']; 
			$cliente = $xmlDecode['Parties']['BuyerParty'];
			$detalles= $xmlDecode['Invoices'];
			$headerDocH = $detalles['Invoice']['InvoiceHeader'];
			if (!$this->isSincronizado($headerDocH, $cliente)) {
				$this->sincronizarFactura($headerDoc, $cliente, $detalles, $headerDocH);
			} else {
				unlink($urlRead);
				$this->toolBox()->i18nLog()->warning('not-allowed-modify');
      		}			
		}
	}
	
	private function isSincronizado ($headerDoc, $cliente) {
		$estado = false;
		$facturaProv = new FacturaProveedor();
		$where = [
			new DataBaseWhere('numero2', $headerDoc['InvoiceDocumentType'].'-'.$headerDoc['InvoiceSeriesCode']
							  .'-'.$headerDoc['InvoiceNumber']),
			new DataBaseWhere('cifnif', $cliente['TaxIdentificationNumber']),
		];
		
		$facturaProv->loadFromCode('', $where);
		if (!is_null($facturaProv->idfactura)) {
			$estado = true;
		}
		return $estado;
	}
	
	private function sincronizarFactura($headerDoc, $cliente, $detalles, $headerDocH) {
		$proveedor = new Proveedor();
		$whereProv = [
			new DataBaseWhere('cifnif', $cliente['TaxIdentification']['TaxIdentificationNumber'])
		];
		#echo "idnumber: ". $cliente['TaxIdentification']['TaxIdentificationNumber'];
		#$proveedor->loadFromCode('', $whereProv);
		#die(var_dump($proveedor));
		if (is_null($proveedor->cifnif)) {
			$datosCliente = $cliente['Individual'];
		
			$proveedor->cifnif = $cliente['TaxIdentification']['TaxIdentificationNumber'];
			$proveedor->nombre = $datosCliente['Name'];
			$proveedor->razonsocial = $datosCliente['Name']. ' '.$datosCliente['FirstSurname'].' '. $datosCliente['SecondSurname'];
			if ($proveedor->save() === true) {
				$contacto = new Contacto();
				$contacto->cifnif = $proveedor->cifnif;
				$contacto->nombre = $proveedor->nombre;
				$contacto->direccion = $datosCliente['AddressInSpain']['Address'].' '.$datosCliente['AddressInSpain']['Province'];
				$contacto->descripcion = $proveedor->razonSocial;
				$contacto->empresa = $proveedor->nombre;
				$contacto->codproveedor = $proveedor->codproveedor;
				if ($contacto->save() === false) {
					$this->toolBox()->i18nLog()->warning('not-allowed-modify');
      				return true;	
				}
			} else {
				$this->toolBox()->i18nLog()->warning('not-allowed-modify');
      			return true;
			}
		}
		
		
		$factura = new FacturaProveedor();
		$factura->cifnif = $proveedor->cifnif;
		$factura->coddivisa = $detalles['Invoice']['InvoiceIssueData']['InvoiceCurrencyCode']; //$headerDoc['InvoiceCurrencyCode'];
		$factura->fecha = $detalles['Invoice']['InvoiceIssueData']['IssueDate'];
		$factura->codejercicio = date('Y', strtotime($factura->fecha));
		$factura->codalmacen = 'ALG';
		$factura->codserie = 'A';
		$factura->numero2 = $headerDocH['InvoiceDocumentType'].'-'.$headerDocH['InvoiceSeriesCode'].'-'.$headerDocH['InvoiceNumber'];
		$factura->codproveedor = $proveedor->codproveedor;
		$factura->hora = date('H:i:s');
		$factura->idempresa = 1;
		$factura->neto = $detalles['Invoice']['TaxesOutputs']['Tax']['TaxableBase']['TotalAmount'];
		$factura->netosindto = $detalles['Invoice']['TaxesOutputs']['Tax']['TaxableBase']['TotalAmount'];
		$factura->nick = $this->user->nick;
		$factura->nombre = $proveedor->razonsocial;
		$factura->total = $detalles['Invoice']['PaymentDetails']['Installment']['InstallmentAmount'];
		$factura->totaleuros = $detalles['Invoice']['PaymentDetails']['Installment']['InstallmentAmount'];
		$factura->totaliva = $detalles['Invoice']['TaxesOutputs']['Tax']['TaxAmount']['TotalAmount'];
		if (false === $factura->save()) {
			$this->toolBox()->i18nLog()->warning('not-allowed-modify');
      		return true;	
		}
		
		foreach($detalles['Invoice']['Items'] as $item) {
			$newLine = $factura->getNewLine();
			$newLine->descripcion = $item['ItemDescription'];
			$newLine->cantidad = $item['Quantity'];
			$newLine->iva = $item['TaxesOutputs']['Tax']['TaxRate'];
			$newLine->pvpunitario = $item['UnitPriceWithoutTax'];
			$newLine->pvpsindto = $newLine->pvpunitario * $newLine->cantidad;
			$newLine->pvptotal = $newLine->pvpunitario * $newLine->cantidad;
			$newLine->recargo = 0;
			$newLine->codImpuesto = ($item['TaxesOutputs']['Tax']['TaxRate'] == 21?'IVA21': 'IVA10');
			
			// para el producto
			$producto = new Producto();
			$where = [
				new DataBaseWhere('descripcion', $newLine->descripcion)	
			];
			$producto->loadFromCode('', $where);
			
			$newLine->idproducto = $producto->idproducto;
			
			if (false === $newLine->save()) {
				$this->toolBox()->i18nLog()->warning('not-allowed-modify');
      			return true;	
			}
		}
		
		
		// PARA EL RECIBO
		$newReceipt = new ReciboProveedor();
        $newReceipt->codproveedor = $factura->codproveedor;
        $newReceipt->coddivisa = $factura->coddivisa;
        $newReceipt->codpago = $factura->codpago;
        $newReceipt->idempresa = $factura->idempresa;
        $newReceipt->idfactura = $factura->idfactura;
        $newReceipt->importe = $factura->totaleuros;
        $newReceipt->nick = $factura->nick;
        
		#$newReceipt->setExpiration($expiration);
        $newReceipt->save();

		$asiento = new Asiento();
		$asiento->codejercicio = $factura->codejercicio;
        $asiento->concepto = "Factura proveedor $factura->codigo - $factura->nombre";
        $asiento->documento = $factura->codigo;
        $asiento->fecha = $factura->fecha;
        $asiento->importe = $factura->totaleuros;
        if ($asiento->save() === true) {
			$factura->idasiento = $asiento->idasiento;
			$factura->save();
			$this->toolBox()->i18nLog()->notice('record-updated-correctly');		
		}
		
		return true;
	}
	
}