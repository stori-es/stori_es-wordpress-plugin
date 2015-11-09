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

define('NAMESPACE_SEPARATOR', '\\');
define('STORI_ES_CLASS_NAMESPACE', 'stori_es');
define('STORI_ES_PATH', plugin_dir_path(__FILE__));
define('STORI_ES_CLASS_PATH', STORI_ES_PATH . 'includes/classes/');
define('STORI_ES_URL',  plugin_dir_url(__FILE__));
define('STORI_ES_API_SUCCESS', 'SUCCESS');
define('STORI_ES_API_ERROR',   'ERROR');
define('STORI_ES_API_INVALID', 'INVALID');
define('STORI_ES_RESOURCE_STORY', 'story');
define('STORI_ES_RESOURCE_COLLECTION', 'collection');

global $wpdb;

$HttpHeaders = array(
	  'Accept: application/json',
		'Authorization: BASIC ' . strtoupper(get_option('stori_es_api_key')),
		'Cache-Control: no-cache');


// Autoload classes
spl_autoload_register(function( $class_name ){
	// Ensure we are only autoloading stori_es classes
	if( substr($class_name, 0, 8) === STORI_ES_CLASS_NAMESPACE ){
		$class_path = str_replace(NAMESPACE_SEPARATOR, DIRECTORY_SEPARATOR, $class_name);
	  include_once(STORI_ES_CLASS_PATH . $class_path . '.class.php');
	}
});


function stori_es_set_options( $options_array ){
	foreach( $options_array as $key => $value ){
		update_option($key, trim($options_array[$key]));
	}
	return true;
}


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
	header('Location: ' . get_site_url() . '/wp-admin/options-general.php?page=stori_es_settings');
	die();
}


// Global $CurlRequest object created at the end of this class file
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


// [stori.es resource="xxxx" id="xxxx" include="xxxx"]
add_shortcode('stori.es', 'stori_es_shortcode');
function stori_es_shortcode( $attributes ){
	$parameters = stori_es_shortcode_parameters($attributes);

	switch( $parameters['resource'] ){
		case STORI_ES_RESOURCE_STORY:
		  $story = stori_es_get_story($parameters);
			$output = $story->output($parameters['include_array']);
			break;
		case STORI_ES_RESOURCE_COLLECTION:
			$collection = stori_es_get_collection($parameters);
			$output = $collection->output(3, $parameters['include_array']);
			break;
	}

	return($output);
}


// Process shortcode attributes into parameters
function stori_es_shortcode_parameters( $attributes ){
	$attributes['resource'] = empty($attributes['resource']) ? 'story' : strtolower($attributes['resource']);

	switch( $attributes['resource'] ){
		case STORI_ES_RESOURCE_STORY:
			$default_story_attributes = array('resource' => STORI_ES_RESOURCE_STORY, 'id' => '', 'include' => 'content');
			$parameters = shortcode_atts($default_story_attributes, $attributes, 'stori_es');
			break;
		case STORI_ES_RESOURCE_COLLECTION:
			$default_collection_attributes = array('resource' => STORI_ES_RESOURCE_COLLECTION, 'id' => '', 'include' => 'content', 'limit' => 3);
			$parameters = shortcode_atts($default_collection_attributes, $attributes, 'stori_es');
			break;
	}

	$parameters['include'] = preg_replace('/\s+/', '', $parameters['include']);
	$parameters['include_array'] = explode(',', $parameters['include']);

	return($parameters);
}


// [stori.es resource="story" id="xxxx" include="xxxx"]
function stori_es_get_story( $parameters ){
	global $CurlRequest, $HttpHeaders;

	// GET Story
	$CurlRequest->setHttpHeaders($HttpHeaders);
	$CurlRequest->createCurl(get_option('stori_es_api_url') . 'stories/' . $parameters['id']);
	$objStory = json_decode($CurlRequest->getContent());

	if( $objStory->meta->status == STORI_ES_API_SUCCESS ){
		$story = new \stori_es\Story($objStory->stories[0]);

		// GET byline via Story Owner Profile
		if( in_array('byline', $parameters['include_array']) ){
			$StoryOwnerUrl = $objStory->stories[0]->links->owner->href;
			$CurlRequest->createCurl($StoryOwnerUrl);
			$objStoryOwner = json_decode($CurlRequest->getContent());

			if( $objStoryOwner->meta->status == STORI_ES_API_SUCCESS )
				$story->owner = new \stori_es\Profile($objStoryOwner->profiles[0]);
		}

		// GET default Content Document
		$DocumentUrl = $objStory->stories[0]->links->default_content->href;
		$CurlRequest->createCurl($DocumentUrl);
		$objDocument = json_decode($CurlRequest->getContent());

	 	if( $objDocument->meta->status == STORI_ES_API_SUCCESS )
			$story->content = new \stori_es\Document($objDocument->documents[0]);
	}

	return($story);
}


// [stori.es resource="collection" id="xxxx" include="xxxx"]
function stori_es_get_collection( $parameters ){
	global $CurlRequest, $HttpHeaders;

	// GET Collection
	$CurlRequest->setHttpHeaders($HttpHeaders);
	$CurlRequest->createCurl(get_option('stori_es_api_url') . 'collections/' . $parameters['id']);
	$collection_response = json_decode($CurlRequest->getContent());

	if( $collection_response->meta->status == STORI_ES_API_SUCCESS ){
		$collection = new \stori_es\Collection($collection_response->collections[0]);

		// Story links are unsorted for the time being [ TASK-1925 ]; implement
		// temporary creation date descending sort
		$story_links = $collection_response->collections[0]->links->stories;
		usort($story_links, function($a, $b){ return strcmp($b->href, $a->href); });

		$story_limit = (count($story_links) < $parameters['limit']) ? count($story_links) : $parameters['limit'];
		$story_parameters = array('resource' => STORI_ES_RESOURCE_STORY, 'id' => '', 'include' => $parameters['include'], 'include_array' => $parameters['include_array']);
		for( $index = 0; $index < $story_limit; $index++ ){
			$story_link_segments = explode('/', $story_links[$index]->href);
			$story_parameters['id'] = end($story_link_segments);
			$collection->stories[] = stori_es_get_story($story_parameters);
		}
	}

	return($collection);
}


/* Add plugin styles */
add_action( 'admin_enqueue_scripts', 'stori_es_add_admin_styles' );
function stori_es_add_admin_styles(){
	wp_register_style('stori_es-stylesheet-admin', STORI_ES_URL . 'includes/css/stori_es-admin.css');
	wp_enqueue_style('stori_es-stylesheet-admin');
}

add_action( 'wp_enqueue_scripts', 'stori_es_add_public_styles' );
function stori_es_add_public_styles(){
	wp_register_style('stori_es-stylesheet-public', STORI_ES_URL . 'includes/css/stori_es-public.css');
	wp_enqueue_style('stori_es-stylesheet-public');
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
function stori_es_activation(){
	// Plugin options
	$options_array = array(
			'stori_es_api_url' => 'https://stori.es/api/',
			'stori_es_api_key' => ''
	);
	stori_es_set_options($options_array);
}


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


add_action('admin_menu', 'stori_es_add_options_page');
function stori_es_add_options_page(){
  add_options_page('stori.es', 'stori.es', 'administrator', 'stori_es_settings', 'stori_es_view_settings');
}


function stori_es_view_settings(){
	include_once STORI_ES_PATH . 'includes/tpl/settings.html';
}


// Add settings link on Plugin Card
$plugin = plugin_basename(__FILE__);
add_filter("plugin_action_links_$plugin", 'stori_es_settings_link');
function stori_es_settings_link( $links ){
	$settings_link = '<a href="options-general.php?page=stori_es_settings">Settings</a>';
	array_unshift($links, $settings_link);
	return $links;
}
