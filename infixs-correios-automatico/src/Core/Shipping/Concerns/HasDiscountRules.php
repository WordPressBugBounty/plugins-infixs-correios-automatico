<?php

namespace Infixs\CorreiosAutomatico\Core\Shipping\Concerns;

use Infixs\CorreiosAutomatico\Utils\NumberHelper;
use Infixs\CorreiosAutomatico\Utils\Sanitizer;

defined( 'ABSPATH' ) || exit;

/**
 * Discount rules of a shipping method.
 *
 * The merchant configures bands ("from R$ 150 the shipping is free") and this
 * decides which band a package falls into and what it does to the cost. Shared
 * so every carrier behaves the same way — the rules are money arithmetic in
 * cents, which is exactly the kind of code that must not drift between copies.
 *
 * The consuming class provides `get_option()` (WC_Settings_API) and owns what
 * to do when no rule matches, since the failure hook differs per carrier.
 *
 * @package Infixs\CorreiosAutomatico
 * @since   1.9.1
 */
trait HasDiscountRules {

	/**
	 * Discount rules
	 *
	 * @var array
	 */
	protected $discount_rules = [];

	/**
	 * Method title used when the shipping ends up free
	 *
	 * @var string
	 */
	protected $discount_free_title = 'Frete Grátis';

	/**
	 * Hide this method when no discount rule matches
	 *
	 * @var bool
	 */
	protected $hidden_when_no_match = false;

	/**
	 * Hide the other shipping methods when a discount rule matches
	 *
	 * @var bool
	 */
	protected $hidden_others_when_match = false;

	/**
	 * Show the original price struck through when discounted
	 *
	 * @var bool
	 */
	protected $show_original_shipping_discount_price = false;

	/**
	 * Instance settings of the discount rules.
	 *
	 * Merged into the method's own `instance_form_fields`.
	 *
	 * @return array
	 */
	protected function discount_form_fields() {
		return [
			'discount_rules' => [
				'title' => __( 'Discount Rules', 'infixs-correios-automatico' ),
				'type' => 'array',
				'description' => __( 'Add or remove discount rules.', 'infixs-correios-automatico' ),
				'desc_tip' => true,
				'sanitize_callback' => [ $this, 'sanitize_discount_rules' ],
				'default' => [
					[
						'enabled' => false,
						'min_amount' => 10000,
						'max_amount' => 0,
						'max_amount_enabled' => false,
						'compare' => 'total',
						'type' => 'percentage',
						'value' => '100',
						'value_amount' => 0,
					]
				],
			],
			'discount_free_title' => [
				'title' => __( 'Título quando grátis', 'infixs-correios-automatico' ),
				'type' => 'text',
				'description' => __( 'Título do método quando for grátis.', 'infixs-correios-automatico' ),
				'desc_tip' => true,
				'default' => 'Frete Grátis',
			],
			'hidden_when_no_match' => [
				'title' => __( 'Hide When no rules match', 'infixs-correios-automatico' ),
				'type' => 'checkbox',
				'description' => __( 'Hide the shipping method when no rules match.', 'infixs-correios-automatico' ),
				'desc_tip' => true,
				'default' => 'no',
			],
			'hidden_others_when_match' => [
				'title' => __( 'Hide Others When no rules match', 'infixs-correios-automatico' ),
				'type' => 'checkbox',
				'description' => __( 'Hide the other shipping methods when no rules match.', 'infixs-correios-automatico' ),
				'desc_tip' => true,
				'default' => 'no',
			],
			'show_original_shipping_discount_price' => [
				'title' => __( 'Exibir valor original riscado', 'infixs-correios-automatico' ),
				'type' => 'checkbox',
				'description' => __( 'Mostra o valor original do frete riscado antes do valor cobrado quando houver desconto.', 'infixs-correios-automatico' ),
				'desc_tip' => true,
				'default' => 'no',
			],
		];
	}

	/**
	 * Read the discount settings into the instance. Call from the constructor.
	 *
	 * @return void
	 */
	protected function init_discount_options() {
		$this->discount_rules = $this->get_option( 'discount_rules' );
		$this->discount_free_title = $this->get_option( 'discount_free_title' );
		$this->hidden_when_no_match = Sanitizer::boolean( $this->get_option( 'hidden_when_no_match' ) );
		$this->hidden_others_when_match = Sanitizer::boolean( $this->get_option( 'hidden_others_when_match' ) );
		$this->show_original_shipping_discount_price = Sanitizer::boolean( $this->get_option( 'show_original_shipping_discount_price' ) );
	}

	/**
	 * Sanitize the rules coming from the settings screen.
	 *
	 * @param array|string $value Rules, JSON encoded when posted by the form.
	 *
	 * @return array
	 */
	public function sanitize_discount_rules( $value ) {
		if ( is_string( $value ) ) {
			$value = json_decode( stripslashes( $value ), true );
		}

		if ( ! is_array( $value ) ) {
			return [];
		}

		foreach ( $value as $key => $rule ) {
			$value[ $key ]['min_amount'] = Sanitizer::numeric( $rule['min_amount'] );
			$value[ $key ]['max_amount'] = Sanitizer::numeric( $rule['max_amount'] );
			$value[ $key ]['value_amount'] = Sanitizer::numeric( $rule['value_amount'] ?? 0 );
			$value[ $key ]['method_title_enabled'] = Sanitizer::boolean( $rule['method_title_enabled'] ?? false );
			$value[ $key ]['method_title'] = sanitize_text_field( $rule['method_title'] ?? '' );
		}

		return $value;
	}

	/**
	 * Convert the stored rules to what the settings screen expects.
	 *
	 * Amounts are persisted in cents and edited in reais.
	 *
	 * @param array $value Stored rules.
	 *
	 * @return array
	 */
	protected function map_discount_rules_value( $value ) {
		if ( ! is_array( $value ) ) {
			return [];
		}

		foreach ( $value as $rule_key => $rule ) {
			$value[ $rule_key ]['min_amount'] = NumberHelper::from100( $rule['min_amount'] );
			$value[ $rule_key ]['max_amount'] = NumberHelper::from100( $rule['max_amount'] ?? 0 );
			$value[ $rule_key ]['max_amount_enabled'] = $rule['max_amount_enabled'] ?? false;
			$value[ $rule_key ]['value_amount'] = isset( $rule['value_amount'] ) ? (int) $rule['value_amount'] / 100 : 0;
			$value[ $rule_key ]['method_title_enabled'] = Sanitizer::boolean( $rule['method_title_enabled'] ?? false );
			$value[ $rule_key ]['method_title'] = sanitize_text_field( $rule['method_title'] ?? '' );

			if ( ! isset( $value[ $rule_key ]['compare'] ) ) {
				$value[ $rule_key ]['compare'] = 'total';
			}
		}

		return $value;
	}

	/**
	 * Decode the rules when WooCommerce hands them over as a JSON string.
	 *
	 * Hook this on `woocommerce_shipping_{$id}_instance_option`.
	 *
	 * @param mixed  $value Option value.
	 * @param string $key   Option key.
	 *
	 * @return mixed
	 */
	public function get_discount_instance_option( $value, $key ) {
		if ( 'discount_rules' === $key && is_string( $value ) ) {
			$value = json_decode( $value, true );
		}

		return $value;
	}

	/**
	 * Get only the rules the merchant left enabled.
	 *
	 * @return array
	 */
	public function get_enabled_discount_rules() {
		if ( ! is_array( $this->discount_rules ) ) {
			return [];
		}

		$enabled_rules = array_filter( $this->discount_rules, function ( $rule ) {
			return $rule['enabled'] === true;
		} );

		return $enabled_rules;
	}

	/**
	 * Apply the first matching discount rule to a shipping cost.
	 *
	 * Rules are evaluated from the highest `min_amount` down, so the most
	 * generous band the package qualifies for wins and the rest is skipped.
	 *
	 * @param float $cost          Cost after taxes, in the store currency.
	 * @param float $original_cost Cost as the carrier quoted it.
	 * @param float $package_total Total of the package contents.
	 *
	 * @return array{cost: float, matched: bool, method_title: string|null, enabled_count: int}
	 */
	protected function apply_discount_rules( $cost, $original_cost, $package_total ) {
		$enabled_rules = $this->get_enabled_discount_rules();
		$matched_rule_method_title = null;
		$matched = false;

		usort( $enabled_rules, function ( $a, $b ) {
			return $b['min_amount'] <=> $a['min_amount'];
		} );

		foreach ( $enabled_rules as $rule ) {
			$min_amount = NumberHelper::from100( $rule['min_amount'] );
			$max_amount_enabled = $rule['max_amount_enabled'] ?? false;
			$max_amount = NumberHelper::from100( $rule['max_amount'] ?? 0 );

			$total_compare = $rule['compare'] === 'shipping' ? $original_cost : $package_total;

			if ( $total_compare < $min_amount
				|| (
					$max_amount_enabled &&
					$min_amount < $max_amount &&
					$total_compare > $max_amount
				)
			) {
				continue;
			}

			$matched = true;

			$type = $rule['type'];

			switch ( $type ) {
				case 'percentage':
					$discount = (int) $rule['value'];
					$cost -= $cost * ( $discount / 100 );
					break;
				case 'amount':
					$discount = (int) $rule['value_amount'] / 100;
					$cost -= $discount;
					break;
				case 'fixed':
					$price = (int) $rule['value_amount'] / 100;
					$cost = $price;
					break;
			}

			if ( $cost < 0 ) {
				$cost = 0;
			}

			if ( Sanitizer::boolean( $rule['method_title_enabled'] ?? false ) ) {
				$method_title = sanitize_text_field( $rule['method_title'] ?? '' );
				if ( ! empty( $method_title ) ) {
					$matched_rule_method_title = $method_title;
				}
			}

			break;
		}

		return [
			'cost' => $cost,
			'matched' => $matched,
			'method_title' => $matched_rule_method_title,
			'enabled_count' => count( $enabled_rules ),
		];
	}

	/**
	 * Resolve the title of a rate that went through the discount rules.
	 *
	 * @param float       $cost         Final cost.
	 * @param string|null $matched_title Title the matching rule asked for.
	 *
	 * @return string
	 */
	protected function discount_rate_title( $cost, $matched_title = null ) {
		if ( ! empty( $matched_title ) ) {
			return $matched_title;
		}

		return $cost > 0 ? $this->title : $this->discount_free_title;
	}
}
