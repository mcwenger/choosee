<?php  if ( ! defined('BASEPATH')) exit('No direct script access allowed');

$plugin_info = array(
	'pi_name'		=> 'Choos:ee',
	'pi_version'	=> '1.0',
	'pi_author'		=> 'Mike Wenger, Q Digital Studio',
	'pi_author_url'	=> 'http://www.qdigitalstudio.com',
	'pi_description'=> 'Allows for front-end output of select, multi-select, checkbox or radio options from an EE field or P&T Fieldpack field - inside or outside of a Matrix',
	'pi_usage'		=> choosee::usage()
);

class Choosee {

	var $return_data = "";

	public function Choosee()
	{
		
		// constructor
		$this->EE =& get_instance();
		
		// fetch the tagdata
		$tagdata = $this->EE->TMPL->tagdata;

		// fetch the params
		$field = $this->EE->TMPL->fetch_param('field');
		$backspace = is_numeric($this->EE->TMPL->fetch_param('backspace')) ? intval($this->EE->TMPL->fetch_param('backspace')) : 0;
		$passed_in = $this->EE->TMPL->fetch_param('value') ? strip_tags($this->EE->TMPL->fetch_param('value')) : '';
		$allow_empty = $this->EE->TMPL->fetch_param('allow_empty');
		$allow_numeric_key = $this->EE->TMPL->fetch_param('allow_numeric_keys');
		$delimiter = $this->EE->TMPL->fetch_param('delimiter') ? $this->EE->TMPL->fetch_param('delimiter') : '|';
		$use = $this->EE->TMPL->fetch_param('use');
		
		$matrix_namespace = 'matrix';
		$grid_namespace = 'grid';
		$matrix_column_table = 'exp_matrix_cols';
		$grid_column_table = 'exp_grid_columns';
		$column_table = '';
		
		// bail if required params aren't present	
		if (! $field) return false;

		// set defaults
		if ($allow_empty){
			$allow_empty = (strtolower($allow_empty) === 'yes' || strtolower($allow_empty) === 'true') ? true : false;
		} else {
			$allow_empty = true;
		}
		if ($allow_numeric_key){
			$allow_numeric_key = (strtolower($allow_numeric_key) === 'yes' || strtolower($allow_numeric_key) === 'true') ? true : false;
		} else {
			$allow_numeric_key = false;
		}
		
		// echo $field.'-'.$allow_empty.'<br>';
		
		// Looking for options in a Matrix or Grid cell
		if (preg_match('/col_id_/', $field) OR preg_match('/:/', $field)){
			
			// if 'use' parameter is not set, check if Matrix is installed first
			if (empty($use)){
				
				$is_matrix_installed = ee()->db->select('name')
					->from('exp_fieldtypes')
					->where('name',$matrix_namespace)
					->get()
					->result_array();
				
				if ($is_matrix_installed){
					 
					 $use = $matrix_namespace;
				} else {
					 
					 $use = $grid_namespace;
				}
			}
			
			// set use case or bail if not valid
			switch ($use){
			    case $matrix_namespace:
			        
			        $column_table = $matrix_column_table;
			        break;
			    case $grid_namespace:
			        
					$column_table = $grid_column_table;
			        break;
			    default:

					return false;
			}			

			// looking by field_name:col_name
			if (preg_match('/:/', $field)){
				
				$field = array_filter(explode(':', $field));
				if (count($field) > 2) $this->return_data = 'Your field parameter is malformed.';
				
				$field_name = $field[0];
				$col_name = $field[1];
				
				$query = ee()->db->select('ecd.col_settings')
					->from("$column_table ecd")
					->join('exp_channel_fields ecf', 'ecd.field_id = ecf.field_id')
					->where(array(
						'ecf.field_name' => $field_name,
						'ecd.col_name' => $col_name
					))
					->get();
				
			// looking by col_id_x
			} else {

				$col_id = str_replace('col_id_', '', $field);
				
				$query = ee()->db->select('col_settings')
					->from($column_table)
					->where('col_id',$col_id)
					->get();
				
			}

			$results = $query->result_array();
			if (! $results) return false;
			
			$settings = $results[0]['col_settings'];
			
			// decode based on encoding			
			if ( is_object(json_decode($settings)) ){

				$options = json_decode($settings);
				$options = $options->field_list_items;
				$options = explode("\n", $options);
			} else {
				
				$options = unserialize(base64_decode($settings));
				$options = $options['options'];
			}

		// standard EE field or P&T fieldpack field
		} else {
			
			$query = ee()->db->select('field_type, field_settings, field_list_items')
				->from('exp_channel_fields')
				->where('field_name',$field)
				->get();
				$results = $query->result_array();			
			
			if (! $results){
				return false;
			}
			
			/** check specific field types to ensure we get what we're looking for: options **/
			
			// is it a fieldpack fieldtype?
			if (
				$results[0]['field_type'] == 'fieldpack_dropdown' 
				|| $results[0]['field_type'] == 'fieldpack_checkboxes' 
				|| $results[0]['field_type'] == 'fieldpack_radio_buttons' 
				|| $results[0]['field_type'] == 'fieldpack_multiselect'){
				
				$options = unserialize(base64_decode($results[0]['field_settings']));
				$options = $options['options'];
			
			// is it an EE fieldtype?
			} else if (
				$results[0]['field_type'] == 'select' 
				|| $results[0]['field_type'] == 'checkboxes' 
				|| $results[0]['field_type'] == 'radio'
				|| $results[0]['field_type'] == 'multi_select'){
				
				$options = explode("\n", $results[0]['field_list_items']);
				foreach($options as $index=>$option){
					
					unset($options[$index]);
					$options[$option] = $option;
				}
			} else {
				
				// no go - no options to return
				return false;
			}
		}
		
		// bail if we have no options prepped
		if (empty($options)) return false;
		
		$count = 0;

		foreach ($options as $key=>$option) {
			if ($allow_empty OR (!$allow_empty && ($key != ' ' && $key != '')) ){
			
				$count++;
				$variables[] = array(
					'option' => $option,
					'value' => $key,
					'selected' => $this->_is_checked_or_selected($key,$option,$passed_in,'selected',$delimiter,$allow_numeric_key),
					'checked' => $this->_is_checked_or_selected($key,$option,$passed_in,'checked',$delimiter,$allow_numeric_key),
					'option_count' => $count,
					'total_options' => count($options)
				);
			}
		}
		
		$parsed_string = $this->EE->TMPL->parse_variables($tagdata, $variables);
		
		// EE handles backspace
		// $this->return_data = substr($parsed_string, 0, strlen($parsed_string) - $backspace);
		$this->return_data = $parsed_string;
	}
	
	private function _is_checked_or_selected($key='',$option='',$passed_in='',$selector="selected",$delimiter="|",$allow_numeric_key=false){
		
		$attr = ' '.$selector.'="'.$selector.'"';
		
		// we have multiple options
		if ( preg_match('/'.$delimiter.'/', $passed_in) ){
			
			// because P&T assumes a key=>value array, and EE does not - EE key would be $index, not CP set value
			if ($allow_numeric_key){
				
				$comparison = $key ? $key : $option;
			} else {
				
				$comparison = !is_numeric($key) ? $key : $option;
			}
			
			// blow out options array
			$options = array_filter(explode($delimiter, $passed_in));
			
			foreach($options as $i=>$option){
				
				if ($comparison == $option){
					
					return $attr;
					break;
				}
			}
			return false;
			
		// only one option	
		} else {
			
			return ($key == $passed_in || $option == $passed_in) ? $attr : '';
		}
	}
	

	// ----------------------------------------------------------------
	
	/**
	 * Plugin Usage
	 */
	public static function usage()
	{
		ob_start();
?>
	Choos:ee can find and return the options for any options-based field. You can target a field in a Matrix column (Fieldpack) or for a field itself.
	
	Parameters:
	---------------------------------------
	
	field="" // REQUIRED: The name of the field to find options for.
	- field="field_name" (field)
	- field="col_id_X" (direct Matrix column query)
	- field="field_name:column_name" (Matrix column query using names)
	
	use="" // set to 'matrix' or 'grid' when getting options for a column. If Matrix is installed, 'matrix' will be assumed, otherwise 'grid' will be assumed.

	value="" // Pass in a value for comparison - to use in conjunction with {selected} or {checked}. Multiple values can be pipe separated (|) - see examples below.
	
	allow_empty="" // Include empty value option in list (yes/no, true/false) - defaults to 'yes'.
	
	allow_numeric_key="" // Allow key to be numeric when checking for {checked} or {selected}? Needed due to difference between EE and P&T options configuration/arrays (yes/no, true/false) - defaults to 'no'.

	backspace="" // Same as other EE backspace parameters.
	
	
	Variables:
	---------------------------------------
	{option}
	{value}
	{selected} // outputs 'selected="selected"' if {value} or {option} equals value parameter
	{checked} // outputs 'checked="checked"' if {value} or {option} equals value parameter
	{option_count}
	{total_options}
	
	
	Example usage:
	---------------------------------------
	
	<select>
		<option value="">Select One</option>
		{exp:choosee field="field_name:column_name" value="{some_field_value}"}
		<option value="{if value}{value}{if:else}{option}{/if}" {selected}>{option}</option>
		{/exp:choosee}
	</select>
	
    <p>
		{exp:choosee field="field_name" value="{some_field_value}"}
		<input id="option_{option_count}" type="checkbox" value="{if value}{value}{if:else}{option}{/if}" {checked}><label for="option_{option_count}">{option}</label><br>
		{/exp:choosee}
    </p>
    
    {matrix_field}
    <p class="pll">Matrix Field: Row {row_count}<br>
		{exp:choosee field="matrix_field:matrix_col" value="{matrix_col backspace='1'}{option_name}|{/matrix_col}"}
		<input id="option_{option_count}" type="checkbox" value="{if value}{value}{if:else}{option}{/if}" {checked}><label class="inline" for="option_{option_count}">{option}</label><br>
		{/exp:choosee}
    </p>
    {/matrix_field}
    
	-- multiple options selected in entry ({option_name} vs {item}) -- 
	
    <p class="pll">
		<label>P&T field</label>
		<select multiple="multiple">
			{exp:choosee field="test" value="{test backspace='1'}{option_name}|{/test}"}
			<option value="{value}" {selected}>{option}</option>
			{/exp:choosee}
		</select>
    </p>
	
    <p class="pll">
		<label>EE field</label>
		<select multiple="multiple">
			{exp:choosee field="test" value="{test backspace='1'}{item}|{/test}"}
			<option value="{value}" {selected}>{option}</option>
			{/exp:choosee}
		</select>
    </p>
	
<?php
		$buffer = ob_get_contents();
		ob_end_clean();
		return $buffer;
	}
}


/* End of file pi.choosee.php */
/* Location: /system/expressionengine/third_party/choosee/pi.choosee.php */