<?php
namespace LWS\WOOVIRTUALWALLET\Ui;

// don't call the file directly
if( !defined( 'ABSPATH' ) ) exit();

/** Manage users virtual wallet amounts in Admin screen. */
class Users
{
	protected $options = array();

	function __construct(array $options = array())
	{
		$this->readOptions($options);

		\add_filter('manage_users_columns', array($this, 'header'), 20);
		\add_filter('manage_users_custom_column', array($this, 'amount'), 10, 3);
		\add_action('manage_users_extra_tablenav', array($this, 'amountAction'), 10, 1);
		\add_action('init', array($this, 'handleAmountChange'), 9);

		static $once = true;
		if( $once ){
			$once = false;
			\add_action('admin_enqueue_scripts', array($this, 'scripts'));
		}
	}

	protected function readOptions($options)
	{
		$this->options = (object)array_merge(
			array(
				'wallet' => false,
				'column' => 'lws_woovirtualwallet_amount',
				'head'   => __("Virtual Wallet", 'woovirtualwallet-lite'),
				'button' => __("Add to Virtual Wallets", 'woovirtualwallet-lite'),
				'sanitize' => '\floatval',
			),
			$options
		);
		if( !$this->options->wallet )
			$this->options->wallet = \LWS\WOOVIRTUALWALLET\Core\Wallet::instanciate();
	}

	function scripts($hook)
	{
		if( $hook == 'users.php' )
		{
			\wp_enqueue_script('lws-wvw-admin-users-script', LWS_WOOVIRTUALWALLET_JS.'/admin-users.js', array('jquery'), LWS_WOOVIRTUALWALLET_VERSION, true);
			\wp_enqueue_style('lws-wvw-admin-users-style', LWS_WOOVIRTUALWALLET_CSS.'/admin-users.css', array(), LWS_WOOVIRTUALWALLET_VERSION);
		}
	}

	function header($column)
	{
		if( \current_user_can('read_other_virtual_wallet') )
			$column[$this->options->column] = $this->options->head;
		return $column;
	}

	function amount($val, $column_name, $userId)
	{
		if( \current_user_can('read_other_virtual_wallet') )
		{
			if( !isset($this->popupArgs) )
			{
				$action = 'lws_woovirtualwallet_balance';
				$this->popupArgs = $this->arrayToDataArgs(array(
					'url'    => \esc_attr(\admin_url('admin-ajax.php')),
					'action' => $action,
					'nonce'  => \esc_attr(\wp_create_nonce($action)),
					'date'   => \date_create()->format('Y-m-d'),
					'period' => \get_option('lws_woovirtualwallet_admin_users_popup_period', 'P3M'),
					'wallet' => $this->options->wallet->getName(),
				));
			}

			switch($column_name)
			{
				case $this->options->column :
					return sprintf(
						"<a href='#' class='lws_wvw_user_hist_popup' data-user='%d' %s>%s</a>",
						$userId,
						$this->popupArgs,
						$this->options->wallet->createFor($userId)->getDisplayAmount()
					);
				break;
			default:
			}
		}
		return $val;
	}

	protected function arrayToDataArgs($array)
	{
		array_walk($array, function(&$v, $k){$v = "data-{$k}='$v'";});
		return implode(' ', $array);
	}

	function handleAmountChange()
	{
		$bk = $this->getButtonId('');
		$bk2 = $this->getButtonId('2');
		$btn = isset($_REQUEST[$bk]) && !empty($_REQUEST[$bk]);
		$btn2 = isset($_REQUEST[$bk2]) && !empty($_REQUEST[$bk2]);
		if( !($btn || $btn2) )
			return;

		if( !\current_user_can('edit_other_virtual_wallet') )
		{
			\lws_admin_add_notice_once('wvw-users-cannot', __("You don't have enough permissions to edit users wallets.", 'woovirtualwallet-lite'), array('level'=>'error'));
			return;
		}

		$kSum = $this->getInputId('amount', $btn ? '' : '2');
		$sum = isset($_REQUEST[$kSum]) ? \sanitize_text_field($_REQUEST[$kSum]) : false;
		if( !$sum )
		{
			\lws_admin_add_notice_once('wvw-amount-change', __("Please, set an amount to add in each selected user's wallet.", 'woovirtualwallet-lite'), array('level'=>'info'));
			return;
		}

		$users = (isset($_REQUEST['users']) && is_array($_REQUEST['users'])) ? array_map('intval', $_REQUEST['users']) : array();
		if( !$users )
		{
			\lws_admin_add_notice_once('wvw-amount-change', __("Please select users.", 'woovirtualwallet-lite'), array('level'=>'warning'));
			return;
		}

		$sign = substr($sum, 0, 1);
		if( !in_array($sign, array('+', '-', '=')) )
			$sign = '+';
		else
			$sum = ltrim(substr($sum, 1));
		$sum = str_replace(',', '.', $sum);

		if( !is_numeric($sum) )
		{
			\lws_admin_add_notice_once('wvw-amount-change', __("A wallet amount must be a number.", 'woovirtualwallet-lite'), array('level'=>'warning'));
			return;
		}
		$sum = max(call_user_func($this->options->sanitize, $sum), 0.0);
		$reason = \LWS\WOOVIRTUALWALLET\Core\Trace::origin('admin')->setProvider(\get_current_user_id());
		$reason->setWallet($this->options->wallet->getName());
		$kReason = $this->getInputId('reason', $btn ? '' : '2');
		$txt = isset($_REQUEST[$kReason]) ? \sanitize_text_field($_REQUEST[$kReason]) : false;
		if ($txt)
			$reason->setReason($txt);
		else
			$reason->setReason("Commercial operation", 'woovirtualwallet-lite');

		foreach($users as $userId)
		{
			$wallet = $this->options->wallet->createFor($userId);
			if( $sign == '=' )
				$wallet->setAmount($sum, $reason);
			else if( $sign == '-' )
				$wallet->subAmount($sum, $reason);
			else
				$wallet->addAmount($sum, $reason);
		}

		// finalise
		\lws_admin_add_notice_once('wvw-amount-change', sprintf(__("%d users wallets amounts modified.", 'woovirtualwallet-lite'), count($users)), array('level'=>'success'));
		$uri = \add_query_arg('update', 'users_updated', \remove_query_arg(array($bk, $bk2), false));
		\wp_redirect($uri);
		exit;
	}

	protected function getInputId($field, $suffix)
	{
		return ('lws-wvw-' . $field . '-input-' . $this->options->wallet->getName() . $suffix);
	}

	protected function getButtonId($suffix)
	{
		return ('lws-wvw-amount-add-' . $this->options->wallet->getName() . $suffix);
	}

	function amountAction($which)
	{
		if( !\current_user_can('edit_other_virtual_wallet') )
			return;

		$suffix      = 'bottom' === $which ? '2' : '';
		$label       = \htmlentities($this->options->button);
		$placeholder = array(
			'amount' => \esc_attr(__('Amount', 'woovirtualwallet-lite')),
			'reason' => \esc_attr(__('Reason', 'woovirtualwallet-lite')),
		);
		$btn         = \get_submit_button($this->options->button, '', $this->getButtonId($suffix), false);
		$input       = array(
			'amount' => \esc_attr($this->getInputId('amount', $suffix)),
			'reason' => \esc_attr($this->getInputId('reason', $suffix)),
		);

		echo <<<EOT
<div class='alignleft actions'>
	<label class='screen-reader-text' for='lws-wvw-amount-input{$suffix}'>{$label}</label>
	<input id='{$input['amount']}' name='{$input['amount']}' type='text' placeholder='{$placeholder['amount']}' size='4'>
	<input id='{$input['reason']}' name='{$input['reason']}' type='text' placeholder='{$placeholder['reason']}'>
	{$btn}
</div>
EOT;
	}

	/** Never call, only to have poedit/wpml able to extract the sentance. */
	private function poeditDeclare()
	{
		__("Commercial operation", 'woovirtualwallet-lite');
	}
}
