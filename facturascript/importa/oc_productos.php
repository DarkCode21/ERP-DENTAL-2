<?php
class importProducts {
	public function isValidToken ($token): bool {
    	$status = false;
    	
    	if (key_api_exp_prod == $token) $status = true;
    
    
    	return $status;
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

	public function getData () {
    	$api_token	=	$this->get_token_oc();
    	$hoy = date('Y-m-d H:i:s');
    	$limit = '2023-03-01'; #date('Y-m-d', strtotime("$hoy - 1 day"));
    	$url = source_oc_api_products."&api_token=".$api_token. "&limit=".$limit;
    	#echo $url;
		$curl = curl_init();
		curl_setopt_array($curl, array(
			CURLOPT_URL => $url,
			CURLOPT_RETURNTRANSFER => true,
		));

		$raw_response = curl_exec($curl);	#die(var_dump($raw_response));
		$err = curl_error($curl);
		curl_close($curl);
		if ($err) {	
			return false;
		} else {
			$response	= json_decode($raw_response);
			return $response->products;
		}
    }

	public function getProduct ($entorno, $param) {
    	$method = 'GET';
    	$url = null;
    	$data = '';
    	$dataArrays = [];
    
    	if ($entorno == 'R') {
        	$url = source_fs_product_r;
        } elseif($entorno == 'D') {
        	$url = source_fs_product;
        }
    
      	if ($entorno == 'D') {
       	  $token_fs = token_fs;
        } elseif ($entorno == 'R') {
       	  $token_fs = token_fs_r;
        }
        
    	$dataArrays = ['filter[referencia]' => $param];
     	$data = http_build_query($dataArrays);
    	
    	$arr_header = array(
    	   	"token:".$token_fs,
            "Content-Type:application/x-www-form-urlencoded"
        ); 
    	
    	$url .='?'.$data;
    
    	$curl = curl_init();
		curl_setopt_array($curl, array(
        	CURLOPT_URL => $url,
         	CURLOPT_CUSTOMREQUEST  => $method,
         	CURLOPT_RETURNTRANSFER => true,
         	CURLOPT_HTTPHEADER => $arr_header
        ));
    
    	
      	$raw_response = curl_exec($curl);
		$err = curl_error($curl);
        curl_close($curl);
    	
	    if (!empty($err)) {	
          return false;
       	} else {
          $response	= json_decode($raw_response);
       	}  
    
    	#die(var_dump($response));
    	return count($response)>0?$response[0]:null;
    }
	
	public function getVariant ($entorno, $param, $param2) {
    	$method = 'GET';
    	$url = null;
    	$data = '';
    	$dataArrays = [];
    
    	if ($entorno == 'R') {
        	$url = source_fs_variant_r;
        } elseif($entorno == 'D') {
        	$url = source_fs_variant;
        }
    
      	if ($entorno == 'D') {
       	  $token_fs = token_fs;
        } elseif ($entorno == 'R') {
       	  $token_fs = token_fs_r;
        }
        
    	$dataArrays = [
        	'filter[referencia]' => $param,
        	'filter[idproducto]' => $param2
        ];
     	$data = http_build_query($dataArrays);
    	
    	$arr_header = array(
    	   	"token:".$token_fs,
            "Content-Type:application/x-www-form-urlencoded"
        ); 
    	
    	$url .='?'.$data;
    
    	$curl = curl_init();
		curl_setopt_array($curl, array(
        	CURLOPT_URL => $url,
         	CURLOPT_CUSTOMREQUEST  => $method,
         	CURLOPT_RETURNTRANSFER => true,
         	CURLOPT_HTTPHEADER => $arr_header
        ));
    
    	
      	$raw_response = curl_exec($curl);
		$err = curl_error($curl);
        curl_close($curl);
    	
	    if (!empty($err)) {	
          return false;
       	} else {
          $response	= json_decode($raw_response);
       	}  
    
    	#die(var_dump($response));
    	return count($response)>0?$response[0]:null;
    }
	
	public function getStock ($entorno, $param, $param2) {
    	$method = 'GET';
    	$url = null;
    	$data = '';
    	$dataArrays = [];
    
    	if ($entorno == 'R') {
        	$url = source_fs_stock_r;
        } elseif($entorno == 'D') {
        	$url = source_fs_stock;
        }
    
      	if ($entorno == 'D') {
       	  $token_fs = token_fs;
        } elseif ($entorno == 'R') {
       	  $token_fs = token_fs_r;
        }
        
    	$dataArrays = [
        	'filter[referencia]' => $param,
        	'filter[idproducto]' => $param2
        ];
     	$data = http_build_query($dataArrays);
    	
    	$arr_header = array(
    	   	"token:".$token_fs,
            "Content-Type:application/x-www-form-urlencoded"
        ); 
    	
    	$url .='?'.$data;
    
    	$curl = curl_init();
		curl_setopt_array($curl, array(
        	CURLOPT_URL => $url,
         	CURLOPT_CUSTOMREQUEST  => $method,
         	CURLOPT_RETURNTRANSFER => true,
         	CURLOPT_HTTPHEADER => $arr_header
        ));
    
    	
      	$raw_response = curl_exec($curl);
		$err = curl_error($curl);
        curl_close($curl);
    	
	    if (!empty($err)) {	
          return false;
       	} else {
          $response	= json_decode($raw_response);
       	}  
    
    	#die(var_dump($response));
    	return count($response)>0?$response[0]:null;
    }
	
	
	public function import($entorno, $urlP, $params, $json) {
    	$url = null;
    	$method = 'POST';
    	if ($entorno == 'R') {
        	switch ($urlP) {
	        	case 'save-product':   $url = source_fs_product_r;  break;        
            	case 'update-product': $url = source_fs_product_r."/$params"; $method = 'PUT'; break;
            	case 'save-variant':   $url = source_fs_variant_r; break;        
            	case 'update-variant': $url = source_fs_variant_r."/$params"; $method = 'PUT'; break;
            	case 'save-stock':   $url = source_fs_stock_r; break;        
            	case 'update-stock': $url = source_fs_stock_r."/$params"; $method = 'PUT'; break;
            }
        } elseif($entorno == 'D') {
         	switch ($urlP) {
	        	case 'save-product':   $url = source_fs_product;  break;        
            	case 'update-product': $url = source_fs_product."/$params"; $method = 'PUT'; break;
            	case 'save-variant':   $url = source_fs_variant; break;        
            	case 'update-variant': $url = source_fs_variant."/$params"; $method = 'PUT'; break;
            	case 'save-stock':   $url = source_fs_stock; break;        
            	case 'update-stock': $url = source_fs_stock."/$params"; $method = 'PUT'; break;
            }
        
        }
        if ($entorno == 'D') {
       	  $token_fs = token_fs;
        } elseif ($entorno == 'R') {
       	  $token_fs = token_fs_r;
        }
        
    	/*if ($method == 'PUT') {
        	$json = json_decode($json, true);
        }*/
    
    	$json = http_build_query($json); 
	
     	$arr_header = array(
    	   	 "Content-Type:application/x-www-form-urlencoded",
        	"token:".$token_fs
        ); 
    
    	$curl = curl_init();
		curl_setopt_array($curl, array(
        	CURLOPT_URL => $url,
         	CURLOPT_CUSTOMREQUEST  => $method,
         	CURLOPT_RETURNTRANSFER => true,
         	CURLOPT_HTTPHEADER => $arr_header,
         	CURLOPT_POSTFIELDS => $json
        ));
    
	  	$response = curl_exec($curl);
		$err = curl_error($curl);
        curl_close($curl);
    	#echo $method .'|'. $url.'|'.$json;
    	if ($err) {
       		return "cURL Error #:" . $err;
       	} else {
      		$response = json_decode($response, true);
        	/*die(var_dump($response));
        	if ($method == 'PUT') {
	        	error_log($response['error'], 0);
    	    	return true;
        	}*/
       		
        	#die(var_dump($response['data']['actualizado']));
       		if (isset($response['ok']) === true) {
        		#die(var_dump($response['data']));
        		return $response['data'];
        	} else {
	        	error_log($response['error'], 0);
      			return null;  
        	}
       	}
    }

	public function init () {
		$token = $_GET['api_key'];
    	$entorno = $_GET['exec'];
    	include('./library_oc/config.php');
		if ($this->isValidToken($token)) {
        	$almacen = SERIE_ALMACEN_DEFAULT;
        	$products = $this->getData();
			$j=0;
			foreach($products as $product) {
            	echo "model: ". substr($product->model,0,30);
                #echo "<br/>";
            	#echo "cantidad:". $product->quantity;
            	echo "<br/>";
           	 	$prodValid = $this->getProduct($entorno, substr($product->model,0,30));
				#echo var_dump($prodValid);
            	if (is_null($prodValid)) {
            		$codImpuesto = array_search($product->tax_class_id, taxes);
            		$codFamilia = codFamiliaTiendaOn;
        			$json = [
                		'actualizado' => date('Y-m-d H:i:s'),
                		'codfamilia'=> $codFamilia,
                		'descripcion' => $product->model,
                		'referencia' => substr($product->model,0,30),
                		'precio' => $product->price,
                		'codimpuesto' => $codImpuesto,
						'codbarras' => $product->ean
                	];
            		$r = $this->import($entorno, 'save-product', null, $json);
                	#die(var_dump($r));
            		if (!is_null($r)){
                		$idproducto = $r['idproducto'];
						#if (!is_null($r2)){
						//$idvariante = $r2['idvariante'];
						$json3 = [
							'cantidad' => $product->quantity,
							'disponible' => $product->quantity,
							//'precio'	 => $product->price,
							'coadlmacen' => $almacen,
							'referencia' => substr($product->model,0,30),
							'idproducto' => $idproducto
						];

						$r3 = $this->import($entorno, 'save-stock', null, $json3);
    	                //}
						$varValid = $this->getVariant($entorno, substr($product->model,0,30), $idproducto);
						if(!is_null($varValid)) {
							$json2 = [
								 'codbarras' => $product->ean,
								 'precio'	 => $product->price,
								 'referencia' => substr($product->model,0,30),
								 'idproducto' => $idproducto
							];
							$r2 = $this->import($entorno, 'update-variant', $varValid->idvariante, $json2);
						}                	
                	}
            	} else {
                	$codImpuesto = array_search($product->tax_class_id, taxes);
            		$codFamilia = codFamiliaTiendaOn;
        			$json = [
                		'actualizado' => date('Y-m-d H:i:s'),
                		'codfamilia'=> $codFamilia,
                		'descripcion' => $product->model,
                		'referencia' => substr($product->model,0,30),
                		'precio' => $product->price,
                		'codimpuesto' => $codImpuesto
                	];
            		
                    $r = $this->import($entorno, 'update-product', $prodValid->idproducto, $json);
                	#die(var_dump($r));
                	if (!is_null($r)){
                		$idproducto = $r['idproducto'];
                    	$varValid = $this->getVariant($entorno, substr($product->model,0,30), $idproducto);
						if(!is_null($varValid)) {
							#echo "idvariante: ".$varValid->idvariante;
							#$product->product_id;
							$json2 = [
								 'codbarras' => $product->ean,
								 'precio'	 => $product->price,
								 'referencia' => substr($product->model,0,30),
								 'idproducto' => $idproducto
							];
							#echo var_dump($json2);
							$r2 = $this->import($entorno, 'update-variant', $varValid->idvariante, $json2);

							if (!is_null($r2)){
								#$idvariante = $r2['idvariante'];
								$stValid = $this->getStock($entorno, substr($product->model,0,30), $idproducto);
								$json3 = [
									'cantidad' => $product->quantity,
									'disponible' => $product->quantity,
									//'precio'	 => $product->price,
									'coadlmacen' => $almacen
									//'referencia' => substr($product->model,0,30),
									//'idproducto' => $idproducto
								];

								$r3 = $this->import($entorno, 'update-stock', $stValid->idstock, $json3);
							}
						}
                	}
                }
				$j++;
				
				if ($j == 100) { die("OK, culminado los 100");}
            	#die(var_dump($product));
			}
        	die("PROCESO CULMINADO");
        
        } else {
        	die("KEY NOT FOUND");
        }
    }
}
$im = new importProducts();
$im->init();

?>