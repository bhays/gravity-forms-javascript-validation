<?php /*

Plugin Name: Gravity Forms Javascript Validation
Plugin URI: http://github.com/bhays/gravity-forms-javascript-validation
Description: Enable front end javascript validation to Gravity Forms
Version: 1.0
Author: Ben Hays
Author URI: http://benhays.com
License: GPL
*/

add_action('init',  array('GFValidate', 'init'));

class GFValidate {

	private static $path = "gravity-forms-javascript-validation/gravity-forms-javascript-validation.php";
	private static $url = "http://www.gravityforms.com";
	private static $slug = "gravity-forms-javascript-validation";
	private static $version = "1.0";
	private static $min_gravityforms_version = "1.7";
	private static $validate_version = '1.11.1';

	//Plugin starting point. Will load appropriate files
	public static function init(){

		//supports logging
		add_filter("gform_logging_supported", array("GFValidate", "set_logging_supported"));

        add_action('after_plugin_row_' . self::$path, array('GFValidate', 'plugin_row') );

		if(!self::is_gravityforms_supported())
		{
			return;
		}

		// Add option to form settings
		add_action('gform_form_settings', array('GFValidate', 'add_form_setting'), 10, 2);
		add_filter('gform_pre_form_settings_save', array('GFValidate', 'save_form_setting'));

		// Load Sisyphus JS when necessary
		add_action('gform_enqueue_scripts', array('GFValidate', 'gform_enqueue_scripts' ), '', 2 );
	}

	public static function gform_enqueue_scripts( $form = null )
	{
		if ( ! $form == null )
		{
			// Check form for enabled validation
			if( array_key_exists('enable_validation', $form) && $form['enable_validation'] == 1 )
			{
				wp_enqueue_script(
					'js-validate',
					plugins_url( 'js/jquery.validate.min.js' , __FILE__ ),
					array( 'jquery' ),
					self::$validate_version
				);
				wp_enqueue_script(
					'gf-js-validate-functions',
					plugins_url( 'js/gf-js-validate-functions.js' , __FILE__ ),
					array('jquery'),
					self::$version
				);

				// Add individual page script for form
				add_action('gform_register_init_scripts', array('GFValidate', 'add_page_script'));
			}
		}
	}


	public static function add_page_script($form)
	{
	    self::log_debug('Adding page script to '.$form['id']);

	    $script = "(function($){" .
	        "var container;".
	        // Find required elements and add class
	        "$('.gfield_contains_required').each(function(i, e){".
	            "$(e).find('input, select, textarea').attr('required', true);".
	        "});".
	        "$('#gform_".$form['id']."').validate({".
	            "debug: true,".
	            "errorElement: 'div',".
	            "errorClass: 'gfield_error',".
	            "errorPlacement: function(error, element){".
	                "container = element.closest('.gfield');".
	                // Only set container level error once
	                //"if( ! container.hasClass('gf_js_error') ){".
	                   // "error.appendTo(container);".
	                    //"container.addClass('gf_js_error');".
	                //"}".
	            "},";

            // Get messages for each input
            $script .= "messages: {";
            foreach ($form['fields'] as $field) {
				$field_name = 'input_' . $field['id']; // test
				$field_errorMessage = "'" . $field['errorMessage'] . "',";
				if ($field['errorMessage'] !== '')  {
				    $script2 = $script2 .
				    $field_name . ": " . $field_errorMessage;
				}
            }
            $script .= "},";

            $script .
	            "groups: getGroups(),".
	            "focusCleanup: true,".
	            // I removed the ugly highlighting that
	            "highlight: function(element, errorClass, validClass) {".
	                "$(element).closest('.gfield').addClass(errorClass).removeClass(validClass);".
	            "},".
	            "unhighlight: function(element, errorClass, validClass) {".
	                "$(element).closest('.gfield').removeClass(errorClass).addClass(validClass);".
	            "},".
	            "invalidHandler: function(event, validator) {".
	                "gf_submitting_".$form['id']." = false;".
	            "}".
	        "});".
	    "})(jQuery);";

		self::log_debug('Script is: '.$script);

	    GFFormDisplay::add_init_script($form['id'], 'gf_js_validate', GFFormDisplay::ON_PAGE_RENDER, $script);
	    return $form;
	}

	public static function add_form_setting( $settings, $form )
	{
		$current = rgar($form, 'enable_validation');
		$checked = !empty($current) ? 'checked="checked"' : '';

	    $settings['Form Options']['enable_validation'] = '
	        <tr>
	        	<th>Javascript Validation <a href="#" onclick="return false;" class="tooltip tooltip_form_animation" tooltip="&lt;h6&gt;Enable Client-side Validation&lt;/h6&gt;Check this option to enable validation messages for the client before reloading the page."></a></th>
	            <td><input type="checkbox" value="1" '.$checked.' name="enable_validation"> <label for="enable_validation">'.__('Validate forms before submission', 'gravity-forms-js-validate').'</label></td>
	        </tr>';

	    return $settings;
	}

	public static function save_form_setting($form)
	{
	    $form['enable_validation'] = rgpost('enable_validation');
	    return $form;
	}

	public static function plugin_row()
	{
		if(!self::is_gravityforms_supported())
		{
			$message = sprintf(__("%sGravity Forms%s 1.7 is required. Activate it now or %spurchase it today!%s"), "<a href='http://benjaminhays.com/gravityforms'>", "</a>", "<a href='http://benjaminhays.com/gravityforms'>", "</a>");
			self::display_plugin_message($message, true);
		}
    }

	public static function display_plugin_message($message, $is_error = false)
	{
		$style = '';
		if($is_error)
		{
			$style = 'style="background-color: #ffebe8;"';
		}
		echo '</tr><tr class="plugin-update-tr"><td colspan="5" class="plugin-update"><div class="update-message" ' . $style . '>' . $message . '</div></td>';
	}

	private static function is_gravityforms_installed(){
		return class_exists("RGForms");
	}

	private static function is_gravityforms_supported(){
		if(class_exists("GFCommon")){
			$is_correct_version = version_compare(GFCommon::$version, self::$min_gravityforms_version, ">=");
			return $is_correct_version;
		}
		else{
			return false;
		}
	}
	function set_logging_supported($plugins)
	{
		$plugins[self::$slug] = "Validate";
		return $plugins;
	}

	private static function log_error($message){
		if(class_exists("GFLogging"))
		{
			GFLogging::include_logger();
			GFLogging::log_message(self::$slug, $message, KLogger::ERROR);
		}
	}

	private static function log_debug($message){
		if(class_exists("GFLogging"))
		{
			GFLogging::include_logger();
			GFLogging::log_message(self::$slug, $message, KLogger::DEBUG);
		}
	}
}
if(!function_exists("rgget")){
	function rgget($name, $array=null){
		if(!isset($array))
			$array = $_GET;

		if(isset($array[$name]))
			return $array[$name];

		return "";
	}
}

if(!function_exists("rgpost")){
	function rgpost($name, $do_stripslashes=true){
		if(isset($_POST[$name]))
			return $do_stripslashes ? stripslashes_deep($_POST[$name]) : $_POST[$name];

		return "";
	}
}

if(!function_exists("rgar")){
	function rgar($array, $name){
		if(isset($array[$name]))
			return $array[$name];

		return '';
	}
}

if(!function_exists("rgempty")){
	function rgempty($name, $array = null){
		if(!$array)
			$array = $_POST;

		$val = rgget($name, $array);
		return empty($val);
	}
}

if(!function_exists("rgblank")){
	function rgblank($text){
		return empty($text) && strval($text) != "0";
	}
}

