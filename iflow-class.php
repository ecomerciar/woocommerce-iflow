<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

//Creamos nuestra clase WC_IFLOW
function woo_iflow_envios_iflow_init() {
	if ( ! class_exists( 'WC_IFLOW' ) ) {
		class WC_IFLOW extends WC_Shipping_Method {
			/**
			 * Constructor de la clase
			 *
			 * @access public
			 * @return void
			 */
			public function __construct($instance_id=0) {
				$this->id                 = 'iflow'; // Id for your shipping method. Should be unique.
				$this->method_title       = 'iFlow';  // Title shown in admin
				$this->method_description = __('Envios con iFlow','woocommerce'); // Description shown in admin
				$this->title = __('Envío con iFlow', 'iflow');
				$this->instance_id = absint( $instance_id );
				$this->supports             = array(
					'shipping-zones',
					'instance-settings',
					'instance-settings-modal'
				);
				// Definimos la configuración
				$this->init();

				add_action( 'woocommerce_update_options_shipping_' . $this->id, array( $this, 'process_admin_options' ) );				
			}			 

			/**
			 * Inicialización de las opciones
			 *
			 * @access public
			 * @return void
			 */
			function init() {
				$this->form_fields     = array(); // No hay config global, solo de instancia
				$this->instance_form_fields =  array(
					'nombre' => array(
							'title' => __( 'Nombre a mostrar en el checkout', 'woocommerce' ),
							'default'     => 'Envío express',
							'type' => 'text'
					),
					'clase' => array(
						'title'       => 'Si existe la clase',
						'type'        => 'select',
						'default'     => '',
						'desc_tip'    => true,
						'options'     => array(
							'nada' => 'Seleccionar'
						)
					),
					'accion' => array(
						'title'       => 'Entonces',
						'type'        => 'select',
						'default'     => '',
						'desc_tip'    => true,
						'options'     => array(
							'nada' => 'No hacer nada',
							'desactivar_metodo' => 'Desactivar método de envio',
							'activar_metodo' => 'Activar método de envio',
							'envio_gratis' => 'Envio gratis'
						)
					),
					'envio_general_gratis' => array(
						'title' => __( 'Envío gratis', 'woocommerce' ),
						'type' => 'checkbox'
					),
					'entorno' => array(
                            'title' => __( 'Ambiente de', 'woocommerce' ),
                            'type'        => 'select',
							'default'     => 'test',
							'desc_tip'    => true,
							'options'     => array(
								'test' => 'Prueba',
								'prod' => 'Producción',
							)
					),
					'debug' => array(
                            'title' => __( 'Debug log?', 'woocommerce' ),
                            'type' => 'checkbox'
					)
				);
				// Cargamos todas las clases disponibles de WC y las insertamos en la config de iflow
				$clases = WC()->shipping->get_shipping_classes();				
				foreach ($clases as $clase) {
					$this->instance_form_fields['clase']['options'][$clase->name] = $clase->name;
				}								
			}

			// =========================================================================
			/**
			 * Replace comma by dot.
			 *
			 * @param  mixed $value Value to fix.
			 *
			 * @return mixed
			 */
			private function fix_format( $value ) {
				$value = str_replace( ',', '.', $value );

				return $value;
			}

			// =========================================================================
			/**
			 * function calculate_shipping.
			 *
			 * @access public
			 * @param mixed $package
			 * @return void
			 */
			public function calculate_shipping( $package = array() ) {
				$productos = array();
				if($this->get_instance_option('debug') === 'yes'){
					$log = new WC_Logger();		
					$log->add( 'iflow', " ====== Inicio de cálculo de precio ======");						
				}
				$envio_gratis = $this->get_instance_option('envio_general_gratis');
				$accion = $this->verificar_clases($productos);

				$peso_total = $precio_total = 0;
				$hay_producto_cero = false;
				foreach($productos as $producto){
					$peso_total += $producto['peso'];
					$largo_total += $producto['largo'];
					$ancho_total += $producto['ancho'];
					$alto_total += $producto['alto'];
					if($producto['peso'] == 0 || $producto['peso'] == '' || $producto['alto'] == 0 || $producto['alto'] == '' || $producto['largo'] == 0 || $producto['largo'] == '' || $producto['ancho'] == 0 || $producto['ancho'] == '' ){
						$hay_producto_cero = true;
						$producto_cero = $producto;
						break;
					}
				}			
				if($hay_producto_cero || $peso_total === '' || $largo_total === '' || $alto_total === '' || $ancho_total === ''){
					if($this->get_instance_option('debug') === 'yes'){
						$log = new WC_Logger();
						if(isset($producto_cero)){
							$log->add('iflow','Detectado producto con malas dimensiones o peso: ');
							$log->add('iflow', print_r($producto_cero,true));
						}else{
							$log->add('iflow','Detectado dimension/peso 0');
						}
					}
					return;
				}

				
				if($accion === 'envio_gratis' || $envio_gratis === 'yes'){

					$this->addRate(0,'gratis');

				}else if($accion === 'activar_metodo' || $accion === 'nada'){

					$medidas_totales = $this->calcular_medidas($productos);
					if($medidas_totales['volumen'] === -1){
						if($this->get_instance_option('debug') === 'yes'){
							$log = new WC_Logger();		
							$log->add( 'iflow', "Medidas incorrectas, producto muy pequeño");						
						}
						return;
					}

					$shipping_zone = WC_Shipping_Zones::get_zone_matching_package( $package );
					$zona = $shipping_zone->get_zone_name();
					$tabla_precios = $this->crear_tabla_precios();
					$aforo = $medidas_totales['volumen']/5000;

					if($zona === 'CABA Y GBA'){
						if($medidas_totales['peso'] <= 3){
							if($tabla_precios['CABAYGBA']['3'] < $aforo){
								$this->addRate($aforo,'');
							}else{
								$this->addRate($tabla_precios['CABAYGBA']['3'],'');
							}
						}else if($medidas_totales['peso'] <= 5){
							if($tabla_precios['CABAYGBA']['5'] < $aforo){
								$this->addRate($aforo,'');
							}else{
								$this->addRate($tabla_precios['CABAYGBA']['5'],'');
							}
						}else if($medidas_totales['peso'] <= 10){
							if($tabla_precios['CABAYGBA']['10'] < $aforo){
								$this->addRate($aforo,'');
							}else{
								$this->addRate($tabla_precios['CABAYGBA']['10'],'');
							}
						}else if($medidas_totales['peso'] <= 15){
							if($tabla_precios['CABAYGBA']['15'] < $aforo){
								$this->addRate($aforo,'');
							}else{
								$this->addRate($tabla_precios['CABAYGBA']['15'],'');
							}
						}else if($medidas_totales['peso'] <= 20){
							if($tabla_precios['CABAYGBA']['20'] < $aforo){
								$this->addRate($aforo,'');
							}else{
								$this->addRate($tabla_precios['CABAYGBA']['20'],'');
							}
						}else if($medidas_totales['peso'] <= 30){
							if($tabla_precios['CABAYGBA']['30'] < $aforo){
								$this->addRate($aforo,'');
							}else{
								$this->addRate($tabla_precios['CABAYGBA']['30'],'');
							}
						}else if($medidas_totales['peso'] <= 50){
							if($tabla_precios['CABAYGBA']['50'] < $aforo){
								$this->addRate($aforo,'');
							}else{
								$this->addRate($tabla_precios['CABAYGBA']['50'],'');
							}
						}else if($medidas_totales['peso'] <= 60){
							if($tabla_precios['CABAYGBA']['60'] < $aforo){
								$this->addRate($aforo,'');
							}else{
								$this->addRate($tabla_precios['CABAYGBA']['60'],'');
							}
						}else if($medidas_totales['peso'] > 60){
							$exceso = $medidas_totales['peso'] - 60;
							$precio = $tabla_precios['CABAYGBA']['60'] + ($tabla_precios['CABAYGBA']['extra'] * $exceso);
							if($precio < $aforo){
								$this->addRate($aforo,'');
							}else{
								$this->addRate($precio,'');
							}
						}
					}else if($zona === 'Interior 1'){
						if($medidas_totales['peso'] <= 3){
							if($tabla_precios['INTERIOR1']['3'] < $aforo){
								$this->addRate($aforo,'');
							}else{
								$this->addRate($tabla_precios['INTERIOR1']['3'],'');
							}
						}else if($medidas_totales['peso'] <= 5){
							if($tabla_precios['INTERIOR1']['5'] < $aforo){
								$this->addRate($aforo,'');
							}else{
								$this->addRate($tabla_precios['INTERIOR1']['5'],'');
							}
						}else if($medidas_totales['peso'] <= 10){
							if($tabla_precios['INTERIOR1']['10'] < $aforo){
								$this->addRate($aforo,'');
							}else{
								$this->addRate($tabla_precios['INTERIOR1']['10'],'');
							}
						}else if($medidas_totales['peso'] <= 15){
							if($tabla_precios['INTERIOR1']['15'] < $aforo){
								$this->addRate($aforo,'');
							}else{
								$this->addRate($tabla_precios['INTERIOR1']['15'],'');
							}
						}else if($medidas_totales['peso'] <= 20){
							if($tabla_precios['INTERIOR1']['20'] < $aforo){
								$this->addRate($aforo,'');
							}else{
								$this->addRate($tabla_precios['INTERIOR1']['20'],'');
							}
						}else if($medidas_totales['peso'] <= 30){
							if($tabla_precios['INTERIOR1']['30'] < $aforo){
								$this->addRate($aforo,'');
							}else{
								$this->addRate($tabla_precios['INTERIOR1']['30'],'');
							}
						}else if($medidas_totales['peso'] <= 50){
							if($tabla_precios['INTERIOR1']['50'] < $aforo){
								$this->addRate($aforo,'');
							}else{
								$this->addRate($tabla_precios['INTERIOR1']['50'],'');
							}
						}else if($medidas_totales['peso'] <= 60){
							if($tabla_precios['INTERIOR1']['60'] < $aforo){
								$this->addRate($aforo,'');
							}else{
								$this->addRate($tabla_precios['INTERIOR1']['60'],'');
							}
						}else if($medidas_totales['peso'] > 60){
							$exceso = $medidas_totales['peso'] - 60;
							$precio = $tabla_precios['INTERIOR1']['60'] + ($tabla_precios['INTERIOR1']['extra'] * $exceso);
							if($precio < $aforo){
								$this->addRate($aforo,'');
							}else{
								$this->addRate($precio,'');
							}
						}
					}else if($zona === 'Tierra del fuego'){
						if($medidas_totales['peso'] <= 3){
							if($tabla_precios['TIERRADELFUEGO']['3'] < $aforo){
								$this->addRate($aforo,'');
							}else{
								$this->addRate($tabla_precios['TIERRADELFUEGO']['3'],'');
							}
						}else if($medidas_totales['peso'] <= 5){
							if($tabla_precios['TIERRADELFUEGO']['5'] < $aforo){
								$this->addRate($aforo,'');
							}else{
								$this->addRate($tabla_precios['TIERRADELFUEGO']['5'],'');
							}
						}else if($medidas_totales['peso'] <= 10){
							if($tabla_precios['TIERRADELFUEGO']['10'] < $aforo){
								$this->addRate($aforo,'');
							}else{
								$this->addRate($tabla_precios['TIERRADELFUEGO']['10'],'');
							}
						}else if($medidas_totales['peso'] <= 15){
							if($tabla_precios['TIERRADELFUEGO']['15'] < $aforo){
								$this->addRate($aforo,'');
							}else{
								$this->addRate($tabla_precios['TIERRADELFUEGO']['15'],'');
							}
						}else if($medidas_totales['peso'] <= 20){
							if($tabla_precios['TIERRADELFUEGO']['20'] < $aforo){
								$this->addRate($aforo,'');
							}else{
								$this->addRate($tabla_precios['TIERRADELFUEGO']['20'],'');
							}
						}else if($medidas_totales['peso'] <= 30){
							if($tabla_precios['TIERRADELFUEGO']['30'] < $aforo){
								$this->addRate($aforo,'');
							}else{
								$this->addRate($tabla_precios['TIERRADELFUEGO']['30'],'');
							}
						}else if($medidas_totales['peso'] <= 50){
							if($tabla_precios['TIERRADELFUEGO']['50'] < $aforo){
								$this->addRate($aforo,'');
							}else{
								$this->addRate($tabla_precios['TIERRADELFUEGO']['50'],'');
							}
						}else if($medidas_totales['peso'] <= 60){
							if($tabla_precios['TIERRADELFUEGO']['60'] < $aforo){
								$this->addRate($aforo,'');
							}else{
								$this->addRate($tabla_precios['TIERRADELFUEGO']['60'],'');
							}
						}else if($medidas_totales['peso'] > 60){
							$exceso = $medidas_totales['peso'] - 60;
							$precio = $tabla_precios['TIERRADELFUEGO']['60'] + ($tabla_precios['TIERRADELFUEGO']['extra'] * $exceso);
							if($precio < $aforo){
								$this->addRate($aforo,'');
							}else{
								$this->addRate($precio,'');
							}
						}
					// Si es Zona 2 o cualquier otra, usar estos precios
					}else{
						if($medidas_totales['peso'] <= 3){
							if($tabla_precios['INTERIOR2']['3'] < $aforo){
								$this->addRate($aforo,'');
							}else{
								$this->addRate($tabla_precios['INTERIOR2']['3'],'');
							}
						}else if($medidas_totales['peso'] <= 5){
							if($tabla_precios['INTERIOR2']['5'] < $aforo){
								$this->addRate($aforo,'');
							}else{
								$this->addRate($tabla_precios['INTERIOR2']['5'],'');
							}
						}else if($medidas_totales['peso'] <= 10){
							if($tabla_precios['INTERIOR2']['10'] < $aforo){
								$this->addRate($aforo,'');
							}else{
								$this->addRate($tabla_precios['INTERIOR2']['10'],'');
							}
						}else if($medidas_totales['peso'] <= 15){
							if($tabla_precios['INTERIOR2']['15'] < $aforo){
								$this->addRate($aforo,'');
							}else{
								$this->addRate($tabla_precios['INTERIOR2']['15'],'');
							}
						}else if($medidas_totales['peso'] <= 20){
							if($tabla_precios['INTERIOR2']['20'] < $aforo){
								$this->addRate($aforo,'');
							}else{
								$this->addRate($tabla_precios['INTERIOR2']['20'],'');
							}
						}else if($medidas_totales['peso'] <= 30){
							if($tabla_precios['INTERIOR2']['30'] < $aforo){
								$this->addRate($aforo,'');
							}else{
								$this->addRate($tabla_precios['INTERIOR2']['30'],'');
							}
						}else if($medidas_totales['peso'] <= 50){
							if($tabla_precios['INTERIOR2']['50'] < $aforo){
								$this->addRate($aforo,'');
							}else{
								$this->addRate($tabla_precios['INTERIOR2']['50'],'');
							}
						}else if($medidas_totales['peso'] <= 60){
							if($tabla_precios['INTERIOR2']['60'] < $aforo){
								$this->addRate($aforo,'');
							}else{
								$this->addRate($tabla_precios['INTERIOR2']['60'],'');
							}
						}else if($medidas_totales['peso'] > 60){
							$exceso = $medidas_totales['peso'] - 60;
							$precio = $tabla_precios['INTERIOR2']['60'] + ($tabla_precios['INTERIOR2']['extra'] * $exceso);
							if($precio < $aforo){
								$this->addRate($aforo,'');
							}else{
								$this->addRate($precio,'');
							}
						}
					}
				}
			}

			// =========================================================================
			/**
			 * funcion verificar_clases
			 * Verifica si la condicion de la config del plugin se cumple, retorna la accion a tomar si se cumple la condicion, en caso contrario devuelve 'nada'
			 *
			 * @access public
			 * @param array $productos
			 * @return string
			 */
			public function verificar_clases(&$productos){
				$accion = $this->get_instance_option('accion');
				$clase = $this->get_instance_option('clase');
				$items = WC()->cart->get_cart();
				
				if($accion !== 'nada' && $clase !== 'nada'){
					$condicion = false;
					$contador = 0;
					foreach($items as $item => $values) { 
				
						$product =  wc_get_product( $values['data']->get_id());
						if($clase === $product->get_shipping_class()){
							$condicion = true;
							$contador++;
						}
						$producto = array();
						if ( method_exists( $product, 'get_height' ) ) {
							$peso = $product->get_weight();
							if($peso < 0){
								$peso = $peso * -1;
							}
							$producto = array(
								"peso" => floatval(wc_get_dimension( $this->fix_format( $peso ), 'kg' )),
								"largo" => floatval(wc_get_dimension( $this->fix_format( $product->get_length() ), 'm' )),
								"ancho" => floatval(wc_get_dimension( $this->fix_format( $product->get_width() ), 'm' )),
								"alto" => floatval(wc_get_dimension( $this->fix_format( $product->get_height() ), 'm' ))
							);
						} else {
							$peso = $product->weight;
							if($peso < 0){
								$peso = $peso * -1;
							}
							$producto = array(
								"peso" => floatval(wc_get_dimension( $this->fix_format( $peso ), 'kg' )),
								"largo" => floatval(wc_get_dimension( $this->fix_format( $product->length ), 'm' )),
								"ancho" => floatval(wc_get_dimension( $this->fix_format( $product->width ), 'm' )),
								"alto" => floatval(wc_get_dimension( $this->fix_format( $product->height ), 'm' ))
							);
						}
						for ($x = 0; $x < $values['quantity']; $x++) {
							array_push($productos,$producto);
						}
					}
					// Si hay envio gratis para X producto, y en el carrito hay otros items, entonces se desactiva el envio gratis
					if($accion === 'envio_gratis' && $contador !== count($items)){
						$condicion = false;
					}
				}else{
					foreach($items as $item => $values) { 
						$product =  wc_get_product( $values['data']->get_id());
						$producto = array();
						if ( method_exists( $product, 'get_height' ) ) {
							$peso = $product->get_weight();
							if($peso < 0){
								$peso = $peso * -1;
							}
							$producto = array(
								"peso" => floatval(wc_get_weight( $this->fix_format( $peso ), 'kg' )),
								"largo" => floatval(wc_get_dimension( $this->fix_format( $product->get_length() ), 'm' )),
								"ancho" => floatval(wc_get_dimension( $this->fix_format( $product->get_width() ), 'm' )),
								"alto" => floatval(wc_get_dimension( $this->fix_format( $product->get_height() ), 'm' ))
							);
						} else {
							$peso = $product->weight;
							if($peso < 0){
								$peso = $peso * -1;
							}
							$producto = array(
								"peso" => floatval(wc_get_weight( $this->fix_format( $peso ), 'kg' )),
								"largo" => floatval(wc_get_dimension( $this->fix_format( $product->length ), 'm' )),
								"ancho" => floatval(wc_get_dimension( $this->fix_format( $product->width ), 'm' )),
								"alto" => floatval(wc_get_dimension( $this->fix_format( $product->height ), 'm' ))
							);
						}
					
						for ($x = 0; $x < $values['quantity']; $x++) {
							array_push($productos,$producto);
						}
					}
					$condicion = false;
				}
				

				if($condicion){
					return $accion;
				}else if(!$condicion && $accion === 'activar_metodo'){
					return 'desactivar_metodo';
				}else{
					return 'nada';
				}
			}


			// =========================================================================
			/**
			 * function crear_tabla_precios
			 *
			 * @access private
			 * @return array
			 */
			private function crear_tabla_precios(){
				$res = array(
					'CABAYGBA' => array(
						'3' => 102.5,
						'5' => 116.39,
						'10' => 133.2,
						'15' => 148.72,
						'20' => 168.12,
						'30' => 206.91,
						'50' => 284.5,
						'60' => 323.3,
						'extra' => 14.87
					),
					'INTERIOR1' => array(
						'3' => 125.44,
						'5' => 153.89,
						'10' => 222.43,
						'15' => 264.52,
						'20' => 318.16,
						'30' => 425.43,
						'50' => 639.98,
						'60' => 747.25,
						'extra' => 16.36
					),
					'INTERIOR2' => array(
						'3' => 151.3,
						'5' => 192.69,
						'10' => 292.26,
						'15' => 392.94,
						'20' => 467.57,
						'30' => 646.65,
						'50' => 1047.33,
						'60' => 1247.67,
						'extra' => 19.14
					),
					'TIERRADELFUEGO' => array(
						'3' => 307.78,
						'5' => 408.65,
						'10' => 483.66,
						'15' => 649.19,
						'20' => 786.27,
						'30' => 889.72,
						'50' => 1278.97,
						'60' => 1527.27,
						'extra' => 23.16
					)
				);
				return $res;				
			}


			// =========================================================================
			/**
			 * function calcular_medidas
			 *
			 * @access private
			 * @return array
			 */
			private function calcular_medidas($productos = array()){
				$res = array(
					'peso' => 0,
				);
				$largo = $ancho = $alto = 0;
				foreach($productos as $producto){						
					$res['peso'] += $producto['peso'];
					$largo += $producto['largo'];
					$ancho += $producto['ancho'];
					$alto += $producto['alto'];
				}
				$res['volumen'] = $largo * $ancho * $alto;
				
				$res['peso'] = number_format($res['peso'], 2);
				if($res['volumen'] < 0.000001 ){
					$res['volumen'] = -1;
				}
				return $res;				
			}


			// =========================================================================
			/**
			 * funcion addRate.
			 * 
			 * Agrega el metodo de envio al carrito
			 *
			 * @access public
			 * @param string $precio, $tipo
			 * @return array
			 */
			public function addRate($precio = '', $tipo = ''){
				if($tipo === 'gratis'){

					$rate = array(
						'id' => 'iflow',
						'label' => $this->get_instance_option('nombre')." Gratuito",
						'cost' => 0,
						'calc_tax' => 'per_item'
					);

				}else if($precio !== ''){
				
					$rate = array(
						'id' => "iflow ".$this->get_instance_option_key(),
						'label' => $this->get_instance_option('nombre'),
						'cost' => $precio,
						'calc_tax' => 'per_item'
					);					

				}
				$this->add_rate( $rate );		
			}

		}
	}
}
add_action( 'woocommerce_shipping_init', 'woo_iflow_envios_iflow_init' );