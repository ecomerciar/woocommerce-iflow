<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}


// =========================================================================
/**
 * Shortcode [rastreo_iflow]
 *
 */
add_shortcode ( 'rastreo_iflow' , 'woo_iflow_rastreo_iflow_func' );
function woo_iflow_rastreo_iflow_func( $atts, $content= NULL) {
	if($_POST['id']){
		$iflow_id = filter_var ($_POST['id'], FILTER_SANITIZE_SPECIAL_CHARS);		
		ob_start();

		// Entorno prod
		$body = array(
			'_username' => get_option('iflow_settings_username_prod'),
			'_password' => get_option('iflow_settings_password_prod')
		);
		$url_token = 'https://api.iflow21.com/api/login';
		$url_tracking = 'https://api.iflow21.com/api/order/state/'.$iflow_id;	

		// Entorno prueba
		if($body['_username'] == ''){
			$body = array(
				'_username' => get_option('iflow_settings_username_test'),
				'_password' => get_option('iflow_settings_password_test')
			);
			$url_token = 'https://test-api.iflow21.com/api/login';
			$url_tracking = 'https://test-api.iflow21.com/api/order/state/'.$iflow_id;	
		}

		// Obtencion de token
		$headers = array(
			'content-type' => 'application/json'		
		);
		$token = wp_remote_post($url_token, array(
			'headers' => $headers,
			'body' => json_encode($body)
		));
		if(is_wp_error($token)){
			$log = new WC_Logger();		
			$log->add( 'iflow', "Error al obtener token para rastrear: ".$token->get_error_message());
			wc_print_notice( __( 'Hubo un error comunicándonos con el envío', 'woocommerce' ), 'error' );
			return;
		}
		$token = json_decode($token['body']);
		if($token->message === ''){
			$token = $token->token;
		}
				

		// Rastreo
		$envio = wp_remote_get($url_tracking, array(
			'headers' => array(
				'Content-Type' => 'application/json',
				'Authorization' => "Bearer ".$token
			)
		));
				
		if(is_wp_error($envio)){
			wc_print_notice( __( 'Hubo un error comunicándonos con el envío', 'woocommerce' ), 'error' );
			return;
		}
		$envio = json_decode($envio['body'],true);

		// Impresion de datos
		if(isset($envio['results']) && $envio['results'] !== false){
			echo '<h3>Envío Nro: '.$envio['results']['tracking_id'].'</h3>';
			echo "<table>";
			echo "<tr>";
				echo "<th width=\"30%\">Fecha</th>";
				echo "<th width=\"70%\">Estado actual</th>";
			echo "</tr>";
			$envios = $envio['results']['shippings'][0]['states'];
			usort($envios, function($a, $b) {
				$date1 = DateTime::createFromFormat('d/m/Y H:i', $a['state_date']);
				$date2 = DateTime::createFromFormat('d/m/Y H:i', $b['state_date']);
				return $date1 > $date2;
			});
			foreach ($envios as $estado_envio) {
				echo "<tr>";
					echo "<td>".$estado_envio['state_date']."</td>";
					echo "<td>".($estado_envio['details'] ? $estado_envio['details'] : $estado_envio['state_name'])." </td>";
				echo "</tr>";
			}
			echo "</table>";
		}else{
			wc_print_notice( __( 'Hubo un error, por favor intenta nuevamente', 'woocommerce' ), 'error' );
		}
		return ob_get_clean();
		
	}
}



// =========================================================================
/**
 * Function agregar_boton_etiqueta_iflow
 *
 */
// Agregamos un botón a las ordenes completadas
add_filter( 'woocommerce_admin_order_actions', 'woo_iflow_agregar_boton_etiqueta_iflow', 100, 2 );
function woo_iflow_agregar_boton_etiqueta_iflow( $actions, $order ) {
	$envio_seleccionado = reset( $order->get_items( 'shipping' ) )->get_method_id();	
    if ( $order->has_status( array( 'completed' ) ) && strpos($envio_seleccionado, 'iflow') !== false ) {
        // Imprimimos el botón
		printf( '<a class="button tips %s" href="%s" target="_blank" data-tip="%s">%s</a>', esc_attr( "view eti_iflow" ), $order->get_meta('etiqueta_iflow'), esc_attr( "Etiqueta" ), esc_attr( "Etiqueta" ) );
		
	}
    return $actions;
}



// =========================================================================
/**
 * Function boton_etiquetas_iflow_css
 *
 */
// Icono para el botón de etiquetas dentro de manage orders
add_action( 'admin_head', 'woo_iflow_boton_etiquetas_iflow_css' );
function woo_iflow_boton_etiquetas_iflow_css() {
    echo '<style>.view.eti_iflow::after { font-family: "WooCommerce"; content: "\e028" !important; }</style>';
}


// =========================================================================
/**
 * Function agregar_columna_iflow
 *
 */
// Agregar columna de numeros de tracking, en el panel de ordenes
add_filter( 'manage_edit-shop_order_columns', 'woo_iflow_agregar_columna_iflow');
function woo_iflow_agregar_columna_iflow( $columns ) {
	$new_columns = array();
	foreach ( $columns as $column_name => $column_info ) {
		$new_columns[ $column_name ] = $column_info;
		if ( 'order_total' === $column_name ) {
			$new_columns['rastreo_iflow'] = __( 'iFlow Nro. Rastreo', 'my-textdomain' );
		}
	}
	return $new_columns;
}


// =========================================================================
/**
 * Function agregar_contenido_columna_iflow
 *
 */
add_action( 'manage_shop_order_posts_custom_column', 'woo_iflow_agregar_contenido_columna_iflow' );
function woo_iflow_agregar_contenido_columna_iflow( $column ) {
    global $post;
    if ( 'rastreo_iflow' === $column ) {
		$order = wc_get_order( $post->ID );
		if($order->get_meta('numeroenvio_iflow') !== ''){
			echo $order->get_meta('numeroenvio_iflow');
		}
    }
}


// =========================================================================
/**
 * Function agregar_estilo_columna_iflow
 *
 */
add_action( 'admin_print_styles', 'woo_iflow_agregar_estilo_columna_iflow' );
function woo_iflow_agregar_estilo_columna_iflow() {
		$css = '.column-rastreo_iflow { width: 9%; }';
		wp_add_inline_style( 'woocommerce_admin_styles', $css );
	}



// =========================================================================
/**
 * Function woo_iflow_clear_wc_shipping_rates_cache
 *
 */
add_filter('woocommerce_checkout_update_order_review', 'woo_iflow_clear_wc_shipping_rates_cache');
function woo_iflow_clear_wc_shipping_rates_cache(){
	$packages = WC()->cart->get_shipping_packages();
	foreach ($packages as $key => $value) {
		$shipping_session = "shipping_for_package_$key";
		unset(WC()->session->$shipping_session);
	}
}
