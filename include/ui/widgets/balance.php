<?php
namespace LWS\WOOVIRTUALWALLET\Ui\Widgets;

// don't call the file directly
if (!defined('ABSPATH')) {
	exit();
}

require_once LWS_WOOVIRTUALWALLET_INCLUDES . '/ui/widgets/widget.php';

/** Provide a widget for customer to input email for sponsorship.
 * Can be used as a Widget, a Shortcode [lws_sponsorship] or a Guttenberg block. */
class Balance extends \LWS\WOOVIRTUALWALLET\Ui\Widgets\Widget
{
	public static function install()
	{
		self::register(get_class());
		$me = new self(false);
		\add_shortcode('wallet_balance', array($me, 'fullShortcode'));
		\add_shortcode('wallet_simple_balance', array($me, 'miniShortcode'));

		\add_filter('lws_adminpanel_stygen_content_get_'.'lws_wvw_balance', array($me, 'template'));

		add_action('wp_enqueue_scripts', function () {
			\wp_register_style('lws_wvw_balance', LWS_WOOVIRTUALWALLET_CSS.'/templates/balance.css?stygen=lws_woovirtualwallet_widget_balance', array(), LWS_WOOVIRTUALWALLET_VERSION);
		});
	}

	/** Will be instanciated by WordPress at need */
	public function __construct($asWidget=true)
	{
		if ($asWidget) {
			parent::__construct(
				'lws_wvw_balance',
				__("WooVirtualWallet balance", 'woovirtualwallet-lite'),
				array(
					'description' => __("Show their wallet balance to customers.", 'woovirtualwallet-lite')
				)
			);
		}
	}

	public function template($snippet='')
	{
		$this->stygen = true;
		$snippet = $this->getContent();
		unset($this->stygen);
		return $snippet;
	}

	/**	Display the widget,
	 *	@see https://developer.wordpress.org/reference/classes/wp_widget/
	 * 	display parameters in $args
	 *	get option from $instance */
	public function widget($args, $instance)
	{
		if( \get_current_user_id() )
		{
			$instance['unlogged'] = 'on';
			echo $args['before_widget'];
			echo $args['before_title'];
			echo \apply_filters('widget_title', empty($instance['title']) ? _x("Virtual Money", "frontend widget", 'woovirtualwallet-lite') : $instance['title'], $instance);
			echo $args['after_title'];
			echo $this->getContent($instance);
			echo $args['after_widget'];
		}
	}

	/** ensure all required fields exist. */
	public function update($new_instance, $old_instance)
	{
		$new_instance = \wp_parse_args(
			array_merge($old_instance, $new_instance),
			$this->defaultArgs('edit')
		);

		\do_action('wpml_register_single_string', 'Widgets', "WooVirtualWallet - Balance Widget - Header", $new_instance['header']);
		return $new_instance;
	}

	/** Widget parameters (admin) */
	public function form($instance)
	{
		$instance = \wp_parse_args($instance, $this->defaultArgs('edit'));

		// title
		$this->eFormFieldText(
			$this->get_field_id('title'),
			__("Title", 'woovirtualwallet-lite'),
			$this->get_field_name('title'),
			\esc_attr($instance['title']),
			\esc_attr(_x("Virtual Wallet", "frontend widget", 'woovirtualwallet-lite'))
		);
		// header
		$this->eFormFieldText(
			$this->get_field_id('header'),
			__("Header", 'woovirtualwallet-lite'),
			$this->get_field_name('header'),
			\esc_attr($instance['header']),
			\esc_attr(_x("Your Wallet Balance", "frontend widget", 'woovirtualwallet-lite'))
		);
	}

	protected function defaultArgs($context='view')
	{
		$args = array(
			'title'  => '',
			'header'  => '',
		);
		if( 'view' == $context )
		{
			$args['mode'] = \LWS_WooVirtualWallet::isModeWallet() ? 'wallet' : 'gems';
		}
		return $args;
	}

	/** @brief shortcode [wallet_balance] */
	public function fullShortcode($atts=array(), $content='')
	{
		if( $userId = \get_current_user_id() )
		{
			return $this->getContent($atts);
		}
		else
			return $content;
	}

	/** @brief shortcode [wallet_simple_balance]
	 *	Display only a formated number. Apply NO style.
	 *  Or the given $content for unlogged users */
	public function miniShortcode($atts=array(), $content='')
	{
		if( $userId = \get_current_user_id() )
		{
			$defaults = array(
				'raw' => '',
				'mode' => \LWS_WooVirtualWallet::isModeWallet() ? 'wallet' : 'gems',
			);
			$atts = \shortcode_atts($defaults, $atts, 'wallet_simple_balance');

			$wallet = \LWS_WooVirtualWallet::helper()->getWallet($atts['mode'])->createFor($userId);
			$amount = $wallet->getAmount();
			if( $atts['raw'] )
				return $wallet->formatAmount($amount, false, false);
			else
				return $wallet->formatAmount($amount, true, true);
		}
		else
			return $content;
	}

	public function getContent($atts=array(), $content='')
	{
		$atts = \shortcode_atts($this->defaultArgs(), $atts, 'wallet_balance');

		if( !isset($atts['header']) || empty($atts['header']) )
			$atts['header'] = \lws_get_option('lws_woovirtualwallet_wallet_widget_header', __("Your Wallet Balance", 'woovirtualwallet-lite'));

		if( !isset($this->stygen) ) // not demo
		{
			$atts['header'] = \apply_filters('wpml_translate_single_string', $atts['header'], 'Widgets', "WooVirtualWallet - Balance Widget - Header");
			$wallet = \LWS_WooVirtualWallet::helper()->getWallet($atts['mode'])->createFor(\get_current_user_id());
			$balance = $wallet->getDisplayAmount();
			\wp_enqueue_style('lws_wvw_balance');
		}
		else
		{
			$balance = \LWS_WooVirtualWallet::helper()->formatAmount(rand(2100, 99999)/100.0, true, true, $atts['mode']);
		}

		$form = <<<EOT
<div class='lwss_selectable lws_woovirtualwallet_wallet_widget' data-type='Main'>
	<div class='lwss_selectable lwss_modify lws_woovirtualwallet_wallet_widget_header' data-id='lws_woovirtualwallet_wallet_widget_header' data-type='Header'>
		<span class='lwss_modify_content'>{$atts['header']}</span>
	</div>
	<div class='lwss_selectable lws_woovirtualwallet_wallet_widget_balance' data-type='Balance'>
		{$balance}
	</div>
</div>
EOT;
		return $form;
	}
}
