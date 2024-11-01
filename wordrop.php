<?php
/*
Plugin Name: Gravity Forms Dropbox Add-On
Description: Integrates Gravity Forms with Dorpbox allowing form submissions to be automatically sent to your DropBox account
Version: 1.0.0
Author: Wordrop.
Author URI: http://www.wordrop.net

------------------------------------------------------------------------
Copyright 2012 wordrop, Inc.

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA 02111-1307 USA
*/


add_action('init',  array('GFWordrop', 'init'));

class GFWordrop {

	private static $name = "Gravity Forms Wordrop Add-On";
	private static $path = "gravity-forms-wordrop/wordrop.php";
	private static $url = "http://www.gravityforms.com";
	private static $slug = "gravity-forms-wordrop";
	private static $version = "1.0";
	private static $min_gravityforms_version = "1.3.9";

    //Plugin starting point. Will load appropriate files
    public static function init(){
	    global $pagenow;
	    
	    if($pagenow === 'plugins.php') {
			add_action("admin_notices", array('GFWordrop', 'is_gravity_forms_installed'), 10);
		}
		
		if(self::is_gravity_forms_installed(false, false) !== 1){
			add_action('after_plugin_row_' . self::$path, array('GFWordrop', 'plugin_row') );
           return;
        }

        if(is_admin()){
		
            //creates a new Settings page on Gravity Forms' settings screen
            if(self::has_access("gravityforms_wordrop")){
                RGForms::add_settings_page("Wordrop", array("GFWordrop", "settings_page"), "");
		//add_action('admin_head', array('GFWordrop', 'my_plugin_admin_styles'));
            }
        }

        //creates the subnav left menu
        add_filter("gform_addon_navigation", array('GFWordrop', 'create_menu'), 20);
	
        if(self::is_wordrop_page()){

            //enqueueing sack for AJAX requests
            wp_enqueue_script(array("sack"));
	    wp_enqueue_style('gravityforms-admin', GFCommon::get_base_url().'/css/admin.css');
	    wp_enqueue_style( 'myPluginStylesheet3' );
	    wp_enqueue_style('myPluginStylesheet4');
	




         }
         else if(in_array(RG_CURRENT_PAGE, array("admin-ajax.php"))){

            add_action('wp_ajax_rg_update_feed_active', array('GFWordrop', 'update_feed_active'));
            add_action('wp_ajax_gf_select_wordrop_form', array('GFWordrop', 'select_wordrop_form'));

        } elseif(in_array(RG_CURRENT_PAGE, array('admin.php'))) {
        	add_action('admin_head', array('GFWordrop', 'show_wordrop_status'));
        }
        else{
             //handling post submission.
            //add_action("gform_pre_submission", array('GFWordrop', 'push'), 10, 2);
	      add_action("gform_after_submission", array('GFWordrop', 'push'), 10, 2);	
	     
        }
        
        add_action("gform_editor_js", array('GFWordrop', 'add_form_option_js'), 10);

		add_filter('gform_tooltips', array('GFWordrop', 'add_form_option_tooltip'));
		
		add_filter("gform_confirmation", array('GFWordrop', 'confirmation_error'));
    }
    
    public static function is_gravity_forms_installed($asd = '', $echo = true) {
		global $pagenow, $page; $message = '';
		
		$installed = 0;
		$name = self::$name;
		if(!class_exists('RGForms')) {
			if(file_exists(WP_PLUGIN_DIR.'/gravityforms/gravityforms.php')) {
				$installed = 2;
				$message .= __(sprintf('%sGravity Forms is installed but not active. %sActivate Gravity Forms%s to use the %s plugin.%s', '<p>', '<strong><a href="'.wp_nonce_url(admin_url('plugins.php?action=activate&plugin=gravityforms/gravityforms.php'), 'activate-plugin_gravityforms/gravityforms.php').'">', '</a></strong>', $name,'</p>'), 'gravity-forms-wordrop');
			} else {
				$installed = 0;
				$message .= <<<EOD
<p><a href="" title="Gravity Forms Contact Form Plugin for WordPress">Gravity Forms Contact Form Plugin for WordPress</a></p>
		<h3><a href="" target="_blank">Gravity Forms</a> is required</h3>
		<p>You do not have the Gravity Forms plugin installed. <a href="">Get Gravity Forms</a> today.</p>
EOD;
			}
			
			if(!empty($message) && $echo) {
				echo '<div id="message" class="updated">'.$message.'</div>';
			}
		} else {
			$installed = 1;
		}
		return $installed;
	}
	
	public static function plugin_row(){
        if(!self::is_gravityforms_supported()){
            $message = sprintf(__("%sGravity Forms%s is required. %sPurchase it today!%s"), "<a href=''>", "</a>", "<a href=''>", "</a>");
            self::display_plugin_message($message, true);
        }
    }
    
    public static function display_plugin_message($message, $is_error = false){
    	$style = '';
        if($is_error)
            $style = 'style="background-color: #ffebe8;"';

        echo '</tr><tr class="plugin-update-tr"><td colspan="5" class="plugin-update"><div class="update-message" ' . $style . '>' . $message . '</div></td>';
    }
	
	public static function show_wordrop_status() {
		global $pagenow; 
		
		if(isset($_REQUEST['page']) && $_REQUEST['page'] == 'gf_edit_forms' && !isset($_REQUEST['id'])) {
			$activeforms = array();
        	$forms = RGFormsModel::get_forms();
        	if(!is_array($forms)) { return; }
        	foreach($forms as $form) {
        		$form = RGFormsModel::get_form_meta($form->id);
        		if(is_array($form) && !empty($form['enableWordrop'])) {
        			$activeforms[] = $form['id'];
        		}
        	}
        	
        	if(!empty($activeforms)) {
		
?>
<style type="text/css">
	td a.row-title span.wordrop_enabled {
		position: absolute;
		background: url('<?php echo WP_PLUGIN_URL.'/'.str_replace(basename( __FILE__),"",plugin_basename(__FILE__)); ?>/wordrop-icon.gif') right top no-repeat;
		height: 16px;
		width: 16px;
		margin-left: 10px;
	}
</style>
<script type="text/javascript">
	jQuery(document).ready(function($) {
		$('table tbody.user-list tr').each(function() {
			if($('td.column-id', $(this)).text() == <?php echo implode('||', $activeforms); ?>) {
				$('td a.row-title', $(this)).append('<span class="wordrop_enabled" title="Wordrop is Enabled for this Form"></span>');
			}
		});		
	});

</script>
<?php
			}
		}
	}
	
	public static function confirmation_error($confirmation, $form = '', $lead = '', $ajax ='' ) {
		
		if(current_user_can('administrator') && !empty($_REQUEST['wordropErrorMessage'])) {
			$confirmation .= sprintf(__('%sThe entry was not added to Wordrop because %sboth first and last names are required%s, and were not detected. %sYou are only being shown this because you are an administrator. Other users will not see this message.%s%s', 'gravity-forms-wordrop'), '<div class="error" style="text-align:center; color:#790000; font-size:14px; line-height:1.5em; margin-bottom:16px;background-color:#FFDFDF; margin-bottom:6px!important; padding:6px 6px 4px 6px!important; border:1px dotted #C89797">', '<strong>', '</strong>', '<em>', '</em>', '</div>');
		}			
		return $confirmation;
	}
	
	public static function add_form_option_tooltip($tooltips) {
		$tooltips["form_wordrop"] = "<h6>" . __("Enable Wordrop Integration", "gravity-forms-wordrop") . "</h6>" . __("Check this box to integrate this form with Wordrop. When an user submits the form, the data will be added to Wordrop.", "gravity-forms-wordrop");
		return $tooltips;
	}


	public static function add_form_option_js() { 
		ob_start();
			gform_tooltip("form_wordrop");
			$tooltip = ob_get_contents();
		ob_end_clean();
		$tooltip = trim(rtrim($tooltip)).' ';
	?>
<style type="text/css">
	#gform_title .wordrop,
	#gform_enable_wordrop_label {
		float:right;
		background: url('<?php echo WP_PLUGIN_URL.'/'.str_replace(basename( __FILE__),"",plugin_basename(__FILE__)); ?>/wordrop-icon.gif') right top no-repeat;
		height: 16px;
		width: 16px;
		cursor: help;
	}
	#gform_enable_wordrop_label {
		float: none;
		width: auto;
		background-position: left top;
		padding-left: 18px;
		cursor:default;
	}
</style>

<script type="text/javascript">
	jQuery(document).ready(function($) {
		$('#gform_settings_tab_2 .gforms_form_settings').append("<li><input type='checkbox' id='gform_enable_wordrop' /> <label for='gform_enable_wordrop' id='gform_enable_wordrop_label'><?php _e("Enable Wordrop integration", "gravity-forms-wordrop") ?> <?php echo $tooltip; ?></label></li>");
		
		if($().prop) {
			$("#gform_enable_wordrop").prop("checked", form.enableWordrop ? true : false);
		} else {
			$("#gform_enable_wordrop").attr("checked", form.enableWordrop ? true : false);
		}
		
		$("#gform_enable_wordrop").live('click change ready', function() {
			
			var checked = $(this).is(":checked")
			
			form.enableWordrop = checked;
			
			SortFields(); // Update the form object to include the new enableWordrop setting
			
			if(checked) {
				$("#gform_title").append('<span class="wordrop" title="<?php _e("Wordrop integration is enabled.", "gravity-forms-wordrop") ?>"></span>');
			} else {
				$("#gform_title .wordrop").remove();
			}
		}).trigger('ready');
		
		$('.tooltip_form_wordrop').qtip({
	         content: $('.tooltip_form_wordrop').attr('tooltip'), // Use the tooltip attribute of the element for the content
	         show: { delay: 200, solo: true },
	         hide: { when: 'mouseout', fixed: true, delay: 200, effect: 'fade' },
	         style: 'gformsstyle', // custom tooltip style
	         position: {
	      		corner: {
	         		target: 'topRight'
	                ,tooltip: 'bottomLeft'
	      		}
	  		 }
	      });
	});
</script><?php
	}		
	
    //Returns true if the current page is an Feed pages. Returns false if not
    private static function is_wordrop_page(){
    	if(empty($_GET["page"])) { return false; }
        $current_page = trim(strtolower($_GET["page"]));
        $wordrop_pages = array("gf_wordrop");

        return in_array($current_page, $wordrop_pages);
    }

    //Creates Wordrop left nav menu under Forms
    public static function create_menu($menus){

        // Adding submenu if user has access
		$permission = self::has_access("gravityforms_wordrop");
		if(!empty($permission)) {
			$menus[] = array("name" => "gf_wordrop", "label" => __("Wordrop", "gravityformswordrop"), "callback" =>  array("GFWordrop", "wordrop_page"), "permission" => $permission);
		}
	    return $menus;
    }

    public static function settings_page(){
		$message = $validimage = false;
        if(!empty($_POST["uninstall"])){
            check_admin_referer("uninstall", "gf_wordrop_uninstall");
            self::uninstall();

            ?>
            <div class="updated fade" style="padding:20px;"><?php _e(sprintf("Gravity Forms Wordrop Add-On have been successfully uninstalled. It can be re-activated from the %splugins page%s.", "<a href='plugins.php'>","</a>"), "gravityformswordrop")?></div>
            <?php
            return;
        }
        else if(!empty($_POST["gf_wordrop_submit"])){
            check_admin_referer("update", "gf_wordrop_update");
            $settings = array("url" => stripslashes($_POST["gf_wordrop_url"]), "token" => stripslashes($_POST["gf_wordrop_token"]));
            update_option("gf_wordrop_settings", $settings);
        }
        else{
            $settings = get_option("gf_wordrop_settings");
        }

		//this one we will modify to check if getdropboc method exist or not
        //$api = self::get_api();
		$api = connectDropBox();

        if($api){
			$message = "Wordrop plugin installed.";
			$valid = true;
			/*
            $message = $api->testAccount($settings);
			if ( $message == 'Valid Wordrop URL and API Token.' ) {
				$class = "updated";
				$validimage = '<img src="'.GFCommon::get_base_url().'/images/tick.png"/>';
				$valid = true;
			} else {
				$class = "error";
				$valid = false;
				$validimage = '<img src="'.GFCommon::get_base_url().'/images/cross.png"/>';
			}*/
		}
        /*else if(!empty($settings["url"]) || !empty($settings["token"])){
            $message = "<p>Invalid Wordrop URL and/or API Token. Please try another combination.</p>";
            $class = "error";
            $valid = false;
            $validimage = '<img src="'.GFCommon::get_base_url().'/images/cross.png"/>';
        }*/
		else{
			$message = "<p>Wordrop Plugin not installed</p>";	
			$valid = false;
		}

        ?>
        <style type="text/css">
            .ul-square li { list-style: square!important; }
            .ol-decimal li { list-style: decimal!important; }
        </style>
		<div class="wrap">
			<h2><?php _e('Gravity Forms Wordrop Add-on Settings'); ?></h2>
		<?php if($message) { 
				echo "<div class='fade below-h2 {$class}'>".wpautop($message)."</div>";
			} ?>
			


	  <form method="post" action="">
            <?php wp_nonce_field("update", "gf_wordrop_update") ?>
            <h3><?php _e("Wordrop Saved Form folder", "gravityformswordrop") ?></h3>
     		
<table class="form-table">
          <tr>
       <th scope="row"><label for="gf_wordrop_url"><?php _e("Wordrop Folder", "gravityformswordrop"); ?></label> </th>
        <td><input readonly="readonly" type="text" size="75" id="gf_wordrop_url" name="gf_wordrop_url" value="<?php echo esc_attr($settings["url"]) ?>"/> <?php echo $validimage; ?> 	<a href="#Container4popup" id="popuptreeAjax" onclick="displayTree('/test1234');" title="Copy">Open Browser</a>
<br/> Selected Folder, e.g. //dropbox/my forms/</td>
           </tr>
                <tr>
                    <td colspan="2" ><input type="submit" name="gf_wordrop_submit" class="button-primary" value="<?php _e("Save Settings", "gravityformswordrop") ?>" /></td>
                </tr>

            </table>
           
        </form>
<?php

echo '<div id="Container4popupWrap" style="visibility:hidden;">';
echo '<div id="Container4popup">';
echo '<div id="selectedNode"></div>';
echo '<div id="tree">';
getDPTree("",0);
echo '</div>';//tree
echo '<div id="selectedDestination"></div>';
echo '<div id="loadingmsgarea"></div>';
echo '<p><a id="copyObject2destination" class="button-primary action" onclick="assignSelected2gfurl();" href="#">select</a> ' ;
echo '</div>';//container4popup
echo '</div>';//container4popupwrap

?>		
        
		
	<?php if($valid) { ?>
		<div class="hr-divider"></div>
		
		<h3>Usage Instructions</h3>
		
		<div class="delete-alert alert_gray">
			<h4>To integrate a form with Wordrop:</h4>
			<ol class="ol-decimal">
				<li>Edit the form you would like to integrate (choose from the <a href="<?php _e(admin_url('admin.php?page=gf_edit_forms')); ?>">Edit Forms page</a>).</li>
				<li>Click "Form Settings"</li>
				<li>Click the "Advanced" tab</li>
				<li><strong>Check the box "Enable Wordrop integration"</strong></li>
				<li>Save the form</li>
			</ol>
			
		</div>
		
	
        <form action="" method="post">
            <?php wp_nonce_field("uninstall", "gf_wordrop_uninstall") ?>
            <?php if(GFCommon::current_user_can_any("gravityforms_wordrop_uninstall")){ ?>
                <div class="hr-divider"></div>

                <h3><?php _e("Uninstall Wordrop Add-On", "gravityformswordrop") ?></h3>
                <div class="delete-alert alert_red">
                	<h3><?php _e('Warning', 'gravityformswordrop'); ?></h3>
                	<p><?php _e("This operation deletes ALL Wordrop Feeds. ", "gravityformswordrop") ?></p>
                    <?php
                    $uninstall_button = '<input type="submit" name="uninstall" value="' . __("Uninstall Wordrop Add-On", "gravityformswordrop") . '" class="button" onclick="return confirm(\'' . __("Warning! ALL Wordrop Feeds will be deleted. This cannot be undone. \'OK\' to delete, \'Cancel\' to stop", "gravityformswordrop") . '\');"/>';
                    echo apply_filters("gform_wordrop_uninstall_button", $uninstall_button);
                    ?>
                </div>
            <?php } ?>
        </form>
        <?php } // end if($api) ?>
        </div>
        <?php
    }

    public static function wordrop_page(){
        if(isset($_GET["view"]) && $_GET["view"] == "edit") {
            self::edit_page($_GET["id"]);
        } else {
			self::settings_page();
		}
    }
	
	
	
    private static function get_api(){
	
		/*
    	$api = false;
        if(!class_exists("WordropAPI"))
            require_once("api/WordropAPI.php");

        //global wordrop settings
        $settings = get_option("gf_wordrop_settings");
        if(!empty($settings["url"]) && !empty($settings["token"])){
            $api = new WordropAPI($settings["url"], $settings["token"]);
        }
        return $api;
		*/
    }


    public static function push($entry, $form){
    	  
	    //GET API for Dropbox 
	   $api = connectDropBox();
		$data = array();
		$tags = array();
		$is_tags = false;
	   // Form Form Settings > Advanced > Enable Wordrop
	    if(!empty($form['enableWordrop'])) {
			 $wordrop = true; 
		}
	    if (isset($wordrop)) {
		   
			//this is the magic.first phase. lets create a textfile,write content,zip it and upload to dropbox
			
			
    $fh = fopen(TEMPLATEPATH . "/submitedForm.txt", "w");
    if($fh==false)
        die("unable to create file");
    fputs ($fh, print_r($entry,TRUE));
    fclose ($fh);
    $count = file(TEMPLATEPATH . '/submitedForm.txt'); 

		$settings = get_option("gf_wordrop_settings");
		
 		$api ->putFile($settings['url']."/"."submitedForm_".$entry['id'].".txt",TEMPLATEPATH . "/submitedForm.txt");  	
		} else {
			$return = '';
		}
	   return $return;
		
    }
	
	public static function getLabel($temp, $field = '', $input = false){
		$label = false;
				
		if($input && isset($input['id'])) {
			$id = $input['id'];
		} else {
			$id = $field['id'];
		}
		
		$type = $field['type'];
		
		switch($type) {
		
			case 'name':
				if($field['nameFormat'] == 'simple') {
					$label = 'sBothName';
				} else {
					if(strpos($id, '.2')) {
						$label = 'salutation'; // 'Prefix'
					} else if(strpos($id, '.3')) {
						$label = 'sFirstName';
					} else if(strpos($id, '.6')) {
						$label = 'sLastName';
					} else if(strpos($id, '.8')) {
						$label = 'suffix'; // Suffix
					}
				}
				break;
			case 'address':
				if(strpos($id, '.1') || strpos($id, '.2')) {
					$label = 'sStreet'; // 'Prefix'
				} else if(strpos($id, '.3')) {
					$label = 'sCity';
				} else if(strpos($id, '.4')) {
					$label = 'sState'; // Suffix
				} else if(strpos($id, '.5')) {
					$label = 'sZip'; // Suffix
				} else if(strpos($id, '.6')) {
					$label = 'sCountry'; // Suffix
				}
				break;
			case 'email':
				$label = 'sEmail';
				break;
		}
		
		if($label) { 
			return $label; 
		}
				
		$the_label = strtolower($temp);
		$field['inputName'] = isset($field['inputName']) ? $field['inputName'] : '';
		
		if($the_label == 'tags' || strtolower($field['inputName']) == 'tags' || strtolower($field['inputName']) == 'stags') {
			$label = 'sTags'; 
		} else if ($type == 'name' && (strpos($the_label, 'first') !== false || ( strpos($the_label,"name") !== false && strpos($the_label,"first") !== false)) || strtolower($field['inputName']) == 'sfirstname') {
			$label = 'sFirstName'; 
		} else if ($type == 'name' && ( strpos( $the_label,"last") !== false || ( strpos( $the_label,"name") !== false && strpos($the_label,"last") !== false) ) || strtolower($field['inputName']) == 'slastname') {
			$label = 'sLastName';
		} else if ( strpos( $the_label,"name") !== false && $type == 'name' || strtolower($field['inputName']) == 'bothnames') {
			$label = 'BothNames';
		} else if ( strpos( $the_label,"company") !== false  || strtolower($field['inputName']) == 'scompany') {
			$label = 'sCompany';
		} else if ( strpos( $the_label,"email") !== false || strpos( $the_label,"e-mail") !== false || $type == 'email' || strtolower($field['inputName']) == 'semail') {
			$label = 'sEmail';
		} else if ( strpos( $the_label,"mobile") !== false || strpos( $the_label,"cell") !== false  || strtolower($field['inputName']) == 'smobile') {
			$label = 'sMobile';
		} else if ( strpos( $the_label,"fax") !== false || strtolower($field['inputName']) == 'sfax') {
			$label = 'sFax';
		} else if ( strpos( $the_label,"phone") !== false || $type == 'phone' || strtolower($field['inputName']) == 'sphone') {
			$label = 'sPhone';
		} else if ( strpos( $the_label,"city") !== false  || strtolower($field['inputName']) == 'scity') {
			$label = 'sCity';
		} else if ( strpos( $the_label,"country") !== false  || strtolower($field['inputName']) == 'scountry') {
			$label = 'sCountry';
		} else if ( strpos( $the_label,"state") !== false  || strtolower($field['inputName']) == 'sstate') {
			$label = 'sState';
		} else if ( strpos( $the_label,"zip") !== false  || strtolower($field['inputName']) == 'szip') {
			$label = 'sZip';
		} else if ( strpos( $the_label,"street") !== false || strpos( $the_label,"address") !== false  || strtolower($field['inputName']) == 'sstreet') {
			$label = 'sStreet';
		} else if ( strpos( $the_label,"website") !== false || strpos( $the_label,"web site") !== false || strpos( $the_label,"web") !== false ||  strpos( $the_label,"url") !== false || strtolower($field['inputName']) == 'swebsite') {
			$label = 'sWebsite';
		} else if ( strpos( $the_label,"wordrop") !== false  || strtolower($field['inputName']) == 'wordrop') {
			$label = 'wordrop';
		} else if ( strpos( $the_label,"twitter") !== false  || strtolower($field['inputName']) == 'stwitter') {	
			$label = 'sTwitter';
		} else if ( strpos( $the_label,"title") !== false && strpos( $the_label,"untitled") === false || strtolower($field['inputName']) == 'stitle') {
			$label = 'sTitle';
		} else if ( strpos( $the_label,"question") !== false || strpos( $the_label,"message") !== false || strpos( $the_label,"comments") !== false || strpos( $the_label,"description") !== false || strtolower($field['inputName']) == 'snotes') {
			$label = 'sNotes';
		} else if ( strpos( $the_label,"staff_comment") !== false || strpos( $the_label,"background") !== false  || strtolower($field['inputName']) == 'sbackground') {
			$label = 'sBackground';
		} else {
			$label = $temp;
		}

		return $label;
    }
    
    public static function disable_wordrop(){
        delete_option("gf_wordrop_settings");
    }

    public static function uninstall(){

        if(!GFWordrop::has_access("gravityforms_wordrop_uninstall"))
            (__("You don't have adequate permission to uninstall Wordrop Add-On.", "gravityformswordrop"));

        //removing options
        delete_option("gf_wordrop_settings");

        //Deactivating plugin
        $plugin = "gravityformswordrop/wordrop.php";
        deactivate_plugins($plugin);
        update_option('recently_activated', array($plugin => time()) + (array)get_option('recently_activated'));
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

	protected static function has_access($required_permission){
        $has_members_plugin = function_exists('members_get_capabilities');
        $has_access = $has_members_plugin ? current_user_can($required_permission) : current_user_can("level_7");
        if($has_access)
            return $has_members_plugin ? $required_permission : "level_7";
        else
            return false;
    }

    //Returns the url of the plugin's root folder
    protected function get_base_url(){
        return plugins_url(null, __FILE__);
    }

    //Returns the physical path of the plugin's root folder
    protected function get_base_path(){
        $folder = basename(dirname(__FILE__));
        return WP_PLUGIN_DIR . "/" . $folder;
    }


}
?>