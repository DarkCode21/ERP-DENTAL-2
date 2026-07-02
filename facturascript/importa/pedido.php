<?php
if( isset($_REQUEST['xcode']) and $_REQUEST['xcode'] == 'fN88!Y4m' ){
	require_once '../config.php';
	
	require_once 'library/config.php';
	require_once 'library/functions_ps.php';
	require_once 'library/functions_fs.php';
	
	$maximo	=	getLastOrderId();

	$data['resources']	=	'orders';
	$data['resource']	=	'order';
	$data['filterkey']	=	'id';
	$data['filtervalue']	=	'>['.$maximo.']';	

	$resources	=	get_resources_ps($data);
	foreach($resources as $resource){	
		$attributes = 	$resource->attributes();
		$data['id'] = 	$attributes['id'];	
		$order		=	get_resource_by_id_ps($data);
		
		$address_id	=	($order->id_address_invoice <> 0) ? $order->id_address_invoice : $order->id_address_delivery;
		
		$datacustomer['resources']	=	'customers';
		$datacustomer['resource']	=	'customer';			
		$datacustomer['id'] 		= 	(int)$order->id_customer;	
		
		$customer			=	get_resource_by_id_ps($datacustomer);
		$address			=	get_resource_by_id_ps(['resources'	=>	'addresses',	'resource'	=>	'address',	'id'	=>	$address_id]);

		if( !$codcliente	=	get_resource_fs(['table' => 'clientes', 'column' => 'codcliente', 'filterkey' => 'telefono2', 'filtervalue' => (int)$order->id_customer])){
			if( !$codcliente	=	get_resource_fs(['table' => 'clientes', 'column' => 'codcliente', 'filterkey' => 'email', 'filtervalue' =>  $customer->email])){
				$cliente 	=	get_resource_by_id_ps(['resources'	=>	'customers', 'resource'	=>	'customer', 'id' => $order->id_customer]);
				$codcliente =	add_cliente_fs($cliente, $address);
			}
		}
		
		//pedido
		$idpedido	 =	add_pedido_fs($codcliente, $order, $address);
	}
}
?>