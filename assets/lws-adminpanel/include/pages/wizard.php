<?php
namespace LWS\Adminpanel\Pages;
if( !defined( 'ABSPATH' ) ) exit();

/** A wizard shows a fullscreen form that should help user for plugin setup.
 *
 * Dev will extends that Wizard class,
 * then declare it with the filter 'lws_adminpanel_wizards'.
 * That filter take and return an array with
 * * key: a wizard slug (usually the plugin admin page slug).
 * * value: fully qualified wizard classname (namespace\classname) extending that wizard.
 * As Wizard is only instanciated at need, it could be better using an autoload @see spl_autoload_register.
 *
 * A wizard can be run via a redirection to admin_url?page={$page}
 * where {$page} is concat of the LWS_WIZARD_SUMMONER define and the wizard specific slug.
 *
 * 3 functions MUST be overriden:
 * * getHierarchy()
 *   define the wizard page graph, chaining and forks
 * * getPage($slug, $mode)
 *   define a wizard page content, for one page in perticular (required page is deduced from hierarchy)
 * * submit($data)
 *   Called at the very end of a wizard path to apply all user inputs at once.
 *
 * Convenience and optim:
 * * getData() A very usefull function.
 *   It returns the wizard state with all user settings as an array.
 *   You can look at it anytime to define a page/title/... based on user inputs.
 *   That data array is the one given to the submit function.
 *   It is also used to test a fork in the hierarchy.
 *   step data are set in ['data'] array, each entry is a of array
 *   that represents a step with all its iterations.
 * * getRollbackData() if user clic on previous, the previous page data can be read here.
 *   You can prefill the dispalyed fields with previous data.
 * * getStepTitle() by default, look at getPage() to get the title only.
 *   A good optimization could be override that function to manage titles directly.
 *
 */
abstract class Wizard
{
	/** When user ends the wizard, that function is called with all user input as argument.
	 * @param $data (array) the result of getData()
	 * @return (string) the url the user must be redirected,
	 * if none, the redirection will be the same as cancel button. */
	abstract protected function submit(&$data);

	/** to be override.
	 *
	 * return a tree (as array of string|array) containing the full step hierarchy.
	 * A simple step will be a string value (the step slug).
	 * An array value means a fork and so is an array of branch array.
	 * Each branch array contains a 'condition' key to let us choose the path depending on getData() content.
	 * The rest are simple strings as usual.
	 * A branch without 'condition' will be the default branch, it should not be more than one through siblings.
	 *
	 * A condition will be tested against data @see getData
	 * * It is an array like WP_Query 'meta_query'.
	 * * Several conditions can be tested using subarray, they are associated with an AND operator
	 *   unless a 'relation' key is defined (accepted values are OR, AND).
	 * * a condition clause contains at least 'key' and 'value'
	 *   The key is search in the data, tested against the value with == opertor
	 *   unless a 'compare' key is set [==, !=, <, >, <=, >=, isset, not_isset, match] (match expect a regex value)
	 * * if the key is deep in the data structure set a 'path' key
	 *   a / separated string with keys to go in. Support wildchard pattern for search (like '*' or '?').
	 *   Without 'path' field is deep searched in reversed data array.
	 */
	protected function getHierarchy()
	{
		// since that function should be override, the following is only for demonstration purpose.
		return array(
			'step1',
			'step2',
			array(
				array(
					'condition' => array(
						'field' => 'choice',
						'value' => 'A',
					),
					'step3a1',
					'step3a2',
					array(
						'condition' => array(
							'relation' => 'OR',
							array(
								'field' => 'job',
								'value' => '2',
								'compare' => '<',
							),
							array(
								'field' => 'job',
								'value' => '',
							),
						),
						'step3a3a1'
					),
				),
				array(
					'step3b1'
				)
			),
			'step4',
		);
	}

	/** to be override.
	 *
	 * @param $slug the step slug as defined in hierarchy
	 * @param $mode define the purpose of the call
	 * * view: get page for display
	 * * title: get page only for title field @see getStepTitle
	 * * register: get page for user input data validation
	 */
	protected function getPage($slug, $mode='')
	{
		$pages = array(
			'title' => "Demonstration page",
			'help'  => "Override that function to provide a real content",
			'loop'  => 2, // if the step can be repeated, the actual occurence index
			'repeatable' => $slug, // a wizard portion can be repeated, name the step to go back again
			'repeat_btn_text' => "Add another things",
			'groups' => array(
				array(
					'require' => array('selector' => '#input_id_to_test', 'value'=>'required_value_to_show_the_group'), // optional, group will be hidden if given input has another value
					'fields' => array(
						// @see \LWS\Adminpanel\Pages\Group
					),
				),
			),
		);
		return $pages;
	}

	/** to be override.
	 * The main color */
	protected function getColor()
	{
		if (!isset($this->color)) {
			$this->color = '#3fa9f5';
		}
		return $this->color;
	}

	function setColor($color)
	{
		$this->color = $color;
	}
	/** to be override.
	 * The color used for already set options */
	protected function getDoneColor()
	{
		return '#1a8cdd';
	}

	/** to be override.
	 * The banner http[s] url */
	protected function getLogoURL()
	{
		return LWS_ADMIN_PANEL_URL . '/images/logo-lws.png';
	}

	protected function getLogoImg()
	{
		return sprintf("<img src='%s'>", \esc_attr($this->getLogoURL()));
	}

	/** to be override.
	 * The url when user leaves the Wizard.
	 * Default use the wizard slug as admin page.
	 * @param $cancel (bool) if user leave cancelling the wizard or should come back later at the same point. */
	protected function getCancelledURL($cancel=false)
	{
		return \add_query_arg('page', $this->slug, admin_url('admin.php'));
	}

	protected function init()
	{
		\add_action('admin_enqueue_scripts', array($this, 'scripts'));
		\add_action('admin_footer', array($this, 'errorFallback'), 9);
		\add_action('admin_footer', array($this, 'wizard'));
		\add_action('admin_bar_menu', array($this, 'topCancelButton'), 65536, 1);
	}

	/** That html bloc is usually hidden by wizard.
	 * But if something crash in a wizard, the usual form cannot be displayed and
	 * we are stuck cause of stored data reloaded again and again.
	 * This expose a button to clean that data (same as leave the wizard) */
	function errorFallback()
	{
		$text = __("If you can see this, an error occured. Click %s here %s to resolve. Then restart the wizard.", 'lws-adminpanel');
		$text = sprintf($text, "<button class='lws-wizard-action-cancel' name='submit' type='submit' value='cancel'>", "</button>");
		$formAttrs = '';
		foreach( $this->getFormAttributes() as $attr => $val )
			$formAttrs .= sprintf(' %s="%s"', $attr, \esc_attr($val));
		$nonce = \wp_nonce_field('lws-wizard-'.$this->slug, '_wpnonce', true, false);
		echo "<form $formAttrs>{$nonce}<p style='margin-left:auto;margin-right:0px;width:50%;'>{$text}</p></form>";
	}

	function topCancelButton($wp_admin_bar)
	{
		$args = array(
			'id' => 'lws-wizard-cancel',
			'title' => sprintf('<span class="label">%s</span> %s', __("Leave the wizard", 'lws-adminpanel'), \lws_get_tooltips_html(__("You can come back later and continue from that point."))),
			'href' => $this->getCancelledURL(),
		);
		$wp_admin_bar->add_node($args);
	}

	function __construct($slug)
	{
		$this->slug = $slug;
		$this->init();
	}

	function scripts()
	{
		\wp_enqueue_script('lws-adm-wizard-style', LWS_ADMIN_PANEL_JS.'/controls/wizard.js', array('jquery'), LWS_ADMIN_PANEL_VERSION);
		\wp_enqueue_style('lws-adm-wizard-style', LWS_ADMIN_PANEL_CSS.'/controls/wizard.css', array('lws-icons'), LWS_ADMIN_PANEL_VERSION);
		\wp_enqueue_style('lws-wizard-style', LWS_ADMIN_PANEL_CSS.'/wizard.min.css', array('lws-icons'), LWS_ADMIN_PANEL_VERSION);
		//$rootColors = sprintf(':root{--lws-wizard-main-color: %s;--lws-wizard-done-color: %s;}', $this->getColor(), $this->getDoneColor());
		//\wp_add_inline_style('lws-adm-wizard-style', $rootColors);
		//\wp_add_inline_style('lws-wizard-style', $rootColors);
	}

	/** Should be override for optimization.
	 * Actual implementation get the full page description
	 * to only extract the title if any.
	 * @return the step title */
	protected function getStepTitle($slug)
	{
		if( $page = $this->getPage($slug, 'title') )
			return isset($page['title']) ? $page['title'] : $slug;
		return $slug;
	}

	function resetData()
	{
		if( isset($this->data) )
			$this->data = array();
		\update_option('lws-wizard-state-'.$this->slug, array());
	}

	/** to be override. @return the head title */
	protected function getTitle()
	{
		return 'LongWatchStudio Wizard';
	}

	/** Callable anytime to check wizard state and user settings.
	 * @return the wizard state, user inputs and so on */
	public function getData()
	{
		if( !isset($this->data) )
		{
			$this->repeat = false;
			$this->lastStep = '';
			$this->timestamp = '';
			$this->requested = isset($_GET['step']) ? \sanitize_key($_GET['step']) : false;

			$this->data = \get_option('lws-wizard-state-'.$this->slug, array());
			if( !is_array($this->data) )
				$this->data = array();
			if( isset($this->data['rollback']) )
			{
				$this->rollbackData = $this->data['rollback'];
				unset($this->data['rollback']);
			}

			$submit = $this->getSubmittedData();

			if( $data = $this->doCustomAction($this->data, $submit) )
			{
				$this->data = $data;
				\update_option('lws-wizard-state-'.$this->slug, $this->data);
			}
			else if( $this->getAction() == 'cancel' )
			{
				$this->doCancel();
			}
			else if( $this->getAction() == 'previous' )
			{
				$this->doPrevious();
			}
			else if( $this->isStorageAction() )
			{
				$this->doValidAndGoOn($submit);
			}
			else if( $this->requested )
			{
				$this->doRequestPage($this->requested);
			}
		}
		return $this->data;
	}

	/** rewind to a visited page
	 * @param $requested (string) a page slug along the critical path */
	protected function doRequestPage($requested)
	{
		if( isset($this->data['data']) )
		{
			$keys = array_keys($this->data['data']);
			while( !empty($this->data['data']) && end($keys) != $requested )
			{
				array_pop($keys);
				array_pop($this->data['data']);
			}

			if( !empty($this->data['data']) )
			{
				$this->repeat = true;
				$keys = array_keys($this->data['data']);
				$last = end($keys);
				$this->rollbackData = $this->data['rollback'] = array_pop($this->data['data'][$last]);
				if( empty($this->data['data'][$last]) )
				{
					$this->repeat = false;
					array_pop($this->data['data']);
				}
			}
			\update_option('lws-wizard-state-'.$this->slug, $this->data);
		}
	}

	/** Override that method to check user input before continue on next page.
	 * @param $step (string) the page slug
	 * @param $submit (IN/OUT array) user input to test. Since passed by ref, you can strip some value in the same time.
	 * @return (bool|string|array) true if ok.
	 * On problem one (string) or several (array of string) reasons that will be shown to the user. */
	function isValid($step, &$submit)
	{
		return true;
	}

	/** After a page form submit, grab data and continue on the critical path.
	 * @param $submit (array) last page data @see getSubmittedData */
	protected function doValidAndGoOn($submit)
	{
		if( true === ($err = $this->isValid($this->lastStep, $submit)) )
		{
			$this->repeat = ($this->getAction() == 'repeat');
			// save last step user inputs
			$this->data['data'][$this->lastStep][$this->timestamp] = $submit;

			if( $this->lastStep && $this->getAction() == 'submit' )
			{
				$url = $this->submit($this->data);
				if( !$url || true === $url )
					$url = $this->getCancelledURL(true);
				\update_option('lws-wizard-state-'.$this->slug, array());
				\wp_redirect($url);
				exit;
			}
			\update_option('lws-wizard-state-'.$this->slug, $this->data);
		}
		else
		{
			if( !$err )
				$this->lastError = array(__("An error occured during form validation.", 'lws-adminpanel'));
			else if( is_array($err) )
				$this->lastError = $err;
			else
				$this->lastError = array($err);

			// restore user input even if some fields are wrong, do not force him to redo from scratch
			$this->data['data'][$this->lastStep][$this->timestamp] = $submit;
			$this->data['rollback'] = $this->rollbackData = $submit;
			$this->repeat = true;
		}
	}

	/** rewind last data page */
	protected function doPrevious()
	{
		// remove last step backup
		if( isset($this->data['data']) && !empty($this->data['data']) )
		{
			$this->repeat = true;
			$keys = array_keys($this->data['data']);
			$last = end($keys);
			$this->rollbackData = $this->data['rollback'] = array_pop($this->data['data'][$last]);
			if( empty($this->data['data'][$last]) )
			{
				$this->repeat = false;
				array_pop($this->data['data']);
			}
			\update_option('lws-wizard-state-'.$this->slug, $this->data);
		}
	}

	/** close the wizard and redirect to a standard admin screen. */
	protected function doCancel()
	{
		// clean state backup and redirect to usual admin
			\update_option('lws-wizard-state-'.$this->slug, array());
			\wp_redirect($this->getCancelledURL(true));
			exit;
	}

	/** if a extended class add button, manage specific behavior here.
	 * @return false for nothing or updated $data to ignore usual behavior. */
	protected function doCustomAction(&$data, &$submit)
	{
		return false;
	}

	/** If user clicked on previous, the step data can be read here. */
	protected function getRollbackData($default=array())
	{
		$this->getData();
		return isset($this->rollbackData) ? $this->rollbackData : $default;
	}

	protected function getSubmittedData()
	{
		$form = array();
		if( isset($_POST['submit']) && isset($_POST['step']) && isset($_POST['timestamp']) && \check_admin_referer('lws-wizard-'.$this->slug, '_wpnonce') )
		{
			$this->lastStep = \sanitize_key($_POST['step']);
			$this->timestamp = \sanitize_key($_POST['timestamp']);
			$form = $_POST;
			// ... do not get all $_POST, but look a getPage($this->lastStep, 'register') to get only relevent values
		}
		return $form;
	}

	protected function getAction()
	{
		if( !isset($this->action) )
			$this->action = isset($_POST['submit']) ? \sanitize_key($_POST['submit']) : false;
		return $this->action;
	}

	protected function isStorageAction()
	{
		return in_array($this->getAction(), array('submit', 'next', 'repeat'));
	}

	/** @return array of path item. Keys are wizard page slugs.
	 * An item is an array with:
	 * * label
	 * * state in [done, current, futur]
	 * * loop (optional) integer if page was repeated */
	protected function getCriticalPath()
	{
		$hierarchy = $this->getHierarchy();
		$this->getData(); // $this->data
		$done = array();
		if( isset($this->data['data']) )
			$done =& $this->data['data'];

		// look for where we are in hierarchy
		$index = 0;
		if( !empty($done) )
		{
			$keys = array_keys($done);
			$step = end($keys);
			while( $index < count($hierarchy) )
			{
				if( $step == $this->fork($index, $hierarchy) )
				{
					++$index;
					break;
				}
				else
					++$index;
			}
		}

		$path = array();
		// look for what is past
		foreach( $done as $step => $iterations )
		{
			$path[$step] = array(
				'label' => $this->getStepTitle($step),
				'state' => 'done',
				'loop'  => count($iterations),
			);
		}

		$state = 'current';
		if( !empty($path) && $this->repeat )
		{
			$keys = array_keys($path);
			$step = end($keys);
			$path[$step]['loop']++;
			$path[$step]['state'] = 'current';
			$state = 'futur';
		}

		while( $index < count($hierarchy) )
		{
			$step = $this->fork($index, $hierarchy);
			$path[$step] = array(
				'label' => $this->getStepTitle($step),
				'state' => $state,
			);
			$state = 'futur';
			++$index;
		}

		return $path;
	}

	/** flat array and recurse until $hierarchy[$index] is only a string */
	protected function fork($index, &$hierarchy)
	{
		if( is_array($hierarchy[$index]) )
		{
			$choice = false;
			foreach( $hierarchy[$index] as $branch )
			{
				if( isset($branch['condition']) )
				{
					if( $this->test($branch['condition']) )
					{
						$choice = $branch;
						break;
					}
				}
				else if( false === $choice )
					$choice = $branch;
			}

			if( false === $choice )
				$choice = reset($hierarchy[$index]);

			if( isset($choice['condition']) )
				unset($choice['condition']);

			$hierarchy = array_merge(array_slice($hierarchy, 0, $index), $choice, array_slice($hierarchy, $index+1));

			if( is_array($hierarchy[$index]) )
				$this->fork($index, $hierarchy);
		}
		return $hierarchy[$index];
	}

	public function getValue(&$data, $field, $path=false, $default=null)
	{
		$exists = false;
		if( false !== $path && !is_array($path) )
			$path = explode('/', $path);
		$value = $this->getDataValue($data, $field, $path, $exists);
		if( null !== $default)
		{
			if( !$exists || (is_string($value) && !strlen(trim($value))) || !$value )
				$value = $default;
		}
		return $value;
	}

	/** @param $field (sring)
	 *  @param $path (array|false)
	 *  @param $exists (ref bool) out
	 *  @return the value in $this->data or null if not found. */
	public function getDataValue(&$data, $field, $path, &$exists)
	{
		$exists = false;
		if( is_array($path) )
		{
			if( empty($path) ) // last path level
			{
				if( isset($data[$field]) )
				{
					$exists = true;
					return $data[$field];
				}
				else
					return null;
			}
			else // dig
			{
				$dir = array_shift($path);
				foreach( array_reverse($data) as $key => &$subdata )
				{
					if( is_array($subdata) && \fnmatch($dir, $key) )
					{
						$subexists = false;
						$value = $this->getDataValue($subdata, $field, $path, $subexists);
						if( $subexists )
						{
							$exists = true;
							return $value;
						}
					}
				}
				return null;
			}
		}

		// recursive search
		if( isset($data[$field]) )
		{
			$exists = true;
			return $data[$field];
		}
		else foreach( array_reverse($data) as $key => $subdata )
		{
			if( is_array($subdata) )
			{
				$subexists = false;
				$value = $this->getDataValue($subdata, $field, $path, $subexists);
				if( $subexists )
				{
					$exists = true;
					return $value;
				}
			}
		}

		return null;
	}

	/** test a hierarchy fork condition against data */
	protected function test($condition)
	{
		if( !is_array($condition) )
			return false;
		if( isset($condition['field']) )
		{
			$value = isset($condition['value']) ? $condition['value'] : '';
			$exists = false;
			$data = $this->getDataValue($this->data, $condition['field'], isset($condition['path']) ? explode('/', $condition['path']) : false, $exists);
			switch(isset($condition['compare']) ? strtolower($condition['compare']) : '==')
			{
				case '==':
					return $exists && $value == $data;
				case '!=':
					return $exists && $value != $data;
				case '<':
					return $exists && $value < $data;
				case '>':
					return $exists && $value > $data;
				case '<=':
					return $exists && $value <= $data;
				case '>=':
					return $exists && $value >= $data;
				case 'match':
					return $exists && \preg_match($value, $data);
				case 'isset':
					return $exists;
				case 'not_isset':
					return !$exists;
				default:
					error_log("Wrong Wizard hierarchy condition 'compare' operator. Accept [==, !=, <, >, <=, >=, isset, not_isset, match] only");
					return false;
			}
		}
		else
		{
			// recursive
			$op = 'AND';
			if( isset($condition['relation']) )
			{
				$op = strtoupper(trim($condition['relation']));
				unset($condition['relation']);
			}
			if( !in_array($op, array('OR', 'AND')) )
			{
				error_log("Wrong Wizard hierarchy condition 'relation' operator. Accept [AND, OR] only");
				return false;
			}
			if( empty($condition) )
				return true;

			$ok = ($op == 'AND');
			foreach( $condition as $sub )
			{
				if( $op == 'AND' )
				{
					if( !$this->test($sub) )
					{
						$ok = false;
						break;
					}
				}
				else // OR
				{
					if( $this->test($sub) )
					{
						$ok = true;
						break;
					}
				}
			}
			return $ok;
		}
		return false;
	}

	/** wizard form attributes as array */
	protected function getFormAttributes()
	{
		return array(
			'method' => 'post',
			'action' => \add_query_arg(
				array(
					'page'=>LWS_WIZARD_SUMMONER.$this->slug,
				),
				admin_url('admin.php')
			),
		);
	}

	protected function getHead()
	{
		$img = $this->getLogoImg();
		$title = $this->getTitle();
		return <<<EOT
<div class='top-container'>
	<div class='logo'>
		{$img}
	</div>
	<div class='title'>
		{$title}
	</div>
</div>
EOT;
	}

	protected function getPathTabs($criticalPath)
	{
		$tabs = array();
		foreach($criticalPath as $step => $item)
		{
			if( !is_array($item) )
				$item = array('label' => $item);

			if( isset($item['label']) && $item['label'] )
			{
				$state = 'futur';
				if( isset($item['state']) )
					$state = $item['state'];

				$tag = 'div';
				$attr = '';
				if( $state == 'done' )
				{
					$tag = 'a';
					$attr = sprintf(
						" href='%s'",
						\esc_attr(\add_query_arg(
							array(
								'page'=>LWS_WIZARD_SUMMONER.$this->slug,
								'step'=>$step,
							),
							admin_url('admin.php')
						))
					);
				}

				if( isset($item['loop']) && ($loop = intval($item['loop'])) > 1 )
					$item['label'] = sprintf(_x('%s (%s)', 'wizard critical path tab title with loop count', 'lws-adminpanel'), $item['label'], $loop);

				$tabs[$step] = <<<EOT
<{$tag} class='item {$state}'{$attr}>
	<div class='line'></div>
	<div class='text'>{$item['label']}</div>
</{$tag}>
EOT;
			}
		}
		return $tabs;
	}

	protected function findCurrentStep($criticalPath)
	{
		$loop = 1;
		$step = '';
		foreach($criticalPath as $k => $item)
		{
			if( is_array($item) )
			{
				if( isset($item['state']) )
				{
					if( $item['state'] == 'current' )
					{
						$step = $k;
						break;
					}
					else if( !$step && !$item['state'] )
						$step = $k;
				}
				else if( !$step )
					$step = $k;
			}
			else if( !$step )
				$step = $k;
		}

		if( isset($criticalPath[$k]) && is_array($criticalPath[$k]) && isset($criticalPath[$k]['loop']) )
			$loop = intval($criticalPath[$k]['loop']);
		return array($step, $loop);
	}

	function wizard()
	{
		$this->getData();
		$criticalPath = $this->getCriticalPath();
		list($step, $loop) = $this->findCurrentStep($criticalPath);
		$page = $this->getPage($step, 'view');

		$head = $this->getHead();
		$tabs = implode('', $this->getPathTabs($criticalPath));

		$formAttrs = '';
		foreach( $this->getFormAttributes() as $attr => $val )
			$formAttrs .= sprintf(' %s="%s"', $attr, \esc_attr($val));
		$nonce = \wp_nonce_field('lws-wizard-'.$this->slug, '_wpnonce', true, false);
		$eStep = \esc_attr($step);
		$time = \esc_attr(\microtime());
		$color = $this->getColor();
		$colorstring = \lws_get_theme_colors('--wizard-color', $color);
		echo <<<EOT
<div class="lws_wizard" style="$colorstring">
	<form $formAttrs>
		<input type='hidden' name='step' value='{$eStep}'>
		<input type='hidden' name='loop' value='{$loop}'>
		<input type='hidden' name='timestamp' value='{$time}'>
		{$nonce}
		<div class="big-container">
			{$head}
			<div class='progress-container'>
				{$tabs}
			</div>
EOT;

		if( $title = isset($page['title']) ? trim($page['title']) : '' )
			$title = "<div class='form-title'>{$title}</div>";
		if( $help = isset($page['help']) ? trim($page['help']) : '' )
			$help = "<div class='form-help'>{$help}</div>";
		if( isset($this->lastError) && $this->lastError )
		{
			$err = implode('</li><li>', $this->lastError);
			$help .= "<div class='form-help form-error'><ul><li>{$err}</li></ul></div>";
		}

		echo <<<EOT
			<div class='main-container' data-step='{$eStep}' data-occurrence='$loop'>
				<div class='form-title-line'>{$title}{$help}</div>
EOT;

		if( isset($page['groups']) )
		{
			require_once LWS_ADMIN_PANEL_INCLUDES.'/pages/field.php';
			\do_action('lws_adminpanel_enqueue_lac_scripts', array('select', 'input', 'checklist', 'taglist'));
			$this->groups($page['groups']);
		}

		$buttons = implode('', $this->getButtons($criticalPath, $step, $page));
		$foot = $this->getFoot();
		echo <<<EOT
				<div class='action-line'>
					{$buttons}
				</div>
			</div>
			{$foot}
		</div>
	</form>
</div>
EOT;
	}

	/** @param $depth (int) group can contain groups, set the group depth (default 0)
	 *  @param $groups array of group.
	 *	A group is an array expecting following entries:
	 * * fields (array) for user input controls
	 * * groups (array) of group (recursivity)
	 * * require (array) the group is hidden until condition is fulfil
	 *		format is ['selector'=>(jQuery selector), 'value'=>(expected input value)]
	 * * class (string) css classes, actually supported:
	 * * * large : omit left title cell, use full width for user input controls
	 * * * horizontal : child groups will use a horizontal layout (defaut is vertical)
	 **/
	protected function groups(&$groups, $depth=0)
	{
		foreach( $groups as $group )
		{
			$style='';
			$vStyle='';
			$class = 'form-grid group-depth-'.$depth;
			if( isset($group['class']) )
			{
				$class .= ' ';
				$class .= \esc_attr($group['class']);
			}
			if( isset($group['columns']) )
			{
				$style .= ' ';
				$style .= 'grid-template-columns:'.\esc_attr($group['columns']).';';
			}
			if( isset($group['value-columns']) )
			{
				$vStyle .= 'grid-template-columns:'.\esc_attr($group['value-columns']).';';
			}
			$attributes = '';
			if( isset($group['require']) )
			{
				$class .= ' lws_wizard_require lws_wizard_hidden_group';
				$s = \esc_attr($group['require']['selector']);
				$c = isset($group['require']['cmp']) ? \esc_attr($group['require']['cmp']) : '==';
				$v = \esc_attr($group['require']['value']);
				$attributes = " data-selector='{$s}' data-value='{$v}' data-operator='{$c}'";
			}
			if( isset($group['groups']) && count($group['groups']) )
				$class .= ' parent-group';

			echo "<div class='{$class}'$attributes style='{$style}'>";

			if( isset($group['title']) && ($title = trim($group['title'])) )
			{
				echo "<div class='group-title'>{$title}</div>";
			}

			if( isset($group['groups']) && count($group['groups']) )
			{
				$this->groups($group['groups'], $depth+1);
			}
			else if( isset($group['fields']) )
			{
				foreach( $group['fields'] as $row )
				{
					$field = \LWS\Adminpanel\Pages\Field::create(strtolower($row['type']), $row['id'], isset($row['title'])?$row['title']:'', isset($row['extra'])?$row['extra']:array());
					if( $field )
					{
						if( isset($this->data['rollback']) && $this->data['rollback'] && isset($this->data['rollback'][$field->id()]) )
						{
							if( !isset($field->extra['value']) )
								$field->extra['value'] = $this->data['rollback'][$field->id()];
						}

						if( !$field->isHidden() )
						{
							$tooltip = '';
							if (!empty($help = $field->help())) {
								echo "<div class='item-help'><div class='icon lws-icons lws-icon-bulb'></div><div class='text'>{$help}</div></div>";
								$tooltip = "<div class='toggle-help'>?</div>";
							}

							$title = $field->title();
							echo "<div class='item-label'>{$title}{$tooltip}</div><div class='item-value' style='{$vStyle}'>";
							$field->input();
							echo "</div>";
						}
						else
							$field->input();
					}
				}
			}

			echo "</div>";
		}
	}

	protected function getButtons(&$criticalPath, $step, &$page)
	{
		$buttons = array();
		$curIndex = array_search($step, array_keys($criticalPath));
		if( false === $curIndex )
			$curIndex = count($criticalPath);

		if( $curIndex > 0 )
		{
			$button = _x("Previous", 'previous wizard step', 'lws-adminpanel');
			$buttons['previous'] = <<<EOT
	<button class='button back' name='submit' type='submit' value='previous'>
		<div class='icon lws-icon lws-icon-arrow-left'></div>
		<div class='label'>{$button}</div>
	</button>
EOT;
		}

		if( isset($page['repeatable']) && $page['repeatable'] )
		{
			$button = (isset($page['repeat_btn_text']) && $page['repeat_btn_text']) ? $page['repeat_btn_text'] : _x("Add", 'repeat a wizard portion', 'lws-adminpanel');
			$buttons['repeat'] = <<<EOT
	<button class='button redo' name='submit' type='submit' value='repeat'>
		<div class='icon lws-icon lws-icon-repeat'></div>
		<div class='label'>{$button}</div>
	</button>
EOT;
		}

		$button =  _x("Next", 'next wizard step', 'lws-adminpanel');
		$value = 'next';
		if( ++$curIndex >= count($criticalPath) )
		{
			$button = _x("Submit", 'final wizard submit', 'lws-adminpanel');
			$value = 'submit';
		}
		$buttons['next'] = <<<EOT
	<button class='button next' name='submit' type='submit' value='{$value}'>
		<div class='label'>{$button}</div>
		<div class='icon lws-icon lws-icon-arrow-right'></div>
	</button>
EOT;
		return $buttons;
	}

	protected function getFoot()
	{
		$href = $this->getCancelledURL();
		$leave = __("Leave this wizard", 'lws-adminpanel');
		$leavetip = \lws_get_tooltips_html(__("You can come back later and continue from that point.", 'lws-adminpanel'));
		$cancel = __("Cancel this wizard", 'lws-adminpanel');
		$canceltip = \lws_get_tooltips_html(__("You will lose all prepared settings.", 'lws-adminpanel'));
		return <<<EOT
<div class='cancel-container'>
	<button class='button cancel' name='submit' type='submit' value='cancel'>
		<div class='icon lws-icon lws-icon-cross'></div>
		<div class='text'>{$cancel}</div>
		{$canceltip}
	</button>
	<a class='button leave' href='{$href}'>
		<div class='icon lws-icon lws-icon-leave'></div>
		<div class='text'>{$leave}</div>
		{$leavetip}
	</a>
</div>
EOT;
	}
}
