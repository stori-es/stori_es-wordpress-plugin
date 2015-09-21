<?php
/**
 * @package stori.es WordPress Plugin
 * @version 1.0
 */

/*
 * Plugin Name: stori.es WordPress Plugin
 * Plugin URI:
 * Description: Enables access to content hosted on the stori.es platform.
 * Version: 1.0
 * Author: Sound Strategies Inc.
 * Author URI: http://soundst.com
 */


if (! defined ( 'ABSPATH' ))
	exit (); // Exit if accessed directly

global $wpdb;

define ( 'CU_STORIES_DIR', plugin_dir_path ( __FILE__ ) );
define ( 'CU_STORIES_URL', plugin_dir_url ( __FILE__ ) );
define ( 'CU_STORIES_TABLE', $wpdb->prefix . 'stories' );

$HttpHeaders = array( 'Accept: application/json',
					  'Authorization: BASIC '. strtoupper(get_option('custory_api_key')),
					  'Cache-Control: no-cache');

if (isset ( $_POST ["from"] ) && trim ( $_POST ["from"] ) == "stories" && isset ( $_POST ["action"] ) && trim ( $_POST ["action"] ) == "update") {
	$options_array = array ();

	foreach ( $_POST as $key => $value ) {
		
		if( !isset( $_POST[$key] ) ) continue; //skip iteration if verifiable POST variable doesn't exist
		
		// for now we need to skip update of locked form fields
		if(in_array($key, array("custory_story_pattern", "custory_story_storyteller", "custory_post_type")))
			continue;

		if($key == "custory_api_url") $value = cu_stories_correct_api_url($value);

		if($key == "custory_post_category" && trim($_POST[$key]) != get_option("custory_post_category")){

			$category = get_option('custory_post_category');
			$term_id = term_exists($category, 'category');
			$post_category = wp_strip_all_tags(trim($_POST[$key]));

			if ($term_id !== 0 && $term_id !== null){
				wp_update_term($term_id["term_id"], 'category', array('name' => $post_category));
			}else{
				$category_id = wp_insert_term($post_category, 'category', array(
						'slug'=>sanitize_title($post_category),
						'parent'=>0
				));
			}
		}

		if (strpos ( $key, "custory" ) === 0) {
			$options_array [$key] = $value;
		}
	}

	cu_stories_set_options ( $options_array );
	header ( "Location: " . get_site_url () . "/wp-admin/edit.php?post_type=custory&page=cu-stories-settings" );
	die ();
}

//CurlRequest object creting in the end of the class file
include_once CU_STORIES_DIR . 'includes/class.CurlRequest.php';

register_activation_hook ( __FILE__, 'cu_stories_activation' );
register_deactivation_hook ( __FILE__, 'cu_stories_deactivation' );

/**
 * *******************************************************************************************************
 *
 * Editable code section
 *
 * ********************************************************************************************************
 */

function cu_stories_generate_key(){
	global $wpdb;

	do {
		$StoryActivationKey = wp_generate_password(32,false,false);
		$query = "SELECT COUNT(*) FROM " . CU_STORIES_TABLE . " WHERE swp_activation_key = {$StoryActivationKey}";
	} while ($wpdb->get_var( $query ) != 0);

	return $StoryActivationKey;
}

add_action( 'wp_ajax_ctrl_sync', 'cu_stories_ctrl_sync_callback' );
function cu_stories_ctrl_sync_callback(){
	$result = "";

	if(isset($_POST['state']) && $_POST['state'] == 'start'){
		if(isset($_POST['time'])){
			$is_scheduled = wp_next_scheduled ( 'cu_daily_event' );
			if(!empty($is_scheduled)) wp_clear_scheduled_hook ( 'cu_daily_event' );

			// create correct timestamp and schedule synchronization
			$cstDateTime = new DateTime ( date ( "Y-m-d" ) . " " . substr($_POST['time'], 0, -4), new DateTimeZone ( "CST" ) );
			wp_schedule_event ( $cstDateTime->getTimestamp (), 'daily', 'cu_daily_event' );
			$is_scheduled = wp_next_scheduled ( 'cu_daily_event' );

			if($is_scheduled){
				update_option("custory_create_event_status","Scheduled");
				update_option("custory_refresh_rate",$_POST['time']);
			}

			$result = (empty($is_scheduled) ? 'ERROR' : 'SUCCESS');
		}
	}else{
		wp_clear_scheduled_hook ( 'cu_daily_event' );
		$is_scheduled = wp_next_scheduled ( 'cu_daily_event' );

		if(empty($is_scheduled)){
			update_option("custory_refresh_rate","");
			update_option("custory_create_event_status","Unscheduled");
		}

		$result = (empty($is_scheduled) ? 'SUCCESS' : 'ERROR');
	}

	echo $result;
	wp_die();

}

add_action( 'delete_post', 'cu_stories_internal_stories_sync', 10 );
function cu_stories_internal_stories_sync( $pid ) {
	global $wpdb;

	$query = $wpdb->prepare( 'SELECT swp_id FROM ' . CU_STORIES_TABLE . ' WHERE swp_post_id = %d', $pid );

	if ( $wpdb->get_var( $query ) ) {
		$wpdb->query( $wpdb->prepare( 'DELETE FROM ' . CU_STORIES_TABLE . ' WHERE swp_post_id = %d', $pid ) );
	}

	return true;
}

add_action('comment_post', 'cu_stories_notify_author',11,2);
function cu_stories_notify_author($comment_ID, $comment_status) {
	global $wpdb;

	if($comment_status === 1){
		$commentdata=&get_comment($comment_ID, ARRAY_A);
		if(isset($commentdata['comment_post_ID'])){
			$swp_id = $wpdb->get_var( "SELECT swp_id FROM " . CU_STORIES_TABLE . " WHERE swp_post_id = {$commentdata['comment_post_ID']}" );
			if( !is_null($swp_id) ){
				wp_notify_postauthor($comment_ID);
			}
		}
	}
}

function cu_stories_correct_api_url($url){
	if(substr(trim($url), -1) !== "/") $url .= "/";
	return $url;
}

add_action( 'wp_ajax_validate_api_url', 'cu_stories_validate_apiurl_callback' );
function cu_stories_validate_apiurl_callback($local = false) {
	global $HttpHeaders;
	$result = 0;

	if( isset($_POST['api_url']) ){
		$api_url = cu_stories_correct_api_url($_POST['api_url']);
		$lHttpHeaders = $HttpHeaders;
		unset($lHttpHeaders[1]);
		
		//get user self json based used passed API URL
		$CurlRequest = new CurlRequest ();
		$CurlRequest->setHttpHeaders($lHttpHeaders);
		$CurlRequest->setCustomRequest();
		$CurlRequest->createCurl ( $api_url . 'users/self' );
		json_decode($CurlRequest->getContent());
		
		$result = $CurlRequest->getHttpStatus();
	}
		
	if($local) return ($result == "200" ?  "SUCCESS" : "ERROR");

	echo ($result == "200" ?  "SUCCESS" : "ERROR"); // return value to ajax script

	wp_die();
}

add_action( 'wp_ajax_validate_api_key', 'cu_stories_validate_apikey_callback' );
function cu_stories_validate_apikey_callback() {
	global $HttpHeaders;
	
	if(isset($_POST['api_key']) && cu_stories_validate_apiurl_callback(true) == "SUCCESS"){
		$lHttpHeaders = $HttpHeaders;
		$lHttpHeaders[1] = 'Authorization: BASIC '. $_POST['api_key'];
		$api_url = cu_stories_correct_api_url($_POST['api_url']);

		//get user self json based used passed API key
		$CurlRequest = new CurlRequest ();
		$CurlRequest->setHttpHeaders($lHttpHeaders);
		$CurlRequest->createCurl ( $api_url . 'users/self' );
		$objUser = json_decode($CurlRequest->getContent());

		echo $objUser->meta->status; // return value to ajax script
	}else{
		echo "INVALID";
	}

	wp_die();
}

add_action( 'wp_ajax_validate_collection', 'cu_stories_validate_collection_callback' );
function cu_stories_validate_collection_callback() {
	global $HttpHeaders;

	if(isset($_POST['collection_id'])){
		//get story json based on passed to shortcode story id
		$CurlRequest = new CurlRequest ();
		$CurlRequest->setHttpHeaders($HttpHeaders);
		$CurlRequest->createCurl ( get_option('custory_api_url') . 'collections/' . $_POST['collection_id'] );
		$objCollection = json_decode($CurlRequest->getContent());
	
		echo $objCollection->meta->status; // return value to ajax script
	}else{
		echo "INVALID";
	}
	
	wp_die();
}

add_action( 'template_redirect', 'cu_stories_story_activation_redirect' );
function cu_stories_story_activation_redirect($do_redirect) {
	global $wpdb, $HttpHeaders;

	$pattern = get_option("custory_story_pattern");
	$collection = get_option('custory_collection_id');

	if(!empty($pattern) && !empty($collection)){
		if(isset($_GET["activate"]) && isset($_GET["start"])){
			$StoryActivationKey = $_GET["activate"];

			$dataRow = $wpdb->get_row("SELECT * FROM " . CU_STORIES_TABLE . " WHERE swp_activation_key = '{$StoryActivationKey}'", ARRAY_A);

			if(isset($dataRow["swp_post_id"])){
				$story_post = array(
						'ID'           => $dataRow["swp_post_id"],
						'post_status'  => 'publish',
				);

				// Update the post into the database
				wp_update_post( $story_post );

				//update wp_story custom table with activation timestamp
				$wpdb->query("UPDATE " . CU_STORIES_TABLE . "
					  SET swp_activation_timestamp = CURRENT_TIMESTAMP
					  WHERE swp_post_id = " . $dataRow["swp_post_id"]
				);

				$post_link = get_post_permalink($dataRow["swp_post_id"]);

				$estDateTime = new DateTime ( "now", new DateTimeZone ( "EST" ) );

				//send POST request to stori.es site to mark story activated
				$locHttpHeaders = $HttpHeaders;
				$locHttpHeaders[0] = "Content-Type: application/json";

				$objDocumentPost = new stdClass();
				$objDocumentPost->document_type="AttachmentDocument";
				$objDocumentPost->title="Activated and deployed to {$_SERVER['SERVER_NAME']} ({$estDateTime->format('Y-m-d\TH:i:s\Z')})";
				$objDocumentPost->source = $post_link;
				$objDocumentPost->entity_id = $dataRow["swp_story_id"];

				$CurlRequest = new CurlRequest ();
				$CurlRequest->setHttpHeaders($locHttpHeaders);
				$CurlRequest->setPost (json_encode($objDocumentPost));
				$CurlRequest->createCurl ( get_option('custory_api_url') . 'documents' );
				$objResponse = json_decode($CurlRequest->getContent());

				if($do_redirect === ""){
					wp_redirect($post_link);
					die ();
				}
			}
		}
	}
	return true;
}

function cu_stories_set_options($options_array) {
	foreach ( $options_array as $key => $value ) {
		update_option ( $key, trim ( $options_array [$key] ) );
	}
	return true;
}

// [stori.es resource="xxxx" id="xxxx"]
add_shortcode ( 'stori.es', 'cu_stories_get_story' );
function cu_stories_get_story($atts) {
	global $CurlRequest, $HttpHeaders;

	$params = shortcode_atts ( array('id' => '', 'resource' => 'story' ), $atts );
	$result = "";

	//get story json based on passed to shortcode story id
	$CurlRequest->setHttpHeaders($HttpHeaders);
	$CurlRequest->createCurl ( get_option('custory_api_url') . 'stories/' . $params['id'] );

	$objStory = json_decode($CurlRequest->getContent());

	if($objStory->meta->status == 'SUCCESS'){
		//get story document content
		$DocumentUrl = $objStory->stories[0]->links->default_content->href;
		$CurlRequest->createCurl ( $DocumentUrl );
		$objDocument = json_decode( $CurlRequest->getContent() );

		foreach ($objDocument->documents[0]->blocks as $key=>$block){
			if($block->block_type == 'TextContentBlock'){
				$result .= $block->value;
			}
		}
	}

	$wrapper = '<div id="stori_es-story-'. $params["id"] . '" class="stori_es-story">'
  			 . '<div class="stori_es-story-content">'
    		 . $result
  			 . '</div></div>';

	return $wrapper;
}

function cu_stories_process_error_messages($objItem = null){
	$message = "";
	
	if($objItem){
		if(isset($objItem->meta->messages[0]->summary)){
			$message = $objItem->meta->messages[0]->summary;
		}else{
			if(isset($objItem->meta->http_code))
				$message = "HTTP status: " . $objItem->meta->http_code;
		}
	}	
	return $message;
}

add_action ( 'cu_daily_event', 'cu_stories_synchronization' );
function cu_stories_synchronization() {
	global $CurlRequest, $HttpHeaders, $wpdb;

	if(get_option('custory_api_key') !== "" && get_option('custory_collection_id') != ""){
		$arrErrors = array();

		$cu_story_user = array(
				'user_login'    => "",
				'user_firstname'    => "",
				'user_lastname'    => "",
				'user_email'    => "",
		);

		$cu_story_post = array(
				'post_title'    => "",
				'post_content'  => "",
				'post_status'   => "",
				'post_author'   => 0,
				'post_category' => array()
		);


		//get and store locally all stories which available on site.
		$arrLocalStoriesSet = $wpdb->get_results( 'SELECT swp_story_id, swp_post_id FROM ' . CU_STORIES_TABLE, OBJECT_K );

		//set headers for future requests to stori.es
		$CurlRequest->setHttpHeaders($HttpHeaders);

		//get collection object
		$CurlRequest->createCurl ( get_option('custory_api_url') . 'collections/' . get_option('custory_collection_id') );
		$objCollection = json_decode($CurlRequest->getContent());

		if($objCollection->meta->status == 'SUCCESS'){
			//cycle through all collection stories
			$arrStories = $objCollection->collections[0]->links->stories;
			foreach($arrStories as $key=>$story){
				$CurlRequest->createCurl ( $story->href );
				$objStory = json_decode($CurlRequest->getContent());

				if($objStory->meta->status == 'SUCCESS'){
					$arrData = explode("/", $objStory->meta->self);
					$story_id = end($arrData);

					//get default_content story document content
					$DocumentUrl = $objStory->stories[0]->links->default_content->href;
					$CurlRequest->createCurl ( $DocumentUrl );
					$objDocument = json_decode( $CurlRequest->getContent() );

					if($objDocument->meta->status == 'SUCCESS'){
						//default_content title
						if(isset($objDocument->documents[0]->title)){
							$cu_story_post["post_title"] = wp_strip_all_tags( $objDocument->documents[0]->title );
						}else{
							$cu_story_post["post_title"] = "Untitled";
						}

						$cu_story_post["post_content"] = '[stori.es resource="resource" id="' . $story_id . '"]';

						//get response document email
						$cu_story_user["user_email"] = "";

						$ResponseDocumentUrl = $objStory->stories[0]->links->responses[0]->href;
						$CurlRequest->createCurl ( $ResponseDocumentUrl );
						$objResponseDocument = json_decode($CurlRequest->getContent());

						if($objResponseDocument->meta->status == 'SUCCESS'){
							foreach ($objResponseDocument->documents[0]->blocks as $key=>$block){
								if($block->block_type == 'EmailQuestionBlock'){
									$cu_story_user["user_email"] = $block->value;
								}
							}
						}

						//Storyteller information
						$cu_story_user["user_login"] = "";
						$cu_story_user["user_firstname"] = "";
						$cu_story_user["user_lastname"] = "";

						$StoryOwnerUrl = $objStory->stories[0]->links->owner->href;
						$CurlRequest->createCurl ( $StoryOwnerUrl );
						$objStoryOwner = json_decode($CurlRequest->getContent());

						if($objStoryOwner->meta->status == 'SUCCESS'){
							$cu_story_user["user_login"] = $objStoryOwner->profiles[0]->id;
							$cu_story_user["user_firstname"] = $objStoryOwner->profiles[0]->given_name;

							$arrContactData = $objStoryOwner->profiles[0]->contacts;
							foreach ($arrContactData as $key=>$contact_data){

								if($contact_data->contact_type == "GeolocationContact"){
									$cu_story_user["user_lastname"] = "of "
											. ucfirst(strtolower($contact_data->location->city))
											. ","
													. strtoupper($contact_data->location->state);
								}

								if(empty($cu_story_user["user_email"]) && $contact_data->contact_type == "EmailContact"){
									$cu_story_user["user_email"] = $contact_data->value;
								}
							}
						}else{
							$arrErrors[] = array(
									'ErrorType' => "StoryOwner",
									'objURL' => $objStoryOwner->meta->self,
									'ErrorMessage' => cu_stories_process_error_messages($objStoryOwner)
							);
						}

						//Generate Story Activation Keys
						$StoryActivationKey = wp_generate_password(32,false,false);
						$user_name = (empty($cu_story_user["user_email"]) ? $cu_story_user["user_login"] : $cu_story_user["user_email"]);

						// Create user for story author object
						$user_id = username_exists( $user_name );
						$user_id = (empty($user_id) ? email_exists($cu_story_user["user_email"]) : $user_id);

						if ( empty($user_id) ) {
							$random_password = wp_generate_password( $length=12 );
							$user_id = wp_create_user( $user_name, $random_password, $cu_story_user["user_email"] );
							if ( ! is_wp_error( $user_id ) ) {
								$user_id = wp_update_user( array(	'ID' => $user_id,
										'role' => get_option('custory_story_storyteller'),
										'first_name' => $cu_story_user["user_firstname"],
										'last_name' => $cu_story_user["user_lastname"],
										'user_email' => $cu_story_user["user_email"],
										'user_nicename' => $cu_story_user["user_email"],
										'nicename' => $cu_story_user["user_email"],
										'display_name' => $cu_story_user["user_firstname"]
										. " "
										. $cu_story_user["user_lastname"],
								) );
								if ( is_wp_error( $user_id ) ) {
									$arrErrors[] = array(
											'ErrorType' => "WPUserUpdate",
											'objURL' => $objStoryOwner->meta->self,
											'ErrorMessage' => implode(",", $user_id->get_error_messages())
									);
								}
							}else{
								$arrErrors[] = array(
										'ErrorType' => "WPUserCreation",
										'objURL' => $objStoryOwner->meta->self,
										'ErrorMessage' => implode(",", $user_id->get_error_messages())
								);
							}
						}

						if( is_numeric($user_id) ){
							// get category object
							$category = get_option('custory_post_category');
							$category_id = term_exists($category, 'category');

							$post_status = (get_option('custory_story_activation') == "on" ? "publish" : "draft");
							$post_comments_status = (get_option('custory_post_comments') == "on" ? "open" : "closed");

							if(!empty($category_id)){

								if(array_key_exists($story_id,$arrLocalStoriesSet)){
									$story_post = array(
											'ID'           => $arrLocalStoriesSet[$story_id]->swp_post_id,
											'post_title'   => $cu_story_post["post_title"]
									);

									// Update the post into the database
									$post_id = wp_update_post( $story_post, true );
									if (is_wp_error($post_id)) {
										$strErrors = "";
										$errors = $post_id->get_error_messages();

										foreach ($errors as $error) {
											$strErrors .= $error;
										}

										$arrErrors[] = array(
												'ErrorType' => "WPPostUpdate",
												'objURL' => "",
												'ErrorMessage' => $strErrors
										);
									}

									if( !empty($cu_story_user["user_firstname"]) && !empty($cu_story_user["user_lastname"]) ){
										$post_author_id = get_post_field( 'post_author', $post_id );

										$user_id = wp_update_user( array(	'ID' => $post_author_id,
												'first_name' => $cu_story_user["user_firstname"],
												'last_name' => $cu_story_user["user_lastname"],
												'display_name' => $cu_story_user["user_firstname"]
												. " "
												. $cu_story_user["user_lastname"],
										) );
									}

									unset($arrLocalStoriesSet[$story_id]);
								}else{
									$cu_story_post = array(
											'post_status'   => $post_status,
											'post_author'   => $user_id,
											'post_category' => array( $category_id ),
											'post_title'   	=> $cu_story_post["post_title"],
											'post_content' 	=> $cu_story_post["post_content"],
											'post_type' 	=> "custory",
											'comment_status'=> $post_comments_status
									);

									// Insert the post into the database
									$post_id = wp_insert_post( $cu_story_post );

									// assign tags to post
									$tags = get_option('custory_post_tags');
									wp_set_post_tags( $post_id, $tags );

									$wpdb->replace(
											CU_STORIES_TABLE,
											array(
													'swp_story_id' => $story_id,
													'swp_story_href' => $objStory->meta->self,
													'swp_storyteller_email' => $cu_story_user["user_email"],
													'swp_activation_key' => $StoryActivationKey,
													'swp_post_id' => $post_id,
											),
											array(
													'%d',
													'%s',
													'%s',
													'%s',
													'%d',
											)
									);

									if(get_option('custory_story_activation') == "on"){
										$_GET["activate"] = $StoryActivationKey;
										$_GET["start"] = true;
										cu_stories_story_activation_redirect(false);
									}
								}
							}else{
								$arrErrors[] = array(
										'ErrorType' => "WPPostCreation",
										'objURL' => "",
										'ErrorMessage' => "Post cannot be created, please check that Category exists."
								);
							}
						}
					}else{
						$arrErrors[] = array(
								'ErrorType' => "Document",
								'objURL' => $objDocument->meta->self,
								'ErrorMessage' => cu_stories_process_error_messages($objDocument)
						);
					}
				}else{
					$arrData = explode("/", $story->href);
					$story_id = end($arrData);
					unset($arrLocalStoriesSet[$story_id]);
					$arrErrors[] = array(
							'ErrorType' => "Story",
							'objURL' => $objStory->meta->self,
							'ErrorMessage' => cu_stories_process_error_messages($objStory)
					);
				} //
			}
		}else{
			$arrErrors[] = array(
					'ErrorType' => "Collection",
					'objURL' => $objCollection->meta->self,
					'ErrorMessage' => cu_stories_process_error_messages($objCollection)
			);
		}

		//delete all stories which is not in collection
		foreach($arrLocalStoriesSet as $swp_story_id => $value){
			cu_stories_delete_story($arrLocalStoriesSet, $swp_story_id);
		}
	}else{
			$arrErrors[] = array(
					'ErrorType' => "Option",
					'objURL' => "",
					'ErrorMessage' => "API key or Collection ID is empty."
			);
	}

	//send email to admin in case errors
	if(!empty($arrErrors)){
		$to = get_bloginfo('admin_email');
		$subject = 'Customer Union Stories synchronization errors';
		$headers = array('Content-Type: text/html; charset=UTF-8');

		$body = "<table>";
		$body .= "<tr><th>Error Type</th><th>Object URL</th><th>Error Message</th></tr>";
		foreach($arrErrors as $key => $error){
			$body .= "<tr>";
			$body .= "<td>".$error["ErrorType"]."</td>";
			$body .= "<td>".$error["objURL"]."</td>";
			$body .= "<td>".$error["ErrorMessage"]."</td>";
			$body .= "</tr>";
		}
		$body .= "</table><br/><br/>";
		wp_mail( $to, $subject, $body, $headers );
	}
	return true;
}

function cu_stories_delete_story($arrStories, $story_id){
	global $wpdb;

	//get stroy post
	$objPostData = get_post($arrStories[$story_id]->swp_post_id);
	$post_author_id = $objPostData->post_author;
	$count = $wpdb->get_var( "SELECT COUNT(*) FROM $wpdb->posts WHERE post_author = {$objPostData->post_author}" );

	if($count == 1)
		wp_delete_user($post_author_id);

	return true;
}

function cu_stories_create_db() {
	global $wpdb;

	$charset_collate = $wpdb->get_charset_collate ();

	$sql = file_get_contents ( CU_STORIES_DIR . "includes/sql/create_table.sql" );
	$sql = str_replace ( "table_name", CU_STORIES_TABLE, $sql );
	$sql = str_replace ( "charset_collate", $charset_collate, $sql );

	require_once (ABSPATH . 'wp-admin/includes/upgrade.php');
	dbDelta ( $sql );

	return true;
}

/* add plugin styles */
add_action ( 'admin_enqueue_scripts', 'cu_stories_adding_styles' );
function cu_stories_adding_styles() {
	wp_register_style ( 'cu-stories-stylesheet', CU_STORIES_URL . 'includes/css/cu-stories-stylesheet.css');
	wp_enqueue_style ( 'cu-stories-stylesheet' );
}

/* add plugin java scripts */
add_action ( 'admin_enqueue_scripts', 'cu_stories_adding_scripts' );
function cu_stories_adding_scripts() {
	wp_register_script ( 'cu-stories-script', CU_STORIES_URL . 'includes/js/cu-stories-js.js', array ('jquery','jquery-ui-core'));
	wp_enqueue_script ( 'cu-stories-script' );

	//in JavaScript, object properties are accessed as ajax_object.ajax_url, ajax_object.we_value
	wp_localize_script( 'cu-stories-script', 'ajax_object',	array( 'ajax_url' => admin_url( 'admin-ajax.php' ) ) );
	wp_localize_script( 'cu-stories-script', 'php_vars',	array( 'api_url' => get_option('custory_api_url'),
																   'api_key' => get_option('custory_api_key'),
																   'collection_id' => get_option('custory_collection_id'),
																   'export_script_url' => CU_STORIES_URL . "includes/export.php" ) );
}

add_action( 'wp_enqueue_scripts', 'cu_stories_adding_public_scripts' );
function cu_stories_adding_public_scripts() {
	global $posts;

	if($posts[0]->ID == get_option( 'custory_page_id' )){
		wp_enqueue_script( 'activation-timer', CU_STORIES_URL . '/includes/js/activation-js.js', array(), '1.0.0', true );
		wp_localize_script( 'activation-timer', 'php_vars',	array( 'custory_activation_link' => get_option('custory_activation_link')));
		delete_option ( "custory_activation_link" );
	}else{
		wp_dequeue_style('activation-timer');
	}
}

add_filter( 'the_posts', 'cu_stories_page_filter' );
function cu_stories_page_filter( $posts ) {
	global $wp_query;

	if( $wp_query->get('cu_stories_page_is_called') ) {
		$posts[0]->post_title = 'Story activation page';
		$posts[0]->post_content = file_get_contents(CU_STORIES_DIR . 'includes/tpl/story-activation-page.html');
	}

	return $posts;
}

add_filter( 'parse_query', 'cu_stories_query_parser' );
function cu_stories_query_parser( $q ) {

	$the_page_name = get_option( "custory_page_name" );
	$the_page_id = get_option( "custory_page_id" );
	$the_pattern = get_option( "custory_story_pattern" );

	if(isset($_GET["activate"]) && !isset($_GET["start"]) && $q->query["name"] == $the_pattern){
		$q->set('cu_stories_page_is_called', TRUE );
		update_option ( "custory_activation_link", get_option('custory_story_transport') . "://{$_SERVER['SERVER_NAME']}{$_SERVER['REQUEST_URI']}&start=true" );
		if(!empty($the_page_id) && intval($the_page_id)){
			wp_redirect(esc_url( get_permalink($the_page_id) ));
			exit;
		}
	}else{
		$qv = $q->query_vars;

		if( isset( $q->query_vars['page_id'] ) AND ( intval($q->query_vars['page_id']) == $the_page_id ) ) {

			// Activation page has been called - NO permalinks
			$q->set('cu_stories_page_is_called', TRUE );

		}
		elseif( isset( $q->query_vars['pagename'] ) AND ( ($q->query_vars['pagename'] == $the_page_name) OR ($_pos_found = strpos($q->query_vars['pagename'],$the_page_name.'/') === 0) ) ) {

			// Activation page has been called - with permalinks
			$q->set('cu_stories_page_is_called', TRUE );

		}
		else {

			// Just a normal WordPress page
			$q->set('cu_stories_page_is_called', FALSE );

		}
	}

	return $q;
}

function cu_stories_remove_page() {
	global $wpdb;

	$the_page_title = get_option( "custory_page_title" );
	$the_page_name = get_option( "custory_page_name" );

	//  the id of our page...
	$the_page_id = get_option( 'custory_page_id' );
	if( $the_page_id ) {

		wp_delete_post( $the_page_id, true ); // this will delete

	}

	delete_option("custory_page_title");
	delete_option("custory_page_name");
	delete_option("custory_page_id");

}

function cu_stories_create_page(){
	global $wpdb;

	$the_page_title = 'Story activation page';
	$the_page_name = 'story-activation-page';

	// the menu entry...
	delete_option("custory_page_title");
	add_option("custory_page_title", $the_page_title, '', 'yes');
	// the slug...
	delete_option("custory_page_name");
	add_option("custory_page_name", $the_page_name, '', 'yes');
	// the id...
	delete_option("custory_page_id");
	add_option("custory_page_id", '0', '', 'yes');

	$the_page = get_page_by_title( $the_page_title );

	if ( ! $the_page ) {

		// Create post object
		$_p = array();
		$_p['post_title'] = $the_page_title;
		$_p['post_content'] = "This text may be overridden by the plugin. You shouldn't edit it.";
		$_p['post_status'] = 'publish';
		$_p['post_type'] = 'page';
		$_p['comment_status'] = 'closed';
		$_p['ping_status'] = 'closed';
		$_p['post_category'] = array(1); // the default 'Uncatrgorised'

		// Insert the post into the database
		$the_page_id = wp_insert_post( $_p );

	}
	else {
		// the plugin may have been previously active and the page may just be trashed...

		$the_page_id = $the_page->ID;

		//make sure the page is not trashed...
		$the_page->post_status = 'publish';
		$the_page_id = wp_update_post( $the_page );

	}

	delete_option( 'custory_page_id' );
	add_option( 'custory_page_id', $the_page_id );
}

function cu_stories_activation() {
	$options_array = array (
			"custory_api_url" => "https://stori.es/api/",
			"custory_api_key" => "",
			"custory_collection_id" => "",
			"custory_story_activation" => "on",
			"custory_story_transport" => "https",
			"custory_story_pattern" => "deploy-story",
			"custory_create_users" => "on",
			"custory_story_storyteller" => "storyteller",
			"custory_post_type" => "Stories",
			"custory_post_category" => "Robocalls",
			"custory_post_tags" => "robocalls",
			"custory_post_comments" => "on",
			"custory_refresh_rate" => "2:00 AM CST"
	);

	// create new wp role with minimum permissions
	if (get_role ( 'storyteller' ) == null) {
		add_role ( 'storyteller', 'Storyteller', array (
				'read' => true,
				'level_0' => true
		) );
	}

	//create activation post page
	cu_stories_create_page ();

	// create database table
	cu_stories_create_db ();

	// create correct timestamp for 2 AM CST and schedule synchronization
	if (! wp_next_scheduled ( 'cu_daily_event' )) {
		$cstDateTime = new DateTime ( date ( "Y-m-d" ) . " 02:00", new DateTimeZone ( "CST" ) );
		wp_schedule_event ( $cstDateTime->getTimestamp (), 'daily', 'cu_daily_event' );
		$options_array["custory_create_event_status"] = "Scheduled";
	}

	cu_stories_set_options ( $options_array ); //set all options

	register_uninstall_hook ( __FILE__, 'cu_stories_uninstall' );
}

function cu_stories_deactivation() {
	wp_clear_scheduled_hook ( 'cu_daily_event' );
	delete_option("custory_api_key");
	delete_option("custory_collection_id");
}

function cu_stories_uninstall() {
	global $wpdb;

	wp_clear_scheduled_hook ( 'cu_daily_event' );

	remove_role ( "cu_storyteller" );

	cu_stories_remove_page ();

	$category = get_option('custory_post_category');
	$term_id = term_exists($category, 'category');
	if ($term_id !== 0 && $term_id !== null)
		wp_delete_category( $term_id );

	$count = $wpdb->get_var( "SELECT COUNT(*) FROM " . CU_STORIES_TABLE );

	//go through all imported stories
	while ($count !== null && $count > 0) {
		$dataRow = $wpdb->get_row("SELECT * FROM " . CU_STORIES_TABLE . " LIMIT 1", OBJECT);
		$post_author_id = get_post_field( 'post_author', $dataRow->swp_post_id );

		wp_delete_post($dataRow->swp_post_id,true);

		$query = $wpdb->prepare( 'SELECT COUNT(*) FROM wp_posts WHERE post_author = %d', $post_author_id );
		$count = $wpdb->get_var( $query );
		if(empty($count))
			wp_delete_user($post_author_id);

		$count = $wpdb->get_var( "SELECT COUNT(*) FROM " . CU_STORIES_TABLE );
	}

	$wpdb->query( "DROP TABLE IF EXISTS " . CU_STORIES_TABLE );

	delete_option("custory_api_url");
	delete_option("custory_api_key");
	delete_option("custory_collection_id");
	delete_option("custory_story_activation");
	delete_option("custory_story_transport");
	delete_option("custory_story_pattern");
	delete_option("custory_create_users");
	delete_option("custory_story_storyteller");
	delete_option("custory_post_type");
	delete_option("custory_post_category");
	delete_option("custory_post_tags");
	delete_option("custory_post_comments");
	delete_option("custory_refresh_rate");
	delete_option("custory_activation_link");
	delete_option("custory_page_id");
	delete_option("custory_page_title");
	delete_option("custory_page_name");

	return true;
}

/**
 * *******************************************************************************************************
 *
 * Create custom post and add Stories menu to WordPress
 *
 * ********************************************************************************************************
 */

function cu_stories_make($singular_label, $plural_label, $settings = array()) {

	// Define the default settings
	$default_settings = array (
			'labels' => array (
					'name' => __ ( $plural_label, 'CU_Story' ),
					'singular_name' => __ ( $singular_label, 'CU_Story' ),
					'add_new_item' => __ ( 'Add New ' . $singular_label, 'CU_Story' ),
					'edit_item' => __ ( 'Edit ' . $singular_label, 'CU_Story' ),
					'new_item' => __ ( 'New ' . $singular_label, 'CU_Story' ),
					'view_item' => __ ( 'View ' . $singular_label, 'CU_Story' ),
					'search_items' => __ ( 'Search ' . $plural_label, 'CU_Story' ),
					'not_found' => __ ( 'No ' . $plural_label . ' found', 'CU_Story' ),
					'not_found_in_trash' => __ ( 'No ' . $plural_label . ' found in trash', 'CU_Story' ),
					'parent_item_colon' => __ ( 'Parent ' . $singular_label, 'CU_Story' )
			),
			'public' => true,
			'menu_icon' => "dashicons-book",
			'has_archive' => true,
			'menu_position' => 20,
			'supports' => array (
					'title',
					'editor',
					'thumbnail',
					'custom-fields'
			),
			'rewrite' => array (
					'slug' => sanitize_title_with_dashes ( $plural_label )
			),
			'delete_with_user' => true
	);

	// Override any settings provided by user
	// and store the settings with the posts array
	return array_merge ( $default_settings, $settings );
}

function cu_stories_register_custom_post() {
	register_post_type ( 'custory', cu_stories_make ( 'Story', 'Stories' ) );
}

function cu_stories_menu() {
	add_submenu_page ( 'edit.php?post_type=custory', 'Settings', 'Settings', 'manage_options', 'cu-stories-settings', 'cu_stories_view_settings' );
	add_submenu_page ( 'edit.php?post_type=custory', 'Export', 'Export', 'manage_options', 'cu-stories-export', 'cu_stories_view_export' );
}

function cu_stories_view_export() {
	echo "<h2>" . __ ( 'Export stories owners e-mails and activation URLs', 'cu_stories_view_export' )
		."<br/><br/><input type='button' class='button-primary' value='" . __('Export Data') . "' onclick='doExport();' />"
	 	. "</h2>";

	return true;
}

function cu_stories_view_settings() {
	/* These parameters available in template as php variable */
	$tpl_domain = str_replace ( "http://", "", home_url() );

	include_once CU_STORIES_DIR . 'includes/tpl/settings.html';
}

// Add settings link on plugin page
$plugin = plugin_basename(__FILE__);

add_filter("plugin_action_links_$plugin", 'cu_stories_settings_link' );
function cu_stories_settings_link($links) {
	$settings_link = '<a href="edit.php?post_type=custory&page=cu-stories-settings">Settings</a>';
	array_unshift($links, $settings_link);
	return $links;
}

add_action ( 'init', 'cu_stories_register_custom_post' );
add_action ( 'admin_menu', 'cu_stories_menu' );
