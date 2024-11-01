<?php
namespace LWS\WOOVIRTUALWALLET;

// don't call the file directly
if( !defined( 'ABSPATH' ) ) exit();

/** satic class to manage activation and version updates. */
class Updater
{
	/** @return array[ version => changelog ] */
	function getNotices()
	{
		$notes = array();

		$notes['1.0'] = <<<EOT
<b>WooVirtualWallet 1.0</b><br/>
<p>Initial release.</p>
<ul>
	<li><b>Payment gateway :</b> ...</li>
</ul>
EOT;

		return $notes;
	}

	static function checkUpdate()
	{
		$wpInstalling = \wp_installing();
		\wp_installing(true); // should force no cache

		if( version_compare(($from = get_option('lws_woovirtualwallet_version', '0')), ($to = LWS_WOOVIRTUALWALLET_VERSION), '<') )
		{
			\wp_suspend_cache_invalidation(false);
			$me = new self();
			$me->update($from, $to);
			$me->notice($from, $to);
		}

		\wp_installing($wpInstalling);
	}

	function notice($fromVersion, $toVersion)
	{
		if( version_compare($fromVersion, '1.0', '>=') )
		{
			$notices = $this->getNotices();
			$text = '';
			foreach($notices as $version => $changelog)
			{
				if( version_compare($fromVersion, $version, '<') && version_compare($version, $toVersion, '<=') ) // from < v <= new
					$text .= "<p>{$changelog}</p>";
			}
			if( !empty($text) )
				\lws_admin_add_notice('woovirtualwallet-lite'.'-changelog-'.$toVersion, $text, array('level'=>'info', 'forgettable'=>true, 'dismissible'=>true));
		}
	}

	/** Update
	 * @param $fromVersion previously registered version.
	 * @param $toVersion actual version. */
	function update($fromVersion, $toVersion)
	{
		$this->from = $fromVersion;
		$this->to = $toVersion;

		$this->database();

		if( empty($fromVersion) || \version_compare($fromVersion, '1.0.0', '<') )
		{
			$this->addCapacity();
			if( \get_option('lws_woovirtualwallet_redirect_to_licence', 0) != -100 )
				\update_option('lws_woovirtualwallet_redirect_to_licence', 1);

			$pos = \get_option('woocommerce_currency_pos', 'left');
			\update_option('lws_woovirtualwallet_currency_format', $pos);
			\update_option('lws_woovirtualwallet_amount_rounding', \absint(\get_option('woocommerce_price_num_decimals', 2)));
		}

		update_option('lws_woovirtualwallet_version', LWS_WOOVIRTUALWALLET_VERSION);
	}

	protected function database()
	{
		global $wpdb;
		$charset_collate = $wpdb->get_charset_collate();

		$history = <<<EOT
CREATE TABLE `{$wpdb->lwsUserWalletHistory}` (
	`id` BIGINT(20) NOT NULL AUTO_INCREMENT,
	`op_date` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Date of operation',
	`user_id` BIGINT(20) NOT NULL,
	`op_amount` DECIMAL(20, 6) NULL DEFAULT NULL COMMENT 'Amount add or substracted, NULL if total value is forced',
	`op_result` DECIMAL(20, 6) NULL DEFAULT NULL COMMENT 'Operation result',
	`reason` TEXT NOT NULL DEFAULT '' COMMENT 'Human readable reason',
	`origin` TINYTEXT NOT NULL DEFAULT '' COMMENT 'An origin mark, the object/class that triggers the operation (max 255)',
	`blog_id` INT(20) NULL DEFAULT NULL COMMENT 'For multisite, the current blog during operation',
	`provider_id` INT(20) NULL DEFAULT NULL COMMENT 'post or user id, depends on origin',
	`order_id` INT(20) NULL DEFAULT NULL COMMENT 'If about a shop_order = post.ID',
	`wallet` VARCHAR(32) NOT NULL DEFAULT 'wallet' COMMENT 'If a user has several wallet',
	PRIMARY KEY `id`  (`id`),
	KEY `user_id` (`user_id`),
	KEY `wallet` (`wallet`)
	) {$charset_collate};
EOT;

		require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
		$this->grabLog();
		dbDelta($history);
		$this->releaseLog();
	}

	/// dbDelta could write on standard output @see releaseLog()
	protected function grabLog()
	{
		ob_start(function($msg){
			if( !empty($msg) )
				error_log($msg);
		});
	}

	/// @see grabLog()
	protected function releaseLog()
	{
		ob_end_flush();
	}

	/** Add 'edit_virtual_wallet_options', 'edit_other_virtual_wallet' and 'read_other_virtual_wallet' capacity to 'administrator' and 'shop_manager'. */
	private function addCapacity()
	{
		$roles = array(
			'administrator' => array('edit_virtual_wallet_options', 'edit_other_virtual_wallet', 'read_other_virtual_wallet'),
			'shop_manager' => array('edit_virtual_wallet_options', 'read_other_virtual_wallet'),
		);
		foreach( $roles as $slug => $caps )
		{
			if( $role = \get_role($slug) )
			{
				foreach( $caps as $cap )
				{
					if( !$role->has_cap($cap) )
						$role->add_cap($cap);
				}
			}
		}
	}

}
