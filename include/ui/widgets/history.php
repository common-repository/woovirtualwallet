<?php
namespace LWS\WOOVIRTUALWALLET\Ui\Widgets;

// don't call the file directly
if (!defined('ABSPATH')) {
	exit();
}

require_once LWS_WOOVIRTUALWALLET_INCLUDES . '/ui/widgets/widget.php';

/** Provide a widget for customer to input email for sponsorship.
 * Can be used as a Widget, a Shortcode [lws_sponsorship] or a Guttenberg block. */
class History extends \LWS\WOOVIRTUALWALLET\Ui\Widgets\Widget
{
	public static function install()
	{
		self::register(get_class());
		$me = new self(false);
		\add_shortcode('wallet_history', array($me, 'shortcode'));

		\add_filter('lws_adminpanel_stygen_content_get_'.'lws_wvw_history', array($me, 'template'));

		add_action('wp_enqueue_scripts', function () {
			\wp_register_style('lws_wvw_history', LWS_WOOVIRTUALWALLET_CSS.'/templates/history.css?stygen=lws_woovirtualwallet_widget_history', array(), LWS_WOOVIRTUALWALLET_VERSION);
		});
	}

	/** Will be instanciated by WordPress at need */
	public function __construct($asWidget=true)
	{
		if ($asWidget) {
			parent::__construct(
				'lws_wvw_history',
				__("WooVirtualWallet History", 'woovirtualwallet-lite'),
				array(
					'description' => __("Show the customer virtual wallet history.", 'woovirtualwallet-lite')
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
			echo \apply_filters('widget_title', empty($instance['title']) ? _x("Virtual Wallet History", "frontend widget", 'woovirtualwallet-lite') : $instance['title'], $instance);
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

		\do_action('wpml_register_single_string', 'Widgets', "WooVirtualWallet - History Widget - Header", $new_instance['header']);
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
			\esc_attr(_x("Virtual Wallet History", "frontend widget", 'woovirtualwallet-lite'))
		);
		// header
		$this->eFormFieldText(
			$this->get_field_id('header'),
			__("Header", 'woovirtualwallet-lite'),
			$this->get_field_name('header'),
			\esc_attr($instance['header']),
			__("The last operations on your virtual wallet", 'woovirtualwallet-lite')
		);
		// period unit
		$this->eFormFieldSelect(
			$this->get_field_id('unit'),
			__("Limit Unit", 'woovirtualwallet-lite'),
			$this->get_field_name('unit'),
			array(
				'months' => __("Number of months to show", 'woovirtualwallet-lite'),
				'rows'   => __("Number of row (last operations) to show", 'woovirtualwallet-lite'),
			),
			\esc_attr($instance['unit'])
		);
		// period count
		$this->eFormFieldText(
			$this->get_field_id('period'),
			__("Months or max. Row count", 'woovirtualwallet-lite'),
			$this->get_field_name('period'),
			\esc_attr($instance['period']),
			__("Number of months to show", 'woovirtualwallet-lite'),
			'number'
		);
	}

	protected function defaultArgs($context='view')
	{
		$args = array(
			'title'  => '',
			'header' => '',
			'period' => 3,
			'unit'   => 'months',
		);
		if( 'view' == $context )
		{
			$args['mode'] = \LWS_WooVirtualWallet::isModeWallet() ? 'wallet' : 'gems';
		}
		return $args;
	}

	/** @brief shortcode [wallet_history]
	 *	Display only a number.
	 *  Or the given $content for unlogged users */
	public function shortcode($atts=array(), $content='')
	{
		if( $userId = \get_current_user_id() )
			return $this->getContent($atts, $content);
		else
			return $content;
	}

	public function getContent($atts=array(), $content='')
	{
		$atts = \shortcode_atts($this->defaultArgs(), $atts, 'wallet_history');

		if( !isset($atts['header']) || empty($atts['header']) )
			$atts['header'] = \lws_get_option('lws_woovirtualwallet_history_widget_header', __("Last operations on your Virtual Wallet", 'woovirtualwallet-lite'));

		if( !(isset($this->stygen) && $this->stygen) ) // not demo
		{
			$atts['header'] = \apply_filters('wpml_translate_single_string', $atts['header'], 'Widgets', "WooVirtualWallet - History Widget - Header");
			\wp_enqueue_style('lws_wvw_history');

			if( !\in_array($unit = \strtolower($atts['unit']), array('months', 'rows')) )
				$unit = 'months';
			if( !($period = absint($atts['period'])) )
				$period = ('months'==$unit ? 3 : 10);

			$trace = \LWS\WOOVIRTUALWALLET\Core\Trace::user(\get_current_user_id());
			$trace->setWallet($atts['mode']);
			if( 'months' == $unit )
				$historys = $trace->readFormated(\date_create()->sub(new \DateInterval("P{$period}M")));
			else
				$historys = $trace->readFormated(null, null, true, $period);
		}
		else
		{
			$historys = array(
				(object)array(
					'date'   => '2019-10-20 10:54', 'move'   => 1000, 'total'  => 1024,
					'reason' => __("Kindly offered for demo", 'woovirtualwallet-lite'),
				),
				(object)array(
					'date'   => '2019-10-18 12:12', 'move'   => 24, 'total'  => 24,
					'reason' => __("Every story has a beginning", 'woovirtualwallet-lite'),
				),
			);
			\LWS\WOOVIRTUALWALLET\Core\Trace::formatResult($historys, $atts['mode']);
		}
		$headers = array(
			'date' => __("Date", 'woovirtualwallet-lite'),
			'description' => __("Description", 'woovirtualwallet-lite'),
			'amount' => __("Amount", 'woovirtualwallet-lite'),
			'total' => __("Total", 'woovirtualwallet-lite'),
		);

		$table = '';
		foreach($historys as $history)
		{
			$table .= <<<EOT
<div class='lwss_selectable lws-woovirtualwallet-widget-history-value date' data-type='Date'>{$history->formatedDate}</div>
<div class='lwss_selectable lws-woovirtualwallet-widget-history-value reason' data-type='Reason'>{$history->reason}</div>
<div class='lwss_selectable lws-woovirtualwallet-widget-history-value move' data-type='Value'>{$history->formatedMove}</div>
<div class='lwss_selectable lws-woovirtualwallet-widget-history-value total' data-type='Total'>{$history->formatedTotal}</div>
EOT;
		}

		$title = <<<EOT
	<div class='lwss_selectable lwss_modify lws_woovirtualwallet_history_widget_header' data-id='lws_woovirtualwallet_history_widget_header' data-type='Header'>
		<span class='lwss_modify_content'>{$atts['header']}</span>
	</div>
EOT;
		$class = 'widget';
		if( isset($this->stygen) && $this->stygen )
			$class .= ' demo';
		$bar = \implode('', \apply_filters('lws_woovirtualwallet_history_title_bar', array(
			$title,
		), array(
			'balance' => $historys,
			'user_id' => \get_current_user_id(),
		), $class));

		$form = <<<EOT
<div class='lwss_selectable lws_woovirtualwallet_history_widget' data-type='Main'>
	{$bar}
	<div class='lwss_selectable lws-woovirtualwallet-widget-history-table' data-type='Grid'>
		<div class='lwss_selectable lws-woovirtualwallet-widget-history-head' data-type='Header'>{$headers['date']}</div>
		<div class='lwss_selectable lws-woovirtualwallet-widget-history-head' data-type='Header'>{$headers['description']}</div>
		<div class='lwss_selectable lws-woovirtualwallet-widget-history-head' data-type='Header'>{$headers['amount']}</div>
		<div class='lwss_selectable lws-woovirtualwallet-widget-history-head' data-type='Header'>{$headers['total']}</div>
		{$table}
	</div>
</div>
EOT;
		return $form;
	}
}
