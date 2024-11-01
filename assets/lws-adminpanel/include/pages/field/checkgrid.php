<?php

namespace LWS\Adminpanel\Pages\Field;

if (!defined('ABSPATH')) {
    exit();
}


/** Designed to be used inside Wizard only.
 * Behavior is similar to a radio,
 * But choices looks like tiles with a grid layout. */
class CheckGrid extends \LWS\Adminpanel\Pages\Field
{
	public function __construct($id, $title, $extra=null)
	{
		parent::__construct($id, $title, $extra);
		add_action('admin_enqueue_scripts', array($this, 'script'), 9);
        \add_filter('pre_update_option_lws_woorewards_wc_my_account_lar_options', array($this, 'transposeLoyaltyEndpointOptions'), 10, 3);
    }

    public function input()
    {
        $name = \esc_attr($this->id());
        $value = $this->readOption(false);
        if(!$value){
            $value = $this->getExtraValue('source', array());
        }
        //error_log(print_r($value,true));
        $ddclass = ($this->getExtraValue('dragndrop')) ? 'lws_checkgrid_sortable' : '';
        echo "<div class='lws_checkgrid lws-checkgrid {$ddclass}' id='sort-{$name}'>";
        $rang = 0;
        foreach ($value as $opt) {
            $val = $opt['value'];
            $label = $opt['label'];
            $active = (isset($opt['active'])) ? $opt['active'] : '';
            $checkIcon = ($active) ? 'lws-icon-checkbox-checked' : 'lws-icon-checkbox-unchecked';
            $actClass = ($active) ? 'checked' : '';
            $input = "<div class='lws_checkgrid_item checkgrid-item {$actClass}'>";
            $input .= "<input type='hidden' name='{$name}[value][]' value='{$val}'/>";
            $input .= "<input type='hidden' name='{$name}[label][]' value='{$label}'/>";
            $input .= "<input type='hidden' class='lws_cg_active' name='{$name}[active][]' value='{$active}'/>";
            $input .= "<div class='checkbox {$checkIcon}'></div>";
            $input .= "<div class='label'>$label</div>";
            $input .= "</div>";
            echo $input;
            $rang += 1 ;
        }
        echo "</div>";
    }

    function transposeLoyaltyEndpointOptions($value, $old_value, $option)
    {
        $transpose = array();
        if( is_array($value) && $value )
        {
            $firstK = array_keys($value)[0];
            for( $i=0 ; $i<count($value[$firstK]) ; ++$i )
            {
                $item = array();
                foreach( $value as $key => $list )
                    $item[$key] = isset($list[$i]) ? $list[$i] : '';
                $transpose[] = $item;
            }
        }
        return $transpose;
    }

	public function script()
	{
		wp_enqueue_script('lws-checkgrid');
	}
}
