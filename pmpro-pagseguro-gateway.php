<?php
/**
 * Plugin Name: Pag Seguro for Paid Memberships Pro
 * Plugin URI: https://github.com/exatasmente/pmpropagseguro
 * Description: Este plugin adiciona o Pag Seguro como forma de pagamento para o Paid Memberships Pro
 * Version: SATV$
 * Author: exatasmente | Paranoid 42 Lab
 * Author URI:  https://github.com/exatasmente
 * Text Domain: pmpro-pagseguro
 * Domain Path: /languages
 */
/**
 * Copyright 2018-2019	Paranoid 42 Lab
 * (email : luizn@alu.ufc.br)
 * GPLv2 Full license details in license.txt
 */


define("PMPRO_PAGSEGUROGATEWAY_DIR", dirname(__FILE__));

//load payment gateway class
require_once(PMPRO_PAGSEGUROGATEWAY_DIR . "/classes/class.pmprogateway_pagseguro.php");