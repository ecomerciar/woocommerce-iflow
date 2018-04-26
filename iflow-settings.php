<?php

/**
 * @internal never define functions inside callbacks.
 * these functions could be run multiple times; this would result in a fatal error.
 */
 
/**
 * custom option and settings
 */
function wporg_settings_init() {
	// register a new setting for "wporg" page
	register_setting( 'iflow', 'iflow_options' );
	
	// register a new section in the "wporg" page
	add_settings_section(
		'iflow_datos',
		'Datos de prueba',
		'',
		'iflow_settings_test'
	);

	// register a new section in the "wporg" page
	add_settings_section(
		'iflow_datos',
		'Datos de produccion',
		'',
		'iflow_settings_prod'
	);
	
	// register a new field in the "wporg_section_developers" section, inside the "wporg" page
	add_settings_field(
		'iflow_username', // as of WP 4.6 this value is used only internally
		__( 'Username', 'iflow' ),
		'iflow_campo_username_test_callback',
		'iflow_settings_test',
		'iflow_datos'
	);

	add_settings_field(
		'iflow_password', // as of WP 4.6 this value is used only internally
		__( 'Password', 'iflow' ),
		'iflow_campo_password_test_callback',
		'iflow_settings_test',
		'iflow_datos'
	);

	add_settings_field(
		'iflow_username', // as of WP 4.6 this value is used only internally
		__( 'Username', 'iflow' ),
		'iflow_campo_username_prod_callback',
		'iflow_settings_prod',
		'iflow_datos'
	);

	add_settings_field(
		'iflow_password', // as of WP 4.6 this value is used only internally
		__( 'Password', 'iflow' ),
		'iflow_campo_password_prod_callback',
		'iflow_settings_prod',
		'iflow_datos'
	);
}
	
/**
* register our wporg_settings_init to the admin_init action hook
*/
add_action( 'admin_init', 'wporg_settings_init' );
	
/**
* custom option and settings:
* callback functions
*/

// pill field cb

// field callbacks can accept an $args parameter, which is an array.
// $args is defined at the add_settings_field() function.
// wordpress has magic interaction with the following keys: label_for, class.
// the "label_for" key value is used for the "for" attribute of the <label>.
// the "class" key value is used for the "class" attribute of the <tr> containing the field.
// you can add custom key value pairs to be used inside your callbacks.
function iflow_campo_username_test_callback( $args ) {
	echo '<input type="text" name="username_test" id="username_test" value="'.get_option('iflow_settings_username_test').'"/>';
}

function iflow_campo_password_test_callback( $args ) {
	echo '<input type="password" name="password_test" id="password_test" value="'.get_option('iflow_settings_password_test').'"/>';
}

function iflow_campo_username_prod_callback( $args ) {
	echo '<input type="text" name="username_prod" id="username_prod" value="'.get_option('iflow_settings_username_prod').'"/>';
}

function iflow_campo_password_prod_callback( $args ) {
	echo '<input type="password" name="password_prod" id="password_prod" value="'.get_option('iflow_settings_password_prod').'"/>';
}

/**
* top level menu
*/
function iflow_options_page() {
// add top level menu page
	add_options_page(
		'Configuración iFlow',
		'Configuración iFlow',
		'manage_options',
		'iflow_config',
		'crear_pagina_config_iflow'
	);
}

/**
* register our wporg_options_page to the admin_menu action hook
*/
add_action( 'admin_menu', 'iflow_options_page' );

/**
* top level menu:
* callback functions
*/
function crear_pagina_config_iflow() {
	// check user capabilities
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	if(isset($_POST['username_test'])){
		$username = filter_var ($_POST['username_test'], FILTER_SANITIZE_SPECIAL_CHARS);
		update_option( 'iflow_settings_username_test', $username );
	}
	if($_POST['password_test']){
		$password = filter_var ($_POST['password_test'], FILTER_SANITIZE_SPECIAL_CHARS);		
		update_option( 'iflow_settings_password_test', $password );
	}
	if($_POST['username_prod']){
		$username = filter_var ($_POST['username_prod'], FILTER_SANITIZE_SPECIAL_CHARS);
		update_option( 'iflow_settings_username_prod', $username );
	}
	if($_POST['password_prod']){
		$password = filter_var ($_POST['password_prod'], FILTER_SANITIZE_SPECIAL_CHARS);		
		update_option( 'iflow_settings_password_prod', $password );
	}

	?>
	<div class="wrap">
	<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
	<form action="options-general.php?page=iflow_config" method="post">
	<?php
	// output security fields for the registered setting "wporg"
	settings_fields( 'iflow_settings_test' );
	settings_fields( 'iflow_settings_prod' );
	// output setting sections and their fields
	// (sections are registered for "wporg", each field is registered to a specific section)
	do_settings_sections( 'iflow_settings_test' );
	do_settings_sections( 'iflow_settings_prod' );
	// output save settings button
	submit_button( 'Guardar' );
	?>
	</form>
	</div>
	<?php
}