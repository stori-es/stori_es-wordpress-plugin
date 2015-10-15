<?php
/**
 * @package stori.es WordPress Plugin
 * @version 1.0
 *
 * Plugin Name: stori.es WordPress Plugin
 * Plugin URI:
 * Description: Enables access to content hosted on the stori.es platform.
 * Version: 1.0
 * Author: Consumer Reports
 * Author URI: http://consumerreports.org/
 */

if( ! defined('ABSPATH') )  exit();  // Exit if accessed directly

define('STORI_ES_PATH', plugin_dir_path(__FILE__));
define('STORI_ES_URL',  plugin_dir_url(__FILE__));
define('STORI_ES_API_SUCCESS', 'SUCCESS');
define('STORI_ES_API_ERROR',   'ERROR');
define('STORI_ES_API_INVALID', 'INVALID');

global $wpdb;

$HttpHeaders = array(
	  'Accept: application/json',
		'Authorization: BASIC ' . strtoupper(get_option('stori_es_api_key')),
		'Cache-Control: no-cache');


// Handle POSTs from the Plugin Settings screen
if( isset($_POST['from']) && trim($_POST['from']) == 'stories' && isset($_POST['action']) && trim($_POST['action']) == 'update' ){
	$options_array = array();
	$locked_fields = array('stori_es_story_pattern', 'stori_es_story_storyteller', 'stori_es_post_type');

	// Filter stori.es options for updating.
	foreach( $_POST as $key => $value ){
		// Skip if verifiable POST variable doesn't exist
		if( !isset($_POST[$key]) )  continue;

		// Skip update of locked form fields
		if( in_array($key, $locked_fields) )  continue;

		// Sanitize API URL value
		if( $key == 'stori_es_api_url' )  $value = stori_es_correct_api_url($value);

		if( strpos($key, 'stori_es') === 0 ){
			$options_array[$key] = $value;
		}
	}

	// Perform the update
	stori_es_set_options($options_array);

	// Redirect back to the Plugin Settings screen
	header('Location: ' . get_site_url() . '/wp-admin/edit.php?post_type=stori_es&page=stori-es-settings');
	die();
}


function stori_es_set_options( $options_array ){
	foreach( $options_array as $key => $value ){
		update_option($key, trim($options_array [$key]));
	}
	return true;
}


// CurlRequest object creating in the end of the class file
include_once STORI_ES_PATH . 'includes/class.CurlRequest.php';


function stori_es_correct_api_url( $url ){
	if( substr(trim($url), -1) !== '/' )  $url .= '/';
	return $url;
}


add_action( 'wp_ajax_validate_api_url', 'stori_es_validate_apiurl_callback' );
function stori_es_validate_apiurl_callback( $local = false ){
	global $HttpHeaders;
	$result = 0;

	if( isset($_POST['api_url']) ){
		$api_url = stori_es_correct_api_url($_POST['api_url']);
		$lHttpHeaders = $HttpHeaders;
		unset($lHttpHeaders[1]);

		// GET /users/self JSON to confirm API access
		$CurlRequest = new CurlRequest ();
		$CurlRequest->setHttpHeaders($lHttpHeaders);
		$CurlRequest->setCustomRequest();
		$CurlRequest->createCurl( $api_url . 'users/self' );
		json_decode($CurlRequest->getContent());

		$result = $CurlRequest->getHttpStatus();
	}

	// Return result value to ajax script
	$result_value = ($result == '200') ? STORI_ES_API_SUCCESS : STORI_ES_API_ERROR;

	if( $local )  return($result_value);
	echo($result_value);
	wp_die();
}


add_action( 'wp_ajax_validate_api_key', 'stori_es_validate_apikey_callback' );
function stori_es_validate_apikey_callback() {
	global $HttpHeaders;

	if( isset($_POST['api_key']) && (stori_es_validate_apiurl_callback(true) == STORI_ES_API_SUCCESS) ){
		$lHttpHeaders = $HttpHeaders;
		$lHttpHeaders[1] = 'Authorization: BASIC '. $_POST['api_key'];
		$api_url = stori_es_correct_api_url($_POST['api_url']);

		// GET /users/self JSON to confirm API access
		$CurlRequest = new CurlRequest ();
		$CurlRequest->setHttpHeaders($lHttpHeaders);
		$CurlRequest->createCurl ( $api_url . 'users/self' );
		$objUser = json_decode($CurlRequest->getContent());

		echo $objUser->meta->status; // return value to ajax script
	} else {
		echo STORI_ES_API_INVALID;
	}

	wp_die();
}


// [stori.es resource="xxxx" id="xxxx"]
add_shortcode('stori.es', 'stori_es_get_story');
function stori_es_get_story( $atts ){
	global $CurlRequest, $HttpHeaders;

	$params = shortcode_atts( array('id' => '','resource' => 'story','include' => ''), $atts );
	$params['include'] = preg_replace('/\s+/', '', $params['include']);
	$arrIncludes = (trim($params['include']) != "" ? explode(",", $params['include']) : array());
	$content = "";
	$title = "";
	$byline = "";
	$byline_pos = "top";

	if(in_array('content',$arrIncludes) && in_array('byline',$arrIncludes)){
		if(array_search('byline',$arrIncludes) > array_search('content',$arrIncludes))
		$byline_pos = "bottom";
	}

	//get story json based on passed to shortcode story id
	$CurlRequest->setHttpHeaders($HttpHeaders);
	$CurlRequest->createCurl ( get_option('stori_es_api_url') . 'stories/' . $params['id'] );

	$objStory = json_decode($CurlRequest->getContent());

	if($objStory->meta->status == 'SUCCESS'){
		//get story document byline
		if(in_array('byline',$arrIncludes)){
			$StoryOwnerUrl = $objStory->stories[0]->links->owner->href;
			$CurlRequest->createCurl ( $StoryOwnerUrl );
			$objStoryOwner = json_decode($CurlRequest->getContent());

			if($objStoryOwner->meta->status == 'SUCCESS'){
				$byline = trim($objStoryOwner->profiles[0]->given_name);

				$arrContactData = $objStoryOwner->profiles[0]->contacts;
				foreach ($arrContactData as $key=>$contact_data){

					if($contact_data->contact_type == "GeolocationContact"){
						if(!empty($byline))
							$byline  .= " of ";
						if(trim($contact_data->location->city) != "")
								$byline  .= ucfirst(strtolower(trim($contact_data->location->city)));
						if(trim($contact_data->location->state) != ""){
							if(trim($byline) != "")
								$byline  .= ", ";
							$byline  .= strtoupper(trim($contact_data->location->state));
						}
					}
				}
			}
		}
		//get story document content
		$DocumentUrl = $objStory->stories[0]->links->default_content->href;
		$CurlRequest->createCurl ( $DocumentUrl );
		$objDocument = json_decode( $CurlRequest->getContent() );

		if($objDocument->meta->status == 'SUCCESS'){
			//get default_content deocument title
			if(in_array('title',$arrIncludes)){
				if(isset($objDocument->documents[0]->title)){
					$title = wp_strip_all_tags( $objDocument->documents[0]->title );
				}else{
					$title = "Untitled";
				}
			}

			//get default_content deocument content
			foreach ($objDocument->documents[0]->blocks as $key=>$block){
				if($block->block_type == 'TextContentBlock')
					$content .= $block->value;
			}
		}
	}

	$wrapper  = '<div id="stori_es-story-'. $params["id"] . '" class="stori_es-story">';
	if(count($arrIncludes) === 1){
		switch (current($arrIncludes)){
			case "title":
				$wrapper .=  '<div class="stori_es-story-title">' . $title . '</div>';
				break;
			case "byline":
				$wrapper .=  '<div class="stori_es-story-byline">' . $byline . '</div>';
				break;
		}
	}else{
		if(in_array('title',$arrIncludes))
			$wrapper .=  '<div class="stori_es-story-title">' . $title . '</div>';
		if(in_array('byline',$arrIncludes) && $byline_pos === 'top')
			$wrapper .=  '<div class="stori_es-story-byline">' . $byline . '</div>';

		$wrapper .= '<div class="stori_es-story-content">' . $content . '</div>';

		if(in_array('byline',$arrIncludes) && $byline_pos === 'bottom')
			$wrapper .=  '<div class="stori_es-story-byline">' . $byline . '</div>';
	}
  	$wrapper .= '</div>';

	return $wrapper;
}


/* Add plugin styles */
add_action( 'admin_enqueue_scripts', 'stori_es_adding_styles' );
function stori_es_adding_styles(){
	wp_register_style('stori-es-stylesheet', STORI_ES_URL . 'includes/css/stori-es-stylesheet.css');
	wp_enqueue_style('stori-es-stylesheet');
}


/* Add plugin JavaScripts */
add_action( 'admin_enqueue_scripts', 'stori_es_adding_scripts' );
function stori_es_adding_scripts(){
	wp_register_script('stori-es-script', STORI_ES_URL . 'includes/js/stori_es-api.js', array('jquery','jquery-ui-core'));
	wp_enqueue_script('stori-es-script');

	// In JavaScript, object properties are accessed as ajax_object.ajax_url, ajax_object.we_value
	wp_localize_script('stori-es-script', 'ajax_object', array('ajax_url' => admin_url('admin-ajax.php')));
	wp_localize_script('stori-es-script', 'php_vars', array(
		  'api_url' => get_option('stori_es_api_url'),
			'api_key' => get_option('stori_es_api_key')
	));
}


register_activation_hook(__FILE__, 'stori_es_activation');
function stori_es_activation(){}


register_deactivation_hook(__FILE__, 'stori_es_deactivation');
function stori_es_deactivation(){
	delete_option('stori_es_api_key');
}


register_uninstall_hook(__FILE__, 'stori_es_uninstall');
function stori_es_uninstall(){
	global $wpdb;
	delete_option('stori_es_api_url');
	delete_option('stori_es_api_key');
	return true;
}


function stori_es_view_settings(){
	include_once STORI_ES_PATH . 'includes/tpl/settings.html';
}


// Add settings link on Plugin Card
$plugin = plugin_basename(__FILE__);
add_filter("plugin_action_links_$plugin", 'stori_es_settings_link');
function stori_es_settings_link( $links ){
	$settings_link = '<a href="edit.php?post_type=stori_es&page=stori-es-settings">Settings</a>';
	array_unshift($links, $settings_link);
	return $links;
}
