<?php
define('codalmacen','ALG');
define('codFamiliaTienda','24');
define('codFamiliaTiendaOn', '13');
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
define('xfeeprocod', 108);

define('key_api_exp', '#@inter#$89単.'); // API KEY EXPORTACION DE DETALLES
define('key_api_exp_prod', '2023prods'); // API KEY EXPORTACION DE DETALLES
define('api_username', 'interiberica');					//usuario de api en OC
define('api_key','VCDbZVbjSe5fwrO7Y7YDjeWavNprc8sgj84WdDRCNpPW8k5dseI0TMVhPGeyQ5mzdBlTLmHHpgs5VtFbeiUoKA0BoJl7gUQke5FzVFg1RG2cklJK4L3Iij7xM4Kz8mkACLf8PNfAOaMKJtw4yWXvRBlwFbaanqViN0SfH5d88glNc7VrNQYqeSb3xiagrJ1d8QooElQne7tMU1EWxvmandh47WDOUyCs1R3m5nAZvNqEuZduXIggfIzP5bUEczNS'); // wuehGFLdp2X8FV4KojAFamenEV0qxnGoYVldA7ds3Oe12hk3X17uRXmwwFt7HHkWGgackpuQhcabtRtKKYUvigjMuZZmg8WgrIIvLLD7Bt8e6dSp0KoYJmVlYvA7E4Jk7zbmnk6Wcw0lj4ftyaazk60hE8IioMqMfXrkWYRWeKqSdkxCpxFbYZT7QcHEjC83gOV3wuK221cnsHSCtYvUnjhpVZQqVXFEawB3CbiStGDdzrGbu377gdWkhXtOJv7B
define('source_oc', 'https://www.libreriacentral.eu/'); //https://www.etienda.com.es/	
define('source_oc_api_order', source_oc . 'index.php?route=api/order/list');
define('source_oc_api_products', source_oc . 'index.php?route=api/product/list');
define('source_oc_api_login', source_oc . 'index.php?route=api/login');	
define('source_oc_api_customer', source_oc . 'index.php?route=api/order/getCustomer');		
define('source_oc_api_product', source_oc . 'index.php?route=api/order/getProduct');				

# VARIABLES ENTORNO DESARROLLO
define('source_fs_api', 'https://dev.erpcloud.com.es/api/3/'); #https://dev2.facturascloud.com.es
define('source_fs_pedidos', source_fs_api .'pedidoclientes/');
define('source_fs_pedidos_list', source_fs_api .'pedidoclientes?filter[numero2]=');
define('source_fs_linea_pedidos', source_fs_api .'lineapedidoclientes?filter[idpedido]=');
define('source_fs_clientes', source_fs_api .'clientes/');
define('source_fs_contactos', source_fs_api .'contactos/');
define('source_fs_series', source_fs_api .'series');
define('source_fs_serie_uq', source_fs_api .'series?filter[codserie]=');
define('source_fs_impuesto_zonas', source_fs_api .'impuestozonas');
define('source_fs_proveedores', source_fs_api .'proveedores?filter[cifnif]=');
define('source_fs_impuesto', source_fs_api.'impuestos?filter[codimpuesto]=');
define('source_fs_provincia', source_fs_api.'provincias?filter[codisoprov]=');
define('source_fs_numeracion', source_fs_api.'secuenciadocumentos?filter[codserie]=');
define('source_fs_asiento', source_fs_api.'asientos?sort[idasiento]=DESC&limit=1');
define('source_fs_factura', source_fs_api.'facturaclientes?filter[codigo]=');
define('source_fs_factura_list', source_fs_api.'facturaclientes?filter[numero2]=');

define('source_fs_save_linea_pedidos', source_fs_api .'lineapedidoclientes');
define('source_fs_save_asiento', source_fs_api.'asientos');
define('source_fs_save_factura', source_fs_api.'facturaclientes');
define('source_fs_save_linea_factura', source_fs_api.'lineafacturaclientes');
define('source_fs_save_numeracion', source_fs_api.'secuenciadocumentos');
define('source_fs_save_transformation', source_fs_api.'doctransformations');
define('source_fs_save_pedidos', source_fs_api .'pedidoclientes');
define('source_fs_update_factura', source_fs_api .'facturaclientes');
define('source_fs_product', source_fs_api.'productos');
define('source_fs_variant', source_fs_api.'variantes');
define('source_fs_stock', source_fs_api.'stocks');

# VARIABLES ENTORNO REAL
define('source_fs_api_r', 'https://dev.erpcloud.com.es/api/3/');
define('source_fs_pedidos_r', source_fs_api_r .'pedidoclientes/');
define('source_fs_pedidos_list_r', source_fs_api_r .'pedidoclientes?filter[numero2]=');
define('source_fs_linea_pedidos_r', source_fs_api_r .'lineapedidoclientes?filter[idpedido]=');
define('source_fs_clientes_r', source_fs_api_r .'clientes/');
define('source_fs_contactos_r', source_fs_api_r .'contactos/');
define('source_fs_series_r', source_fs_api_r .'series');
define('source_fs_serie_uq_r', source_fs_api_r .'series?filter[codserie]=');
define('source_fs_impuesto_zonas_r', source_fs_api_r .'impuestozonas');
define('source_fs_proveedores_r', source_fs_api_r .'proveedores?filter[cifnif]=');
define('source_fs_impuesto_r', source_fs_api_r.'impuestos?filter[codimpuesto]=');
define('source_fs_provincia_r', source_fs_api_r.'provincias?filter[codisoprov]=');
define('source_fs_numeracion_r', source_fs_api_r.'secuenciadocumentos?filter[codserie]=');
define('source_fs_asiento_r', source_fs_api_r.'asientos?sort[idasiento]=DESC&limit=1');
define('source_fs_factura_r', source_fs_api_r.'facturaclientes?filter[codigo]=');
define('source_fs_factura_list_r', source_fs_api_r.'facturaclientes?filter[numero2]=');

define('source_fs_save_linea_pedidos_r', source_fs_api_r .'lineapedidoclientes');
define('source_fs_save_asiento_r', source_fs_api_r.'asientos');
define('source_fs_save_factura_r', source_fs_api_r.'facturaclientes');
define('source_fs_save_linea_factura_r', source_fs_api_r.'lineafacturaclientes');
define('source_fs_save_numeracion_r', source_fs_api_r.'secuenciadocumentos');
define('source_fs_save_transformation_r', source_fs_api_r.'doctransformations');
define('source_fs_save_pedidos_r', source_fs_api_r .'pedidoclientes');
define('source_fs_update_factura_r', source_fs_api_r .'facturaclientes');

define('source_fs_product_r', source_fs_api_r.'productos');
define('source_fs_variant_r', source_fs_api_r.'variantes');
define('source_fs_stock_r', source_fs_api_r.'stocks');

# VARIABLES GLOBALES
define('TYPE_PERCENTAGE', 1);
define('TYPE_FIXED_VALUE', 2);
define('TAX_SYSTEM_EXEMPT', 'Exento');
define('TAX_SYSTEM_GENERAL', 'General');
define('TAX_SYSTEM_SURCHARGE', 'Recargo');
define('ESTADO_FACTURADO', 25);
define('ESTADO_FACTURA', 10);
define('MAX_MONTO_FACTURADO', 400);

define('ESTADO_EMITIDO', 11);
define('SERIE_DEFAULT', 'S');
define('SERIE_ALMACEN_DEFAULT', 'E-32');


define('FS_NF0', 2); #['property' => 'decimals', 'default' => 2]

# TOKEN DE ENTORNO DE DESARROLLO
define("token_fs", "MhdRAHYoTDzZu4xXfPcQ"); #MhdRAHYoTDzZu4xXfPcQ

# TOKEN DE ENTORNO REAL
define("token_fs_r", "obe16zBGlVKYtESmusPI");

?>