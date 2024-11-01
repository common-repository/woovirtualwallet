<?php
namespace LWS\Adminpanel\Internal;
if( !defined( 'ABSPATH' ) ) exit();


/** As post, display a list of item with on-the-fly edition. */
class EditlistControler
{
	private $KeyAction = 'action-uid';
	private $hasActions = false;
	private $columns = array();

	/**
	 * @param $editionId (string) is a unique id which refer to this EditList.
	 * @param $recordUIdKey (string) is the key which will be used to ensure record unicity.
	 * @param $source instance which etends EditListSource.
	 * @param $mode allows list for modification (use bitwise operation, @see ALL)
	 * @param $filtersAndActions an array of instance of \LWS\Adminpanel\EditList\Action or \LWS\Adminpanel\EditList\Filter. */
	public function __construct( $editionId, $recordUIdKey, $source, $mode = \LWS\Adminpanel\EditList::ALL, $filtersAndActions=array() )
	{
		$this->slug = sanitize_key($editionId);
		$this->m_Id = esc_attr($editionId);
		$this->m_UId = esc_attr($recordUIdKey);
		$this->columnTitles = array();

		if( $this->m_UId != $recordUIdKey )
			error_log("!!! $recordUIdKey is not safe to be used as record key (html escape = {$this->m_UId}).");

		$sourceClass = '\LWS\Adminpanel\EditList\Source';
		if( !is_a($source, $sourceClass) )
			error_log("!!! EditList data source is not a $sourceClass");
		else
			$this->m_Source = $source;

		$this->m_Mode = $mode;
		$this->m_PageDisplay = new \LWS\Adminpanel\EditList\Pager($this->m_Id);

		if( !is_array($filtersAndActions) )
			$filtersAndActions = array($filtersAndActions);

		$this->m_Actions = array();
		$this->m_Filters = array();
		foreach( $filtersAndActions as $faa )
		{
			if( is_a($faa, '\LWS\Adminpanel\EditList\Action') )
				$this->m_Actions[] = $faa;
			else if( is_a($faa, '\LWS\Adminpanel\EditList\Filter') )
				$this->m_Filters[] = $faa;
		}

		add_action('wp_loaded', array($this, 'manageActions'), 0);
		add_action('wp_ajax_lws_adminpanel_editlist', array($this, 'ajax'));
	}

	/** Apply actions */
	public function manageActions()
	{
		$this->m_Actions = \apply_filters('lws_adminpanel_editlist_actions_'.$this->slug, $this->m_Actions);
		$this->applyActions();
	}

	public function ajax()
	{
		if( isset($_REQUEST['id']) && isset($_REQUEST['method']) && isset($_REQUEST['line']) )
		{
			$method = \sanitize_key($_REQUEST['method']);
			if( !in_array($method, self::methods()) )
				exit(0);

			$id = \sanitize_text_field($_REQUEST['id']);
			$line = \sanitize_text_field($_REQUEST['line']);
			if( empty($id) || empty($line) )
				exit(0);

			$up = $this->accept($id, $method, $line);
			if( !is_null($up) )
			{
				wp_send_json($up);
				exit();
			}
		}
	}

	/**	Editlist will be splitted and grouped by given settings.
	 *	@param $groupby (array) the entries must be as follow:
	 *	*	'key'  => the grouping field, must exists in editlist rows.
	 *	*	'head' => a readonly html bloc used as group header. Use span[data-name] to allow value placing, where name are same as input names.
	 *	*	'form' => an html input bloc if grouped values are editable, where input names exist in rows. If empty, no add or edit is allowed.
	 * 	*	'add'  => (bool|string) if false, no add button set. A string should be used as add button label. True will set a default 'Add' button text.
	 *	*	'activated' => (bool) default is true, does the groupby should be activated at loading.
	 *	@return $this for method chaining */
	public function setGroupBy($groupby=array())
	{
		if( is_array($groupby) )
		{
			if( isset($groupby['key']) && !empty($groupby['key']) )
			{
				$this->groupBy = \wp_parse_args($groupby, array(
					'head' => "<span data-name='{$groupby['key']}'>&nbsp;</span>",
					'form' => '',
					'add'  => true,
					'activated' => true
				));
			}
			else
				error_log("Require an grouped by editlist[{$this->slug}] without any grouping key.");
		}
		else if( isset($this->groupBy) )
			unset($this->groupBy);
		return $this;
	}

	public function setCssClass($class)
	{
		$this->css = $class;
		return $this;
	}

	/** Display list by page (default is true)
	 * @return $this for method chaining */
	public function setPageDisplay($yes=true)
	{
		if( $yes === false || is_null($yes) )
			$this->m_PageDisplay = null;
		else if( $yes === true )
			$this->m_PageDisplay = new \LWS\Adminpanel\EditList\Pager($this->m_Id);
		else if( is_a($yes, '\LWS\Adminpanel\EditList\Pager') )
			$this->m_PageDisplay = $yes;
		else
			$this->m_PageDisplay = null;
		return $this;
	}

	protected function getGroupByForm()
	{
		$str = '';
		if( isset($this->groupBy) )
		{
			$add = '';
			if( !empty($this->groupBy['form']) && $this->groupBy['add'] && ($this->m_Mode & \LWS\Adminpanel\EditList::ADD) ) // no edit -> no add
			{
				if( $this->groupBy['add'] === true )
					$this->groupBy['add'] = _x("Add a group", "editlist groupby", 'lws-adminpanel');
				$add = (" data-add='" . \esc_attr($this->groupBy['add']) . "'");
			}

			$str .= "<div data-groupby='{$this->groupBy['key']}'$add class='lws_editlist_groupby_settings' style='display:none;'>";

			$str .= "<div class='lws_editlist_groupby_head'>";
			$str .= "<div class='lws-editlist-groupby-header'>{$this->groupBy['head']}";
			if( !empty($this->groupBy['form']) && ($this->m_Mode & \LWS\Adminpanel\EditList::MOD) ) // edit
				$str .= "<button class='lws-editlist-group-btn lws_editlist_modal_edit_button lws_editlist_group_head_edit lws-icon lws-icon-pencil'></button>";
			if( $this->m_Mode & \LWS\Adminpanel\EditList::DEL ) // del (no add -> no del)
				$str .= "<button class='lws-editlist-group-btn lws_editlist_modal_edit_button lws_editlist_group_del lws-icon lws-icon-bin'></button>";
			$str .= "</div></div>";

			if( !empty($this->groupBy['form']) )
			{
				$str .= "<div class='lws_editlist_groupby_form lws_editlist_modal_form' style='display:none;'>";
				$str .= "<div class='lws-editlist-groupby-header'>{$this->groupBy['form']}";
				$str .= "<button class='lws-editlist-group-btn lws_editlist_group_form_submit lws-icon lws-icon-checkmark'></button>"; // submit
				$str .= "<button class='lws-editlist-group-btn lws_editlist_group_form_cancel lws-icon lws-icon-cross'></button>"; // submit
				$str .= "</div></div>";
			}

			$str .= "</div>";
		}
		return $str;
	}

	/**	Echo the list as a grid */
	public function display()
	{
		$dataGrpBy = (isset($this->groupBy) && $this->groupBy['activated']) ? " data-groupby='on'" : '';
		$class = 'lws_editlist lws-master-editlist';
		if( isset($this->css) )
			$class .= (' '.$this->css);

		echo "<div id='{$this->m_Id}' class='$class'$dataGrpBy>";
		if( isset($this->groupBy) )
			echo $this->getGroupByForm();

		$rcount = -1;  // in|out
		$limit = null; // in|out
		echo $this->displayFilters($rcount, $limit, true);

		/// if the execution off an action has something to say
		/// open a  dialog with it at page loaded @see editlistfilters.js
		$actionReport = '';
		if( isset($this->actionResult) && !empty($this->actionResult) )
			$actionReport = " data-popup='" . base64_encode($this->actionResult) . "'";

		$table = \apply_filters('lws_adminpanel_editlist_read_'.$this->slug, $this->m_Source->read($limit), $limit);
		$this->hasActions = $this->addActionsColumn($table);
		$this->columns = $this->completeLabels(\apply_filters('lws_adminpanel_editlist_labels_'.$this->slug, $this->m_Source->labels()), $this->hasActions);

		$rows = $this->getHead(true);
		foreach ($table as $values)
			$rows .= $this->getRow($values); // data line
		if( !isset($this->repeatHead) || $this->repeatHead ) // default true
			$rows .= $this->getHead(false);

		$style = $this->getColumnsStyle();
		$template = $this->getRow(false); // template line

		echo <<<EOT
<div {$style} class='lws_editlist_table lws-editlist' data-editlist='{$this->m_Id}' uid='{$this->m_UId}'{$actionReport}>
	{$rows}
</div>
<div style='display:none;' class='lws_editlist_row_template' data-editlist='{$this->m_Id}'>
	{$template}
</div>
EOT;

		echo $this->getEditionForm();

		echo "<div class='lws-editlist-bottom-line'>";
		echo $this->getAddButton();
		if( $this->m_Actions )
			$this->displayActions($this->m_Actions);
		echo "</div>";

		foreach( ($deps = array('jquery', 'jquery-ui-core', 'jquery-ui-dialog' , 'lws-base64', 'lws-tools')) as $dep )
			\wp_enqueue_script($dep);
		\wp_register_script('lws-adminpanel-editlist', LWS_ADMIN_PANEL_JS.'/controls/editlist/editlist.js', $deps, LWS_ADMIN_PANEL_VERSION, true);
		\wp_localize_script('lws-adminpanel-editlist', 'lws_editlist_ajax_url', \add_query_arg('action', 'lws_adminpanel_editlist', \admin_url('/admin-ajax.php')));
		\wp_enqueue_script('lws-adminpanel-editlist');
		\wp_enqueue_script('lws-adminpanel-editlist-filters', LWS_ADMIN_PANEL_JS.'/controls/editlist/editlistfilters.js', $deps, LWS_ADMIN_PANEL_VERSION, true);

		echo "</div>";
	}

	/** default is true: repeat head in footer.
	 * @return $this for method chaining */
	function setRepeatHead($yes=true)
	{
		$this->repeatHead = $yes;
		return $this;
	}

	protected function displayFilters(&$rcount, &$limit, $above=true)
	{
		$result = '';
		if( $this->m_PageDisplay )
		{
			if( $filters = \apply_filters('lws_adminpanel_editlist_filters_'.$this->slug, $this->m_Filters) )
			{
				$rows = '';
				foreach( $filters as $filter )
				{
					if (\is_a($filter, '\LWS\Adminpanel\EditList\FilterColumnsVisibility'))
					{
						$rows .= sprintf('<div class="%s">%s</div>', $filter->cssClass(), $filter->input($above, $this->m_Source->labels()));
					}
					else
					{
						$rows .= sprintf('<div class="%s">%s</div>', $filter->cssClass(), $filter->input($above));
					}
				}
				$result .= "<div class='lws-editlist-filters-first-line'>{$rows}</div>";
			}
			if( !$limit )
			{
				$rcount = \apply_filters('lws_adminpanel_editlist_total_'.$this->slug, $this->m_Source->total());
				$limit = $this->m_PageDisplay->readLimit($rcount);
			}
			$result .= $this->m_PageDisplay->navDiv($rcount, $limit);

			$place = $above ? 'above' : 'below';
			$result = "<div class='lws-editlist-filters lws-editlist-{$place} {$this->m_Id}-filters'>{$result}</div>";
		}
		return $result;
	}

	protected function displayActions()
	{
		$ph = __('Apply', 'lws-adminpanel');
		echo "<div class='lws_editlist_actions'>";
		echo "<div class='lws-editlist-actions-cont'>";
		echo "<div class='lws-editlist-actions-left'><div class='lws-editlist-actions-icon lws-icon lws-icon-arrow-right'></div></div>";
		echo "<div class='lws-editlist-actions-right'>";
		$first = true;
		foreach( $this->m_Actions as $action )
		{
			//if($first){$first=false;}else{echo "<div class='lws-editlist-action-sep'></div>";}
			echo "<div class='lws-editlist-action' data-id='{$this->m_Id}'>";
			echo "<input type='hidden' name='{$this->KeyAction}' value='{$action->UID}'>";
			echo $action->input();
			echo "<button class='lws-adm-btn lws-editlist-action-trigger'>$ph</button>";
			echo "</div>";
		}
		echo "</div></div></div>";
	}

	/** For grid, no choice to define column count via style attribute */
	protected function getColumnsStyle()
	{
		$sizes = array_column($this->columns, 1);
		if ($this->m_Actions) {
			array_unshift($sizes, 'min-content');
		}

		return sprintf(
			'style="display: grid;grid-template-columns:%s;"',
			implode(' ', $sizes)
		);
	}

	protected function completeLabels($lab, $hasActions=false)
	{
		$width = 'auto';
		foreach( array_keys($lab) as $k )
		{
			if( !is_array($lab[$k]) )
				$lab[$k] = array($lab[$k], $width);
			while( count($lab[$k]) < 2 )
				$lab[$k][] = $width;
		}
		if ($hasActions) {
			$lab['lws_ap_editlist_item_actions'] = array(__('Action', 'lws-adminpanel'), 'min-content');
		}
		return $lab;
	}

	protected function getAddButton()
	{
		$buttons = array();
		if( $this->m_Mode & \LWS\Adminpanel\EditList::ADD )
		{
			$buttons['add'] = sprintf(
				"<button class='lws-adm-btn lws_editlist_modal_edit_button lws-editlist-add lws_editlist_item_add' data-id='%s'>%s</button>",
				$this->m_Id,
				__("Add", 'lws-adminpanel')
			);
		}
		$buttons = \apply_filters('lws_ap_editlist_button_add_value_'.$this->slug, $buttons, $this);
		return implode('', $buttons);
	}

	protected function entityEncode($entity)
	{
		if( \is_object($entity) )
			$entity = \get_object_vars($entity);

		if( \is_array($entity) )
		{
			$decode = array();
			foreach( $entity as $k => $v )
			{
				if( !(\is_object($v) || \is_array($v)) )
					$decode[$k] = html_entity_decode($v);
				else
					$decode[$k] = \base64_encode(\json_encode($v));
			}
			return $decode;
		}
		else
		{
			return \html_entity_decode($entity);
		}
	}

	protected function getHead($top=true)
	{
		$cells = array();

		if( $this->m_Actions )
		{
			$chk = "<input type='checkbox' class='lws_checkbox lws_editlist_check_selectall lws-ignore-confirm' data-size='16' data-class='select-all'>";
			$cells[] = array(
				'class'   => 'lws-editlist-checkbox',
				'content' => $chk,
				'key'     => false,
			);
		}

		foreach( $this->columns as $key => $label )
		{
			$cells[] = array(
				'atts'    => sprintf(' data-key="%s"', \esc_attr($key)),
				'content' => $label[0],
				'key'     => 'lws_ap_editlist_item_actions' != $key ? $key : false,
			);
		}

		return $this->flattenCells($cells, 'th', 'head ' . ($top ? 'top' : 'bottom'), '');
	}

	protected function getTemplateValues($hasActions)
	{
		$values = \apply_filters('lws_adminpanel_editlist_default_'.$this->slug, $this->m_Source->defaultValues());
		if( !($values && \is_array($values)) )
			$values = array();
		$table = array($values);
		$this->addActionsColumn($table, $hasActions);
		return \reset($table);
	}

	protected function getRow($values=false)
	{
		$template = (false === $values ? ' data-template="1"' : '');
		if( $template )
			$values = $this->getTemplateValues($this->hasActions);
		$rowId = sprintf(' data-id="%s"', isset($values[$this->m_UId]) ? \base64_encode($values[$this->m_UId]) : '');

		$cells = array();
		foreach( $this->columns as $k => $td )
		{
			$cells[] = array(
				'atts'    => sprintf(' data-key="%s"', \esc_attr($k)),
				'content' => isset($values[$k]) ? $values[$k] : '',
				'key'     => 'lws_ap_editlist_item_actions' != $k ? $k : false,
			);
		}
		$cells[0]['class'] = 'title column-primary';

		if( $this->m_Actions )
		{
			$chk = "<input type='checkbox'{$rowId} class='lws_checkbox lws_editlist_check_selectitem lws-ignore-confirm' data-size='16'>";
			\array_unshift($cells, array(
				'class'   => 'lws-editlist-checkbox',
				'content' => $chk,
				'key'     => false,
			));
		}

		$rowId .= sprintf(' data-line="%s"', \base64_encode(\json_encode($this->entityEncode($values))));
		return $this->flattenCells($cells, 'td', $template ? 'template' : 'editable', $rowId . $template);
	}

	/**	Flat an array to make a grid row.
	 *	@param $cells (array) cell definition ['class'=>'', 'content'=>'', 'atts=>'']
	 *	@return (string) HTML bloc */
	protected function flattenCells(array $cells, $cellKind = 'td', $rowKind = 'editable', $rowAttrs = '')
	{
		$colspan = 0;
		foreach ($cells as $i => $cell)
		{
			$cells[$i] = \array_merge(array('class'=>'', 'atts'=>'', 'content'=>''), $cell);
			$cells[$i]['class'] = \trim($cells[$i]['class'] . ' lws-editlist-cell ' . \trim($cellKind));
			if ($cell['key'])
				++$colspan;
		}

		$index = 0;
		$head = false !== \strpos($rowKind, 'head') ? ' th' : '';
		$str = array();
		$firstValue = true;
		foreach ($cells as $cell)
		{
			if ($cell['key']) {
				if ($firstValue) {
					$firstValue = false;
					// insert the small version in a cell
					$str[] = sprintf(
						"<div class='lws-small-media-cell lws-editlist-cell lws_deep_cell{$head}' style='grid-column:span %d;'>%s</div>",
						$colspan,
						$head ? __("Values", 'lws-adminpanel') : $this->getSmallEditableRow($cells)
					);
				}
				$cell['class'] .= ' large-media-cell-content';
			}
			$cell['atts'] .= sprintf(' style="grid-column: %d;"', ++$index);
			$str[] = "<div class='{$cell['class']}'{$cell['atts']}><div class='cell-content'>{$cell['content']}</div></div>";
		}

		$class = ('lws_editlist_row '.\trim($rowKind));
		$rowAttrs = (' ' . \trim($rowAttrs));
		$str = implode('', $str);
		return "<div class='{$class}'{$rowAttrs}>{$str}</div>";
	}

	protected function getSmallEditableRow($cells)
	{
		$str = '';
		foreach ($cells as $cell)
		{
			if (!$cell['key'])
				continue;
			$title = '';
			if (isset($this->columns[$cell['key']])){
				$title = $this->columns[$cell['key']];
				if (\is_array($title))
					$title = reset($title);
			}

			$str .= <<<EOT
<div class='small-media-subcell subtd'{$cell['atts']}>
	<div class='cell-title'>{$title}</div>
	<div class='cell-content'>{$cell['content']}</div>
</div>
EOT;
		}
		return $str;
	}

	protected function getEditionForm()
	{
		$ph = array(
			'cancel' => __('Cancel', 'lws-adminpanel'),
			'save'   => __('Save', 'lws-adminpanel')
		);
		$form = \apply_filters('lws_adminpanel_editlist_input_' . $this->slug, $this->m_Source->input());
		$next = _x("Next", 'Confirm event/unlockable type choice', 'lws-adminpanel');
		$back = _x("Back", 'Undo event/unlockable type choice', 'lws-adminpanel');

		$popup = <<<EOT
<div class='lws-editlist-form-container lws_editlist_form_hidden lws_editlist_line_form' data-editlist='{$this->m_Id}'>
	<div class='lws-editlist-form-popup lws_editlist_modal_form'>
		<div class='lws-editlist-line-inputs popup'>
			{$form}
		</div>
		<div class='lws-editlist-line-btns popup'>
			<button class='button lws-adm-btn btn-cancel'>
				<div class='button-icon lws-icon-undo'></div>
				<div class='button-text'>{$ph['cancel']}</div>
			</button>
			<div class='back_button'>
				<button class='button lws-adm-btn lws-type-btn undo bt-hidden'>
					<div class="button-icon lws-icon-arrow-left"></div>
					<div class="button-text">{$back}</div>
				</button>
			</div>
			<button class='button lws-adm-btn lws-type-btn confirm bt-hidden'>
				<div class="button-text">{$next}</div>
				<div class="button-icon lws-icon-arrow-right"></div>
			</button>
			<button class='button lws-adm-btn btn-save'>
				<div class='button-icon lws-icon-floppy-disk-2'></div>
				<div class='button-text'>{$ph['save']}</div>
			</button>
		</div>
	</div>
</div>
EOT;
		return $popup;
	}

	// the button line which appear under each line.
	protected function addActionsColumn(&$table, $hasActions=false)
	{
		foreach( $table as &$data )
		{
			$id = (isset($data[$this->m_UId]) ? $data[$this->m_UId] : null);
			$ph = apply_filters(
				'lws_ap_editlist_item_action_names_' . $this->slug,
				array(
					\LWS\Adminpanel\EditList\Modes::MOD => __('Quick Edit', 'lws-adminpanel'),
					\LWS\Adminpanel\EditList\Modes::DUP => __('Copy', 'lws-adminpanel'),
					\LWS\Adminpanel\EditList\Modes::DEL => __('Delete', 'lws-adminpanel'),
				),
				$id,
				$data
			);

			$btns = array();
			if ($this->m_Mode & \LWS\Adminpanel\EditList\Modes::MOD) {
				$btns['mod'] = "<div class='editlist-btn mod lws-icon-edit'><div class='btn-descr'>{$ph[\LWS\Adminpanel\EditList\Modes::MOD]}</div></div>";
			}
			if ($this->m_Mode & \LWS\Adminpanel\EditList\Modes::DUP) {
				$btns['dup'] = "<div class='editlist-btn dup lws-icon-copy'><div class='btn-descr'>{$ph[\LWS\Adminpanel\EditList\Modes::DUP]}</div></div>";
			}
			if ($this->m_Mode & \LWS\Adminpanel\EditList\Modes::DEL) {
				$btns['del'] = "<div class='editlist-btn del lws-icon-bin'><div class='btn-descr'>{$ph[\LWS\Adminpanel\EditList\Modes::DEL]}</div></div>";
			}
			$btns = apply_filters('lws_ap_editlist_item_actions_' . $this->slug, $btns, $id, $data);

			if( $btns )
			{
				$hasActions = true;
				$btns = implode('', $btns);
				$data['lws_ap_editlist_item_actions'] = "<div class='lws-editlist-action-button lws-icon-menu-5'><div class='editlist-actions-popup hidden'>{$btns}</div></div>";
			}
			else
				$data['lws_ap_editlist_item_actions'] = '';
		}

		$actionModes = ($this->m_Mode & \LWS\Adminpanel\Editlist::DDD);
		$hasActions = \apply_filters('lws_ap_editlist_show_action_column_' . $this->slug, $hasActions || $actionModes, $table);
		if( !$hasActions )
		{
			foreach( $table as &$data )
				unset($data['lws_ap_editlist_item_actions']);
		}
		return $hasActions;
	}

	/// @return an array with accepted method value.
	static public function methods()
	{
		return array("put", "del");
	}

	/**	Test if this instance is concerne (based on $editionId),
	 *	then save the $line. @see write().
	 * 	or return a list of the lines. @see read().
	 * 	or delete a line. @see erase().
	 * 	or null if not concerned.
	 *	ajax {action: 'editlist', method: 'put', id: "?", line: {json ...}} */
	public function accept($editionId, $method, $line)
	{
		if( $editionId === $this->m_Id )
		{
			$data = json_decode( base64_decode($line), true );
			if( $method === "put" )
			{
				$result = array( "status" => 0 );
				$data = \apply_filters('lws_adminpanel_editlist_write_'.$this->slug, $this->m_Source->write($data));
				if( \is_wp_error($data) )
				{
					$result["error"] = $data->get_error_message();
				}
				else if( \LWS\Adminpanel\EditList\UpdateResult::isA($data) )
				{
					$result["status"] = $data->success ? 1 : 0;
					if( $data->success )
					{
						$result["line"] = base64_encode(json_encode($this->entityEncode($data->data)));
						if( !empty($data->message) )
							$result["message"] = $data->message;
					}
					else if( !empty($data->message) )
						$result["error"] = $data->message;
				}
				else if( $data !== false )
				{
					$result["status"] = 1;
					$result["line"] = base64_encode(json_encode($this->entityEncode($data)));
				}
				return $result;
			}
			else if( $method === "del" )
			{
				return array( "status" => (\apply_filters('lws_adminpanel_editlist_erase_'.$this->slug, $this->m_Source->erase($data)) ? 1 : 0) );
			}
		}
		return null;
	}

	/** If any local action match the posted action uid,
	 * we apply it on the posted selection.
	 * Then, unset the uid from $_POST to ensure it is done only once. */
	protected function applyActions()
	{
		$keyItems = 'action-items';
		if( isset($_POST[$this->KeyAction]) && !empty($_POST[$this->KeyAction])
			&& isset($_POST[$keyItems]) && !empty($_POST[$keyItems]) )
		{
			$uid = sanitize_key($_POST[$this->KeyAction]);
			$items = json_decode( base64_decode($_POST[$keyItems]), true );
			foreach( $this->m_Actions as $action )
			{
				if( $uid == $action->UID )
				{
					$ret = $action->apply($items);
					if( !empty($ret) && is_string($ret) )
						$this->actionResult = $ret;
					unset($_POST[$this->KeyAction]);
					break;
				}
			}
		}
	}

}
