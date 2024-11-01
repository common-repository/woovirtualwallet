<?php
namespace LWS\WOOVIRTUALWALLET;

// don't call the file directly
if( !defined( 'ABSPATH' ) ) exit();

/** Add refunds feature to wallet gateway. */
class Ajax
{
	static function install()
	{
		$me = new self();
		\add_action('wp_ajax_lws_woovirtualwallet_balance', array($me, 'userBalance'));
	}

	/** Echo an HTML string with balance table.
	 * Require a valid 'nonce'.
	 * All other args optional.
	 * Default return the current user balance.
	 * * 'u' to see another user (need read_other_virtual_wallet capability)
	 * * 'd' to define the last date to return (default is today)
	 * * 'p' to define a period in Month, Year, Day (default is 1M) */
	function userBalance()
	{
		if( !($loggedId = \get_current_user_id()) )
			\wp_die(__("A connected user is required", 'woovirtualwallet-lite'), 401);

		if( !isset($_GET['nonce']) || !\wp_verify_nonce($_GET['nonce'], 'lws_woovirtualwallet_balance') )
			\wp_die(__("Action control failed. Try to refresh the page.", 'woovirtualwallet-lite'), 403);

		$userId = isset($_GET['u']) ? intval($_GET['u']) : $loggedId;
		if( $userId != $loggedId && !\current_user_can('read_other_virtual_wallet') )
			\wp_die(__("You don't have the capacity to do that.", 'woovirtualwallet-lite'), 403);

		$class = '';
		if( isset($_GET['c']) && $_GET['c'] )
			$class = \sanitize_key($_GET['c']);
		if( $class )
			$class = (' ' . $class);
		else
			$class = ' ajax';

		$period = '1M';
		if( isset($_GET['p']) && $_GET['p'] )
		{
			$period = ltrim(strtoupper(\sanitize_key($_GET['p'])), 'P');
			if( !\preg_match('/\d+[YMD]/', $period) )
				$period = '1M';
		}
		if( !($end = isset($_GET['d']) ? \date_create($_GET['d']) : false) )
			$end = \date_create();
		$end->setTime(0,0);
		$start = clone $end;
		$start->sub(new \DateInterval('P'.$period));

		$df = \get_option('date_format');
		$response = array(
			'user_id' => $userId,
			'display_start' => \date_i18n($df, $start->getTimestamp()),
			'display_end'   => \date_i18n($df, $end->getTimestamp()),
			'start'   => $start->format('Y-m-d'),
			'end'     => $end->format('Y-m-d'),
		);

		$trace = \LWS\WOOVIRTUALWALLET\Core\Trace::user($userId);
		if( isset($_GET['w']) )
			$trace->setWallet(\sanitize_key($_GET['w']));
		$response['balance'] = $trace->readFormated($start, $end->add(new \DateInterval('P1D')));

		$rows = '';
		foreach( $response['balance'] as $row )
		{
			$rows .= <<<EOT
<div class='value date{$class}'>{$row->formatedDate}</div>
<div class='value reason{$class}'>{$row->reason}</div>
<div class='value move{$class}'>{$row->formatedMove}</div>
<div class='value total{$class}'>{$row->formatedTotal}</div>
EOT;
		}

		$labels = array(
			'title'   => __("Last operations", 'woovirtualwallet-lite'),
			'date'    => __("Date", 'woovirtualwallet-lite'),
			'reason'  => __("Reason", 'woovirtualwallet-lite'),
			'move'    => __("Operation", 'woovirtualwallet-lite'),
			'total'   => __("Total", 'woovirtualwallet-lite'),
		);
		$bar = \implode('', \apply_filters('lws_woovirtualwallet_history_title_bar', array(
			"<div class='popup-title'>{$labels['title']}</div>",
			"<button class='popup-button-close lws-icon lws-icon-cross'></button>",
		), $response, $class));

		echo <<<EOT
<div class='popup-container'>
	<div class='popup-title-bar'>
		{$bar}
	</div>
	<div class='popup-content'>
		<div class='popup-grid'>
			<div class='head date'>{$labels['date']}</div>
			<div class='head date'>{$labels['reason']}</div>
			<div class='head date'>{$labels['move']}</div>
			<div class='head date'>{$labels['total']}</div>
			{$rows}
		</div>
	</div>
</div>
EOT;
		exit;
	}
}
