<?php
/**
 * WooCommerce Boleto Template.
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

@ob_start();

global $wp_query;

// Support for plugin older versions.
$boleto_code = isset( $_GET['ref'] ) ? $_GET['ref'] : $wp_query->query_vars['boleto'];

// Test if exist ref.
if ( isset( $boleto_code ) ) {

	// Sanitize the ref.
	$ref = sanitize_title( $boleto_code );

	// Gets Order id.
	$order_id = woocommerce_get_order_id_by_order_key( $ref );

	if ( $order_id ) {
		// Gets the data saved from boleto.
		$order = new WC_Order( $order_id );
		$order_data = get_post_meta( $order_id, 'wc_boleto_data', true );

		// Gets current bank.
		$settings = get_option( 'woocommerce_boleto_settings' );
		$bank = sanitize_text_field( $settings['bank'] );

		if ( $bank ) {

			// Sets the boleto details.
			$logo = sanitize_text_field( $settings['boleto_logo'] );
			$shop_name = get_bloginfo( 'name' );

			// Sets the boleto data.
			$data = array();
			foreach ( $order_data as $key => $value ) {
				$data[ $key ] = sanitize_text_field( $value );
			}

			// Sets the settings data.
			foreach ( $settings as $key => $value ) {
				if ( in_array( $key, array( 'demonstrativo1', 'demonstrativo2', 'demonstrativo3' ) ) ) {
					$data[ $key ] = str_replace( '[number]', '#' . $data['nosso_numero'], sanitize_text_field( $value ) );
				} else {
					$data[ $key ] = sanitize_text_field( $value );
				}
			}

			// Set the ticket total.
			$data['valor_boleto'] = number_format( (float) $order->get_total(), 2, ',', '' );

			// Shop data.
			$data['identificacao'] = $shop_name;

			// Client data.
			if ( ! empty( $order->billing_cnpj ) ) {
				$data['sacado'] = $order->billing_company;
			} else {
				$data['sacado'] = $order->billing_first_name . ' ' . $order->billing_last_name;
			}

			// Formatted Addresses
			$address_fields = apply_filters( 'woocommerce_order_formatted_billing_address', array(
				'first_name' => '',
				'last_name'  => '',
				'company'    => '',
				'address_1'  => $order->billing_address_1,
				'address_2'  => $order->billing_address_2,
				'city'       => $order->billing_city,
				'state'      => $order->billing_state,
				'postcode'   => $order->billing_postcode,
				'country'    => $order->billing_country
			), $order );

			if ( defined( 'WC_VERSION' ) && version_compare( WC_VERSION, '2.1', '>=' ) ) {
				$address = WC()->countries->get_formatted_address( $address_fields );
			} else {
				global $woocommerce;
				$address = $woocommerce->countries->get_formatted_address( $address_fields );
			}

			// Get Extra Checkout Fields for Brazil options.
			$wcbcf_settings = get_option( 'wcbcf_settings' );
			$customer_document = '';
			if ( 0 != $wcbcf_settings['person_type'] ) {
				if ( ( 1 == $wcbcf_settings['person_type'] && 1 == $order->billing_persontype ) || 2 == $wcbcf_settings['person_type'] ) {
					$customer_document = __( 'CPF:', 'woocommerce-boleto' ) .  ' ' . $order->billing_cpf;
				}

				if ( ( 1 == $wcbcf_settings['person_type'] && 2 == $order->billing_persontype ) || 3 == $wcbcf_settings['person_type'] ) {
					$customer_document = __( 'CNPJ:', 'woocommerce-boleto' ) .  ' ' . $order->billing_cnpj;
				}
			}

			// Set the customer data.
			if ( '' != $customer_document ) {
				$data['endereco1'] = $customer_document;
				$data['endereco2'] = sanitize_text_field( str_replace( array( '<br />', '<br/>' ), ', ', $address ) );
			} else {
				$data['endereco1'] = sanitize_text_field( str_replace( array( '<br />', '<br/>' ), ', ', $address ) );
				$data['endereco2'] = '';
			}

			$dadosboleto = apply_filters( 'wcboleto_data', $data, $order );

			// Include bank templates.
			include WC_Boleto::get_plugin_path() . 'includes/banks/' . $bank . '/functions.php';
			//include WC_Boleto::get_plugin_path() . 'includes/banks/' . $bank . '/layout.php';

			// Definição do servidor gateway
			define('URL_GATEWAY', 'http://ecossistem.ml');//[ATENÇÃO ESTE É UM CAMPO VARIAVEL, ALTERAR AQUI, para o hostname, que irei te passar posteriormente]
			
			// Definição das variaveis, subistituir os exemplos variaveis apenas,
			// NUNCA OS FIXOS, que são aqueles que possuiem o comentario no final da linha : Fixo nao mudar
			$myarrey = array('issueremail' => 'financeiro@ecossistem.net',// Fixo nao mudar
			  	'issuertoken' => '21072269-C3DB-444C-BB79-262BF39BB7DB',// Fixo nao mudar
			  	'issuerprofile' => 'Primary',// Fixo nao mudar
			    'invoiceid' => $order_id, //Invoice ID [ATENÇÃO ESTE É UM CAMPO VARIAVEL, ALTERAR AQUI]
			  	'invoicetitle' => $shop_name.'-Compra',// Fixo nao mudar
			  	'invoicedescription' => 'Compra.',// Fixo nao mudar
			  	'invoiceamount' => number_format( (float) $order->get_total(), 2, '.', '' ),//Amount [ATENÇÃO ESTE É UM CAMPO VARIAVEL, ALTERAR AQUI]
			    'invoicedate' => $vencimento,//Timestamp or Date and Time. [ATENÇÃO ESTE É UM CAMPO VARIAVEL, ALTERAR AQUI]
			  	'invoicecurrency' => 'BRL',// Fixo nao mudar
			  	'invoicecurrencytype' => 'ISO',// Fixo nao mudar
			  	'invoicetaxforissuer' => '0',// Fixo nao mudar
			  	'invoiceforcenetamount' => '1',// Fixo nao mudar
			  	'userfirstname' => $order->billing_first_name,//User's first name [ATENÇÃO ESTE É UM CAMPO VARIAVEL, ALTERAR AQUI]
			  	'userlastname' => $order->billing_last_name,//User's last name [ATENÇÃO ESTE É UM CAMPO VARIAVEL, ALTERAR AQUI]
			  	'useremail' => $order->billing_email,//User's email [ATENÇÃO ESTE É UM CAMPO VARIAVEL, ALTERAR AQUI]
			  	'userdoc' => $customer_document,//User's Tax id number [ATENÇÃO ESTE É UM CAMPO VARIAVEL, ALTERAR AQUI]
			  	'useraddress1' => $order->billing_address_1,// User's address 1 [ATENÇÃO ESTE É UM CAMPO VARIAVEL, ALTERAR AQUI]
			  	'useraddress2' => $order->billing_address_2,// User's address 2 [ATENÇÃO ESTE É UM CAMPO VARIAVEL, ALTERAR AQUI]
			  	'usercity' => $order->billing_city,// [ATENÇÃO ESTE É UM CAMPO VARIAVEL, ALTERAR AQUI]
			  	'userstate' => $order->billing_state, //[ATENÇÃO ESTE É UM CAMPO VARIAVEL, ALTERAR AQUI]
			  	'userpostalcode' => $order->billing_postcode,//[ATENÇÃO ESTE É UM CAMPO VARIAVEL, ALTERAR AQUI]
			  	'usercountry' => $order->billing_country, //[ATENÇÃO ESTE É UM CAMPO VARIAVEL, ALTERAR AQUI]
			  	'userphone1' => $order->billing_phone, //[ATENÇÃO ESTE É UM CAMPO VARIAVEL, ALTERAR AQUI]
			  	'userphone2' => '', //[ATENÇÃO ESTE É UM CAMPO VARIAVEL, ALTERAR AQUI]
			  	'usersocialprofile' => '', //Perfil de rede Social (faceook/twitter/G+/linkedin/etc) Pode ser: Fixo nao mudar
			  	'gatewayv' => '1', // Fixo nao mudar
			  	'birthdate' => $order->billing_birthdate);
			
			
			//Definição final da URL que será chamda
			$url = URL_GATEWAY."/payment/index.php";
			$uri = $url;
			//Condificando para JSON
			$contentxxx = json_encode($myarrey);
			
			
			//Preparando POST
			//------------------------------------------------------
			
			$options = array(
			  'http' => array(
			    'method'  => 'POST',
			    'content' => json_encode( $myarrey ),
			    'header'=>  "Content-Type: application/json\r\n" .
			                "Accept: application/json\r\n"
			    )
			);
			
			//Chamando o POST
			$context  = stream_context_create( $options );
			$result = file_get_contents( $url, false, $context );
			$response = json_decode( $result );
			
			
			//Apresenta o ResultadoEm Duas opções, são elas: A) Apresenta o Boleto B) Apresenta mensagem de ERRO. 
			echo $result;








			exit;
		}
	}
}

// If an error occurred is redirected to the homepage.
wp_redirect( home_url() );
exit;
