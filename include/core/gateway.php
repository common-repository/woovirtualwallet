<?php
namespace LWS\WOOVIRTUALWALLET\Core;

// don't call the file directly
if( !defined( 'ABSPATH' ) ) exit();

/** Provides an Offline Payment Gateway using user's virtual wallet. */
class Gateway extends \WC_Payment_Gateway
{
	protected $isMainInstance = true;

	function __construct($install=true)
	{
		$this->isMainInstance = $install;
		$this->wvw_init();

		$this->init_form_fields();
		$this->init_settings();

		$this->title        = $this->get_option('title');
		$this->description  = $this->get_option('description');
		$this->instructions = $this->get_option('instructions');

		if( $install )
		{
			// callback
			\add_action('woocommerce_update_options_payment_gateways_'.$this->id, array($this, 'process_admin_options'));

			// append information
			\add_action('woocommerce_thankyou_'.$this->id, array($this, 'thankyou'));
			\add_action('woocommerce_email_before_order_table', array($this, 'emailInstructions'), 10, 3);
		}
	}

	protected function wvw_init()
	{
		$this->id = 'lws_woovirtualwallet_gateway';
		$this->icon = LWS_WOOVIRTUALWALLET_IMG . '/icon-wvw-gateway.png';
		$this->has_fields = false;
		$this->method_title = _x("Virtual Wallet", 'Admin Gateway title', 'woovirtualwallet-lite');
		$this->method_description = _x("Customers use Virtual Wallet Money", 'Admin Gateway descr', 'woovirtualwallet-lite');
	}

	function init_form_fields()
	{
		$this->form_fields = \apply_filters('lws_woovirtualwallet_gateway_form_fields', array(
			'enabled'      => array(
				'title'   => __('Enable/Disable', 'woovirtualwallet-lite'),
				'type'    => 'checkbox',
				'label'   => __('Enable Virtual Wallet payments', 'woovirtualwallet-lite'),
				'default' => 'no',
			),
			'title'        => array(
				'title'       => __('Title', 'woovirtualwallet-lite'),
				'type'        => 'text',
				'description' => __('This controls the title which the user sees during checkout.', 'woovirtualwallet-lite'),
				'default'     => _x('Virtual Wallet Payment', 'Virtual Wallet payment method', 'woovirtualwallet-lite'),
				'desc_tip'    => true,
			),
			'description'  => array(
				'title'       => __('Description', 'woovirtualwallet-lite'),
				'type'        => 'textarea',
				'description' => __('Payment method description that the customer will see on your checkout.', 'woovirtualwallet-lite'),
				'default'     => __('Order amount will be deduced from your virtual wallet.', 'woovirtualwallet-lite'),
				'desc_tip'    => true,
			),
			'instructions' => array(
				'title'       => __('Instructions', 'woovirtualwallet-lite'),
				'type'        => 'textarea',
				'description' => __('Instructions that will be added to the thank you page and emails.', 'woovirtualwallet-lite'),
				'default'     => '',
				'desc_tip'    => true,
			),
			'not_logged' => array(
				'title'       => __('Not logged user message', 'woovirtualwallet-lite'),
				'type'        => 'textarea',
				'description' => __('Message that will be added to the payment method description when customer is not logged in.', 'woovirtualwallet-lite'),
				'default'     => __('You must be connected to use your Virtual Wallet', 'woovirtualwallet-lite'),
				'desc_tip'    => true,
			),
			'show_balance' => array(
				'title'       => __('Show balance', 'woovirtualwallet-lite'),
				'type'        => 'checkbox',
				'label'       => __('The payment method title includes the customer\'s wallet balance.', 'woovirtualwallet-lite'),
				'default'     => 'yes',
			),
			'show_icon' => array(
				'title'       => __('Show icon', 'woovirtualwallet-lite'),
				'type'        => 'checkbox',
				'label'       => __('The payment method title includes the Virtual Wallet icon.', 'woovirtualwallet-lite') . '&nbsp;' . $this->getIconImg(),
				'default'     => 'yes',
			),
		));
	}

	/** @return false or error string */
	protected function restrictions()
	{
		if( !\WC()->cart )
			return false;

		if( !($userId = \get_current_user_id()) )
			return $this->get_option('not_logged');

		$products = $this->getProductFromCart(\WC()->cart);
		if( $err = $this->getRestrictionOnProducts($products, 'alert') )
			return $err;

		$wallet = \LWS\WOOVIRTUALWALLET\Core\Wallet::instanciate($userId);
		if( \WC()->cart->get_total('edit') > $wallet->getAmount(true) )
			return sprintf(__("You don't have enough money in your wallet : Actual balance is %s", 'woovirtualwallet-lite'), $wallet->getDisplayAmount(true, true));

		return false;
	}

	/** return an array of product and variation instances */
	protected function getProductFromCart($cart)
	{
		$products = array();
		foreach( $cart->get_cart() as $item )
		{
			$product = false;
			if( isset($item['variation_id']) && $item['variation_id'] )
				$product = \wc_get_product($item['variation_id']);

			if( !$product && isset($item['product_id']) )
				$product = \wc_get_product($item['product_id']);

			if( $product )
				$products[$product->get_id()] = $product;
		}
		return $products;
	}

	/** return an array of product and variation instances */
	protected function getProductFromOrder($order)
	{
		$products = array();
		foreach( $order->get_items() as $item )
		{
			$product = (\method_exists($item, 'get_product') ? $item->get_product() : $order->get_product_from_item($item));
			if( $product )
				$products[$product->get_id()] = $product;
		}
		return $products;
	}

	protected function getRestrictionOnProducts($products, $class='alert')
	{
		if( !empty(\get_option('lws_woovirtualwallet_forbids_bought_wallet_with_wallet', 'on')) )
		{
			$forbiddenProducts = array();
			foreach( $products as $product )
			{
				if( !$this->isAllowedProduct($product) )
					$forbiddenProducts[$product->get_id()] = '<span class="lws-woovirtualwallet-forbidden-product {$class}">'.$product->get_name().'</span>';
			}
			if( $forbiddenProducts )
			{
				return sprintf(
					__("Your order contains products that cannot be bought with your Virtual Wallet: %s", 'woovirtualwallet-lite'),
					implode(_x(', ', 'product name separator', 'woovirtualwallet-lite'), $forbiddenProducts)
				);
			}
		}
		return false;
	}

	protected function isAllowedProduct($product)
	{
		return !$this->getGainForProduct($product);
	}

	protected function getGainForProduct(&$product)
	{
		$metakey = 'lws_woovirtualwallet_wallet_add';
		$isVariation = $product->is_type('variation');
		$pId = $isVariation ? $product->get_parent_id() : $product->get_id();
		$sum = \get_post_meta($pId, $metakey, true);

		// check for variation
		if( $isVariation )
		{
			$override = \trim(\get_post_meta($product->get_id(), $metakey, true));
			if( \strlen($override) )
				$sum = $override;
		}

		return $sum;
	}

	public function set_current()
	{
		if( !$this->restrictions() )
			$this->chosen = true;
	}

	protected function getIconImg()
	{
		if( isset($this->icon) && $this->icon )
		{
			$title = \esc_attr($this->get_title());
			$src = \esc_attr(\WC_HTTPS::force_https_url($this->icon));
			return \apply_filters('lws_woowallet_gateway_img', "<img src='{$src}' alt='{$title}' />", $src, $title);
		}
		return '';
	}

	/** @return (string) the gateway's icon with warning if required, with balance if option set. */
	public function get_icon()
	{
		if( \is_admin() )
			return parent::get_icon();
		$icon = '';

		if( $this->get_option('show_icon', 'yes') == 'yes' )
		{
			$img = $this->getIconImg();
			$icon = "<div class='lws-woovirtualwallet-payment-method-icon'>{$img}</div>";
		}

		if( $this->get_option('show_balance', 'yes') == 'yes' )
		{
			if( $userId = \get_current_user_id() )
			{
				$wallet = \LWS\WOOVIRTUALWALLET\Core\Wallet::instanciate($userId);
				$amount = $wallet->getDisplayAmount(true, true);
				$amount = sprintf(_x('Your current balance : %s', 'wallet balance decoration', 'woovirtualwallet-lite'),'<strong>'.$amount.'</strong>');
				$icon .= "<div class='lws-woovirtualwallet-payment-method-balance'>{$amount}</div>";
			}
		}

		if( $icon )
			$icon = "<div class='lws-woovirtualwallet-payment-method-label'>{$icon}</div>";

		if( $this->restrictions() )
			$icon = '<span id="wvw_payment_method_warning" class="lws-icon lws-icon-warning wvw-payment-method-warning"></span> '.$icon;
		return \apply_filters('woocommerce_gateway_icon', $icon, $this->id);
	}

	protected function invalidProcess($order)
	{
		return false;
	}

	public function get_description()
	{
		$descr = $this->description;
		if( !is_admin() )
		{
			if( $err = $this->restrictions() )
				$descr = "<div class='lws-woovirtualwallet-payment-method-alert'>$err</div>";
		}
		return apply_filters('woocommerce_gateway_description', $descr, $this->id);
	}

	function process_payment($order_id)
	{
		$order = \wc_get_order($order_id);
		if( !($userId = $order->get_customer_id('edit')) )
			throw new \Exception(__("Virtual Wallet Payment cannot be used by logged off customers&hellip;", 'woovirtualwallet-lite'));

		$products = $this->getProductFromOrder($order);
		if( $err = $this->getRestrictionOnProducts($products, 'error') )
			throw new \Exception($err);

		if( $err = $this->invalidProcess($order) )
			throw new \Exception($err);

		$transactionId = $this->soldOrder($order, $userId);

		$order->payment_complete($transactionId);

		/// Reduce stock levels, a doc says it is recommended, but no default WC gateway does it.
		// $order->reduce_order_stock();

		// Remove cart
//		\WC()->cart->empty_cart();

		return array(
			'result' 	=> 'success',
			'redirect'	=> $this->get_return_url($order),
		);
	}

	/** Consume Wallet Money
	 * @return a transaction id. */
	protected function soldOrder(&$order, $userId)
	{
		if( $order->get_total() > 0 )
		{
			$reason = \LWS\WOOVIRTUALWALLET\Core\Trace::order($order)->setOrigin('gateway');
			$reason->setReason(array("Order %s payment", $order->get_order_number()), 'woovirtualwallet-lite');

			// no need to 'on-hold' status, taking virtual wallet money is a local action
			$wallet = \LWS\WOOVIRTUALWALLET\Core\Wallet::instanciate($userId);
			if( !$wallet->pay($order->get_total(), $reason) )
			{
				throw new \Exception(sprintf(
					__("Not enough money in your Virtual Wallet (balance: %s)&hellip;", 'woovirtualwallet-lite'),
					$wallet->getDisplayAmount(true, true)
				));
			}
		}
		return '';
	}

	function thankyou()
	{
		if( $this->instructions )
		{
			echo \wpautop(\wptexturize($this->instructions));
		}
	}

	function emailInstructions($order, $sentToAdmin, $plainText=false)
	{
		if( $this->instructions && !$sentToAdmin && $this->id === $order->get_payment_method() )
		{
			echo \wpautop(\wptexturize($this->instructions)) . PHP_EOL;
		}
	}

	/** Never call, only to have poedit/wpml able to extract the sentance. */
	private function poeditDeclare()
	{
		__("Order %s payment", 'woovirtualwallet-lite');
	}
}
