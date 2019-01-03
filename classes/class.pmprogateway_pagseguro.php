<?php

define('PLUGIN_PATH', plugin_dir_path(__FILE__));
define('PLUGIN_DIR', plugin_dir_path(__DIR__));
DEFINE('PMPROPAGSEGURO', "pagseguro-paidmembershipspro");
define("PMPRO_PAGSEGURO_VERSION", 'S4TiV4');
	//load classes init method
add_action('init', array('PMProGateway_PagSeguro', 'init'));


add_action('pmpro_save_membership_level', array('PMProGateway_PagSeguro', 'pagseguro_level_meta'));
//add_action("pmpro_delete_membership_level", array('PMProGateway_PagSeguro', 'delete_pagseguro_recurring_payment'));

add_filter('plugin_action_links', array('PMProGateway_PagSeguro', 'plugin_action_links'), 10, 2);

class PMProGateway_PagSeguro extends PMProGateway
{

	/**
	 * @var bool    Is the PagSeguro/PHP Library loaded
	 */
	private static $is_loaded = false;
	/**
	 * @var PagSeguroAssinaturas is the pagseguro library for membership
	 */
	private static $pagseguroAssinaturas;
	/**
	 * @var PagSeguroCompras is the pagseguro library for charge
	 */
	private static $pagseguroCompras;


	function __construct($gateway = null)
	{

		$this->gateway = $gateway;
		$this->gateway_environment = pmpro_getOption("gateway_environment");

		if (true === $this->dependencies()) {
			$this->loadPagSeguroLibrary(true);
			$this->loadPagSeguroLibrary(false);


			self::$is_loaded = true;
		}



		return $this->gateway;
	}
	public static function dependencies()
	{
		global $msg, $msgt, $pmpro_pagseguro_error;

		if (version_compare(PHP_VERSION, '5.3.29', '<')) {

			$pmpro_pagseguro_error = true;
			$msg = -1;
			$msgt = sprintf("O PagSeguro para Paid Membership Pro precisa de uma versão do  PHP 5.3.29 ou maior.Atualize a sua versão do PHP para  %s ou maior. Entre em contato com o administrador", PMPRO_PHP_MIN_VERSION);

			if (!is_admin()) {
				pmpro_setMessage($msgt, "pmpro_error");
			}

			return false;
		}

		$modules = array('curl', 'json');

		foreach ($modules as $module) {
			if (!extension_loaded($module)) {
				$pmpro_pagseguro_error = true;
				$msg = -1;
				$msgt = sprintf("O %s depende do módulo %s para o PHP. Ative esta estenção, ertre em contato com o administrador da hospedagem para saber como ativar", 'Pag Seguro', $module);
				
				//throw error on checkout page
				if (!is_admin())
					pmpro_setMessage($msgt, 'pmpro_error');

				return false;
			}
		}

		self::$is_loaded = true;
		return true;
	}

	/**
	 * Load the Pag Seguro API library.
	 *
	 * @since 1.8
	 * Moved into a method in version 1.8 so we only load it when needed.
	 */
	function loadPagSeguroLibrary($preAproval)
	{
		global $gateway_environment;
		$email = pmpro_getOption('pagseguro_email');

		if ($gateway_environment == 'sandbox') {
			$token = pmpro_getOption('pagseguro_sandbox_token');
			$sandbox = true;
		} else {
			$token = pmpro_getOption('pagseguro_token');
			$sandbox = false;
		}
		if ($preAproval)
			self::$pagseguroAssinaturas = new PagSeguroAssinaturas($email, $token, $sandbox);
		else
			self::$pagseguroCompras = new PagSeguroCompras($email, $token, $sandbox);
	}

	public static function plugin_action_links($links, $file)
	{
		$links[] = '<a href="' .
			admin_url('admin.php?page=pmpro-paymentsettings') .
			'">' . __('Settings') . '</a>';
		return $links;
	}


	function pagseguro_level_meta($level_id)
	{
		global $wpdb, $gateway_environment, $gateway;
		$gateway = pmpro_getGateway();
		if ($gateway != "pagseguro") return;
		
		
		// $data = array('meta_key' => 'pagseguro_period', 'meta_value' => $_REQUEST['pagseguro_period'], 'pmpro_membership_level_id' => $level_id);
		// $format = array('%s', '%s');
		// $wpdb->insert($wpdb->pmpro_membership_levelmeta, $data, $format);
		// $metaid = $wpdb->insert_id;



	}

	static function init()
	{
		//make sure PagSeguro is a gateway option
		add_filter('pmpro_gateways', array('PMProGateway_PagSeguro', 'pmpro_gateways'));
			
		//add fields to payment settings
		add_filter('pmpro_payment_options', array('PMProGateway_PagSeguro', 'pmpro_payment_options'));
		add_filter('pmpro_payment_option_fields', array('PMProGateway_PagSeguro', 'pmpro_payment_option_fields'), 10, 2);
		//setup the callback, for pagseguro notifications 
		add_action('wp_ajax_pmpropagseguro', array('PMProGateway_PagSeguro', 'pmpro_pagseguro_wp_ajax'));
		add_action('wp_ajax_nopriv_pmpropagseguro', array('PMProGateway_PagSeguro', 'pmpro_pagseguro_wp_ajax'));

		//code to add at checkout
		$gateway = pmpro_getGateway();
		if ($gateway == "pagseguro") {
			//change the biling fields for pagseguro
			add_filter('pmpro_required_billing_fields', array('PMProGateway_PagSeguro', 'pmpro_required_billing_fields'));

			//load the transparent checkout javascript dependencies
			add_action('pmpro_checkout_preheader', array('PMProGateway_PagSeguro', 'pmpro_checkout_preheader'));
			add_action('pmpro_billing_preheader', array('PMProGateway_PagSeguro', 'pmpro_checkout_preheader'));

			//change the checkout process
			add_filter('pmpro_checkout_order', array('PMProGateway_PagSeguro', 'pmpro_checkout_order'));
			add_filter('pmpro_billing_order', array('PMProGateway_PagSeguro', 'pmpro_checkout_order'));

			//change the fontend checkout fields
			add_filter('pmpro_include_payment_information_fields', array('PMProGateway_PagSeguro', 'pmpro_include_payment_information_fields'));
			add_filter('pmpro_include_billing_address_fields', array('PMProGateway_PagSeguro', 'pmpro_include_billing_address_fields'));
			
			
			//add_filter('pmpro_checkout_confirmed', array('PMProGateway_PagSeguro', 'pmpro_checkout_confirmed'));
			add_filter('pmpro_checkout_default_submit_button', array('PMProGateway_PagSeguro', 'pmpro_checkout_default_submit_button'));





			add_filter('pmpro_pages_shortcode_invoice', array('PMProGateway_PagSeguro', 'pmpro_pages_shortcode_invoice'), 20, 1);
			add_filter('pmpro_pages_shortcode_confirmation', array('PMProGateway_PagSeguro', 'pmpro_pages_shortcode_confirmation'), 20, 1);
			add_action('pmpro_after_order_settings', array('PMProGateway_PagSeguro', 'pmpro_after_order_settings'));
			add_action('pmpro_membership_level_after_other_settings', array('PMProGateway_PagSeguro', 'pmpro_membership_level_after_other_settings'));
		}
	}


	/**
	 * Code added to checkout preheader.
	 *
	 * @since 1.8
	 */
	static function pmpro_checkout_preheader()
	{
		global $gateway, $pmpro_level, $gateway_environment;
		if (!pmpro_isLevelRecurring($pmpro_level)) return true;
		if ($gateway != "pagseguro") return true;
		self::loadPagSeguroLibrary(true);
		if ($gateway == "pagseguro" && !pmpro_isLevelFree($pmpro_level)) {
			try {
				global $pagsegurojs;
				$pagsegurojs = self::$pagseguroAssinaturas->preparaCheckoutTransparente();

				wp_enqueue_script("pagseguro", $pagsegurojs['script'], array(), null);
				if (!function_exists('pmpro_pagseguro_javascript')) {
					include_once(PLUGIN_DIR . "includes/pagseguro-js.php");
				}
				return false;
			} catch (Exception $e) {
				echo $e->getMessage();
				return true;
			}

		}
	}


	static function pmpro_include_billing_address_fields($include)
	{
		global $pmpro_level;
		if (!pmpro_isLevelRecurring($pmpro_level)) return true;
		//load the billign fields template
		$content = file_get_contents(PLUGIN_DIR . "templates/checkout/billing-address-fields.html");
		// get the request data form
		$data = array();
		$data['cep'] = isset($_REQUEST['cep']) ? $_REQUEST['cep'] : '';
		$data['endereco'] = isset($_REQUEST['endereco']) ? $_REQUEST['endereco'] : '';
		$data['numero'] = isset($_REQUEST['numero']) ? $_REQUEST['numero'] : '';
		$data['complemento'] = isset($_REQUEST['complemento']) ? $_REQUEST['complemento'] : '';
		$data['bairro'] = isset($_REQUEST['bairro']) ? $_REQUEST['bairro'] : '';
		$data['cidade'] = isset($_REQUEST['cidade']) ? $_REQUEST['cidade'] : '';
		$data['estado'] = isset($_REQUEST['estado']) ? $_REQUEST['estado'] : '';
		$data['telefoneddd'] = isset($_REQUEST['telefoneddd']) ? $_REQUEST['telefoneddd'] : '';
		$data['telefonenumber'] = isset($_REQUEST['telefonenumber']) ? $_REQUEST['telefonenumber'] : '';

		//set up billing fields form values
		foreach ($data as $key => $value) {
			$content = str_replace("!!" . $key . "!!", $value, $content);
		}
		// print the billing fields checkout 
		echo ($content);
		return false;
	}
	static function pmpro_include_payment_information_fields($include)
	{
		global $pmpro_requirebilling, $pmpro_show_discount_code, $discount_code, $pmpro_level;
		if (!pmpro_isLevelRecurring($pmpro_level)) return true;
		//include the payment checkout fields and values
		include_once(PLUGIN_DIR . "templates/checkout/payment-fields.php");

		return false;
	}

	/**
	 * Swap in our submit buttons.
	 *
	 * @since 1.8
	 */
	static function pmpro_checkout_default_submit_button($show)
	{
		global $gateway, $pmpro_level;
		if (pmpro_isLevelRecurring($pmpro_level)) return $show;

		include_once(PLUGIN_DIR . "templates/checkout/button.php");
		return false;
	}
	static function pmpro_membership_level_after_other_settings()
	{
		global $wpdb, $gateway_environment, $gateway;
		$gateway = pmpro_getGateway();
		if ($gateway != "pagseguro") return;

		if (isset($_REQUEST['edit'])) {
			$level = $wpdb->get_row(
				$wpdb->prepare(
					"
					SELECT * FROM $wpdb->pmpro_membership_levels
					WHERE id = %d LIMIT 1",
					intval($_REQUEST['edit'])
				),
				OBJECT
			);

			if (intval($level->cycle_number) == 1 && $level->cycle_period == 'Week')
				$levelperiod = '1';
			elseif (intval($level->cycle_number) == 1 && $level->cycle_period == 'Month')
				$levelperiod = '2';
			elseif (intval($level->cycle_number) == 2 && $level->cycle_period == 'Month')
				$levelperiod = '3';
			elseif (intval($level->cycle_number) == 3 && $level->cycle_period == 'Month')
				$levelperiod = '4';
			elseif (intval($level->cycle_number) == 6 && $level->cycle_period == 'Month')
				$levelperiod = '5';
			else
				$levelperiod = '6';
		}
		include_once(PLUGIN_DIR . "templates/admin/level-fields-js.php");

	}

	/**
	 * Make sure PagSeguro is in the gateways list
	 */
	static function pmpro_gateways($gateways)
	{
		if (empty($gateways['pagseguro'])) {
			$gateways = array_slice($gateways, 0, 1) + array("pagseguro" => __('PagSeguro', PMPROPAGSEGURO)) + array_slice($gateways, 1);
		}
		return $gateways;
	}


	function pmpro_pagseguro_wp_ajax()
	{
		global $wpdb, $gateway_environment, $gateway, $current_user;


		$email = pmpro_getOption('pagseguro_email');

		if ($gateway_environment == 'sandbox') {
			header("access-control-allow-origin: https://sandbox.pagseguro.uol.com.br");
			$token = pmpro_getOption('pagseguro_sandbox_token');
			$sandbox = true;
		} else {

			$token = pmpro_getOption('pagseguro_token');
			$sandbox = false;
		}
		$pagseguro = new PagSeguroCompras($email, $token, $sandbox);

		if (isset($_REQUEST['notificationCode'])) {
			if ($_REQUEST['notificationType'] == 'transaction') {
				$codigo = $_REQUEST['notificationCode']; //Recebe o código da notificação e busca as informações de como está a assinatura
				$response = self::$pagseguroAssinaturas->consultarNotificacao($codigo);
				$code = $response['code'];
			}
		} else {
			exit;

		}

		$pagseguro = new PagSeguroCompras($email, $token, $sandbox);

		$response = self::$pagseguroAssinaturas->consultarCompra($code);
				
				//Pelo Código da Referencia
		$referencia = $response['reference'];

		$order = new MemberOrder($referencia);
		$order->payment_transaction_id = $response['code'];

		$order->getUser();
		$level = pmpro_getLevel($order->membership_id);


				//$order->getMembership();

		switch (intval($response['paymentMethod']->type)) {
			case 1: // cartão 
				break;
			case 2: //boleto				
				$order->cardtype = "boleto";
				$order->notes = $response['paymentLink'];
				break;
			case 3: // Debito Online TEF
				break;
			case 4: // saldo PagSeguro
				break;

		}
		$end_timestamp = strtotime("+" . $level->cycle_number . " " . $level->cycle_period, current_time('timestamp'));
		switch (intval($response['status'])) {
			case 1: //pending
				$order->status = "pending";


				self::add_order_note($order->id, sprintf("Detalhes do Pagamento <br><br>Referência  : %s</a>. Aguardando Pagamento.", $order->code));
				break;
			case 2: //review
				$order->status = "review";
			
				//	self::add_order_note($order->id, sprintf(__("Detalhes do Pagamento <br><br>Referência  : %s</a>. Pagamento em Análize.", PMPROPAGSEGURO), $order->code));
				break;
			case 3: //success

				$order->status = "success";

				$wpdb->query($sqlQuery);
				self::add_order_note($order->id, sprintf("Detalhes do Pagamento <br><br>Referência  : %s</a>. Pagamento Realizado.", $order->code));
				break;
			case 4: // Disponivel

				$order->status = "success";

				$wpdb->query($sqlQuery);	
					//self::add_order_note($order->id, sprintf(__("Detalhes do Pagamento <br><br>Referência  : %s</a>. Pagamento Disponivel.", PMPROPAGSEGURO), $order->code));
				break;
			case 5: // Em Disputa
				$order->status = "review";
				$wpdb->query($sqlQuery);
					//self::add_order_note($order->id, sprintf(__("Detalhes do Pagamento <br><br>Referência  : %s</a>. Pagamento em Disputa, consute o site do Pag Seguro.", PMPROPAGSEGURO), $order->code));
				break;
			case 6: // refunded
				$order->status = "refunded";
				self::add_order_note($order->id, sprintf("Detalhes do Pagamento <br><br>Referência  : %s</a>. Pagamento Devolvido.", $order->code));
				break;
			case 7:
				$order->status = "error";
				self::add_order_note($order->id, sprintf("Detalhes do Pagamento <br><br>Referência  : %s</a>. Pagamento Não Concluido.", $order->code));
				break;
			case 8:
				$order->status = "cancelled";
				self::add_order_note($order->id, sprintf("Detalhes do Pagamento <br><br>Referência  : %s</a>. Pagamento Cancelado.", $order->code));
				break;
		}
		if ($order->status == 'success') {

			$pmpro_level = $wpdb->get_row("SELECT * FROM $wpdb->pmpro_membership_levels WHERE id = '" . (int)$order->membership_id . "' LIMIT 1");
				//var_dump($pmpro_level);
			$startdate = apply_filters("pmpro_checkout_start_date", "'" . current_time("mysql") . "'", $order->user_id, $pmpro_level);

			if (!empty($pmpro_level->expiration_number)) {
				$enddate = "'" . date("Y-m-d", strtotime("+ " . $pmpro_level->expiration_number . " " . $pmpro_level->expiration_period, current_time("timestamp"))) . "'";
			} else {
				$enddate = "NULL";
			}

			$custom_level = array(
				'user_id' => $order->user_id,
				'membership_id' => $pmpro_level->id,
				'code_id' => '',
				'initial_payment' => $pmpro_level->initial_payment,
				'billing_amount' => $pmpro_level->billing_amount,
				'cycle_number' => $pmpro_level->cycle_number,
				'cycle_period' => $pmpro_level->cycle_period,
				'billing_limit' => $pmpro_level->billing_limit,
				'trial_amount' => $pmpro_level->trial_amount,
				'trial_limit' => $pmpro_level->trial_limit,
				'startdate' => $startdate,
				'enddate' => $enddate
			);


			if (pmpro_changeMembershipLevel($custom_level, $order->user_id, 'changed')) {
				$order->status = "success";
				$order->membership_id = $pmpro_level->id;
				update_user_meta(
					$order->user_id,
					"pmpro_pagseguro_next_update",
					json_encode(array(
						'date' => date("Y-m-d", strtotime("+ $pmpro_level->cycle_number $pmpro_level->cycle_period")),
						'reference' => $order->code
					))
				);

			}
		}
		$order->saveOrder();
		http_response_code(200);
		exit;
	}



	/**
	 * Get a list of payment options that the PagSeguro gateway needs/supports.
	 */
	static function getGatewayOptions()
	{
		$options = array(
			'pagseguro_email',
			'pagseguro_sandbox_token',
			'pagseguro_token',
			'gateway_environment',
			'currency',
			'tax_state',
			'tax_rate',
		);

		return $options;
	}

	/**
	 * Set payment options for payment settings page.
	 */
	static function pmpro_payment_options($options)
	{
				//get PagSeguro options
		$pagseguro_options = self::getGatewayOptions();

				//merge with others.
		$options = array_merge($pagseguro_options, $options);

		return $options;
	}
	/**
	 * Display fields for this gateway's options.
	 *
	 * @since 1.8
	 */
	static function pmpro_payment_option_fields($values, $gateway)
	{
		include_once(PLUGIN_DIR . "includes/admin-options.php");
	}

	/**
	 * Remove required billing fields
	 */
	static function pmpro_required_billing_fields($fields)
	{
		unset($fields['bfirstname']);
		unset($fields['blastname']);
		unset($fields['baddress1']);
		unset($fields['bcity']);
		unset($fields['bstate']);
		unset($fields['bzipcode']);
		unset($fields['bphone']);
		unset($fields['bemail']);
		unset($fields['bcountry']);
		unset($fields['CardType']);
		unset($fields['AccountNumber']);
		unset($fields['ExpirationMonth']);
		unset($fields['ExpirationYear']);
		unset($fields['CVV']);

		return $fields;
	}
	static function pmpro_checkout_order($order)
	{
		global $wpdb, $current_user;

		$data = $_REQUEST;
		$order->accountnumber = $data['cardnumber'];
		$order->expirationmonth = $data['cardexpmonth'];
		$order->expirationyear = $data['cardexpyear'];
		$order->FirstName = $current_user->user_firstname;
		$order->LastName = $current_user->user_lastname;
		$order->Email = $current_user->user_email;
		$order->Address1 = $data['endereco'] . " " . $data['numero'];
		$order->Address2 = $data['complemento'];
		$order->billing->name = $data['cardholdername'];
		$order->billing->street = $data['endereco'] . " " . $data['numero'];
		$order->billing->city = $data['cidade'];
		$order->billing->state = $data['estado'];
		$order->billing->country = 'BRA';
		$order->billing->zip = $data['cep'];
		$order->billing->phone = $data['telefoneddd'] . " " . $data['telefonenumber'];
		$order->client_hash = $data['client_hash'];
		$order->card_token = $data['card_token'];
		$order->pagseguro_data = $data;
		if (empty($order->FirstName) && empty($order->LastName)) {
			if (!empty($current_user->ID)) {
				$order->FirstName = get_user_meta($current_user->ID, "first_name", true);
				$order->LastName = get_user_meta($current_user->ID, "last_name", true);
			} elseif (!empty($_REQUEST['first_name']) && !empty($_REQUEST['last_name'])) {
				$order->FirstName = sanitize_text_field($_REQUEST['first_name']);
				$order->LastName = sanitize_text_field($_REQUEST['last_name']);
			}
		}

		return $order;
	}

	/**
	 * Code to run after checkout
	 *
	 * @since 1.8
	 */
	static function pmpro_after_checkout($user_id, $order)
	{
		global $gateway;

		if ($gateway == "pagseguro") {
			if (self::$is_loaded && !empty($order) && !empty($order->pagseguro_plancode)) {
				update_user_meta($user_id, "pmpro_pagseguro_plancode", $order->pagseguro_plancode);
			}
		}
	}

	/**
	 * Process checkout and decide if a charge and or subscribe is needed
	 *
	 * @since 1.4
	 */
	function process(&$order)
	{
		if (pmpro_isLevelRecurring($order->membership_level)) {
			if ($this->subscribe($order)) {
				$order->saveOrder();
				return true;
			} else {
				if (empty($order->error)) {
					if (!self::$is_loaded) {

						$order->error = __("Payment error: Please contact the webmaster (pagseguro-load-error)", 'paid-memberships-pro');

					} else {

						$order->error = __("Unknown error: payment failed.", 'paid-memberships-pro');
					}
				}

				return false;
			}
		} else {
			return $this->sendToPagSeguro($order);
		}


	}



	function subscribe(&$order)
	{
		global $wpdb, $gateway, $gateway_environment;
		//create a code for the order
		if (empty($order->code))
			$order->code = $order->getRandomCode();

				//filter order before subscription. use with care.
		$order = apply_filters("pmpro_subscribe_order", $order, $this);
		if (!empty($order->user_id))
			$user_id = $order->user_id;
		else {
			global $current_user;
			$user_id = $current_user->ID;
		}

		$level_id = $order->membership_id;
		$level = $wpdb->get_row(
			$wpdb->prepare(
				"
				SELECT * FROM $wpdb->pmpro_membership_levels
				WHERE id = %d LIMIT 1",
				intval($level_id)
			),
			OBJECT
		);

		if (intval($level->cycle_number) == 1 && $level->cycle_period == 'Week')
			$levelperiod = self::$pagseguroAssinaturas::SEMANAL;
		elseif (intval($level->cycle_number) == 1 && $level->cycle_period == 'Month')
			$levelperiod = self::$pagseguroAssinaturas::MENSAL;
		elseif (intval($level->cycle_number) == 2 && $level->cycle_period == 'Month')
			$levelperiod = self::$pagseguroAssinaturas::BIMESTRAL;
		elseif (intval($level->cycle_number) == 3 && $level->cycle_period == 'Month')
			$levelperiod = self::$pagseguroAssinaturas::TRIMESTRAL;
		elseif (intval($level->cycle_number) == 6 && $level->cycle_period == 'Month')
			$levelperiod = self::$pagseguroAssinaturas::SEMESTRAL;
		else
			$levelperiod = self::$pagseguroAssinaturas::ANUAL;
		if ($gateway_environment == "sandbox") {
			$levelmeta = $wpdb->get_row($wpdb->prepare("
			SELECT * FROM $wpdb->pmpro_membership_levelmeta WHERE pmpro_membership_level_id	= %d and meta_key = 'pmpro_pagseguro_sandbox_code' LIMIT 1", $level_id), OBJECT);
		} else {
			$levelmeta = $wpdb->get_row($wpdb->prepare("
			SELECT * FROM $wpdb->pmpro_membership_levelmeta WHERE pmpro_membership_level_id	= %d and meta_key = 'pmpro_pagseguro_code' LIMIT 1", $level_id), OBJECT);
		}
		$order->user_id = $user_id;
		$order->subtotal = floatval($order->PaymentAmount) + floatval($order->InitialPayment);
		$order->total = $order->subtotal;
		$order->payment_type = "Pag Seguro";
		$morder->ProfileStartDate = date_i18n("Y-m-d") . "T0:0:0";
		$order->saveOrder();

		//print_r($order);
		// die;



		$codigoPlano = '';
		try {
			if (!isset($levelmeta)) {

				self::$pagseguroAssinaturas->setReferencia("PAGSEGURO-PMPRO: " . $order->membership_name);
				self::$pagseguroAssinaturas->setNomeAssinatura($order->membership_name);
				self::$pagseguroAssinaturas->setDescricao("Plano: " . $order->membership_name);

				self::$pagseguroAssinaturas->setPeriodicidade($levelperiod);
				self::$pagseguroAssinaturas->setValor(floatval($order->PaymentAmount));

				if (intval($level->expiration_number) > 0)
					self::$pagseguroAssinaturas->setExpiracao(intval($level->expiration_number), ($level->expiration_period == "Month" ? 'MONTHS' : 'YEARS'));
				if (!empty($order->TrialBillingCycles) && $order->TrialAmount == 0)
					self::$pagseguroAssinaturas->setPeriodoTeste(intval($order->TrialBillingCycles));
				if (!empty($order->InitialPayment))
					self::$pagseguroAssinaturas->setTaxaAdesao(floatval($order->InitialPayment));


				// self::$pagseguroAssinaturas->setRedirectURL(pmpro_url("checkout", "?level=" . $order->membership_id . "&order_ref=" . $order->code));


				$codigoPlano = self::$pagseguroAssinaturas->criarPlano();
				if ($gateway_environment == "sandbox")
					$data = array('meta_key' => 'pmpro_pagseguro_sandbox_code', 'meta_value' => $codigoPlano, 'pmpro_membership_level_id' => $order->membership_id);
				else
					$data = array('meta_key' => 'pmpro_pagseguro_code', 'meta_value' => $codigoPlano, 'pmpro_membership_level_id' => $order->membership_id);
				$format = array('%s', '%s');

				$wpdb->insert($wpdb->pmpro_membership_levelmeta, $data, $format);


				if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
					$ip = $_SERVER['HTTP_CLIENT_IP'];
				} elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
					$ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
				} else {
					$ip = $_SERVER['REMOTE_ADDR'];
				}
				if ($gateway_environment == 'sandbox') {
					$ip = '192.168.0.1';
				}
				self::$pagseguroAssinaturas->setIp($ip);
				self::$pagseguroAssinaturas->setNomeCliente($order->FirstName . " " . $order->LastName);
				self::$pagseguroAssinaturas->setNomeCliente($order->pagseguro_data['cardholdername'], true);
				if ($gateway_environment == 'sandbox')
					self::$pagseguroAssinaturas->setEmailCliente('email@sandbox.pagseguro.com.br');
				else
					self::$pagseguroAssinaturas->setEmailCliente(get_user_meta($user_id, "user_email", true));

				self::$pagseguroAssinaturas->setTelefone($order->pagseguro_data['telefoneddd'], $order->pagseguro_data['telefonenumber']);

				self::$pagseguroAssinaturas->setCPF($order->pagseguro_data['cpfholder'], true);
				self::$pagseguroAssinaturas->setCPF($order->pagseguro_data['cpfsender']);

				self::$pagseguroAssinaturas->setEnderecoCliente($order->pagseguro_data['endereco'], $order->pagseguro_data['numero'], $order->pagseguro_data['complemento'], $order->pagseguro_data['bairro'], $order->pagseguro_data['cidade'], $order->pagseguro_data['estado'], $order->pagseguro_data['cep']);

				self::$pagseguroAssinaturas->setNascimentoCliente($order->pagseguro_data['birthday'] . "/" . $order->pagseguro_data['birthmonth'] . "/" . $order->pagseguro_data['birthyear']);

				self::$pagseguroAssinaturas->setHashCliente($order->pagseguro_data['client_hash']);

				self::$pagseguroAssinaturas->setTokenCartao($order->pagseguro_data['card_token']);

				self::$pagseguroAssinaturas->setReferencia($order->code);


				self::$pagseguroAssinaturas->setPlanoCode($codigoPlano);
				$codigoAssinatura = self::$pagseguroAssinaturas->assinaPlano();
				$order->payment_transaction_id = $codigoAssinatura;
				$order->subscription_transaction_id = $codigoAssinatura;
				$order->status = "success";
				$order->saveOrder();
			} else {
				// $codigoPlano = $levelmeta->meta_value;
				// $url = self::$pagseguroAssinaturas->assinarPlanoCheckout($codigoPlano);
				// wp_redirect($url);
				// die;


				if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
					$ip = $_SERVER['HTTP_CLIENT_IP'];
				} elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
					$ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
				} else {
					$ip = $_SERVER['REMOTE_ADDR'];
				}
				if ($gateway_environment == 'sandbox') {
					$ip = '192.168.0.1';
				}
				self::$pagseguroAssinaturas->setIp($ip);
				self::$pagseguroAssinaturas->setNomeCliente($order->FirstName . " " . $order->LastName);
				self::$pagseguroAssinaturas->setNomeCliente($order->pagseguro_data['cardholdername'], true);
				if ($gateway_environment == 'sandbox')
					self::$pagseguroAssinaturas->setEmailCliente('email@sandbox.pagseguro.com.br');
				else
					self::$pagseguroAssinaturas->setEmailCliente(get_user_meta($user_id, "user_email", true));

				self::$pagseguroAssinaturas->setTelefone($order->pagseguro_data['telefoneddd'], $order->pagseguro_data['telefonenumber']);

				self::$pagseguroAssinaturas->setCPF($order->pagseguro_data['cpfholder'], true);
				self::$pagseguroAssinaturas->setCPF($order->pagseguro_data['cpfsender']);

				self::$pagseguroAssinaturas->setEnderecoCliente($order->pagseguro_data['endereco'], $order->pagseguro_data['numero'], $order->pagseguro_data['complemento'], $order->pagseguro_data['bairro'], $order->pagseguro_data['cidade'], $order->pagseguro_data['estado'], $order->pagseguro_data['cep']);

				self::$pagseguroAssinaturas->setNascimentoCliente($order->pagseguro_data['birthday'] . "/" . $order->pagseguro_data['birthmonth'] . "/" . $order->pagseguro_data['birthyear']);

				self::$pagseguroAssinaturas->setHashCliente($order->pagseguro_data['client_hash']);

				self::$pagseguroAssinaturas->setTokenCartao($order->pagseguro_data['card_token']);

				self::$pagseguroAssinaturas->setReferencia($order->code);


				self::$pagseguroAssinaturas->setPlanoCode($codigoPlano);
				$codigoAssinatura = self::$pagseguroAssinaturas->assinaPlano();
				$order->payment_transaction_id = $codigoAssinatura;
				$order->subscription_transaction_id = $codigoAssinatura;
				$order->status = "success";
				$order->saveOrder();



			}
			return true;

		} catch (Exception $e) {
			$order->error = __("Erro ao assinar o plano com o Pag Seguro: ", 'paid-memberships-pro') . $e->getMessage();
			$order->shorterror = $order->error;
			$order->status = "error";
			$order->saveOrder();
			return false;
		}


	}




	function sendToPagSeguro(&$order)
	{
		global $wpdb, $gateway, $gateway_environment;
		//create a code for the order
		if (empty($order->code))
			$order->code = $order->getRandomCode();

		self::loadPagSeguroLibrary(false);
		
				//filter order before subscription. use with care.
		$order = apply_filters("pmpro_subscribe_order", $order, $this);
		if (!empty($order->user_id))
			$user_id = $order->user_id;
		else {
			global $current_user;
			$user_id = $current_user->ID;
		}

		$order->user_id = $user_id;
		$order->saveOrder();
		// print_r($pagseguro);
		// die;
		if ($gateway_environment == 'sandbox')
			self::$pagseguroCompras->setEmailCliente('emails@sandbox.pagseguro.com.br');
		else
			self::$pagseguroCompras->setEmailCliente($current_user->user_email);
			//Nome do comprador (OPCIONAL)
		self::$pagseguroCompras->setNomeCliente($order->billing->name);	
			//Email do comprovador (OPCIONAL)
		self::$pagseguroCompras->setEmailCliente($current_user->user_email);
			//Código usado pelo vendedor para identificar qual é a compra (OPCIONAL)
		self::$pagseguroCompras->setReferencia($order->code);	
			//print_r(self::$pagseguroAssinaturas->setReferencia($order->code));
			//Adiciona os itens da compra (ID do ITEM, DESCRICAO, VALOR, QUANTIDADE)
		self::$pagseguroCompras->adicionarItem(
			$order->membership_id,
			$order->membership_name,
			floatval($order->total),
			1
		);


		$url = esc_url_raw(admin_url("admin-ajax.php") . "?action=pmpropagseguro");
		// 	// //URL para onde será enviado as notificações de alteração da compra (OPCIONAL)
		self::$pagseguroCompras->setNotificationURL($url);		
		// 	// //URL para onde o comprador será redicionado após a compra (OPCIONAL)
		self::$pagseguroCompras->pagseguroCompras(pmpro_url("checkout", "?level=" . $order->membership_id . "&order_ref=" . $order->code));




		try {

			$url = self::$pagseguroCompras->gerarURLCompra();
			//print_r($order);
			wp_redirect($url);
			exit;
		} catch (Exception $e) {
			$order->status = "error";
			//print_r($order);
			die;
			return false;
		}
			//print_r($order);


	}


	function getPagSeguroCheckoutDetails($ref)
	{
		global $gateway_environment;
		$email = pmpro_getOption('pagseguro_email');

		if ($gateway_environment == 'sandbox') {
			$token = pmpro_getOption('pagseguro_sandbox_token');
			$sandbox = true;
		} else {
			$token = pmpro_getOption('pagseguro_token');
			$sandbox = false;
		}
		//self::$pagseguroAssinaturas = new PagSeguroCompras($email, $token, $sandbox);
		try {
			$response = self::$pagseguroCompras->consultarCompraByReferencia($ref);
			return $response;
		} catch (Exception $e) {
			return null;
		}

	}


	function cancel(&$order)
	{
		$order->updateStatus("cancelled");
		global $wpdb;
		try {
		//self::$pagseguroAssinaturas->cancelarAssinatura($order->subscription_transaction_id);
			$order->saveOrder();
		} catch (Exception $e) {
			$order->saveOrder();
		}
		//$wpdb->query("DELETE FROM $wpdb->pmpro_membership_orders WHERE id = '" . $order->id . "'");
		return true;

	}
	function delete(&$order)
	{
		//no matter what happens below, we're going to cancel the order in our system
		$order->updateStatus("cancelled");
		global $wpdb;
		$wpdb->query("DELETE FROM $wpdb->pmpro_membership_orders WHERE id = '" . $order->id . "'");
	}




	/**
	 * 1.12 Custom confirmation page
	 *
	 */

	static function pmpro_checkout_confirmed($pmpro_confirmed)
	{
		global $pmpro_msg, $pmpro_msgt, $pmpro_level, $current_user, $pmpro_review, $pmpro_pagseguro_ref, $discount_code, $bemail;
		if (!pmpro_isLevelRecurring($pmpro_level)) return $pmpro_confirmed;
		if (isset($_REQUEST['order_ref'])) {
			$order_ref = $_REQUEST['order_ref'];
			$order = new MemberOrder($order_ref);
			$response = $order->Gateway->getPagSeguroCheckoutDetails($order_ref);
			$pmpro_confirmed = true;


		}


		if (!empty($order)) {
			$pmpro_pagseguro_ref = $order->code;
			return array("pmpro_confirmed" => $pmpro_confirmed, "order" => $order);
		} else
			return $pmpro_confirmed;
	}

	public static function pmpro_pages_shortcode_confirmation($content)
	{
		global $wpdb, $current_user, $pmpro_invoice, $pmpro_msg, $pmpro_msgt;
		$gateway = pmpro_getGateway();

		if ($gateway != "pagseguro") return $content;

		if ($pmpro_msg) {
			sprintf('<div class="pmpro_message <?php echo $pmpro_msgt ?>"><?php echo $pmpro_msg ?></div>');
		}

		if (empty($current_user->membership_level))
			$confirmation_message = "<p>" . __('Your payment has been submitted. Your membership will be activated shortly.', 'paid-memberships-pro') . "</p>";
		else
			$confirmation_message = "<p>" . sprintf(__('Thank you for your membership to %s. Your %s membership is now active.', 'paid-memberships-pro'), get_bloginfo("name"), $current_user->membership_level->name) . "</p>";		
	
	//confirmation message for this level
		$level_message = $wpdb->get_var("SELECT l.confirmation FROM $wpdb->pmpro_membership_levels l LEFT JOIN $wpdb->pmpro_memberships_users mu ON l.id = mu.membership_id WHERE mu.status = 'active' AND mu.user_id = '" . $current_user->ID . "' LIMIT 1");
		if (!empty($level_message))
			$confirmation_message .= "\n" . stripslashes($level_message) . "\n";
		if (!empty($pmpro_invoice) && !empty($pmpro_invoice->id)) {
			$pmpro_invoice->getUser();
			$pmpro_invoice->getMembershipLevel();

			$confirmation_message .= "<p>" . sprintf(__('Below are details about your membership account and a receipt for your initial membership invoice. A welcome email with a copy of your initial membership invoice has been sent to %s.', 'paid-memberships-pro'), $pmpro_invoice->user->user_email) . "</p>";
			$confirmation_message = apply_filters("pmpro_confirmation_message", $confirmation_message, $pmpro_invoice);

			echo $confirmation_message;


			if (!empty($pmpro_invoice) && $pmpro_invoice->gateway == "pagseguro") {

				$content = file_get_contents(PLUGIN_DIR . "templates/invoice.html");

				$button = array('src' => pmpro_url('account'), 'text' => 'Continuar');
				$data = self::getInvoiceTemplateKeys($pmpro_invoice, $button);

				foreach ($data as $key => $value) {
					$content = str_replace("!!" . $key . "!!", $value, $content);
				}




			}

		}
		return $content;
	}

	public static function getInvoiceTemplateKeys($invoice, $button)
	{

		$data = array();
		if (empty($invoice->accountnumber)) {
			$paymenttype = "Boleto";
			$paymentdetais = "<a href='$invoice->notes' target='_blank'>Mostrar Boleto</a>";
		} else {
			$paymenttype = "Cartão";
			$paymentdetails = " $invoice->accountnumber | $invoice->expirationmonth/$invoice->expirationyear ";
		}

		$int_cycle = intval($invoice->membership_level->cycle_number);
		if ($invoice->membership_level->cycle_period == "Month") {
			$cycle_period = $int_cycle == 1 ? "Mês" : "Meses";
		} else if ($invoice->membership_level->cycle_period == "Year") {
			$cycle_period = $int_cycle == 1 ? "Ano" : "Anos";
		} else if ($invoice->membership_level->cycle_period == "Week") {
			$cycle_period = $int_cycle == 1 ? "Semana" : "Semanas";
		} else if ($invoice->membership_level->cycle_period == "Day") {
			$cycle_period = $int_cycle == 1 ? "Dia" : "Dias";
		} else {
			$cycle_period = null;
		}
		$status = "N/A";
		$status_color = "#fafafa";
		if ($invoice->status == "pending") {
			$status = "	Aguardando pagamento";
			$status_color = "#ff9800";
		} elseif ($invoice->status == "review") {
			$status = "Em análise";
			$status_color = "#ffc107";
		} elseif ($invoice->status == "success") {
			$status = "Paga";
			$status_color = "#8bc34a";
		} elseif ($invoice->status == 'error') {
			$status = "Não Concluida";
			$status_color = "#ff3434";
		} elseif ($invoice->status == 'cancelled') {
			$status = "Cancelada Pelo Comprador";
			$status_color = "#ff3434";
		}

		$data = array_merge($data, array(
			'invoice_id' => $invoice->code,
			'invoice_date' => date_format(date_create(), "d/m/Y"),
			'invoice_status' => $status,
			'status_color' => $status_color,
			'baddress1' => $invoice->billing->street,
			'cidade' => $invoice->billing->city,
			'estado' => $invoice->billing->state,
			'cep' => $invoice->billing->zip,
			'firstname' => $invoice->FirstName,
			'lastname' => $invoice->LastName,
			'email' => $invoice->Email,
			'paymenttype' => $paymenttype,
			'paymentdetails' => $paymentdetails,
			'membership_name' => $invoice->membership_level->name,
			'membership_initial_payment' => (isset($cycle_period) ? "R$ " . $invoice->membership_level->initial_payment : "-----"),
			'membership_billing_amount' => (isset($cycle_period) ? "R$ " . $invoice->membership_level->billing_amount : '-----'),
			'membership_cycle_period' => (isset($cycle_period) ? $invoice->membership_level->cycle_number . " " . $cycle_period : '-----'),
			'membership_price' => (isset($cycle_period) ? "R$ " . $invoice->membership_level->initial_payment . " + (R$ " . $invoice->membership_level->billing_amount . " * " . $invoice->membership_level->cycle_number . " " . $cycle_period . ") " : $invoice->membership_level->initial_payment),
			'button_src' => $button['src'],
			'button_text' => $button['text']
		));


		return $data;
	}






	/**
	 * 1.14 Custom invoice
	 *
	 */
	public static function pmpro_pages_shortcode_invoice($content)
	{
		global $wpdb, $pmpro_invoice, $pmpro_msg, $pmpro_msgt, $current_user;

		$gateway = pmpro_getGateway();
		if ($gateway != "pagseguro") return $content;

		if ($pmpro_msg) {
			sprintf('<div class="pmpro_message <?php echo $pmpro_msgt ?>"><?php echo $pmpro_msg ?></div>');
		}



		if ($pmpro_invoice) {
			$pmpro_invoice->getUser();
			$pmpro_invoice->getMembershipLevel();
		}

		if (!empty($pmpro_invoice)) {

			$content = file_get_contents(PLUGIN_DIR . "templates/invoice.html");
			$button = array('src' => pmpro_url('invoice'), 'text' => 'Voltar');
			$data = self::getInvoiceTemplateKeys($pmpro_invoice, $button);

			foreach ($data as $key => $value) {
				$content = str_replace("!!" . $key . "!!", $value, $content);
			}


			return $content;

		} else {
			return $content;
		}
	}




	/**
	 * 1.15 Show payment log on order details page
	 */
	public static function pmpro_after_order_settings($order)
	{
		if (!empty($order) && $order->gateway == "pagseguro") {
			$data = self::display_order_notes();

			if ($data) {
				$tmp = '<tr><th scope="row" valign="top"></th>';
				$tmp .= '<td>';
				$tmp .= $data;
				$tmp .= '</td>';
				$tmp .= '</tr>';

				echo $tmp;
			}
		}

		return true;
	}




	/**
	 * 1.16 Save payment log
	 */
	public static function add_order_note($order_id, $notes)
	{
		$id = PMPROPAGSEGURO . "_" . $order_id . "_pagseguro_log";
		$dt = date("d M Y, H:i", current_time('timestamp'));

		$arr = get_option($id);
		if (!$arr) $arr = array();
		$arr[] = "<tr><th style='padding-top:15px' valign='top'>" . $dt . "</th><td>" . $notes . "</td></tr>";
		update_option($id, $arr);

		return true;
	}




	/**
	 * 1.17 Display payment log
	 */
	public static function display_order_notes()
	{
		$tmp = "";
		if (is_admin() && isset($_GET["order"]) && is_numeric($_GET["order"]) && isset($_GET["page"]) && $_GET["page"] == "pmpro-orders") {
			$order_id = $_GET["order"];

			$data = get_option(PMPROPAGSEGURO . "_" . $order_id . "_pagseguro_log");

			if ($data) {
				$tmp = "<br><h3>" . __("Log de PAgamento", PMPROPAGSEGURO) . " -</h3>";
				$tmp .= "<table>" . implode("\n", $data) . "</table>";
			}
		}

		return $tmp;
	}

	static function pmpro_next_payment($timestamp, $user_id, $order_status)
	{
	//find the last order for this user
		if (!empty($user_id)) {
		//get last order
			$order = new MemberOrder();
			$order->getLastMemberOrder($user_id, $order_status);


			if (!empty($order->id) && !empty($order->subscription_transaction_id) && $order->gateway == "pagseguro") {
				return $timestamp;

			}
		}
		return $timestamp;

	}

}


class PagSeguroAssinaturas
{
	
	//===================================================
	// 					URL
	//===================================================
	/**
	 * URL para a API em produção
	 * @access private
	 * @var string
	 */
	private $urlAPI = 'https://ws.pagseguro.uol.com.br/';
	/**
	 * URL para o pagamento em produção
	 * @access private
	 * @var string
	 */
	private $urlPagamento = 'https://pagseguro.uol.com.br/v2/pre-approvals/request.html?code=';
	/**
	 * URL para a API em Sandbox
	 * @access private
	 * @var string
	 */
	private $urlAPISandbox = 'https://ws.sandbox.pagseguro.uol.com.br/';
	/**
	 * URL para o pagamento em Sandbox
	 * @access private
	 * @var string
	 */
	private $urlPagamentoSandbox = 'https://sandbox.pagseguro.uol.com.br/v2/pre-approvals/request.html?code=';
	/**
	 * Verifica se é Sanbox ou em Produção
	 * @access private
	 * @var bool
	 */
	private $isSandbox = false;
	//===================================================
	// 					Dados da Compra
	//===================================================
	/**
	 * O nome e mail do cliente | Deve ser um nome composto
	 * @access private
	 * @var array
	 */
	private $cliente = array(
		'name' => '',
		'email' => '',
		'ip' => '',
		'hash' => '',
		'phone' => array(
			'areaCode' => '',
			'number' => ''
		),
		'address' => array(
			'street' => '',
			'number' => '',
			'complement' => '',
			'district' => '',
			'city' => '',
			'state' => '',
			'country' => 'BRA',
			'postalCode' => ''
		),
		'documents' => array(
			array(
				'type' => "CPF",
				'value' => ''
			)
		)

	);
	private $formaPagamento = array(
		'type' => 'CREDITCARD',
		'creditCard' => array(
			'token' => '',
			'holder' => array(
				'name' => '',
				'birthDate' => '',
				'phone' => array(
					'areaCode' => '',
					'number' => ''
				),
				'documents' => array(
					array(
						'type' => "CPF",
						'value' => ''
					)
				),
				'billingAddress' => array(
					'street' => '',
					'number' => '',
					'complement' => '',
					'district' => '',
					'city' => '',
					'state' => '',
					'country' => 'BRA',
					'postalCode' => ''
				)
			)
		)
	);
	/**
	 * Nome da Assinatura
	 * @access private
	 * @var string
	 */
	private $nome;
	/**
	 * Um ID qualquer para identificar qual é a compra no sistema 
	 * @access private
	 * @var string
	 */

	public $referencia = '';
	/**
	 * Descricao da compra
	 * @access private
	 * @var string
	 */
	private $descricao = ' PMPROPAGSEGURO ';
	/**
	 * Valor cobrado
	 * @access private
	 * @var float
	 */
	private $valor = 0.00;

	/**
	 * Taxa de Adesão
	 * @access private
	 * @var float
	 */
	private $taxaAdesao = 0.00;

	/**
	 * Duração do período de teste
	 * @access private
	 * @var int
	 */
	private $periodoTeste = 0;

	/**
	 * Periodicidade
	 * @access private
	 * @var string 'WEEKLY'|'MONTHLY'|'BIMONTHLY'|'TRIMONTHLY'|'SEMIANNUALLY'|'YEARLY'
	 */
	private $periodicidade = 'MONTHLY';
	/** PERIODIIDADE **/
	const SEMANAL = 'WEEKLY';
	const MENSAL = 'MONTHLY';
	const BIMESTRAL = 'BIMONTHLY';
	const TRIMESTRAL = 'TRIMONTHLY';
	const SEMESTRAL = 'SEMIANNUALLY';
	const ANUAL = 'YEARLY';
	/**
	 * Link para onde a pessoa será redicionada após concluir a assinatura no Pagseguro
	 * @access private
	 * @var string (url)
	 */
	private $redirectURL = null;
	/**
	 * Link para onde será enviada as notificações a cada alteração na compra
	 * @access private
	 * @var string (url)
	 */
	private $notificationURL = null;
	/**
	 * Código do PagSeguro referente a assinatura
	 * @access private
	 * @var string (url)
	 */
	private $preApprovalCode = '';
	/**
	 * Código do Plano criado
	 * @access private
	 * @var string
	 */
	private $planoCode;
	//===================================================
	// 					OPCIONAIS PARA PLANOS
	//===================================================
	/**
	 * Após quanto tempo de contratado a assinatura expira
	 * @acces private
	 * @var array
	 */
	private $expiracao = null;
	/**
	 * URL para a página para onde o usuário é enviado ao solicitar o cancelamento da assinatura no pagseguro
	 * @var string
	 */
	private $URLCancelamento = null;
	/**
	 * Informa o máximo de usuários que podem usar o plano (Opcional | Deixar 0 para nõa ter limite)
	 * @access private
	 * @var int
	 */
	private $maximoUsuarios = 0;
	/** 
	 * Headers para acesso a API do gerarSolicitacaoPagSeguro
	 * @access private
	 * @var array
	 */
	private $headers = array(
		'Content-Type:  application/json;charset=ISO-8859-1',
		'Accept: application/vnd.pagseguro.com.br.v3+xml;charset=ISO-8859-1'
	);
	//===================================================
	// 					Credencias
	//===================================================
	/**
	 * Email do vendedor do PagSeguro
	 * @access private
	 * @var string
	 */
	private $email;
	/**
	 * token do vendedor do PagSeguro
	 * @access private
	 * @var string
	 */
	private $token;
	// ================================================================
	// API Assinatura PagSeguro
	// ================================================================
	/**
	 * Construtor
	 * @param $email string
	 * @param $token string
	 * @param isSandbox bool (opcional | Default false)
	 */
	public function __construct($email, $token, $isSandbox = false)
	{
		$this->email = $email;
		$this->token = $token;
		$this->isSandbox = $isSandbox;
	}
	/**
	 * Criar um novo plano
	 */
	public function criarPlano()
	{
		
		//Dados da assinatura
		$dados['reference'] = $this->referencia;
		$dados['preApproval']['charge'] = 'AUTO';
		$dados['preApproval']['name'] = $this->referencia;
		$dados['preApproval']['details'] = $this->descricao;
		$dados['preApproval']['amountPerPayment'] = $this->valor;
		$dados['preApproval']['membershipFee'] = $this->taxaAdesao;
		$dados['preApproval']['period'] = $this->periodicidade;
		$dados['receiver']['email'] = $this->email;

		if (isset($this->expiracao))
			$dados['preApproval']['expiration'] = $this->expiracao;
		if ($this->periodoTeste > 0)
			$dados['preApproval']['trialPeriodDuration'] = $this->periodoTeste;
		
		//Opcionais
		if (!empty($this->URLCancelamento))
			$dados['preApproval']['cancelURL'] = $this->URLCancelamento;

		if (!empty($this->redirectURL))
			$dados['redirectURL'] = $this->redirectURL;

		if ($this->maximoUsuarios > 0)
			$dados['maxUses'] = $this->maximoUsuarios;

		$response = $this->post($this->getURLAPI() . 'pre-approvals/request', $dados);

		if ($response['http_code'] == 200) {
			return $response['body']['code'];
		} else {

			throw new \Exception(current($response['body']['errors']));
		}
	}
	/** Cria um ID para comunicação com Checkout Transparente 
	 * @return id string
	 */
	public function iniciaSessao()
	{
		$url = $this->getURLAPI() . 'v2/sessions/' . $this->getCredenciais();
		$curl = curl_init($url);
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($curl, CURLOPT_CUSTOMREQUEST, "POST");
		curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
		$xml = curl_exec($curl);
		curl_close($curl);		
		//Problema Token do vendedor

		if ($xml == 'Unauthorized') {
			throw new \Exception("Token inválido");
		}
		$xml = simplexml_load_string($xml);
		return $xml->id;
	}
	/**
	 * GEra todo o JavaScript necessário
	 */
	public function preparaCheckoutTransparente()
	{
		$sessionID = $this->iniciaSessao();
		$javascript = array();

		//Sessão
		if ($this->isSandbox)
			$javascript['script'] = 'https://stc.sandbox.pagseguro.uol.com.br/pagseguro/api/v2/checkout/pagseguro.directpayment.js';
		else
			$javascript['script'] = 'https://stc.pagseguro.uol.com.br/pagseguro/api/v2/checkout/pagseguro.directpayment.js';
		$javascript['session_id'] = $sessionID;
		return $javascript;
	}

	/**
	 * Inicia um pedido de compra
	 * @access public
	 * @return array (url para a compra e código da compra)
	 */
	public function assinaPlano()
	{
		$dados['plan'] = $this->planoCode;
		$dados['reference'] = $this->referencia;

		$dados['sender'] = $this->cliente;
		//Dados do pagamento
		$dados['paymentMethod'] = $this->formaPagamento;
		//Dados do plano
        
		//Dados da compra

		$response = $this->post($this->getURLAPI() . 'pre-approvals/', $dados);
		//print_r($response);
		if ($response['http_code'] == 200) {
			return $response['body']['code'];
		} else {
			throw new \Exception(current($response['body']['errors']));
		}
	}
	/**
	 * Realiza assinatura do plano pelo ambiente checkout padrão
	 */
	public function assinarPlanoCheckout($planoCode)
	{
		return $this->getURLPagamento() . $planoCode;
	}

	/** Realiza uma consulta a notificação **/
	public function consultarNotificacao($codePagSeguro)
	{
		$response = $this->get($this->getURLAPI() . 'pre-approvals/notifications/' . $codePagSeguro);
		if ($response['http_code'] == 200) {
			return $response['body'];
		} else {
			throw new \Exception(current($response['body']['errors']));
		}
	}
	/** Consulta uma assinatura **/
	public function consultaAssinatura($codePagSeguro)
	{
		$response = $this->get($this->getURLAPI() . 'pre-approvals/' . $codePagSeguro);
		if ($response['http_code'] == 200) {
			return $response['body'];
		} else {
			throw new \Exception(current($response['body']['errors']));
		}
	}

	/**
	 * Cancela a assinatura
	 * @access public
	 * @param $codePagSeguro string (Código fornecido pelo pagseguro para uma compra)
	 * @return bool
	 */
	public function cancelarAssinatura($codePagSeguro)
	{
		$response = $this->put($this->getURLAPI() . 'pre-approvals/' . $codePagSeguro . '/cancel');
		if ($response['http_code'] == 204) {
			return true;
		} else {
			throw new \Exception(current($response['body']['errors']));
		}
	}
	/**
	 * Habilita ou Desabilita uma assinatura
	 * @access public
	 * @param $codePagSeguro $codigoPreApproval
	 * @param $habilitar bool
	 */
	public function setHabilitarAssinatura($codePagSeguro, $habilitar = true)
	{
		$dados['status'] = ($habilitar ? 'ACTIVE' : 'SUSPENDED');
		$response = $this->put($this->getURLAPI() . 'pre-approvals/' . $codePagSeguro . '/status', $dados);

		if ($response['http_code'] == 204) {
			return true;
		} else {
			throw new \Exception(current($response['body']['errors']));
		}
	}
	
	// =================================================================
	// Util
	// =================================================================
	/**
	 * Formata a credêncial do pagseguro
	 * @access private
	 * @return array(email, token)
	 */
	private function getCredenciais()
	{
		$dados['email'] = $this->email;
		$dados['token'] = $this->token;
		return '?' . http_build_query($dados);
	}
	/**
	 * Busca a URL da API de acordo com a opção Sandbox
	 * @access private
	 * @return string url
	 */
	private function getURLAPI()
	{
		return ($this->isSandbox ? $this->urlAPISandbox : $this->urlAPI);
	}
	/**
	 * Busca a URL de Pagamento de acordo com a opção Sandbox
	 * @access private
	 * @return string url
	 */
	private function getURLPagamento()
	{
		return ($this->isSandbox ? $this->urlPagamentoSandbox : $this->urlPagamento);
	}
	// =================================================================
	// GET e SET
	// =================================================================

	/**
	 * @param $nome string
	 */
	public function setNomeAssinatura($nome)
	{
		return $this->nome = $nome;
	}

	/**
	 * @param $emailCliente string
	 */
	public function setEmailCliente($emailCliente)
	{
		return $this->cliente['email'] = $emailCliente;
	}

	/**
	 * @param $referencia string
	 */
	public function setReferencia($referencia)
	{
		return $this->referencia = $referencia;
	}

	/**
	 * @param $razao string
	 */
	public function setDescricao($descricao)
	{
		return $this->descricao = $descricao;
	}

	/**
	 * @param $valor float
	 */
	public function setValor($valor)
	{
		return $this->valor = number_format($valor, 2, '.', '');
	}

	/**
	 * @param $valor float
	 */
	public function setTaxaAdesao($valor)
	{
		return $this->taxaAdesao = number_format($valor, 2, '.', '');
	}
	/**
	 * @param $duracao int
	 */
	public function setPeriodoTeste($duracao)
	{
		return $this->periodoTeste = $duracao;
	}
	/**
	 * @param $periodicidade int | string('WEEKLY', 'MONTHLY', 'BIMONTHLY', 'TRIMONTHLY', 'SEMIANNUALLY', 'YEARLY')
	 */
	public function setPeriodicidade($periodicidade)
	{
		$this->periodicidade = $periodicidade;
		//Tratamento
		if (!in_array($this->periodicidade, array('WEEKLY', 'MONTHLY', 'BIMONTHLY', 'TRIMONTHLY', 'SEMIANNUALLY', 'YEARLY')))
			$this->periodicidade = '-'; //Erro
		return $this->periodicidade;
	}

	/**
	 * @param $redirectURL string
	 */
	public function setRedirectURL($redirectURL)
	{
		return $this->redirectURL = $redirectURL;
	}
	/**
	 * @return string
	 */
	public function setNotificationURL($url)
	{
		$this->notificationURL = $url;
	}

	/**
	 * @param $preApprovalCode string
	 */
	public function setPreApprovalCode($preApprovalCode)
	{
		return $this->preApprovalCode = $preApprovalCode;
	}

	public function setIp($ip)
	{
		$this->cliente['ip'] = $ip;
	}
	/**
	 * Muda o periodo para o plano expirar sozinho após contratado
	 * @param $periodo int
	 * @param $unidade string
	 */
	public function setExpiracao($periodo, $unidade)
	{

		$this->expiracao = array(
			'value' => $periodo,
			'unit' => $unidade
		);
	}
	/**
	 * Seta a url para onde o usuário é enviado para cancelar a assinatura
	 * @param $url string
	 */
	public function setURLCancelamento($url)
	{
		$this->URLCancelamento = $url;
	}
	/**
	 * Informa o máximo de usuários a usar o plano
	 */
	public function setMaximoUsuariosNoPlano($valor)
	{
		$this->maximoUsuarios = intval($valor);
	}

	/**
	 * @param $preApprovalCode string
	 */
	public function setPlanoCode($planoCode)
	{
		return $this->planoCode = $planoCode;
	}
	/**
	 * @param $hash string
	 */
	public function setHashCliente($hash)
	{
		$this->cliente['hash'] = $hash;
	}
	/**
	 * @param $nomeCliente string
	 */
	public function setNomeCliente($nomeCliente, $holder = false)
	{
		$this->cliente['name'] = $nomeCliente;
		if ($holder)
			$this->formaPagamento['creditCard']['holder']['name'] = $nomeCliente;
	}
	/**
	 * Seta o dia de nascimento do cliente
	 * @param $ano (dd/MM/YYYY)
	 */
	public function setNascimentoCliente($ano)
	{
		$this->formaPagamento['creditCard']['holder']['birthDate'] = $ano;
	}

	/** Seta o CPF do Cliente **/
	public function setCPF($numero, $holder = false)
	{
		$this->cliente['documents'][0]['value'] = $numero;
		if ($holder)
			$this->formaPagamento['creditCard']['holder']['documents'][0]['value'] = $numero;
	}
	/**
	 * @param $ddd int
	 * @param $numero int
	 */
	public function setTelefone($ddd, $numero)
	{
		$this->cliente['phone']['areaCode'] = $ddd;
		$this->cliente['phone']['number'] = $numero;
		$this->formaPagamento['creditCard']['holder']['phone']['areaCode'] = $ddd;
		$this->formaPagamento['creditCard']['holder']['phone']['number'] = $numero;
	}
	/** Seta o token do Cartão **/
	public function setTokenCartao($token)
	{
		$this->formaPagamento['creditCard']['token'] = $token;
	}
	public function setEnderecoCliente($rua, $numero, $complemento, $bairro, $cidade, $estado, $cep)
	{
		$this->formaPagamento['creditCard']['holder']['billingAddress'] = $this->cliente['address'] = array(
			'street' => $rua,
			'number' => $numero,
			'complement' => $complemento,
			'district' => $bairro,
			'city' => $cidade,
			'state' => $estado,
			'country' => 'BRA',
			'postalCode' => $cep
		);
	}
	/********** REST ******************/
	/**
	 * Realiza uma requisição GET
	 * @access private
	 * @param $url string
	 * @return array
	 */
	private function get($url)
	{
		$url .= $this->getCredenciais();
		$curl = curl_init($url);
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
		curl_setopt($curl, CURLOPT_HTTPHEADER, $this->headers);
		$curl_response = curl_exec($curl);
		$response = curl_getinfo($curl);
		$response['body'] = json_decode(json_encode(simplexml_load_string($curl_response)), true);
		curl_close($curl);
		return $response;
	}
	/**
	 * Realiza uma requisição POST
	 * @access private
	 * @param $url string
	 * @param $data array
	 * @return array
	 */
	private function post($url, $data = array())
	{
		$url .= $this->getCredenciais();
		$curl = curl_init($url);
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($curl, CURLOPT_CUSTOMREQUEST, "POST");
		curl_setopt($curl, CURLOPT_HTTPHEADER, $this->headers);
		curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 0);
		if (!empty($data))
			curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($data));
		curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
		$curl_response = curl_exec($curl);
		$response = curl_getinfo($curl);
		$response['body'] = json_decode(json_encode(simplexml_load_string($curl_response)), true);
		curl_close($curl);
		return $response;
	}
	/**
	 * Realiza uma requisição PUT
	 * @access private
	 * @param $url string
	 * @param $data array
	 * @return array
	 */
	private function put($url, $data = array())
	{
		$url .= $this->getCredenciais();
		$curl = curl_init($url);
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($curl, CURLOPT_CUSTOMREQUEST, "PUT");
		curl_setopt($curl, CURLOPT_HTTPHEADER, $this->headers);
		@curl_setopt($curl, CURLOPT_SAFE_UPLOAD, false);
		if (!empty($data))
			curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($data));
		curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
		$curl_response = curl_exec($curl);
		$response = curl_getinfo($curl);
		$response['body'] = json_decode(json_encode(simplexml_load_string($curl_response)), true);
		curl_close($curl);
		return $response;
	}
}

class PagSeguroCompras
{
	
	//===================================================
	// 					URL
	//===================================================
	/**
	 * URL para a API em produção
	 * @access private
	 * @var string
	 */
	private $urlAPI = 'https://ws.pagseguro.uol.com.br/';
	/**
	 * URL para o pagamento em produção
	 * @access private
	 * @var string
	 */
	private $urlPagamento = 'https://pagseguro.uol.com.br/v2/checkout/payment.html?code=';
	/**
	 * URL para a API em Sandbox
	 * @access private
	 * @var string
	 */
	private $urlAPISandbox = 'https://ws.sandbox.pagseguro.uol.com.br/';
	/**
	 * URL para o pagamento em Sandbox
	 * @access private
	 * @var string
	 */
	private $urlPagamentoSandbox = 'https://sandbox.pagseguro.uol.com.br/v2/checkout/payment.html?code=';
	/**	
	 * Verifica se é Sanbox ou em Produção
	 * @access private
	 * @var bool
	 */
	private $isSandbox = false;
	//===================================================
	// 					Dados da Compra
	//===================================================
	/**
	 * O nome e mail do cliente | Deve ser um nome composto
	 * @access private
	 * @var array
	 */
	private $cliente = array(
		'senderName' => '',
		'senderEmail' => '',
	);
	/**
	 * Lista de itens
	 * @var array
	 */
	private $itens = array();
	/**
	 * Um ID qualquer para identificar qual é a compra no sistema 
	 * @access private
	 * @var string
	 */
	public $referencia = '';
	/**
	 * Link para onde a pessoa será redicionada após concluir a assinatura no Pagseguro
	 * @access private
	 * @var string (url)
	 */
	private $redirectURL = '';
	/**
	 * Link para onde será enviada as notificações a cada alteração na compra
	 * @access private
	 * @var string (url)
	 */
	private $notificationURL = '';
	/** 
	 * Headers para acesso a API do gerarSolicitacaoPagSeguro
	 * @access private
	 * @var array
	 */
	private $headers = array(
		'Content-Type: application/x-www-form-urlencoded; charset=ISO-8859-1',
		'Accept: application/xml;charset=ISO-8859-1'
	);
	//===================================================
	// 					Credencias
	//===================================================
	/**
	 * Email do vendedor do PagSeguro
	 * @access private
	 * @var string
	 */
	private $email;
	/**
	 * token do vendedor do PagSeguro
	 * @access private
	 * @var string
	 */
	private $token;

	/**
	 * Onde será setado o valor de Limite para Parcelamento
	 * @access private
	 * @var int
	 */
	private $parcelaLimit = '';
	// ================================================================
	// API Assinatura PagSeguro
	// ================================================================
	/**
	 * Construtor
	 * @param $email string
	 * @param $token string
	 * @param isSandbox bool (opcional | Default false)
	 */
	public function __construct($email, $token, $isSandbox = false)
	{
		$this->email = $email;
		$this->token = $token;
		$this->isSandbox = $isSandbox;
	}

	/** Cria um ID para comunicação com Checkout Transparente 
	 * @return id string
	 */
	public function iniciaSessao()
	{
		$auth = '?' . http_build_query($this->getCredenciais());
		$url = $this->getURLAPI('v2/sessions/') . $auth;
		$curl = curl_init($url);
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($curl, CURLOPT_CUSTOMREQUEST, "POST");
		curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
		$xml = curl_exec($curl);

		curl_close($curl);		
		//Problema Token do vendedor
		if ($xml == 'Unauthorized') {
			throw new \Exception("Token inválido");
		}
		$xml = simplexml_load_string($xml);
		return $xml->id;
	}
	/**
	 * GEra todo o JavaScript necessário
	 */
	public function preparaCheckoutTransparente()
	{
		$sessionID = $this->iniciaSessao();
		$javascript = array();

		//Sessão
		if ($this->isSandbox)
			$javascript['script'] = 'https://stc.sandbox.pagseguro.uol.com.br/pagseguro/api/v2/checkout/pagseguro.directpayment.js';
		else
			$javascript['script'] = 'https://stc.pagseguro.uol.com.br/pagseguro/api/v2/checkout/pagseguro.directpayment.js';
		$javascript['session_id'] = $sessionID;
		return $javascript;
	}

	/**
	 * Inicia um pedido de compra
	 * @access public
	 * @return array (url para a compra e código da compra)
	 */
	public function gerarURLCompra()
	{
		$dados = array();
		//Dados do cliente
		if ($this->cliente)
			$dados = array_merge($this->cliente, $dados);
		//Itens

		foreach ($this->itens as $itens) {
			foreach ($itens as $key => $value)
				$dados[$key] = $value;
		}
		
		//Links
		if (isset($this->redirectURL))
			$dados['redirectURL'] = $this->redirectURL;
		if (isset($this->notificationURL))
			$dados['notificationURL'] = $this->notificationURL;
		//Dados da compra
		$dados['reference'] = $this->referencia;
		$dados['currency'] = 'BRL';

		if (isset($this->parcelaLimit)) {

			$dados['acceptPaymentMethodGroup'] = 'CREDIT_CARD,BOLETO,BALANCE,DEPOSIT,EFT';
		}
		$response = $this->post($this->getURLAPI('v2/checkout'), $dados);
		//print_r($response);
		if (isset($response->code)) {
			return $this->getURLPagamento() . $response->code;
		} else {
			throw new \Exception($response);
		}
	}

	/** Realiza uma consulta a notificação **/
	public function consultarNotificacao($codePagSeguro)
	{
		$response = $this->get($this->getURLAPI('v2/transactions/notifications/' . $codePagSeguro));

		if (isset($response->code)) {
			$dados = (array)$response;
			$dados['info'] = $this->getStatusCompra($dados['status']);
			return $dados;
		} else {
			throw new \Exception($response);
		}
	}
	/** Consulta uma compra pelo código da compra **/
	public function consultarCompra($codePagSeguro)
	{
		$response = $this->get($this->getURLAPI('v2/transactions/' . $codePagSeguro));

		if (isset($response->code)) {
			$dados = (array)$response;
			$dados['info'] = $this->getStatusCompra($dados['status']);
			return $dados;
		} else {
			throw new \Exception($response);
		}
	}
	/** Consulta uma compra pela referencia **/
	public function consultarCompraByReferencia($referencia)
	{
		$response = $this->get($this->getURLAPI('v2/transactions'), array('reference' => $referencia));

		if (isset($response->transactions)) {
			$dados = (array)$response;
			$dados['transactions'] = (array)$dados['transactions'];

			return $dados;
		} else {
			throw new \Exception($response);
		}
	}
 
	
	
	// =================================================================
	// Util
	// =================================================================
	/**
	 * Formata a credêncial do pagseguro
	 * @access private
	 * @return array(email, token)
	 */
	private function getCredenciais()
	{
		$dados['email'] = $this->email;
		$dados['token'] = $this->token;
		return $dados;
	}
	/**
	 * Busca a URL da API de acordo com a opção Sandbox
	 * @access private
	 * @return string url
	 */
	private function getURLAPI($url = '')
	{
		return ($this->isSandbox ? $this->urlAPISandbox : $this->urlAPI) . $url;
	}
	/**
	 * Busca a URL de Pagamento de acordo com a opção Sandbox
	 * @access private
	 * @return string url
	 */
	private function getURLPagamento()
	{
		return ($this->isSandbox ? $this->urlPagamentoSandbox : $this->urlPagamento);
	}
	/**
	 * Retorna a URL para criar uma sessão
	 * @access private
	 * @return string
	 */
	private function getSessionURL()
	{
		return ($this->isSandbox ? $this->urlSessionAPISandbox : $this->urlSessionAPI);
	}
	/**
	 * Retorna uma descrição do estdo da comprA
	 * @param $status int
	 * @return array
	 */
	public function getStatusCompra($status)
	{
		$info = array();
		switch ($status) {
			case 1:
				$info =
					array(
					'estado' => 'Aguardando pagamento',
					'descricao' => 'o comprador iniciou a transação, mas até o momento o PagSeguro não recebeu nenhuma informação sobre o pagamento.'
				);
				break;
			case 2:
				$info =
					array(
					'estado' => 'Em análise',
					'descricao' => 'o comprador optou por pagar com um cartão de crédito e o PagSeguro está analisando o risco da transação.'
				);
				break;
			case 3:
				$info =
					array(
					'estado' => 'Paga',
					'descricao' => 'a transação foi paga pelo comprador e o PagSeguro já recebeu uma confirmação da instituição financeira responsável pelo processamento..'
				);
				break;
			case 4:
				$info =
					array(
					'estado' => 'Disponível',
					'descricao' => 'a transação foi paga e chegou ao final de seu prazo de liberação sem ter sido retornada e sem que haja nenhuma disputa aberta.'
				);
				break;
			case 5:
				$info =
					array(
					'estado' => 'Em disputa',
					'descricao' => 'o comprador, dentro do prazo de liberação da transação, abriu uma disputa.'
				);
				break;
			case 6:
				$info =
					array(
					'estado' => 'Devolvida',
					'descricao' => 'o valor da transação foi devolvido para o comprador.'
				);
				break;
			case 7:
				$info =
					array(
					'estado' => 'Cancelada',
					'descricao' => 'a transação foi cancelada sem ter sido finalizada.'
				);
				break;
			default:
				$info =
					array(
					'estado' => 'Desconhecido',
					'descricao' => 'Esse estado não consta na documentação do PagSeguro.'
				);
				break;
		}
		$info['status'] = $status;
		return $info;
	}
	// =================================================================
	// GET e SET
	// =================================================================

	/**
	 * @param $emailCliente string
	 */
	public function setEmailCliente($emailCliente)
	{
		return $this->cliente['senderEmail'] = $emailCliente;
	}
	/**
	 * @param $nomeCliente string
	 */
	public function setNomeCliente($nomeCliente)
	{
		$this->cliente['senderName'] = $nomeCliente;
	}
	public function adicionarItem($id, $descricao, $valor, $quantidade)
	{
		$index = count($this->itens) + 1;
		$valor = number_format($valor, 2, '.', '');
		$this->itens[] = array(
			'itemId' . $index => $id,
			'itemDescription' . $index => $descricao,
			'itemAmount' . $index => $valor,
			'itemQuantity' . $index => $quantidade
		);
	}
	/**
	 * @param $referencia string
	 */
	public function setReferencia($referencia)
	{
		return $this->referencia = $referencia;
	}

	/**
	 * @param $redirectURL string
	 */
	public function setRedirectURL($redirectURL)
	{
		return $this->redirectURL = $redirectURL;
	}
	/**
	 * @return string
	 */
	public function setNotificationURL($url)
	{
		$this->notificationURL = $url;
	}

	/**
	 * @param $parcelaLimit int
	 */
	public function setParcelaLimit($parcelaLimit)
	{
		return $this->parcelaLimit = $parcelaLimit;
	}
	/********** REST ******************/
	/**
	 * Realiza uma requisição GET
	 * @access private
	 * @param $url string
	 * @return array
	 */
	private function get($url, $dados = array())
	{
		$dados = array_merge($this->getCredenciais(), $dados);
		$curl = curl_init($url . '?' . http_build_query($dados));
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
		curl_setopt($curl, CURLOPT_HTTPHEADER, $this->headers);


		$xml = curl_exec($curl);
		curl_close($curl);

		if ($xml == 'Unauthorized')
			throw new \Exception("Falha na autenticação");

		$xml = simplexml_load_string($xml);

		return $xml;
	}
	/**
	 * Realiza uma requisição POST
	 * @access private
	 * @param $url string
	 * @param $data array
	 * @return array
	 */
	private function post($url, $dados = array())
	{
		$dados = array_merge($this->getCredenciais(), $dados);
		$curl = curl_init($url);
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($curl, CURLOPT_CUSTOMREQUEST, "POST");
		curl_setopt($curl, CURLOPT_HTTPHEADER, $this->headers);
		curl_setopt($curl, CURLOPT_POSTFIELDS, http_build_query($dados));
		curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
		$xml = curl_exec($curl);
		curl_close($curl);
		if ($xml == 'Unauthorized')
			throw new \Exception("Falha na autenticação");
		$xml = simplexml_load_string($xml);
		return $xml;
	}
}