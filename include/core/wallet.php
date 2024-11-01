<?php
namespace LWS\WOOVIRTUALWALLET\Core;

// don't call the file directly
if( !defined( 'ABSPATH' ) ) exit();

/** Manage user wallet amount. */
class Wallet
{
	private $userRef = false;
	protected $prefix = 'lws_woovirtualwallet_';

	/** @return a new instance of Wallet.
	 * @param $user (false|int|WP_User) wallet owner, if false get the current user. */
	static function instanciate($user=false)
	{
		$classname = static::getClassname();
		$me = new $classname($user);
		if( !is_a($me, \get_class()) )
		{
			$me = new self($user);
		}
		return $me;
	}

	/** To be used with a 'late binding' */
	static protected function getClassname()
	{
		static $cnWallet = false;
		if( false === $cnWallet )
		{
			$cnWallet = \apply_filters('lws_woovirtualwallet_wallet_classname', \get_class());
		}
		return $cnWallet;
	}

	/** Create a new instance of Wallet for the given user
	 * equivalent of clone, but set a different user. */
	function createFor($user)
	{
		$classname = \get_class($this);
		return new $classname($user);
	}

	/** @param $user (false|int|WP_User) wallet owner, if false get the current user.
	 * @user is loaded on demand @see getUser()
	 *
	 * direct usage is deprecated, prefer call instanciate() */
	function __construct($user)
	{
		$this->userRef = $user;
	}

	/** @return false|WP_User */
	function getUser()
	{
		if( !isset($this->user) )
		{
			if( !$this->userRef )
				$this->user = \wp_get_current_user();
			else if( is_numeric($this->userRef) )
			{
				$this->user = \get_user_by('ID', $this->userRef);
				if( !$this->user->ID )
					$this->user = false;
			}
			else if( is_a($this->userRef, 'WP_User') )
				$this->user = $this->userRef;
			else
				$this->user = false;
		}
		return $this->user;
	}

	/** @return false|int */
	function getUserId()
	{
		if( !isset($this->userId) )
		{
			$this->userId = false;
			if( is_numeric($this->userRef) && $this->userRef )
				$this->userId = \absint($this->userRef);
			else if( $user = $this->getUser() )
				$this->userId = $user->ID;
		}
		return $this->userId;
	}

	function getAmount($substractContribution=false)
	{
		$amount = floatval(\get_user_meta($this->getUserId(), "{$this->prefix}amount", true));
		if( $substractContribution )
			$amount -= $this->getContribution(false);
		return $amount;
	}

	/** id for history */
	public function getName()
	{
		return 'wallet';
	}

	/** for admin ui display */
	public function getDisplayName()
	{
		return __("Virtual Wallet");
	}

	protected function &trace($reason, $total, $old=false)
	{
		if( is_a($reason, '\LWS\WOOVIRTUALWALLET\Core\Trace') )
		{
			$reason->setUser($this->getUserId());
			$reason->setWallet($this->getName());
			$reason->trace($total, $old);
		}
		else if( is_array($reason) )
		{
			$reason['user'] = $this->getUserId();
			$trace = new \LWS\WOOVIRTUALWALLET\Core\Trace($reason);
			$trace->setWallet($this->getName());
			$trace->trace($total, $old);
		}
		else
		{
			$trace = \LWS\WOOVIRTUALWALLET\Core\Trace::user($this->getUserId())->setReason($reason);
			$trace->setWallet($this->getName());
			$trace->trace($total, $old);
		}
		return $this;
	}

	function &setAmount($amount, $reason='')
	{
		\update_user_meta($this->getUserId(), "{$this->prefix}amount", $amount);
		return $this->trace($reason, $amount);
	}

	function &addAmount($amount, $reason='')
	{
		$old = $this->getAmount();
		$amount += $old;
		\update_user_meta($this->getUserId(), "{$this->prefix}amount", $amount);
		return $this->trace($reason, $amount, $old);
	}

	function &subAmount($amount, $reason='')
	{
		$old = $this->getAmount();
		$rest = $old - $amount;
		if( $rest < 0.0 )
		{
			$userId = $this->getUserId();
			error_log("Unautorised credit for user {$userId}: wallet amount cannot be negative {$rest}. Amount clamped to 0.");
			$rest = 0.0;
		}
		\update_user_meta($this->getUserId(), "{$this->prefix}amount", $rest);
		return $this->trace($reason, $rest, $old);
	}

	/** format amount with currency */
	function getDisplayAmount($withCurrency=true, $substractContribution=false)
	{
		return $this->formatAmount($this->getAmount($substractContribution), $withCurrency);
	}

	/** @return (bool) false if user does not have enough money. */
	function pay($amount, $reason='', $force=false)
	{
		$old = $this->getAmount();
		if (!$force) {
			if( ($old - $this->getContribution(false)) < $amount )
				return false;
		}
		$rest = $old - $amount;
		\update_user_meta($this->getUserId(), "{$this->prefix}amount", $rest);
		$this->trace($reason, $rest, $old);
		return true;
	}

	/** @return the real value substracted from wallet. */
	function applyContribution($reason='')
	{
		$amount = $this->getContribution();
		$old = $this->getAmount();
		$rest = $old - $amount;
		\update_user_meta($this->getUserId(), "{$this->prefix}amount", $rest);
		$this->trace($reason, $rest, $old);
		\update_user_meta($this->getUserId(), "{$this->prefix}contribution", 0);
		return $amount;
	}

	/** clamped to wallet available amount */
	function &setContribution($amount)
	{
		$amount = max(0, $amount);
		$amount = min($this->getAmount(), $amount);
		\update_user_meta($this->getUserId(), "{$this->prefix}contribution", $amount);
		return $this;
	}

	/** cannot exceed wallet available amount.
	 * to be overridden
	 * Free version always return zero since we do not want
	 * uncontrolable contribution remind on order after plugin downgrade. */
	function getContribution($clamp=true)
	{
		return 0;
	}

	function getAmountUserMetaKey()
	{
		return "{$this->prefix}amount";
	}

	function getContributionUserMetaKey()
	{
		return "{$this->prefix}contribution";
	}

	function getCurrencySymbol($count=1, $raw=false)
	{
		if( \LWS_WooVirtualWallet::isWC() )
			return \get_woocommerce_currency_symbol();
		else
			return __("Virtual Money", 'woovirtualwallet-lite');
	}

	function formatAmount($amount, $withCurrency=true, $withDecoration=true)
	{
		$price = $amount;
		if( $negative = $price < 0 )
			$price *= -1;

		if( \LWS_WooVirtualWallet::isWC() )
		{
			$price    = apply_filters('raw_woocommerce_price', floatval($price));
			$args     = array('d' => \wc_get_price_decimals(), 'ds' => \wc_get_price_decimal_separator(), 'ts' => \wc_get_price_thousand_separator());
			$price    = apply_filters('formatted_woocommerce_price', \number_format($price, $args['d'], $args['ds'], $args['ts']), $price, $args['d'], $args['ds'], $args['ts']);
		}
		else
			$price = \number_format_i18n($price, \get_option('lws_woovirtualwallet_amount_rounding', 2));

		if( $withDecoration )
			$text = "<span class='lws-woovirtualwallet-amount-number'>{$price}</span>";
		else
			$text = $price;

		if( $withCurrency )
			$text = sprintf($this->amountFormat(), $this->getCurrencySymbol($amount), $text);

		if( $negative )
			$text = ('-' . $text);

		if( $withDecoration )
		{
			$mode = \esc_attr(\LWS_WooVirtualWallet::getMode());
			$text = "<span class='lws-woovirtualwallet-amount {$mode}'>{$text}</span>";
		}
		return $text;
	}

	function amountFormat()
	{
		if( \function_exists('get_woocommerce_price_format') )
			return \get_woocommerce_price_format();

		static $format = false;
		if( !$format )
		{
			$pos = \get_option('lws_woovirtualwallet_currency_format', 'left');
			$known = array(
				'right' => '%2$s%1$s',
				'left'  => '%1$s%2$s',
				'right_space' => '%2$s&nbsp;%1$s',
				'left_space'  => '%1$s&nbsp;%2$s',
			);
			$format = isset($known[$pos]) ? $known[$pos] : $known['left'];
		}
		return $format;
	}
}
