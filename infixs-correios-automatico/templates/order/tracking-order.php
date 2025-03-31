<?php

use Infixs\CorreiosAutomatico\Core\Support\Template;
use Infixs\CorreiosAutomatico\Utils\Formatter;
/**
 * Tracking Order Template
 * 
 * @package Infixs\CorreiosAutomatico
 * @since   1.2.1
 * 
 * @global array $objects
 * @global \WC_Order $order
 */

defined( 'ABSPATH' ) || exit;
?>

<h2>Acompanhe seu Pedido</h2>

<?php
Template::loadComponent( "tracking/tracking-history.php", [ 
	'objects' => $objects,
	'order' => $order,
] );
?>