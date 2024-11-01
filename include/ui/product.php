<?php
namespace LWS\WOOVIRTUALWALLET\Ui;

// don't call the file directly
if( !defined( 'ABSPATH' ) ) exit();

/** A Product can grant virtual money when ordered.
 * Add product edition screen option.
 * Manage order checkout gain action.
 *
 * Support variation
 *
 * Use filter "lws_woovirtualwallet_product_gains_{$wallet_name}" to know
 * the amounts, where $wallet_name is $this->options->wallet->getName()
 * @see productGains() */
class Product
{
	protected $options = array();

	function __construct(array $options = array())
	{
		$this->readOptions($options);

		static $once = true;
		if( $once ){
			$once = false;
			\add_filter('woocommerce_product_data_tabs', array($this, 'setTab'));
			\add_action('woocommerce_product_data_panels', array($this, 'echoTabContent'));
		}

		\add_action('save_post', array($this, 'savePost'));
		\add_filter('lws_woovirtualwallet_product_options_panel', array($this, 'optionPanelContent'), 100, 3);

		\add_filter($this->options->hooks['product_gains'], array($this, 'productGains'), 10, 2);

		\add_action('woocommerce_product_after_variable_attributes', array($this, 'echoVariationContent'), 10, 3);
		\add_action('woocommerce_save_product_variation', array($this, 'saveVariation'), 10, 2);

		$status = array('processing', 'completed');
		if( \get_option('lws_woovirtualwallet_order_events_on_completed_status_only', false) )
			$status = array('completed');
		foreach (array_unique($status) as $s)
			\add_action('woocommerce_order_status_' . $s, array($this, 'triggerOrderDone'), 99, 2); // priority late to let wc to save order
	}

	protected function readOptions($options)
	{
		$this->options = (object)array_merge(
			array(
				'wallet'   => false,
				'metakey'  => 'lws_woovirtualwallet_wallet_add', /// post.meta_key: gain amount value
				'oncekey'  => 'lws_woovirtualwallet_add_amount_done', /// order processed flag
				'sanitize' => '\floatval', /// sanitize anount to save
			),
			$options
		);

		if( !$this->options->wallet )
			$this->options->wallet = \LWS\WOOVIRTUALWALLET\Core\Wallet::instanciate();

		$this->options->hooks = array(
			'product_gains' => 'lws_woovirtualwallet_product_gains_'.$this->options->wallet->getName(),
		);
	}

	/** add virtual money at checkout */
	function triggerOrderDone($order_id, $order)
	{
		if( empty(\get_post_meta($order_id, $this->options->oncekey, true)) )
		{
			\update_post_meta($order_id, $this->options->oncekey, \date(DATE_W3C));

			if( !($userId = $order->get_customer_id('edit')) )
				return;

			foreach( $order->get_items() as $item )
			{
				$product = (\method_exists($item, 'get_product') ? $item->get_product() : $order->get_product_from_item($item));
				if( $product )
				{
					$sum = \apply_filters($this->options->hooks['product_gains'], 0, $product);//$this->getGainForProduct($product);
					if( $sum )
					{
						$sum = call_user_func($this->options->sanitize, $sum);
						if( $sum > 0 )
						{
							$qty = $item->get_quantity();
							$sum *= $qty;
							$reason = \LWS\WOOVIRTUALWALLET\Core\Trace::order($order)->setOrigin('product')->setProvider($product->get_id());
							$reason->setReason(array('%1$s purchased in order %2$s', $product->get_name(), $order->get_order_number()), 'woovirtualwallet-lite');
							$wallet = $this->options->wallet->createFor($userId);
							$wallet->addAmount($sum, $reason);

							$order->add_order_note(sprintf(
								_x('Product <i>%1$s</i> offers %2$s on customer\'s %3$s', 'g2d order note', 'woovirtualwallet-lite'),
								$product->get_name(),
								$wallet->formatAmount($sum),
								$wallet->getDisplayName()
							));
						}
					}
				}
			}
		}
	}

	protected function getGainForProduct(&$product)
	{
		$isVariation = $product->is_type('variation');
		$pId = $isVariation ? $product->get_parent_id() : $product->get_id();
		$sum = \get_post_meta($pId, $this->options->metakey, true);

		// check for variation
		if( $isVariation )
		{
			$override = \trim(\get_post_meta($product->get_id(), $this->options->metakey, true));
			if( \strlen($override) )
				$sum = $override;
		}

		return $sum;
	}

	/**	Sum the product gain at checkout in the current Wallet.
	 *	@param $gains (int|float) should be init to zero
	 * 	@param $product (WC_Product instance) the product to check, can be a variation.
	 * 	@return (int|float) the amount given at checkout for that product
	 *	If $gains was not zero, amount is added. */
	function productGains($gains, $product)
	{
		$amount = $this->getGainForProduct($product);
		if( $amount && \is_numeric($amount) )
			$gains += $amount;
		return $gains;
	}

	/** Add lateral tabs for product settings. */
	function setTab($tabs)
	{
		$tabs['lws-wvw'] = array(
			'label' => __("Virtual Wallet", 'woovirtualwallet-lite'),
			'target' => 'lws_woovirtualwallet_product_data',
			'class' => array('lws_woovirtualwallet_product_tab hide_if_grouped')
		);
		return $tabs;
	}

	function echoTabContent()
	{
		global $product_object;
		if( !$product_object ) return;

		$contents = \apply_filters('lws_woovirtualwallet_product_options_panel', array(), $product_object, $product_object->get_id());

		echo "<div id='lws_woovirtualwallet_product_data' class='panel woocommerce_options_panel lws_woovirtualwallet'>";
		foreach( $contents as $class => $content )
		{
			echo "<div class='options_group {$class}'>{$content}</div>";
		}
		echo "</div>";
	}

	function optionPanelContent($contents, $product, $productId)
	{
		$currency = $this->options->wallet->getCurrencySymbol(2);
		$text = sprintf(
			__('Purchase that product adds %1$s in customer\'s %2$s', 'woovirtualwallet-lite'),
			$currency,
			$this->options->wallet->getDisplayName()
		);
		$text = "<p>{$text}</p>";

		$warn = sprintf(
			__("Customers must be logged in to receive wallet credit. Guest orders won't give any %s.", 'woovirtualwallet-lite'),
			$currency
		);
		$text .= "<p>{$warn}</p>";

		\ob_start();
		\woocommerce_wp_text_input(array(
			'id'          => $this->options->metakey,
			'label'       => sprintf(__("Amount (%s)", 'woovirtualwallet-lite'), $currency),
		));
		$text .= \ob_get_clean();
		$contents['lws-woovirtualwallet-grant-amount-'.$this->options->wallet->getName()] = $text;

		return $contents;
	}

	function savePost($postId)
	{
		if( false === \strpos(\get_post_type($postId), 'product') ) return;
		$amount = isset($_POST[$this->options->metakey]) ? \sanitize_text_field($_POST[$this->options->metakey]) : '';
		$this->saveGain($amount, $postId);
	}

	protected function saveGain($amount, $postId)
	{
		if( \strlen($amount = \trim($amount)) )
		{
			if( \is_numeric($amount = \str_replace(',', '.', $amount)) )
			{
				$amount = call_user_func($this->options->sanitize, $amount);
				if( $amount >= 0 )
				{
					\update_post_meta($postId, $this->options->metakey, $amount);
				}
				else
				{
					\lws_admin_add_notice_once(
						'woovirtualwallet-lite'.$this->options->wallet->getName().'-bad_amount',
						sprintf(__("%s amount must be greater than zero or null.", 'woovirtualwallet-lite'), $this->options->wallet->getCurrencySymbol()),
						array('level'=>'error')
					);
				}
			}
			else
			{
				\lws_admin_add_notice_once(
					'woovirtualwallet-lite'.$this->options->wallet->getName().'-bad_amount',
					sprintf(__("Bad %s amount format", 'woovirtualwallet-lite'), $this->options->wallet->getCurrencySymbol()),
					array('level'=>'error')
				);
			}
		}
		else
			\update_post_meta($postId, $this->options->metakey, '');
	}

	/** show money gain field for the given variation */
	function echoVariationContent($loop, $variation_data, $variation)
	{
		$currency = $this->options->wallet->getCurrencySymbol(2);
		$text = sprintf(
			__('Purchase that product adds %1$s in customer\'s %2$s', 'woovirtualwallet-lite'),
			$currency,
			$this->options->wallet->getDisplayName()
		);
		$text .= sprintf(
			__(" (Customers must be logged in to receive wallet credit. Guest orders won't give any %s.)", 'woovirtualwallet-lite'),
			$currency
		);
		$field = ($this->options->metakey . '_' . $variation->ID);
		$amount = \get_post_meta($variation->ID, $this->options->metakey, true);

		\woocommerce_wp_text_input(array(
			'id'          => $field,
			'name'        => $field,
			'value'       => \esc_attr($amount),
			'label'       => $text,//sprintf(__("Amount (%s)", 'woovirtualwallet-lite'), $currency),
		));
	}

	/** save money gain value for the given variation */
	function saveVariation($variation_id, $i)
	{
		if( !isset($_POST['variable_post_id'][ $i ]) )
			return;
		$field = ($this->options->metakey . '_' . $variation_id);
		$amount = isset($_POST[$field]) ? \sanitize_text_field($_POST[$field]) : '';
		$this->saveGain($amount, $variation_id);
	}

	/** Never call, only to have poedit/wpml able to extract the sentance. */
	private function poeditDeclare()
	{
		__('%1$s purchased in order %2$s', 'woovirtualwallet-lite');
	}
}
