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
		if(!class_exists('PagSeguroAssinaturas') && !class_exists('PagSeguroCompras')){
			require(PLUGIN_DIR."PagSeguroLib/PagSeguroAssinaturas.php");
			require(PLUGIN_DIR."PagSeguroLib/PagSeguroCompras.php");
		}
		
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
			add_filter('pmpro_pages_shortcode_levels', array('PMProGateway_PagSeguro', 'pmpro_pages_shortcode_levels'), 20, 1);
			add_filter('pmpro_pages_shortcode_checkout', array('PMProGateway_PagSeguro', 'pmpro_pages_shortcode_checkout'), 20, 1);

			add_action('pmpro_after_order_settings', array('PMProGateway_PagSeguro', 'pmpro_after_order_settings'));
			add_action('pmpro_membership_level_after_other_settings', array('PMProGateway_PagSeguro', 'pmpro_membership_level_after_other_settings'));

			add_action("pmpro_cron_trial_ending_warnings",  array('PMProGateway_PagSeguro','pmpro_cron_trial_ending_warnings'));
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
		include_once(PLUGIN_DIR . "includes/level-fields-js.php");

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
		global $wpdb, $gateway_environment, $gateway, $current_user, $pmpro_error;
		

		if (isset($_REQUEST['notificationCode'])) {
			if ($_REQUEST['notificationType'] == 'transaction') {
				self::loadPagSeguroLibrary(false);
				$code = $_REQUEST['notificationCode'];
				$response = self::$pagseguroCompras->consultarNotificacao($code);
				$referencia = $response['reference'];
				$order = new MemberOrder($referencia);
				print_r($response);
				switch (intval($response['status'])) {
					case 1: //pending
						$order->status = "pending";


						self::add_order_note($order->id, sprintf("Detalhes do Pagamento <br><br>Referência  : %s</a>. Aguardando Pagamento.", $order->code));
						break;
					case 2: //review
						$order->status = "review";
						
						self::add_order_note($order->id, sprintf(__("Detalhes do Pagamento <br><br>Referência  : %s</a>. Pagamento em Análize.", PMPROPAGSEGURO), $order->code));
						break;
					case 3: //success

						$order->status = "success";
						self::add_order_note($order->id, sprintf("Detalhes do Pagamento <br><br>Referência  : %s</a>. Pagamento Realizado.", $order->code));
						break;
					case 4: // Disponivel
						$order->status = "success";			
						self::add_order_note($order->id, sprintf(__("Detalhes do Pagamento <br><br>Referência  : %s</a>. Pagamento Disponivel.", PMPROPAGSEGURO), $order->code));
						break;
					case 5: // Em Disputa
						$order->status = "review";
				
						self::add_order_note($order->id, sprintf(__("Detalhes do Pagamento <br><br>Referência  : %s</a>. Pagamento em Disputa, consute o site do Pag Seguro.", PMPROPAGSEGURO), $order->code));
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
				$order->saveOrder();
			} elseif ($_REQUEST['notificationType'] == 'preApproval') {
				self::loadPagSeguroLibrary(true);
				$code = $_REQUEST['notificationCode'];
				$response = self::$pagseguroAssinaturas->consultarNotificacao($code);
				print_r($response);
				
				if ($response['status'] == "CANCELLED") {
					$referencia = $response['reference'];
					$order = new MemberOrder($referencia);
					$order->updateStatus("cancelled");
					if (pmpro_changeMembershipLevel(0, $order->user_id)) {
						$order->saveOrder();
						http_response_code(200);
						self::add_order_note($order->id, sprintf("Detalhes do Pagamento <br><br>Referência  : %s</a>. Pagamento Cancelado.", $order->code));
					} else {
						self::add_order_note($order->id, sprintf("Detalhes do Pagamento <br><br>Referência  : %s</a>. Falha ao cancelar pagamento :" . $pmpro_error, $order->code));
					}

				}

			}
		} else {
			exit;

		}

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
		$order->cardtype = $data['card_brand'];
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
		$order->ProfileStartDate = date_i18n("Y-m-d") . "T0:0:0";
		$order->saveOrder();

		//print_r($order);
		// die;



		
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
				if(empty($ip) )
					$ip = '192.168.0.1';

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

				$response = self::$pagseguroAssinaturas->consultaAssinatura($codigoAssinatura);
				
				$order->status = "success";
				$order->saveOrder();
			} else {
				// $codigoPlano = $levelmeta->meta_value;
				// $url = self::$pagseguroAssinaturas->assinarPlanoCheckout($codigoPlano);
				// wp_redirect($url);
				// die;
				$codigoPlano = $levelmeta->meta_value;

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
				if(empty($ip) )
					$ip = '192.168.0.1';


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
		$response = self::$pagseguroAssinaturas->consultaAssinatura($ref);
		print_r($response);

	}


	function cancel(&$order)
	{
		$order->updateStatus("cancelled");
		global $wpdb;
		try {
			self::$pagseguroAssinaturas->cancelarAssinatura($order->subscription_transaction_id);
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
	public static function pmpro_pages_shortcode_checkout($content)
	{
		$gateway = pmpro_getGateway();

		if ($gateway != "pagseguro") return $content;
		echo str_replace(array("suas primeras", "mensalidades", "gratuitas"), array("seus primeiros", "dias", "gratuitos"), $content);
	}
	public static function pmpro_pages_shortcode_levels($content)
	{
		$gateway = pmpro_getGateway();

		if ($gateway != "pagseguro") return $content;

		echo str_replace(array("suas primeras", "mensalidades", "gratuitas"), array("seus primeiros", "dias", "gratuitos"), $content);

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


		if (intval($invoice->membership_level->cycle_number) == 1 && $invoice->membership_level->cycle_period == 'Week')
			$cycle_period = 'SEMANAL';
		elseif (intval($invoice->membership_level->cycle_number) == 1 && $invoice->membership_level->cycle_period == 'Month')
			$cycle_period = 'MENSAL';
		elseif (intval($invoice->membership_level->cycle_number) == 2 && $invoice->membership_level->cycle_period == 'Month')
			$cycle_period = 'BIMESTRAL';
		elseif (intval($invoice->membership_level->cycle_number) == 3 && $invoice->membership_level->cycle_period == 'Month')
			$cycle_period = 'TRIMESTRAL';
		elseif (intval($invoice->membership_level->cycle_number) == 6 && $invoice->membership_level->cycle_period == 'Month')
			$cycle_period = 'SEMESTRAL';
		elseif (intval($invoice->membership_level->cycle_number) == 6 && $invoice->membership_level->cycle_period == 'Year')
			$cycle_period = 'ANUAL';
		else
			$cycle_period = null;


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
			'membership_cycle_period' => (isset($cycle_period) ? $cycle_period : '-----'),
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
				$tmp = "<br><h3 id='pagseguro-log'>" . __("Log de Pagamento", PMPROPAGSEGURO) . " -</h3>";
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
	function pmpro_cron_trial_ending_warnings()
	{
		global $wpdb;
	
		//clean up errors in the memberships_users table that could cause problems
		pmpro_cleanup_memberships_users_table();
	
		$today = date_i18n("Y-m-d 00:00:00", current_time("timestamp"));
	
		$pmpro_email_days_before_trial_end = apply_filters("pmpro_email_days_before_trial_end", 3);
	
		//look for memberships with trials ending soon (but we haven't emailed them within three days)
		$sqlQuery = "
		SELECT
			mu.user_id, mu.membership_id, mu.startdate, mu.cycle_period, mu.trial_limit FROM $wpdb->pmpro_memberships_users mu LEFT JOIN $wpdb->usermeta um ON um.user_id = mu.user_id AND um.meta_key = 'pmpro_trial_ending_notice'
		WHERE
			mu.status = 'active' AND mu.trial_limit IS NOT NULL AND mu.trial_limit > 0
				AND DATE_ADD(mu.startdate, INTERVAL mu.trial_limit Day) <= DATE_ADD('" . $today . "', INTERVAL " . $pmpro_email_days_before_trial_end . " Day)				
				AND (um.meta_value IS NULL OR um.meta_value = '' OR DATE_ADD(um.meta_value, INTERVAL " . $pmpro_email_days_before_trial_end . " Day) <= '" . $today . "')
		ORDER BY mu.startdate";
	
		$trial_ending_soon = $wpdb->get_results($sqlQuery);
		
		foreach($trial_ending_soon as $e)
		{
			$send_email = apply_filters("pmpro_send_trial_ending_email", true, $e->user_id);
			if($send_email)
			{
				//send an email
				$pmproemail = new PMProEmail();
				$euser = get_userdata($e->user_id);
				$pmproemail->sendTrialEndingEmail($euser);
	
				if(current_user_can('manage_options'))
					printf(__("Trial ending email sent to %s. ", 'paid-memberships-pro' ), $euser->user_email);
				else
					echo ". ";
			}
	
			//update user meta so we don't email them again
			update_user_meta($e->user_id, "pmpro_trial_ending_notice", $today);
		}
	}
	
}