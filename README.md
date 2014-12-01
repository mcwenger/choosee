#Choos:ee

Get ExpressionEngine select dropdown, checkbox, multiselect or radio options from the CP on the front-end. Can grab options from EE fields or P&amp;T fields, inside or outside of a Grid or Matrix.


## Parameters
	
* field="" // REQUIRED: The name of the field to find options for.
	* field="field_name" (field)
	* field="col_id_X" (direct Matrix column query)
	* field="field_name:column_name" (Matrix column query using names)

* use="" // set to 'matrix' or 'grid' when getting options for a column. If Matrix is installed, 'matrix' will be assumed, otherwise 'grid' will be assumed.

* value="" // Pass in a value for comparison - to use in conjunction with {selected} or {checked}. Multiple values can be pipe separated (|) - see examples below.

* allow_empty="" // Include empty value option in list (yes/no, true/false) - defaults to 'yes'.

* allow_numeric_key="" // Allow key to be numeric when checking for {checked} or {selected}? Needed due to difference between EE and P&T options configuration/arrays (yes/no, true/false) - defaults to 'no'.

* backspace="" // Same as other EE backspace parameters.


Variables:
---------------------------------------
	{option}
	{value}
	{selected} // outputs 'selected="selected"' if {value} or {option} equals value parameter
	{checked} // outputs 'checked="checked"' if {value} or {option} equals value parameter
	{option_count}
	{total_options}

### Example usage

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

multiple options selected in entry ({option_name} vs {item})
	
    <p>
		<label>P&T field</label>
		<select multiple="multiple">
			{exp:choosee field="test" value="{test backspace='1'}{option_name}|{/test}"}
			<option value="{value}" {selected}>{option}</option>
			{/exp:choosee}
		</select>
    </p>
	
    <p>
		<label>EE field</label>
		<select multiple="multiple">
			{exp:choosee field="test" value="{test backspace='1'}{item}|{/test}"}
			<option value="{value}" {selected}>{option}</option>
			{/exp:choosee}
		</select>
    </p>