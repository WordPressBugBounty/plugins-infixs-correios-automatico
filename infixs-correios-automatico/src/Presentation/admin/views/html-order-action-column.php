<?php
/**
 * Order Tracking Column
 *
 * @since   1.0.0
 * 
 * @var \WC_Order $order
 * @var string $print_url
 * @var string|null $printed
 * 
 * @package Infixs\CorreiosAutomatico
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>

<div class="infixs-correios-automatico-tracking-column-wrapper infixs-correios-automatico-actions-buttons-column"
	data-order-id="<?php echo esc_attr( $order->get_id() ); ?>"
	data-order-printed="<?php echo esc_attr( $printed ? '1' : '0' ); ?>">
	<?php if ( $printed ) : ?>
		<a class="button wc-action-button"
			title="<?php echo sprintf( "Etiqueta impressa em: %s", esc_attr( ( new DateTime( $printed ) )->format( 'd/m/Y H:i:s' ) ) ); ?>"
			href="<?php echo esc_attr( $print_url ); ?>" target="_blank"
			style="display: inline-flex;align-items: center;justify-content: center; border-color: #4ade80; color: #4ade80;">
			<svg width="1.3em" height="1.3em" viewBox="0 0 12 12" xmlns="http://www.w3.org/2000/svg">
				<path
					d="M8.70004 6.0001C9.41612 6.0001 10.1029 5.71563 10.6092 5.20929C11.1156 4.70294 11.4 4.01618 11.4 3.3001C11.4 2.58401 11.1156 1.89726 10.6092 1.39091C10.1029 0.884561 9.41612 0.600098 8.70004 0.600098C7.98395 0.600098 7.2972 0.884561 6.79085 1.39091C6.2845 1.89726 6.00003 2.58401 6.00003 3.3001C6.00003 4.01618 6.2845 4.70294 6.79085 5.20929C7.2972 5.71563 7.98395 6.0001 8.70004 6.0001ZM10.1124 2.6125L8.31244 4.4125C8.28457 4.44044 8.25146 4.4626 8.21501 4.47773C8.17857 4.49285 8.1395 4.50063 8.10003 4.50063C8.06057 4.50063 8.0215 4.49285 7.98505 4.47773C7.94861 4.4626 7.9155 4.44044 7.88763 4.4125L7.28763 3.8125C7.2313 3.75617 7.19966 3.67976 7.19966 3.6001C7.19966 3.52043 7.2313 3.44403 7.28763 3.3877C7.34397 3.33137 7.42037 3.29972 7.50003 3.29972C7.5797 3.29972 7.6561 3.33137 7.71243 3.3877L8.10003 3.7759L9.68764 2.1877C9.74397 2.13137 9.82037 2.09972 9.90004 2.09972C9.9797 2.09972 10.0561 2.13137 10.1124 2.1877C10.1688 2.24403 10.2004 2.32043 10.2004 2.4001C10.2004 2.47976 10.1688 2.55617 10.1124 2.6125Z"
					fill="#4ade80" />
				<path fill-rule="evenodd" clip-rule="evenodd"
					d="M6.18461 1.28873L4.17861 1.2918C3.86633 1.29226 3.56699 1.41663 3.34634 1.6376C3.12568 1.85858 3.00175 2.15809 3.00175 2.47037V2.99966H2.57146C2.11681 2.99966 1.68077 3.18027 1.35928 3.50176C1.03779 3.82325 0.857178 4.25929 0.857178 4.71395V7.71395C0.857178 8.05494 0.992637 8.38196 1.23375 8.62308C1.47487 8.8642 1.8019 8.99966 2.14289 8.99966H3.00003V9.42823C3.00003 9.76922 3.13549 10.0962 3.37661 10.3374C3.61773 10.5785 3.94476 10.7139 4.28575 10.7139H7.71432C8.05531 10.7139 8.38234 10.5785 8.62346 10.3374C8.86458 10.0962 9.00004 9.76922 9.00004 9.42823V8.99966H9.85718C10.1982 8.99966 10.5252 8.8642 10.7663 8.62308C11.0074 8.38196 11.1429 8.05494 11.1429 7.71395V5.48828C10.9568 5.70338 10.7411 5.89014 10.5 6.04235V7.71395C10.5 7.88444 10.4323 8.04796 10.3117 8.16851C10.1912 8.28907 10.0277 8.3568 9.85718 8.3568H9.00004V7.28537C9.00004 6.94438 8.86458 6.61735 8.62346 6.37624C8.38234 6.13512 8.05531 5.99966 7.71432 5.99966H4.28575C3.94476 5.99966 3.61773 6.13512 3.37661 6.37624C3.13549 6.61735 3.00003 6.94438 3.00003 7.28537V8.3568H2.14289C1.9724 8.3568 1.80888 8.28907 1.68832 8.16851C1.56776 8.04796 1.50003 7.88444 1.50003 7.71395V4.71395C1.50003 4.42979 1.61292 4.15726 1.81385 3.95633C2.01478 3.7554 2.2873 3.64252 2.57146 3.64252H5.52277C5.49443 3.5353 5.47925 3.42154 5.47925 3.30039C5.47925 3.19898 5.48393 3.09867 5.4931 2.99966H3.64461V2.47037C3.64461 2.17466 3.88375 1.93509 4.17946 1.93466L5.78346 1.93221C5.89191 1.70143 6.02694 1.48563 6.18461 1.28873ZM7.71432 6.64252H4.28575C4.11525 6.64252 3.95174 6.71025 3.83118 6.83081C3.71062 6.95136 3.64289 7.11488 3.64289 7.28537V9.42823C3.64289 9.59873 3.71062 9.76224 3.83118 9.8828C3.95174 10.0034 4.11525 10.0711 4.28575 10.0711H7.71432C7.88482 10.0711 8.04833 10.0034 8.16889 9.8828C8.28945 9.76224 8.35718 9.59873 8.35718 9.42823V7.28537C8.35718 7.11488 8.28945 6.95136 8.16889 6.83081C8.04833 6.71025 7.88482 6.64252 7.71432 6.64252Z"
					fill="#4ade80" />
			</svg>

		</a>
	<?php else : ?>
		<a class="button wc-action-button" title="Imprimir Etiqueta" href="<?php echo esc_attr( $print_url ); ?>"
			target="_blank" style="display: inline-flex;align-items: center;justify-content: center;">
			<svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" aria-hidden="true" role="img"
				width="1.3em" height="1.3em" viewBox="0 0 32 32">
				<path fill="currentColor"
					d="M24 6.5V8h1a5 5 0 0 1 5 5v7.5a3.5 3.5 0 0 1-3.5 3.5H25v1.5a3.5 3.5 0 0 1-3.5 3.5h-11A3.5 3.5 0 0 1 7 25.5V24H5.5A3.5 3.5 0 0 1 2 20.5V13a5 5 0 0 1 5-5h1V6.5A3.5 3.5 0 0 1 11.5 3h9A3.5 3.5 0 0 1 24 6.5m-14 0V8h12V6.5A1.5 1.5 0 0 0 20.5 5h-9A1.5 1.5 0 0 0 10 6.5m-1 19a1.5 1.5 0 0 0 1.5 1.5h11a1.5 1.5 0 0 0 1.5-1.5v-6a1.5 1.5 0 0 0-1.5-1.5h-11A1.5 1.5 0 0 0 9 19.5zM25 22h1.5a1.5 1.5 0 0 0 1.5-1.5V13a3 3 0 0 0-3-3H7a3 3 0 0 0-3 3v7.5A1.5 1.5 0 0 0 5.5 22H7v-2.5a3.5 3.5 0 0 1 3.5-3.5h11a3.5 3.5 0 0 1 3.5 3.5z">
				</path>
			</svg>
		</a>
	<?php endif; ?>
	<a class="button wc-action-button infixs-correios-automatico-tracking-update-button"
		title="Atualizar Código de Rastreamento"
		style="display: inline-flex;align-items: center;justify-content: center;">
		<svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" aria-hidden="true" role="img"
			width="1.3em" height="1.3em" viewBox="0 0 14 14">
			<g fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round">
				<path d="M7 11.5a4.5 4.5 0 1 0 0-9a4.5 4.5 0 0 0 0 9"></path>
				<path d="M7 7.5a.5.5 0 1 0 0-1a.5.5 0 0 0 0 1m0-5v-2m0 13v-2M11.5 7h2M.5 7h2"></path>
			</g>
		</svg>
	</a>
	<div class="infixs-correios-automatico-tracking-edit-form" style="display:none;">
		<div style="flex: 1; position:relative;">
			<input type="text" name="tracking-update-input-<?php echo esc_attr( $order->get_id() ); ?>" value=""
				class="infixs-correios-automatico-tracking-update-input" style="width: 100%;">
			<div class="infixs-correios-automatico-spin-animation">
				<svg xmlns="http://www.w3.org/2000/svg" class="infixs-correios-automatico-spin-icon" width="1.5em"
					height="1.5em" viewBox="0 0 24 24">
					<g fill="none" fill-rule="evenodd">
						<path
							d="m12.593 23.258l-.011.002l-.071.035l-.02.004l-.014-.004l-.071-.035q-.016-.005-.024.005l-.004.01l-.017.428l.005.02l.01.013l.104.074l.015.004l.012-.004l.104-.074l.012-.016l.004-.017l-.017-.427q-.004-.016-.017-.018m.265-.113l-.013.002l-.185.093l-.01.01l-.003.011l.018.43l.005.012l.008.007l.201.093q.019.005.029-.008l.004-.014l-.034-.614q-.005-.018-.02-.022m-.715.002a.02.02 0 0 0-.027.006l-.006.014l-.034.614q.001.018.017.024l.015-.002l.201-.093l.01-.008l.004-.011l.017-.43l-.003-.012l-.01-.01z" />
						<path fill="currentColor"
							d="M12 4.5a7.5 7.5 0 1 0 0 15a7.5 7.5 0 0 0 0-15M1.5 12C1.5 6.201 6.201 1.5 12 1.5S22.5 6.201 22.5 12S17.799 22.5 12 22.5S1.5 17.799 1.5 12"
							opacity="0.1" />
						<path fill="currentColor"
							d="M12 4.5a7.46 7.46 0 0 0-5.187 2.083a1.5 1.5 0 0 1-2.075-2.166A10.46 10.46 0 0 1 12 1.5a1.5 1.5 0 0 1 0 3" />
					</g>
				</svg>
			</div>
		</div>

		<a class="button wc-action-button infixs-correios-automatico-tracking-confirm-button"
			style="display: inline-flex;align-items: center;justify-content: center;">
			<span class="dashicons dashicons-yes" style="font-size: 18px;"></span>
		</a>
		<a class="button wc-action-button infixs-correios-automatico-tracking-cancel-button"
			style="display: inline-flex;align-items: center;justify-content: center;">
			<span class="dashicons dashicons-no-alt" style="font-size: 18px;"></span>
		</a>
	</div>

</div>