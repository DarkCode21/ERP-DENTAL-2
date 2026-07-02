<?php
define('codalmacen','ALG');
define('coddivisa','EUR');
	   //$codpais			=		'ESP';
define('codserie', 'A');
define('idempresa',1);
define('idestado',4);
define("nick", "admin");
//arreglo de correspondencia entre impuestos en facturascript y opencart
//indice = codimpuesto facturascript
//valor = tax_class_id opencart
//$taxes[indice] = valor
$taxes['IGIC3']	=	0;
$taxes['IGIC7']	=	0;
$taxes['IVA0']	=	0;
$taxes['IVA4']	=	12;
$taxes['IVA10']	=	11;
$taxes['IVA21']	=	10;
define('taxes', $taxes);

//$codpago - crear arreglo que correlacione pagos facturascript con pagos en open
$pagos['cod']	=	'1';
$pagos['bank_transfer']	=	'TRANS';	   
$pagos['pp_standard']	=	'PAYPAL';
$pagos['pp_express']	=	'PAYPAL';
$pagos['cheque']	=	'1';	   
$pagos['redsys']	=	'TARJETA';	   
define('pagos', $pagos);

define('shippingcod', 107);
define('xfeeprocod', 145); #108
#define('xpaypalprocod', 145);

define('api_username', 'interiberica');					//usuario de api en OC
define('api_key','us5h4yqrVLHLcjLWDT2EXSzK0GzEYt3ovzy0ioL5sk72WaGfBNktuevDAbtDiKGhwbYaFrvdVOP6SXE77KNQuCdNBIykJKD1o8QLZ3YUGMggqygHUu4tZRgfGeWVD64qBnyz4BGZteZJR4QI8TFeRvZ1wcHEskrvWNvu4frlMb7h2kNQsfMUZwZWnu4GpGH0boNuPcyaC1GHrFUpjBtoZlQyn2Ui6ReP8LvjwF4QbxZg0guGqQZ2j0IdfVplogvC'); // wuehGFLdp2X8FV4KojAFamenEV0qxnGoYVldA7ds3Oe12hk3X17uRXmwwFt7HHkWGgackpuQhcabtRtKKYUvigjMuZZmg8WgrIIvLLD7Bt8e6dSp0KoYJmVlYvA7E4Jk7zbmnk6Wcw0lj4ftyaazk60hE8IioMqMfXrkWYRWeKqSdkxCpxFbYZT7QcHEjC83gOV3wuK221cnsHSCtYvUnjhpVZQqVXFEawB3CbiStGDdzrGbu377gdWkhXtOJv7B
define('source_oc', 'https://www.opticabegiristain.com/'); // https://www.etienda.com.es/	
define('source_oc_api_order', source_oc . 'index.php?route=api/order/list');
define('source_oc_api_login', source_oc . 'index.php?route=api/login');	
define('source_oc_api_customer', source_oc . 'index.php?route=api/order/getCustomer');		
define('source_oc_api_product', source_oc . 'index.php?route=api/order/getProduct');				

?>