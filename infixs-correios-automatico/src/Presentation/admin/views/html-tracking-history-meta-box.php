<?php

use Infixs\CorreiosAutomatico\Core\Support\Template;
/**
 * Tracking History HTML
 *
 * @package Infixs\CorreiosAutomatico
 * @since   1.2.2
 * 
 * @global array $objects
 * @global \WC_Order $order
 */

defined( 'ABSPATH' ) || exit;
?>

<div style="padding: 15px 20px 15px 20px;">
	<?php
	Template::loadComponent( "tracking/tracking-history.php", [ 
		'objects' => $objects,
		'order' => $order,
	] );
	?>
</div>