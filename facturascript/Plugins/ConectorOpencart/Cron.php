<?php
namespace FacturaScripts\Plugins\ConectorOpencart;

use FacturaScripts\Core\Base\DataBase;
use FacturaScripts\Core\Base\DataBase\DataBaseWhere;

use FacturaScripts\Dinamic\Model\PedidoCliente;
use FacturaScripts\Dinamic\Model\LineaPedidoCliente;
use FacturaScripts\Dinamic\Model\Producto;
use FacturaScripts\Dinamic\Model\Variante;
use FacturaScripts\Dinamic\Model\Impuesto;
use FacturaScripts\Dinamic\Model\Cliente;
use FacturaScripts\Dinamic\Model\Contacto;
use FacturaScripts\Dinamic\Model\Familia;
use FacturaScripts\Dinamic\Model\Fabricante;

class Cron extends \FacturaScripts\Core\Base\CronClass 
{
	const JOB = 'import-order-todo-external-time-abc';
    const PERIOD = '24 hours';

    public function run() {
        #if ($this->isTimeForJob(self::JOB, self::PERIOD)) {
            $this->importOrderTodoExternalTimeAbc();
            $this->jobDone(self::JOB);
        #}
    }
	
	protected function getCodEjercicio ($fecha, $conn) {
		#echo "<br>fecha: $fecha";
		$data = $conn->select("SELECT codejercicio FROM ejercicios WHERE fechainicio <= '$fecha' AND fechafin >= '$fecha';");
		#die(var_dump($data));
		if (!empty($data[0]['codejercicio'])) {
			return $data[0]['codejercicio'];
		}
		
		return null;
	}

	protected function getNumero ($code, $conn, $type='PedidoCliente') {
		#echo "<br>fecha: $fecha";
		$data = $conn->select("SELECT numero FROM secuencias_documentos WHERE tipodoc='$type' AND codejercicio='$code';");
		#die(var_dump($data));
		if (!empty($data[0]['numero'])) {
			return (int)$data[0]['numero'] - 1;
		}
		
		return null;
	}
	
	protected function getCodigo ($code, $conn, $type='PedidoCliente') {
		#echo "<br>fecha: $fecha";
		$data = $conn->select("SELECT * FROM secuencias_documentos WHERE tipodoc='$type' AND codejercicio='$code';");
		#die(var_dump($data));
		if (!empty($data[0]['numero'])) {
			$max = (int)$data[0]['numero'] + 1;
			if ($conn->exec("UPDATE secuencias_documentos SET numero = $max WHERE tipodoc='$type' AND codejercicio='$code';")) {
				  $patron = $data[0]['patron'];
				  $patron = str_replace('{EJE}', $data[0]['codejercicio'], $patron);
                  $patron = str_replace('{SERIE}', $data[0]['codserie'], $patron);
                  $patron = str_replace('{NUM}', $data[0]['numero'], $patron);
				return $patron;
				// PED{EJE}{SERIE}{NUM}
			}
			
			return null;
		}
		
		return null;
	}

	protected function save($pedido) {
		$insertFields = [];
        $insertValues = [];
		$dataBase = new DataBase();
		#die("OK");
		#die(var_dump($pedido->getModelFields()));
		foreach ($pedido->getModelFields() as $field) {
           	#if($field['name'] =='fecha') die(var_dump($pedido->{$field['name']}));
			if (isset($pedido->{$field['name']}) && $field['name'] != 'numero') {
                $fieldName = $field['name'];
				$fieldValue = $pedido->{$fieldName};
                #echo "fieldName: $fieldName <br>";
				#echo "value[]: $values[$fieldName] <br>";
                #echo "fieldValue: ". $this->{$fieldName};; 
				#if($fieldName=='fecha')die(var_dump($field));
			    $insertFields[] = $dataBase->escapeColumn($fieldName);
                $insertValues[] = $dataBase->var2str($fieldValue);
            
				if ($fieldName == 'fecha') {
				   $codejercicio = $this->getCodEjercicio($fieldValue, $dataBase);
			       $insertFields[] = $dataBase->escapeColumn('codejercicio');
			       $insertValues[] = $dataBase->var2str($codejercicio);					
				
				   $insertFields[] = $dataBase->escapeColumn('codigo');
			       $insertValues[] = $dataBase->var2str($this->getCodigo($codejercicio, $dataBase));					

				   $insertFields[] = $dataBase->escapeColumn('numero');
			       $insertValues[] = $dataBase->var2str($this->getNumero($codejercicio, $dataBase));					

				}
			}
        }

        $sql = 'INSERT INTO ' . $pedido->tableName() . ' (' . implode(',', $insertFields) . ') VALUES (' . implode(',', $insertValues). ');';	
		#echo "sql:";
		#echo $sql; exit; 
		#echo var_dump($dataBase->exec($sql));
        if ($dataBase->exec($sql)) {
		#	echo "lastval: ". $dataBase->lastval(); exit;
			#die(var_dump($dataBase->lastval()));
			return $dataBase->lastval();
        }
		
		return false;
	}

	
	private function importOrderTodoExternalTimeAbc() {
		#die("Ok");
		include(getcwd().'/Plugins/ImportadorPedidos/config.php');

		$dataBase = new DataBase();
		$data = $dataBase->select('SELECT MAX(numero2) AS start FROM pedidoscli;');
		if(!empty($data[0]["start"])){
			$min		=		(int)$data[0]["start"];
		}else{
			$min	=	0;
		}
		#echo "Inhabilitado por seguridad";
		#$min = 29069;
		$orders		=		$this->get_orders_oc($min);
		#echo var_dump($orders); exit;
		foreach($orders as $order) {
			#echo var_dump($order);
			$pedido		=		new PedidoCliente();
			$pedido->cifnif	=	'';		   
			$pedido->codcliente 		=	$this->getCliente($order);
			$pedido->codpago			=	pagos[$order->payment_code];
			$pedido->codalmacen		=	codalmacen;
			$pedido->coddivisa		=	coddivisa;
			$pedido->codpais			=	$order->country;
			$pedido->codserie		=	codserie;
			$pedido->idempresa		=	idempresa;
			$pedido->idestado		=	idestado;
			
			$pedido->nick		=		nick;
			
			$pedido->codpostal		=		$order->payment_postcode;
			$pedido->direccion		=		$order->payment_address_1;
			$pedido->provincia		=		$order->payment_city;
			
			$fecha					=		date('Y-m-d H:i:s', strtotime($order->date_added)); //explode(" ",$order->date_added);
			$pedido->fecha			=	 	date('Y-m-d', strtotime($fecha));
			$pedido->hora			=		date('H:i:s', strtotime($fecha));
			  
			/*$fecha					=		explode(" ",$order->date_added);
			$pedido->fecha			=		$fecha[0];
			$pedido->hora			=		$fecha[1];*/
			
			//$pedido->netosindto	=		'';
			$pedido->nombrecliente	=		$order->firstname . ' ' . $order->lastname;
			$pedido->numero2			=		$order->order_id;
			$pedido->observaciones	=		$order->comment;
			
			$tax			=		0;
			//$suplidos	=		0;
			$order_totals	=		array();
			
			foreach($order->totals as $total){
				// echo "total code: ". $total->code . "<br>";
				// echo "<br>total: " . var_dump($total);
				switch($total->code){
					case 'sub_total':
						$pedido->neto		=		$total->value;
						$pedido->netosindto	=		$total->value;
						break;
					case 'total':
						$pedido->total		=		$total->value;
						$pedido->totaleuros =		$total->value;
						break;
					case 'tax':
						$tax	+=	$total->value;
						break;
					default:
						$pedido->neto		+=		$total->value;
						$order_totals[$total->code]		=	$total->value;
						break;
						//$suplidos	+=	$total;
				}
			}
			$pedido->totaliva		=		$tax;	
			#echo "pedido: ". var_dump($pedido); 
			#echo var_dump($pedido->save());
			$idpedido = $this->save($pedido);
			if (false === $idpedido) {
				$this->toolBox()->i18nLog()->error('record-save-error');
				return true;
			}

			#$idpedido = $pedido->primaryColumnValue();

			foreach($order->order_products as $order_product){
				$lineapedido	=	new LineaPedidoCliente();
				$lineapedido->idpedido		=		$idpedido;

				$variante_nombre		=		'';
				if(sizeof($order_product->option)){
					$variante_nombre		=		$order_product->option[0]->value;
					$start	=	strpos($order_product->option[0]->value, ": ");
					$end		=	strpos($order_product->option[0]->value, " )");
					if($start !== false && $end !== false){					   
						$referencia		=		substr($order_product->option[0]->value, $start + 2, $end - $start - 1);
					}
				}else{
					$referencia		=		$order_product->model;
				}

				$variante 		= 		$this->getVariante($referencia, $order_product->product_id);

				$lineapedido->idproducto		=	$variante->idproducto;

				$lineapedido->referencia		=	(strlen($referencia)>30?substr($referencia,0,30):$referencia);
				$lineapedido->descripcion	=	$order_product->name . ' ' . $variante_nombre;
				$lineapedido->cantidad		=	$order_product->quantity;
				$lineapedido->pvpunitario	=	$order_product->price;
				$lineapedido->pvptotal		=	$order_product->total;

				$producto	=		new Producto();
				$producto->loadFromCode($variante->idproducto);
				$lineapedido->codimpuesto	=	$producto->codimpuesto;
				//$lineapedido->descripcion	=	$producto->descripcion;

				$impuesto	=		new Impuesto();
				$impuesto->loadFromCode($producto->codimpuesto);
				$lineapedido->iva			=		$impuesto->iva;
				$lineapedido->recargo		=		$impuesto->recargo;


				if ($lineapedido->iva == 21) {
					//$lineapedido->pvpunitario	=	$order_product->price/1.21;
					//$lineapedido->pvptotal		=	$order_product->total/1.21;
					$lineapedido->recargo		=   0;					
				}

				#echo "lineaPedido: <br>";
				#echo var_dump($lineapedido);
				if (false === $lineapedido->save()) {
					// $this->toolBox()->i18nLog()->error('record-save-error');
					return true;
				}		   
			}

			// echo var_dump($order_totals); exit;
			foreach($order_totals as $code => $value){
				$producto	=		new Producto();
				$producto->loadFromCode(constant($code.'cod'));	

				$lineapedido	=	new LineaPedidoCliente();
				$lineapedido->idpedido		=	$idpedido;
				$lineapedido->referencia		=	$producto->referencia;
				$lineapedido->descripcion	=	$producto->descripcion;
				//$lineapedido->cantidad		=	1;
				$lineapedido->pvpunitario	=	$value;
				$lineapedido->pvptotal		=	$value;

				$impuesto	=		new Impuesto();
				$impuesto->loadFromCode($producto->codimpuesto);

				//echo json_encode($producto);
				//echo json_encode($impuesto);

				$lineapedido->iva			=		$impuesto->iva;
				$lineapedido->recargo		=		$impuesto->recargo;

				//echo json_encode($lineapedido);
				//exit;

				if ($lineapedido->iva == 21) {
					// $lineapedido->pvpunitario	=	$value*1.21;
					// $lineapedido->pvptotal		=	$value*1.21;
					$lineapedido->recargo		=   0;					
				}
				if (false === $lineapedido->save()) {
					// $this->toolBox()->i18nLog()->error('record-save-error');
					return true;
				}
			}
			#die("Proceso Completado");
			#return true;
			#exit;
		}	   
		// $this->toolBox()->i18nLog()->notice('record-updated-correctly');

		return true; /// continuamos con la carga normal
	}
	
	private function getVariante($referencia, $product_id){
	   $variante = new Variante();
	   if(false === $variante->loadFromCode('', [ new DataBaseWhere('referencia', $referencia) ])){
		   //insertar producto
		   $product		=		$this->get_product($product_id);
		   $producto	=		new Producto();
			//die(var_dump($product));		   
		   if($product->category){
			   $familia = new Familia();
			   if(false === $familia->loadFromCode('', [ new DataBaseWhere('descripcion', $product->category) ])){
					 $familia->descripcion	=	$product->category;
					 if (false === $familia->save()) {	
							// $this->toolBox()->i18nLog()->error('record-save-error');
							return true;
					}
			   }
			   $producto->codfamilia	=	$familia->codfamilia;
		   }
		   
		   if($product->manufacturer_id){
		   		$fabricante		=		new Fabricante();
			   if(false === $fabricante->loadFromCode('', [ new DataBaseWhere('nombre', $product->mannufacturer) ])){
					 $fabricante->nombre	=	$product->manufacturer;
					 if (false === $fabricante->save()) {	
							//$this->toolBox()->i18nLog()->error('record-save-error');
							return true;
					}
			   }
			   $producto->codfabricante	=	$fabricante->codfabricante;			   
		   }		   

		   $producto->descripcion	=	$product->name;
		   $producto->referencia	=	$referencia;
		   $producto->precio		=	$product->price;
		   $producto->codimpuesto	=	array_search($product->tax_class_id, taxes);
		   $producto->stockfis		=	$product->quantity;

		   if (false === $producto->save()) {	
				//$this->toolBox()->i18nLog()->error('record-save-error');
				return true;
			}
	   }
	}
	
	private function getCliente($order){
	   $cliente = new Cliente();
	   $flagRegCliente = false;
	   //echo "orden: ". json_encode($order);
	   if(false === $cliente->loadFromCode('', [ new DataBaseWhere('telefono2',$order->customer_id) ]) || $order->customer_id == '0'){
		   $customer	=	$this->get_customer($order->customer_id);
		   // echo "<br>Customer <br>". var_dump($customer); exit
		   //crear cliente
		   if ($order->customer_id == '0') {
		   		if (false === $cliente->loadFromCode('', [ new DataBaseWhere('email',$order->email) ]) ) {
					$cliente->razonsocial	=	$order->firstname . ' ' . $order->lastname;
					$cliente->nombre		=	$order->firstname . ' ' . $order->lastname;
					$cliente->telefono1 	=	$order->telephone;
					$cliente->telefono2 	=	$order->customer_id;
					$cliente->email			=	$order->email;
					$cliente->cifnif		=	$order->cifnif;
					$flagRegCliente = true;
			    }
		   } else {
				$cliente->razonsocial	=	$customer->firstname . ' ' . $customer->lastname;
				$cliente->nombre		=	$customer->firstname . ' ' . $customer->lastname;
				$cliente->telefono1 	=	$customer->telephone;
				$cliente->telefono2 	=	$order->customer_id;
				$cliente->email			=	$customer->email;
		    	$cliente->cifnif		=	$order->cifnif;
			    $flagRegCliente = true;
		   }		   

		   if ($flagRegCliente) {			   
			   if (false === $cliente->save()) {
					#$this->toolBox()->i18nLog()->error('record-save-error');
					return true;
			   }

			   
			   $contacto	=		new Contacto();
			   $contacto->loadFromCode($cliente->idcontactofact);
			   $contacto->apellidos		=		$order->payment_lastname;
			   $contacto->nombre		=		$order->payment_firstname;
			   $contacto->ciudad		=		$order->payment_city;
			   $contacto->codpostal		=		$order->payment_postcode;
			   $contacto->codcliente	=		$cliente->codcliente;
			   $contacto->descripcion	=		$order->payment_firstname . ' ' . $order->payment_lastname;
			   $contacto->direccion		=		$order->payment_address_1;
			   $contacto->empresa		=		$order->company;
			   $contacto->telefono2		=		'';
			   $contacto->codpais		=		$order->country;

				if (false === $contacto->save()) {
					// $this->toolBox()->i18nLog()->error('record-save-error');
					return true;
				}			   
		   } else {
		 		// $this->toolBox()->i18nLog()->error('record-save-error');
			   return $cliente->codcliente;
		   }
	   }
	   return $cliente->codcliente;
	}
	
	
	private function get_token_oc(){
		$url = source_oc_api_login;
		$post = array (
		  'username' => api_username,
		  'key' =>  api_key
		);

		$curl = curl_init();
		curl_setopt_array( $curl, array(
		  CURLOPT_URL => $url,
		  CURLOPT_RETURNTRANSFER=> TRUE,
		  CURLOPT_POSTFIELDS      => $post
		) );	

		$raw_response = curl_exec($curl);
		$response = json_decode($raw_response);
		curl_close($curl);		//die(var_dump($raw_response));
		$api_token = $response->api_token;
		return $api_token;
	}

	private function get_product($product_id){
		$api_token	=	$this->get_token_oc();
		$url		=	source_oc_api_product . "&api_token=".$api_token . "&product_id=".$product_id;
		$curl = curl_init();
		curl_setopt_array($curl, array(
			CURLOPT_URL => $url,
			CURLOPT_RETURNTRANSFER => true,
		));

		$raw_response = curl_exec($curl);
		$err = curl_error($curl);
		curl_close($curl);		//die(var_dump($raw_response));
		if ($err) {	
			return false;
		} else {
			$response	=	json_decode($raw_response);
			return $response->product;
		}		  
	}
	
	private function get_customer($customer_id){
		$api_token	=	$this->get_token_oc();
		$url		=	source_oc_api_customer . "&api_token=".$api_token . "&customer_id=".$customer_id;
		$curl = curl_init();
		curl_setopt_array($curl, array(
			CURLOPT_URL => $url,
			CURLOPT_RETURNTRANSFER => true,
		));

		$raw_response = curl_exec($curl);
		$err = curl_error($curl);
		curl_close($curl);
		//echo die(var_dump($raw_response));

		if ($err) {	
			return false;
		} else {
			$response	=	json_decode($raw_response);
			return $response->customer;
		}		  
	}

	private function get_orders_oc($min){
		$api_token	=	$this->get_token_oc();
		$url		=	source_oc_api_order . "&api_token=".$api_token . "&min=".$min;

		$curl = curl_init();
		curl_setopt_array($curl, array(
			CURLOPT_URL => $url,
			CURLOPT_RETURNTRANSFER => true,
		));

		$raw_response = curl_exec($curl);	//die(var_dump($raw_response));
		$err = curl_error($curl);
		curl_close($curl);
		if ($err) {	
			return false;
		} else {
			$response	=	json_decode($raw_response);
			return $response->orders;
		}
	}


}

?>