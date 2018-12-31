<?php
/*
Plugin Name: PagSeguro Gateway for Paid Memberships Pro
Description: PagSeguro Gateway for Paid Memberships Pro by exatasmente 
Version: s4tv4
*/

define("PMPRO_PAGSEGUROGATEWAY_DIR", dirname(__FILE__));

//load payment gateway class
require_once(PMPRO_PAGSEGUROGATEWAY_DIR . "/classes/class.pmprogateway_pagseguro.php");