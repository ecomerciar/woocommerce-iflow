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
		ob_start();

		$body = array(
			'_username' => get_option('iflow_settings_username_test'),
			'_password' => get_option('iflow_settings_password_test')
		);
		$headers = array(
			'content-type' => 'application/json'		
		);
		$url = 'https://test-api.iflow21.com/api/login';
		$token = wp_remote_post($url, array(
			'headers' => $headers,
			'body' => json_encode($body)
		));
		if(is_wp_error($token)){
			$log = new WC_Logger();		
			$log->add( 'iflow', "Error al obtener token para rastrear: ".$token->get_error_message());
		}
		$token = json_decode($token['body']);
		if($token->message === ''){
			$token = $token->token;
		}
		$iflow_id = filter_var ($_POST['id'], FILTER_SANITIZE_SPECIAL_CHARS);
		$url = 'https://test-api.iflow21.com/api/order/state/'.$iflow_id;
		$envio = wp_remote_get($url, array(
			'headers' => array(
				'Content-Type' => 'application/json',
				'Authorization' => "Bearer ".$token
			)
		));
		$envio = json_decode($envio['body'],true);
		highlight_string("<?php\n\$data =\n" . var_export($envio, true) . ";\n?>");
		if(isset($envio['results'])){
			echo '<h3>Envío Nro: '.$envio['results']['tracking_id'].'</h3>';
			echo "<table>";
			echo "<tr>";
				echo "<td>Estado actual: </td>";
				echo "<td>".$envio['results']['shippings']['state']['state_name']."</td>";
			echo "</tr>";
			echo "<tr>";
				echo "<td>Fecha: </td>";
				echo "<td>".$envio['results']['shippings']['state']['state_date']."</td>";
			echo "</tr>";
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
    echo '<style>.view.eti_iflow::after { font-family: woocommerce; content: "\e028" !important; }</style>';
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
		if($order->get_meta('ordenretiro_iflow') !== ''){
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