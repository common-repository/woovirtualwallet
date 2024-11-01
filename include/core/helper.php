<?php
namespace LWS\WOOVIRTUALWALLET\Core;

// don't call the file directly
if( !defined( 'ABSPATH' ) ) exit();

/** Group convenience methods */
class Helper
{
	function defaultCurrency()
	{
		if( \LWS_WooVirtualWallet::isWC() )
			return \get_woocommerce_currency_symbol();
		else
			return __("Virtual Money", 'woovirtualwallet-lite');
	}

	function currencySymbol($count=1, $mode='', $raw=false)
	{
		return $this->getWallet($mode)->getCurrencySymbol($count, $raw);
	}

	function formatAmount($amount, $withCurrency=true, $withDecoration=true, $mode='')
	{
		return $this->getWallet($mode)->formatAmount($amount, $withCurrency, $withDecoration);
	}

	function walletDisplayName()
	{
		return $this->getWallet($mode)->getDisplayName();
	}

	function getWallet($mode)
	{
		if( !isset($this->wallet) )
			$this->wallet = new \LWS\WOOVIRTUALWALLET\Core\Wallet(false);
		return $this->wallet;
	}
}
