<?php

use Infixs\CorreiosAutomatico\Core\Support\Template;

/**
 * Tracking View Template
 * 
 * @package Infixs\CorreiosAutomatico
 * @since   1.5.9
 * 
 * @global array $objects
 * @global \WC_Order $order
 */

defined( 'ABSPATH' ) || exit;
?>

<form method="get" class="infixs-correios-automatico-tracking-search-form">
	<label for="search_tracking_code">Insira o código de rastreio:</label>
	<div class="infixs-correios-automatico-tracking-search-form-input">
		<input type="text" id="search_tracking_code" name="code" value="<?php echo esc_attr( $_GET['code'] ?? '' ); ?>"
			required>
		<button type="submit">Rastrear</button>
	</div>
</form>

<?php
Template::loadComponent( "tracking/tracking-history.php", [ 
	'objects' => $objects,
	'search' => true,
] );
?>