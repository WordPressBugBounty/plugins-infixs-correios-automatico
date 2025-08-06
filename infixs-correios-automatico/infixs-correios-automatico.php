<?php

/**
 * Correios Automático - Rastreio, Frete, Etiqueta, Declaração e Devolução
 *
 * @link              https://infixs.io
 * @since             1.0.0
 * @package           Infixs\CorreiosAutomatico
 *
 * @wordpress-plugin
 * Plugin Name:       		Correios Automático - Rastreio, Frete, Etiqueta, Declaração e Devolução
 * Description:       		Integração com correios automatizada (Tudo em um), com ou sem contrato, código de rastreio automático, geração de etiquetas, devolução e muito mais.
 * Version:           		1.6.25
 * Requires at least: 		6.0
 * Requires PHP:      		7.4
 * WC requires at least:	7.7
 * WC tested up to:      	10.0.2
 * Author:            		Infixs Technology
 * Author URI:        		https://infixs.io
 * Text Domain:       		infixs-correios-automatico
 * License:           		GPLv2 or later
 * License URI:       		http://www.gnu.org/licenses/gpl-2.0.txt
 */


defined( 'ABSPATH' ) || exit;

//Define globals
define( 'INFIXS_CORREIOS_AUTOMATICO_PLUGIN_NAME', 'infixs-correios-automatico' );
define( 'INFIXS_CORREIOS_AUTOMATICO_PLUGIN_VERSION', '1.6.25' );
define( 'INFIXS_CORREIOS_AUTOMATICO_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'INFIXS_CORREIOS_AUTOMATICO_PLUGIN_PATH', plugin_dir_path( __FILE__ ) );
define( 'INFIXS_CORREIOS_AUTOMATICO_BASE_NAME', plugin_basename( __FILE__ ) );
define( 'INFIXS_CORREIOS_AUTOMATICO_DIR_NAME', dirname( plugin_basename( __FILE__ ) ) );
define( 'INFIXS_CORREIOS_AUTOMATICO_FILE_NAME', __FILE__ );

require INFIXS_CORREIOS_AUTOMATICO_PLUGIN_PATH . 'vendor/autoload.php';

/**
 * Initialize instance().
 *
 * @since 1.0.0
 *
 * @return \Infixs\CorreiosAutomatico\Container
 */

function infixs_correios_automatico() {
	return \Infixs\CorreiosAutomatico\Container::getInstance();
}

infixs_correios_automatico();

( new \Infixs\CorreiosAutomatico\Core\Core() )->init();
