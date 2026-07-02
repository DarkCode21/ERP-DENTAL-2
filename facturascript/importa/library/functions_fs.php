<?php
$mysqli = new mysqli(FS_DB_HOST, FS_DB_USER, FS_DB_PASS, FS_DB_NAME);

if ($mysqli->connect_errno) {
   die("error de conexión: " . $mysqli->connect_error);
}

$mysqli->set_charset("utf8");


function getLastOrderId(){
	global $mysqli;
	$sql	=	"SELECT MAX(CAST(numero2 AS INT)) AS last FROM pedidoscli";
	$result = 	$mysqli->query($sql);

	$row	=	$result->fetch_array();
	return (int)$row['last'];
}

function get_resource_fs($data){
	global $mysqli;
	extract($data);
	$sql	=	"SELECT " . $column . " FROM " . $table;
	if($filterkey){
		$sql	.=		" WHERE " . $filterkey . " = '".  $filtervalue. "'";
	}
	$result = 	$mysqli->query($sql);
	if( $result->num_rows ){
		$row	=	$result->fetch_array();	
		return $row[$column];
	}
	return false;
}


function getCodEjercicio($fecha) {
	global $mysqli;
	$sql	=	"SELECT codejercicio FROM ejercicios WHERE fechainicio <= '$fecha' AND fechafin >= '$fecha';";
	$result = 	$mysqli->query($sql);
	if( $result->num_rows ){	
		$row	=	$result->fetch_array();	
		return $row['codejercicio'];
	}
	return false;
}

function getCodigo($codejercicio, $type='PedidoCliente') {
	global $mysqli;
	$sql	=	"SELECT * FROM secuencias_documentos WHERE tipodoc='$type' AND codejercicio='$codejercicio'";
	$result = 	$mysqli->query($sql);

	if( $result->num_rows ){	
		$row	=	$result->fetch_array();
		$max = (int)$row['numero'] + 1;
		
		$sql_update	=	"UPDATE secuencias_documentos SET numero = $max WHERE tipodoc='$type' AND codejercicio='$codejercicio'";
		$mysqli->query($sql_update) or die("Error: " . $sql_update . "<br>" . $mysqli->error);	
	 	
		$patron = $row['patron'];
		$patron = str_replace('{EJE}', $row['codejercicio'], $patron);
		$patron = str_replace('{SERIE}', $row['codserie'], $patron);
		$patron = str_replace('{NUM}', $row['numero'], $patron);
		return $patron;
	}
		
	return null;
}

function getMaxContactId(){
	global $mysqli;
	$sql	=	"SELECT MAX(CAST(idcontacto AS INT)) AS max FROM contactos";
	$result = 	$mysqli->query($sql);

	$row	=	$result->fetch_array();	
	return (int)$row['max'];
}

function getMaxClientId(){
	global $mysqli;
	$sql	=	"SELECT MAX(CAST(codcliente AS INT)) AS max FROM clientes";
	$result = 	$mysqli->query($sql);

	$row	=	$result->fetch_array();	
	return (int)$row['max'];
}

function getMaxFabricanteId(){
	global $mysqli;
	$sql	=	"SELECT MAX(CAST(codfabricante AS INT)) AS max FROM fabricantes";
	$result = 	$mysqli->query($sql);

	$row	=	$result->fetch_array();	
	return (int)$row['max'];
}

function add_atributo($nombre){
	global $mysqli;
	$sql		=		"INSERT INTO atributos SET codatributo = '" . strtolower($nombre) . "', nombre = '" . $mysqli->real_escape_string($nombre) ."'";
	$mysqli->query($sql) or die("Error: " . $sql . "<br>" . $mysqli->error);
	return strtolower($nombre);
}

function add_atributovalor($valor, $nombre){
	global $mysqli;
	$sql		=		"INSERT INTO atributos_valores SET codatributo = '" . $mysqli->real_escape_string(strtolower($nombre)) . "', descripcion = '" . $mysqli->real_escape_string($nombre) . " " . $valor ."', valor = '" . $valor ."'";
	$mysqli->query($sql) or die("Error: " . $sql . "<br>" . $mysqli->error);
	return	$mysqli->insert_id;
}

function add_cliente_fs($cliente, $address){
	global $mysqli;

	$razonsocial	=	$mysqli->real_escape_string($cliente->firstname . ' ' . $cliente->lastname);
	$nombre			=	$mysqli->real_escape_string($cliente->firstname . ' ' . $cliente->lastname);
	$telefono1		=	'';
	$telefono2		=	(int)$cliente->id;
	$email			=	$cliente->email;
	$idcontacto		=	getMaxContactId() + 1;
	$codcliente		=	getMaxClientId() + 1;
			
	$sql	=	"INSERT INTO clientes SET codcliente = '" . $codcliente . "', razonsocial = '" . $razonsocial . "', nombre = '" . $nombre ."', telefono1 = '" . $telefono1 ."', telefono2 = '" . $telefono2 ."', email = '" . $email . "', idcontactofact = '" . $idcontacto . "', cifnif =''";
	$mysqli->query($sql) or die("Error: " . $sql . "<br>" . $mysqli->error);
	
	//contacto
	$apellidos		=	$mysqli->real_escape_string($address->lastname);
	$nombre			=	$mysqli->real_escape_string($address->firstname);
	$ciudad			=	$mysqli->real_escape_string($address->city);
	$codpostal		=	$mysqli->real_escape_string($address->postcode);
	$descripcion	=	$nombre . ' '. $apellidos;
	$direccion		=	$mysqli->real_escape_string($address->address1);
	$empresa		=	$mysqli->real_escape_string($address->company);
	$telefono1		=	$mysqli->real_escape_string($address->phone);
	$telefono2		=	$mysqli->real_escape_string($address->phone_mobile);
	$codpais		=	'ES';
	
	$sql		=		"INSERT INTO contactos SET idcontacto = '" . (int)$idcontacto . "', codcliente = '" . $codcliente . "', apellidos = '" . $apellidos . "', nombre = '" . $nombre . "', ciudad = '" . $ciudad . "', codpostal = '" . $codpostal . "', descripcion = '" . $descripcion . "', direccion = '" . $direccion . "', empresa = '" . $empresa . "', email = '" .  $email . "', telefono1 = '" . $telefono1 . "', telefono2 = '" . $telefono2 . "', codpais = '" . $codpais . "' 	";

	$mysqli->query($sql) or die("Error: " . $sql . "<br>" . $mysqli->error);	
	return $codcliente;		
}


function add_pedido_fs($codcliente, $order, $address){
	global $mysqli;
		$codalmacen		=		codalmacen;
		$coddivisa		=		coddivisa;
		$codpais		=		codpais;
		$codserie		=		codserie;
		$idempresa		=		idempresa;
		$idestado		=		idestado;
	   	$nick			=		nick;		
		
		$codpago		=		pagos[trim($order->module)];
		$codpostal		=		$address->postcode;
		$direccion		=		$address->address1;
		$provincia		=		$address->city;
		
		$fecha			=	 	date('Y-m-d', strtotime($order->date_add));
		$hora			=		date('H:i:s', strtotime($order->date_add));					   
		$nombrecliente	=		$address->firstname . ' ' . $address->lastname;
		$numero2		=		$order->id;
		$observaciones	=		$order->note;
		$codejercicio	=		getCodEjercicio($fecha);
		$codigo			=		getCodigo($codejercicio);
	
		$neto		=		(float)$order->total_products - (float)$order->total_discounts_tax_incl;
		$netosindto	=		(float)$order->total_products;
		$total		=		(float)$order->total_products_wt - (float)$order->total_discounts_tax_incl + (float)$order->total_shipping_tax_incl;
		$totaleuros =		$total;	
		$totaliva	=		(float)$order->total_products_wt - (float)$order->total_products;	
	
		$sql		=		"INSERT INTO pedidoscli SET codigo = '" . $codigo . "', codalmacen = '" . $codalmacen . "', coddivisa = '" . $coddivisa . "', codpais = '" . $codpais . "', codserie = '" . $codserie . "', codejercicio = '" . $codejercicio ."', idempresa = '" . $idempresa . "', idestado = '" . $idestado . "', nick = '" . $nick . "', codcliente = '" . $codcliente . "', codpago = '" . $codpago . "', codpostal = '" . $codpostal . "', direccion = '" . $mysqli->real_escape_string($direccion) . "', provincia = '" . $mysqli->real_escape_string($provincia) . "', fecha = ' " . $fecha . "', hora = '" . $hora . "', nombrecliente = '" . $nombrecliente . "', numero2 = '" . $numero2. "', observaciones = '" . $mysqli->real_escape_string($observaciones) . "', neto = '" . $neto . "', netosindto = '" . $netosindto . "', total = '" . $total . "', totaleuros = '" . $totaleuros . "', totaliva = '" . $totaliva . "', cifnif ='', numero = ''";
		$mysqli->query($sql) or die("Error: " . $sql . "<br>" . $mysqli->error);	
		$idpedido	=	$mysqli->insert_id;
	
echo 'pedido :'. $idpedido . '<br>';

		foreach($order->associations->order_rows->order_row as $detail){
			$order_row		=	get_resource_by_id_ps(['resources' => 'order_details',  'resource' => 'order_detail', 'id' => $detail->id]);
			$product_id		=	false;

			if( $product		= 	get_resource_ps([ 'resource' => 'products', 'filterkey' => 'id', 'filtervalue' => (int)$order_row->product_id, 'display' => 'ean13, reference' ]) ) {
				//bucar producto en facturasscript
				$producto		=	get_variante_fs($product, (int)$order_row->product_id, (int)$order_row->product_attribute_id);
//var_dump($producto);				
				$idproducto		=	$producto["idproducto"];
				$referencia		= 	$producto["referencia"]; 		
				   
				$descripcion	=	$order_row->product_name;
				$cantidad		=	$order_row->product_quantity;				
				$pvpunitario	=	$order_row->product_price;
				$pvptotal		=	$pvpunitario * $cantidad;				
				
				$impuesto		=	get_impuesto_fs($producto["codimpuesto"]);				
				if($producto["codimpuesto"]  == 'IVA21'){
					$recargo	=	0;
				}else{
					$recargo	=	$impuesto["recargo"] ;
				}
				
				$sql		=		"INSERT INTO lineaspedidoscli SET cantidad = '" . $cantidad . "', codimpuesto = '" . $producto["codimpuesto"] ."', descripcion = '" . $mysqli->real_escape_string($descripcion) ."', idpedido = '" . $idpedido ."', idproducto = '" . $idproducto ."', iva = '" . $impuesto["iva"] ."', pvpunitario = '" . $pvpunitario . "', pvptotal = '" . $pvptotal . "', recargo = '" . $recargo ."', referencia = '" . $referencia ."', dtopor = '0', pvpsindto = '0'";
				$mysqli->query($sql) or die("Error: " . $sql . "<br>" . $mysqli->error);				
			}
		}
	
		if($order->total_shipping){
			$envio			=	get_producto_envio_fs();
			$impuesto		=	get_impuesto_fs($envio['codimpuesto']);			
			$sql		=		"INSERT INTO lineaspedidoscli SET cantidad = '1', codimpuesto = '" . $envio['codimpuesto'] ."', descripcion = '" . $mysqli->real_escape_string($envio['descripcion']) . "', idpedido = '" . $idpedido ."', idproducto = '" . $envio['idproducto'] ."', iva = '" . $impuesto["iva"] ."', pvpunitario = '" . $order->total_shipping . "', pvptotal = '" . $order->total_shipping . "', recargo = '" . $impuesto["recargo"] ."', referencia = '" . $envio['referencia'] ."', dtopor = '0', pvpsindto = '0'";
			$mysqli->query($sql) or die("Error: " . $sql . "<br>" . $mysqli->error);
		}
}


function get_producto_envio_fs(){
	global $mysqli;
	$sql	=	"SELECT * FROM productos WHERE idproducto = '" . idenvio . "';";
	$result = 	$mysqli->query($sql);
	if( $result->num_rows ){	
		$row	=	$result->fetch_array();	
		return $row;
	}
	return false;
}

function get_variante_fs($product, $product_id, $product_attribute_id){
	global $mysqli;
	if($product_attribute_id){
		//producto con combinaciones
		$referencia	=	$product->reference . "-" . $product_attribute_id;
	}else{
		//producto sin combinaciones
		$referencia	=	$product->reference;
	}
	
	$sql	=		"SELECT p.idproducto, codimpuesto FROM variantes v INNER JOIN productos p ON p.idproducto = v.idproducto WHERE v.referencia = '" . $referencia . "'";
//echo $sql . '<br>';	
	$result = 	$mysqli->query($sql);
	if( $result->num_rows ){
		//existe variante
		$row	=	$result->fetch_array();	
		return [	'idproducto'	=>	$row['idproducto'],  'referencia'	=>	$referencia, 	'codimpuesto'	=>	$row['codimpuesto']	];
	}else{
		//no existe variante
		$sql		=	"SELECT p.idproducto, codimpuesto FROM variantes v INNER JOIN productos p ON p.idproducto = v.idproducto  WHERE codbarras = '" . $product_id . "'";

		$result 	= 	$mysqli->query($sql);
		if( $result->num_rows ){
			//existe producto
			$row			=	$result->fetch_array();	
			$idproducto		=	$row['idproducto'];	
			$codimpuesto	=	$row['codimpuesto'];	
			$nuevo			=	false;
		}else{
			//no existe producto
			$nuevo			= 	true;
			$psproduct		=	get_resource_by_id_ps([ 'resources' => 'products',  'resource' => 'product', 'id' => $product_id ]);

			$descripcion	=	$psproduct->name->language;
			$referencia		=	$referencia;
			$precio			=	(float)$psproduct->price;
//			$codimpuesto	=	array_search($psproduct->id_tax_rules_group, taxes);
        	$codimpuesto	=	taxes[(int)$psproduct->id_tax_rules_group];
			$stockfis		=	(int)$psproduct->quantity;	
        
			//fabricante
			$codfabricante	=	get_fabricante_fs($psproduct->id_manufacturer);

			$sql		=		"INSERT INTO productos SET descripcion = '" . $mysqli->real_escape_string($descripcion) . "', referencia = '" . $mysqli->real_escape_string($referencia) . "', precio = '" . $precio ."', codfamilia = '". codfamilia . "', codimpuesto = '" . $codimpuesto . "', codfabricante = '" . $codfabricante . "', stockfis = '" . $stockfis . "', actualizado = NOW()";
			$mysqli->query($sql) or die("Error: " . $sql . "<br>" . $mysqli->error);			
			$idproducto	=	$mysqli->insert_id;
		}
		
		//variantes
		$pscombination	=	get_resource_by_id_ps([ 'resources' => 'combinations',  'resource' => 'combination', 'id' => $product_attribute_id ]);		
		$precio			=	(float)$pscombination->price;
		$stockfis		=	(int)$pscombination->quantity;	

    	if($pscombination->associations->product_option_values->product_option_value){
			//producto con combinaciones        
        	$i 	=	0;
			$sql		=		"INSERT INTO variantes SET ";
			if($nuevo)		$sql	.=		" codbarras = '" . $product_id . "', ";
			$sql	.=		" idproducto = '" . $idproducto . "', precio = '" . $precio ."', referencia = '" . $referencia ."', stockfis = '". $stockfis ."' ";			
			foreach($pscombination->associations->product_option_values->product_option_value as $product_option_value){
				$i++;
				$idatributovalor	=	get_atributovalor_fs($product_option_value);
				$sql		.=		" , idatributovalor" . $i ." = '" . $idatributovalor. "'";
			}
//echo $sql . '<br>';
			$mysqli->query($sql) or die("Error: " . $sql . "<br>" . $mysqli->error);
		}else{
			//producto simple
			$sql		=		"INSERT INTO variantes SET ";
			if($nuevo)		$sql	.=		" codbarras = '" . $product_id . "', ";
			$sql	.=		" idproducto = '" . $idproducto . "', precio = '" . $precio ."', referencia = '" . $referencia ."', stockfis = '". $stockfis ."' ";			
//			$sql		.=		" , idatributovalor" . $i ." = '" . $idatributovalor. "'";
//echo $sql . '<br>';
			$mysqli->query($sql) or die("Error: " . $sql . "<br>" . $mysqli->error);			
		}
		return ['idproducto'	=>	$idproducto,  'referencia'	=>	$referencia,	'codimpuesto'	=> 	$codimpuesto];
	}	
}

function get_atributo_fs($nombre){
	global $mysqli;
	$sql	=	"SELECT codatributo FROM atributos WHERE LOWER(codatributo) = '" . strtolower($nombre) ."'";
	$result = 	$mysqli->query($sql);
	if( $result->num_rows ){
		$row	=	$result->fetch_array();	
		return $row['codatributo'];
	}else{
		$codatributo	=	add_atributo($nombre);
		return $codatributo;
	}
}

function get_atributovalor_fs($product_option_value){
	global $mysqli;	
	//echo ($product_option_value->id);
	$pspov	=	get_resource_by_id_ps([ 'resources' => 'product_option_values',  'resource' => 'product_option_value', 'id' => $product_option_value->id ]);	
	//echo ($product_option_value->id_attribute_group);
	$pspo	=	get_resource_by_id_ps([ 'resources' => 'product_options',  'resource' => 'product_option', 'id' => $pspov->id_attribute_group ]);		
	
	$sql	=	"SELECT id FROM `atributos_valores` av INNER JOIN atributos a ON a.codatributo = av.codatributo WHERE LOWER(a.nombre) = '" . strtolower($pspo->name->language) ."' AND LOWER(valor) = '" . strtolower($pspov->name->language) ."'";	
	//echo "##" . $pspo->name->language. "##" . $pspov->name->language . "<br>";
	
	$result = 	$mysqli->query($sql);
	if( $result->num_rows ){
		$row	=	$result->fetch_array();	
		return $row['id'];		
	}else{
		//atributo
		$codatributo		=	get_atributo_fs($pspo->name->language);
		//atributovalor
		$idatributovalor	=	add_atributovalor($pspov->name->language, $pspo->name->language);
		return $idatributovalor;
	}
}

function get_fabricante_fs($id_manufacturer){
	global $mysqli;
	
	$psmanufacturer	=	get_resource_ps([ 'resource' => 'manufacturers', 'filterkey' => 'id', 'filtervalue' => (int)$id_manufacturer, 'display' => 'name' ]);
	
	$nombre	=		$psmanufacturer->name;
	$sql	=		"SELECT codfabricante FROM fabricantes WHERE nombre = '" . $nombre . "'";
	$result = 		$mysqli->query($sql);

	if( $result->num_rows ){
		$row	=	$result->fetch_array();	
		return $row['codfabricante'];		
	}else{
		$codfabricante	=	getMaxFabricanteId() + 1;
		$sql	=	"INSERT INTO fabricantes SET codfabricante = '". $codfabricante . "', nombre = '" . $mysqli->real_escape_string($nombre) . "'";
		$mysqli->query($sql) or die("Error: " . $sql . "<br>" . $mysqli->error);
		return $codfabricante;
	}
}

function get_impuesto_fs($codimpuesto){
	global $mysqli;
	$sql	=	"SELECT * FROM impuestos WHERE codimpuesto = '" . $codimpuesto ."'";
	$result = 		$mysqli->query($sql);

	if( $result->num_rows ){
		$row	=	$result->fetch_array();	
		return $row;		
	}	
	return false;
}

?>