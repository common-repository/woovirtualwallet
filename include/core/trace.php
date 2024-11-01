<?php
namespace LWS\WOOVIRTUALWALLET\Core;

// don't call the file directly
if( !defined( 'ABSPATH' ) ) exit();

/** Save wallet amount changes.
 * Contains a lot of conveniency methods.
 * All setters and trace method return the object itself to be chained.
 *
 * That class implements a just-in-time translation features.
 * Since current user langage could be different at reading time
 * than reason generation time.
 * The reading method could call the underscore translation functions at reading
 * if reason is set properly (with sentance and arg as array with a domain)
 * @see LWS\WOOVIRTUALWALLET\Core\Trace::setReason() */
class Trace
{
	public $userId     = false;
	public $referral     = ''; /// origin
	public $orderId    = null;
	public $providerId = false;
	public $blogId     = false;
	public $reason     = '';
	public $wallet     = 'wallet';

	function __construct($values=array())
	{
		$this->userId     = false;
		$this->referral   = ''; /// origin
		$this->orderId    = null;
		$this->providerId = false;
		$this->blogId     = false;
		$this->reason     = '';
		$this->wallet     = 'wallet';

		foreach( $values as $key=>$value )
		{
			switch($key)
			{
				case 'user'    : $this->setUser($value);     break;
				case 'origin'  : $this->setOrigin($value);   break;
				case 'order'   : $this->setOrder($value);    break;
				case 'provider': $this->setProvider($value); break;
				case 'blog'    : $this->setBlog($value);     break;
				case 'reason'  : $this->setReason($value);   break;
				case 'wallet'  : $this->setWallet($value);   break;
			}
		}
	}

	static function tablename()
	{
		global $wpdb;
		return $wpdb->lwsUserWalletHistory;
	}

	static function user($user)
	{
		$inst = new self();
		return $inst->setUser($user);
	}

	static function order($order)
	{
		$inst = new self();
		return $inst->setOrder($order);
	}

	static function origin($origin)
	{
		$inst = new self();
		return $inst->setOrigin($origin);
	}

	/** @see setReason() */
	static function byReason($reason, $domain='')
	{
		$inst = new self();
		return $inst->setReason($reason, $domain);
	}

	function &setUser($user)
	{
		if( \is_a($user, '\WP_User') )
			$this->userId = $user->ID;
		else
			$this->userId = intval($user);
		return $this;
	}

	function &setOrigin($origin)
	{
		$this->referral = $origin;
		return $this;
	}

	function &setWallet($wallet)
	{
		if( \is_object($wallet) && \is_a($wallet, '\LWS\WOOVIRTUALWALLET\Core\Wallet') )
			$this->wallet = $wallet->getName();
		else
			$this->wallet = $wallet;
		return $this;
	}

	function &setOrder($order)
	{
		if( \is_a($order, '\WC_Order') )
			$this->orderId = $order->get_id();
		else
			$this->orderId = intval($order);
		return $this;
	}

	function &setProvider($user)
	{
		if( \is_a($user, '\WP_User') )
			$this->providerId = $user->ID;
		else
			$this->providerId = intval($user);
		return $this;
	}

	function &setBlog($blogId)
	{
		$this->blogId = intval($blogId);
		return $this;
	}

	function getBlog()
	{
		return $this->blogId !== false ? \absint($this->blogId) : \get_current_blog_id();
	}

	/** Define the label of the operation.
	 * @param $domain (string|false) the text domain of the reason string.
	 * Allow a just-in-time translation (call __() the method @see read()).
	 * @param $reason (string|array) if array, assume as sprintf arguments.
	 * Take care to declare your string for translation somewhere anyway.
	 * Call the one of the WordPress underscore function,
	 * so PoEdit/WPML can extract it,
	 * for example in a never call part of code.
	 */
	function &setReason($reason, $domain='')
	{
		if( is_array($reason) )
		{
			if( $domain )
			{
				$reason[] = $domain;
				$reason = serialize($reason);
			}
			else
				$reason = self::reasonToString($reason, false);
		}
		else if( $domain )
		{
			$reason = serialize(array($reason, $domain));
		}
		$this->reason = $reason;
		return $this;
	}

	static function reasonToString($args, $translate=true)
	{
		$format = array_shift($args);
		if( $translate && $args )
		{
			$domain = array_pop($args);
			$format = __($format, $domain);
		}
		if( $args )
			$format = vsprintf($format, $args);
		return $format;
	}

	static function unserializeReason($raw)
	{
		$reason = @unserialize($raw);
		if( $reason && is_array($reason) )
			$raw = self::reasonToString($reason, true);
		return $raw;
	}

	function &trace($total, $oldTotal=false)
	{
		global $wpdb;
		$values = array(
			'user_id'   => $this->userId ? $this->userId : \get_current_user_id(),
			'op_result' => $total,
			'reason'    => $this->reason,
			'origin'    => $this->referral,
			'blog_id'   => $this->getBlog(),
			'wallet'    => $this->wallet,
		);
		$formats = array(
			'%d',
			'%s',
			'%s',
			'%s',
			'%d',
			'%s',
		);
		if( $oldTotal !== false )
		{
			$values['op_amount'] = $total - $oldTotal;
			$formats[] = '%s';
		}
		if( $this->orderId )
		{
			$values['order_id'] = $this->orderId;
			$formats[] = '%d';
		}
		if( $this->providerId )
		{
			$values['provider_id'] = $this->providerId;
			$formats[] = '%d';
		}

		$wpdb->insert($wpdb->lwsUserWalletHistory, $values, $formats);
		\do_action('lws_woovirtualwallet_amount_changed', $this->userId, $total, $oldTotal, $this);
		return $this;
	}

	/** @param $greaterThan (DateTime|null) starting date (included)
	 *	@param $lessThan (DateTime|null) ending date (excluded)
	 *	@param $desc (bool) true from last to oldest operation
	 *	@param $limit (int|array) int: max row count, array of int [offset, count]
	 *	@return array of operations for one user.
	 *	[{date, reason, move, total}]
	 * reason is raw @see readFormated
	 *
	 * Filter by user, date and wallet
	 * (define an empty wallet value to ignore that last filter). */
	function read($greaterThan=null, $lessThan=null, $desc=true, $limit=false)
	{
		global $wpdb;
		$request = \LWS\Adminpanel\Tools\Request::from($wpdb->lwsUserWalletHistory);
		$request->select('user_id, op_date as `date`, op_amount as `move`, op_result as `total`, reason');
		$request->order('op_date', !$desc);
		$request->order('id', !$desc);

		if ($this->userId){
			$request->where("user_id=%d")->arg($this->userId);
		}
		if ($lessThan){
			$request->where("op_date < DATE(%s)")->arg($lessThan->format('Y-m-d'));
		}
		if ($greaterThan){
			$request->where("op_date >= DATE(%s)")->arg($greaterThan->format('Y-m-d'));
		}
		if ($this->wallet){
			$request->where("wallet = %s")->arg($this->wallet);
		}
		if ($limit){
			if( \is_array($limit) )
				$request->limit($limit[0], $limit[1]);
			else
				$request->limit(0, $limit);
		}
		return $request->getResults();
	}

	/** Provided for conveniency.
	 *  Format amounts before return.
	 *  If reason was stored with a text-domain, translation fonction __()
	 *  is called just before return.
	 *  @see Trace::read()
	 *  @see \LWS_WooVirtualWallet::formatAmount
	 */
	function readFormated($greaterThan=null, $lessThan=null, $desc=true, $limit=false)
	{
		$balance = $this->read($greaterThan, $lessThan, $desc, $limit);
		if( $balance )
			self::formatResult($balance, $this->wallet);
		return $balance;
	}

	static function formatResult(&$result, $wallet='wallet')
	{
		$nc = _x("—", "Amount set by hand: move = n/c", 'woovirtualwallet-lite');
		$df = \get_option('date_format') .' '.\get_option('time_format');

		foreach($result as &$row)
		{
			$row->formatedDate = \mysql2date($df, $row->date);
			$reason = @unserialize($row->reason);
			if( $reason && is_array($reason) )
				$row->reason = self::reasonToString($reason, true);
			$row->formatedMove = null !== $row->move ? \LWS_WooVirtualWallet::helper()->formatAmount($row->move, true, true, $wallet) : $nc;
			$row->formatedTotal = \LWS_WooVirtualWallet::helper()->formatAmount($row->total, true, true, $wallet);
		}
	}
}
