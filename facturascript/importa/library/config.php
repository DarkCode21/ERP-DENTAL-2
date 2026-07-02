<?php
//constantes prestashop
define('source','https://www.prestatienda.com.es/');
define('source_api','https://www.prestatienda.com.es/api/');
define('ps_key', 'VNAT119E266VGFP47WG4187VCQ664G4G');

//constantes en facturascript
define('codalmacen','ALG');
define('coddivisa','EUR');
define('codfamilia',12);
define('codpais','ESP');
define('codserie', 'A');
define('idempresa',1);
define('idestado',4);
define('nick', "admin");
//arreglo de correspondencia entre impuestos en facturascript y prestashop
//indice = id_tax_rules_group
//valor = codimpuesto facturascript
//$taxes[indice] = valor
$taxes[1]	=	'IVA21';
define('taxes', $taxes);

//arreglo de correspondencia entre pagos facturascript con pagos en prestashop
//indice = module prestashop
//valor = id de pago en facturascript
$pagos['ps_checkout']	=	'1';
$pagos['ps_wirepayment']	=	'TRANS';	   
$pagos['ps_checkpayment']	=	'1';	   
//$pagos['pp_standard']	=	'PAYPAL';
//$pagos['pp_express']	=	'PAYPAL';
//$pagos['redsys']	=	'TARJETA';	   
define('pagos', $pagos);

//gastos de envío
define('idenvio', 107);
?>