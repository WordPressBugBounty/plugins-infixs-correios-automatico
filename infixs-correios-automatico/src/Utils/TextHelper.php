<?php

namespace Infixs\CorreiosAutomatico\Utils;

defined('ABSPATH') || exit;
class TextHelper
{
	public static function extractAddressNumber($address)
	{
		if (preg_match('/,\s*(\d+(\.\d+)?)\b/', $address, $matches)) {
			return Sanitizer::numeric_text($matches[1] ?? '');
		}
		preg_match_all('/\b\d+(\.\d+)?\b/', $address, $matches);
		if (! empty($matches[0])) {
			return Sanitizer::numeric_text(end($matches[0]));
		}
		return '';
	}
	public static function removeAddressNumber($address)
	{
		$number = self::extractAddressNumber($address);
		if ($number === '') {
			return rtrim($address, ', ');
		}
		$pattern = '/\b' . preg_quote($number, '/') . '\b/';
		if (preg_match_all($pattern, $address, $matches, PREG_OFFSET_CAPTURE)) {
			$last_match = end($matches[0]);
			$address    = substr_replace($address, '', $last_match[1], strlen($last_match[0]));
		}
		$address = trim($address);
		$address = rtrim($address, ', ');
		return $address;
	}


	public static function removeShippingTime($name)
	{
		return trim(preg_replace('/ \(\s*\d+(?: a \d+)? dia[s]? út(eis|il)\s*\)/', '', $name));
	}
}
