<?php

/*
Plugin Name: Woocommerce iFlow
Plugin URI: http://ecomerciar.com
Description: Integración de iflow para realizar envíos a través de la plataforma WooCommerce.
Version: 1.7.1
Author: Ecomerciar
Author URI: http://ecomerciar.com
License: GPL2
*/

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

require_once 'iflow-class.php';
require_once 'iflow-shipping.php';
require_once 'iflow-utilities.php';
require_once 'iflow-settings.php';

// Creamos paginas necesarias al instalar el plugin				
function woo_iflow_install(){
	$contenido = '<h2>Número de envío</h2>
	<form method="post">
	<input type="text" name="id"style="width:40%"><br>
	<br />
	<input name="submit_button" type="submit"  value="Consultar"  id="update_button"  class="update_button"/>
	</form>
	[rastreo_iflow]';
	if(! post_exists('Rastreo', $contenido)){
		wp_insert_post( array(
			'post_title'     => 'Rastreo',
			'post_name'      => 'rastreo_iflow',
			'post_status'    => 'publish',
			'post_type'      => 'page',
			'post_content'   => $contenido,
			'comment_status' => 'closed',
			'ping_status'    => 'closed'
		) );
	}
}
register_activation_hook(__FILE__,'woo_iflow_install');