<?php
require_once 'PSWebServiceLibrary.php';

global $webService;
$webService = new PrestaShopWebservice(source, ps_key, false); 

function get_resource_ps($data){
	global $webService;
	$opts	=	array();
	try{
		if( !isset($data['display']) )	$display	= 'id';
		else	$display	= $data['display'];
		
		$opts['resource'] 		= 	$data['resource'];
		$opts['display']		=	($display=='full') ? $display : '['.$display.']';
    
    	if(is_array($data['filterkey'])){
	        foreach($data['filterkey'] as $k => $v){
    	        $opts['filter['.$v.']']		=	$data['filtervalue'][$k];
        	}
        }else{
        	$opts['filter['.$data['filterkey'].']']		 =	 $data['filtervalue'];
        }
	    $xml = $webService->get($opts);
    
/*
		$xml = $webService->get([
			'resource' => $data['resource'],
			'filter['.$data['filterkey'].']' => $data['filtervalue'],
			'display'	=>	'['.$display.']'
		]);
*/		
		$resources	=	$xml->{$data['resource']}->children();
	} catch (PrestaShopWebserviceException $e){
		return;
	}

	if($resources){
		if( isset($data['count']) ){
			return $resources;
		}else{
			if( !isset($data['display']) )	{
				return $resources[0]->id;
			}else{
				return $resources[0];
			}
		}
	}
	return false;
}

function get_resources_ps($data){
	global $webService;
	$opts['resource'] = $data['resources'];

	if(isset($data['display'])){
    	$opts['display'] = '['.$data['display'].']';
    }

	if(isset($data['filterkey'])){
    	$opts['filter['.$data['filterkey'].']'] = $data['filtervalue'];
    }

	try{
		$xml = $webService->get($opts);
	} catch (PrestaShopWebserviceException $e){
		return;
	}
	$resources	=	$xml->{$data['resources']}->children();
	return $resources;
}

function get_resource_by_id_ps($data){
	global $webService;
	
	try {
		$xml = $webService->get([
			'resource' => $data['resources'],
			'id' => $data['id']
		]);
		$resource	=	$xml->{$data['resource']}->children();	
	} catch (PrestaShopWebserviceException $ex) {
		return;
	}
	if($resource){
		return $resource;
	}
	return false;
}
?>