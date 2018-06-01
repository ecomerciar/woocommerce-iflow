<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

// =========================================================================
/**
 * Function woo_iflow_generar_envio_iflow
 *
 */
add_action( 'woocommerce_order_status_completed', 'woo_iflow_generar_envio_iflow');
function woo_iflow_generar_envio_iflow( $order_id ){
	$order = wc_get_order( $order_id );	
	$envio_seleccionado = reset( $order->get_items( 'shipping' ) )->get_method_id();
	$envio = explode(" ", $envio_seleccionado);
	if($envio[0] === 'iflow'){
		$datos = get_option($envio[1]);	
		$token = iflow_obtener_token($datos);
		if($token === ''){
			$log = new WC_Logger();		
			$log->add( 'iflow', "Token vacío, finalizando ejecución");
			return;
		}
		$body = woo_iflow_crear_datos_iflow($datos, $order);
		if($datos['entorno'] === 'prod'){
			$url = 'https://api.iflow21.com';
		}else{
			$url = 'https://test-api.iflow21.com';
		}
		$url .= '/api/order/create';
		$response = wp_remote_post($url, array(
			'headers' => array(
				'Content-Type' => 'application/json',
				'Authorization' => 'Bearer '.$token
			),
			'body' => json_encode($body)
		));
		if(is_wp_error($response)){
			$log = new WC_Logger();		
			$log->add( 'iflow', "Error al realizar envio: ".$response->get_error_message());
			return '';						
		}
		$response = json_decode($response['body'], true);
		if($response['success'] == 'true'){
			if($datos['debug'] === 'yes'){
				$log = new WC_Logger();		
				$log->add( 'iflow', "Envio creado con exito: ".print_r($response,true));
			}
			$order->update_meta_data('numeroenvio_iflow', $response['results']['tracking_id'] );
			$order->update_meta_data('etiqueta_iflow', $response['results']['shippings'][0]['print_url'] );
			$order->save();
		}else{
			$log = new WC_Logger();		
			$log->add( 'iflow', "Error de enviar data: ".print_r($body,true));
			$log->add( 'iflow', "Error del servidor al obtener response: ".print_r($response,true));
		}
	}
}

// =========================================================================
/**
 * Function iflow_obtener_token
 *
 */
function iflow_obtener_token($datos = array()){
	if($datos['entorno'] === 'prod'){
		$body = array(
			'_username' => get_option('iflow_settings_username_prod'),
			'_password' => get_option('iflow_settings_password_prod')
		);
	}else{
		$body = array(
		'_username' => get_option('iflow_settings_username_test'),
		'_password' => get_option('iflow_settings_password_test')
		);
	}
	$headers = array(
		'content-type' => 'application/json'		
	);
	if($datos['entorno'] === 'prod'){
		$url = 'https://api.iflow21.com';
	}else{
		$url = 'https://test-api.iflow21.com';
	}
	$url .= '/api/login';
	$token = wp_remote_post($url, array(
		'headers' => $headers,
		'body' => json_encode($body)
	));
	if(is_wp_error($token)){
		$log = new WC_Logger();		
		$log->add( 'iflow', "Error al obtener token: ".$token->get_error_message());
		return '';						
	}
	$token = json_decode($token['body']);
	if($token->message === ''){
		return $token->token;
	}else{
		$log = new WC_Logger();		
		$log->add( 'iflow', "Error del servidor al obtener token: ".$token->message);
		return '';
	}
}

// =========================================================================
/**
 * Function calcular_empaquetado
 *
 */
function calcular_empaquetado($dimensiones){

	$dimensiones = substr($dimensiones, 0, -1);
	$dimensions = explode("|", $dimensiones);

	//1. Find total volume
	$volume = 0;
	//2. Find WHD ranges
	$widthRange     = array();
	$heightRange    = array();
	$depthRange     = array();
	foreach($dimensions as $dimension) {
		list($width, $height, $depth) = explode(',', $dimension);

		$volume += $width * $height * $depth;

		$widthRange[] = $width;

		$heightRange[] = $height;

		$depthRange[] = $depth;
	}

	//3. Order the WHD ranges
	sort($widthRange);
	sort($heightRange);
	sort($depthRange);

	//4. Figure out every combination with WHD
	$widthCombination   = array();
	$heightCombination  = array();
	$depthCombination   = array();

	function combination($list) {
		$combination = array();
		$total = pow(2, count($list)); 
		for ($i = 0; $i < $total; $i++) {   
			$set = array();
			//For each combination check if each bit is set  
			for ($j = 0; $j < $total; $j++) {  
			//Is bit $j set in $i?  
				if (pow(2, $j) & $i) $set[] = $list[$j];       
			}  

			if(empty($set) || in_array(array_sum($set), $combination)) {
				continue;
			}

			$combination[] = array_sum($set);
		}

		sort($combination);

		return $combination;
	}

	$widthCombination = combination($widthRange);
	$heightCombination = combination($heightRange);
	$depthCombination = combination($depthRange);

	$stacks = array();
	foreach($widthCombination as $width) {
		foreach($heightCombination as $height) {
			foreach($depthCombination as $depth) {
				$v = $width*$height*$depth;
				if($v >= $volume) {
					$stacks[$v][$width+$height+$depth] = array($width, $height, $depth);
				}
			}
		}
	}

	ksort($stacks);

	foreach($stacks as $i => $dims) {
		ksort($stacks[$i]);
		foreach($stacks[$i] as $j => $stack) {
			rsort($stack);
			break;
		}

		break;
	}

	$stacks = array_shift(array_shift($stacks));
	return $stacks;
}


// =========================================================================
/**
 * Function woo_iflow_crear_datos_iflow
 *
 */
function woo_iflow_crear_datos_iflow($datos = array(), $order = ''){

	$countries_obj = new WC_Countries();
	$country_states_array = $countries_obj->get_states();
	$log = new WC_Logger();
	if($order->get_shipping_first_name()){
		$provincia = $country_states_array['AR'][$order->get_shipping_state()];
		$datos['nombre_cliente'] = $order->get_shipping_first_name();
		$datos['apellido_cliente'] = $order->get_shipping_last_name();
		$datos['direccion_cliente'] = $order->get_shipping_address_1();
		$datos['ciudad_cliente'] = $order->get_shipping_city();
		$datos['cp_cliente'] = $order->get_shipping_postcode();
		$datos['observaciones_cliente'] = $order->get_shipping_address_2();
	}else{
		$provincia = $country_states_array['AR'][$order->get_billing_state()];
		$datos['nombre_cliente'] = $order->get_billing_first_name();
		$datos['apellido_cliente'] = $order->get_billing_last_name();
		$datos['direccion_cliente'] = $order->get_billing_address_1();
		$datos['ciudad_cliente'] = $order->get_billing_city();
		$datos['cp_cliente'] = $order->get_billing_postcode();
		$datos['observaciones_cliente'] = $order->get_billing_address_2();
	}
	$datos['provincia_cliente'] = $provincia;	
	$datos['telefono_cliente'] = $order->get_billing_phone();
	$datos['celular_cliente'] = $order->get_billing_phone();
	$datos['email_cliente'] = $order->get_billing_email();
	$array_direccion = explode(" ", $datos['direccion_cliente']);
	$datos['nro_calle_cliente'] = array_pop($array_direccion);
	if(! is_numeric($datos['nro_calle_cliente'])){
		$datos['nro_calle_cliente'] = '';
	} else {
		if(empty($array_direccion)) {
			$datos['direccion_cliente'] = "Sin Dirección";
		} else {
			$datos['direccion_cliente'] = implode(" ", $array_direccion);
		}
	}
	// Si hay un número en la segunda dirección, y no se pudo conseguir ningún numero en la primera dirección, lo enviamos a esta 2da direcc.
	if(is_numeric($datos['observaciones_cliente']) && $datos['nro_calle_cliente'] == ''){
		$datos['nro_calle_cliente'] = $datos['observaciones_cliente'];
	}
	if( empty($datos['nro_calle_cliente']) ) {
		$datos['nro_calle_cliente'] = ' ';
	}

	if(html_entity_decode($datos['provincia_cliente']) == 'Tucumán'){
		$datos['provincia_cliente'] = 'Tucumán';
	}

	// Se filtran los caracteres
	$datos = array_map(function($value){
		$value = str_replace('"', "", $value);
		$value = str_replace("'", "", $value);
		$value = str_replace(";", "", $value);
		$value = str_replace("&", "", $value);
		$value = str_replace("<", "", $value);
		$value = str_replace(">", "", $value);
		$value = str_replace("º", "", $value);
		$value = str_replace("ª", "", $value);
		return $value;
	}, $datos);

	$items = $order->get_items();
	$datos['peso'] = $datos['precio_total'] = 0;
	$datos['volumen'] = '';
	foreach ( $items as $item ) {
		$product_id = $item['product_id'];
		$product_variation_id = $item['variation_id'];
		$product =  wc_get_product( $product_id );
		$product_variado =  wc_get_product( $product_variation_id );
		//Se obtienen los datos del producto
		if($product->get_weight() !== ''){
			$datos['volumen'] .= floatval(wc_get_dimension( fix_format( $product->get_length() ), 'cm' )).','.floatval(wc_get_dimension( fix_format( $product->get_height() ), 'cm' )).','.floatval(wc_get_dimension( fix_format( $product->get_width() ), 'cm' )).'|';
			$datos['peso'] += floatval(wc_get_weight( fix_format( $product->get_weight() ), 'g' ));
			$datos['precio_total'] += $item->get_total();
			$sku = $product->get_sku();
			if($sku == ''){
				$sku = 'SIN SKU';
			}
			$datos['items'][] = array(
				'item' => $product->get_name(),
				'sku' => $sku,
				'quantity' => $item->get_quantity()
			);
		}else{
			$sku = $product_variado->get_sku();
			if($sku == ''){
				$sku = 'SIN SKU';
			}
			$datos['volumen'] .= floatval(wc_get_dimension( fix_format( $product_variado->get_length() ), 'cm' )).','.floatval(wc_get_dimension( fix_format( $product_variado->get_height() ), 'cm' )).','.floatval(wc_get_dimension( fix_format( $product_variado->get_width() ), 'cm' )).'|';			
			$datos['peso'] += floatval(wc_get_weight( fix_format( $product_variado->get_weight() ), 'g' ));
			$datos['precio_total'] += $item->get_total();
			$datos['items'][] = array(
				'item' => $product_variado->get_name(),
				'sku' => $sku,
				'quantity' => $item->get_quantity()
			);
		}
	}
	$datos['dimensiones_generales'] = calcular_empaquetado($datos['volumen']);
	$datos['width'] = $datos['length'] = $datos['height'] = $datos['volumen'] / 3;


	$data = array(
		'order_id' => $order->get_order_number(),
		'shipments' => array(
			array(
				'items_value' => $datos['precio_total'],
				'width' => $datos['dimensiones_generales'][0],
				'height' => $datos['dimensiones_generales'][1],
				'length' => $datos['dimensiones_generales'][2],
				'weight' => $datos['peso'],
				'items' => $datos['items']
			)
		),
		'receiver' => array(
			'first_name' => $datos['nombre_cliente'],
			'last_name' => $datos['apellido_cliente'],
			'receiver_name' => $datos['nombre_cliente'],
			'receiver_phone' => $datos['telefono_cliente'],
			'email' => $datos['email_cliente'],
			'address' => array(
				'street_name' => $datos['direccion_cliente'],
				'street_number' => $datos['nro_calle_cliente'],
				'between_1' => $datos['direccion_cliente'],
				'other_info' => $datos['observaciones_cliente'],
				'neighborhood_name' => $datos['ciudad_cliente'],
				'zip_code' => $datos['cp_cliente'],
				'city' => $datos['provincia_cliente']
			)
		)
	);

	return $data;
}

// =========================================================================
/**
 * Replace comma by dot.
 *
 * @param  mixed $value Value to fix.
 *
 * @return mixed
 */
function fix_format( $value ) {
	$value = str_replace( ',', '.', $value );

	return $value;
}



// =========================================================================
/**
 * Function agregar_envio_iflow
 *
 */
//Agrega envios iFlow como metodo de envio internamente
add_filter( 'woocommerce_shipping_methods', 'woo_iflow_agregar_envio_iflow' );
function woo_iflow_agregar_envio_iflow( $methods ) {
	$methods['iflow'] = 'WC_IFLOW';
	return $methods;
}
