<?php

define ( 'ABSPATH', dirname(dirname(dirname(dirname(dirname(__FILE__))))) . '/');

require_once(ABSPATH . 'wp-config.php');

global $wpdb;

function array2csv(array &$array){
	if (count($array) == 0) {
		return null;
	}
	ob_start();
	$df = fopen("php://output", 'w');
	fputcsv($df, array_keys(reset($array)));
	foreach ($array as $row) {
		fputcsv($df, $row);
	}
	fclose($df);
	return ob_get_clean();
}

function download_send_headers($filename){
	// disable caching
	$now = gmdate("D, d M Y H:i:s");
	header("Expires: Tue, 03 Jul 2001 06:00:00 GMT");
	header("Cache-Control: max-age=0, no-cache, must-revalidate, proxy-revalidate");
	header("Last-Modified: {$now} GMT");

	// force download
	header("Content-Type: application/force-download");
	header("Content-Type: application/octet-stream");
	header("Content-Type: application/download");

	// disposition / encoding on response body
	header("Content-Disposition: attachment;filename={$filename}");
	header("Content-Transfer-Encoding: binary");
}

$table_name = $wpdb->prefix . 'stories';
$result = $wpdb->get_results("SELECT swp_storyteller_email as 'User Email', swp_activation_key as 'Activation Key' FROM {$table_name} WHERE swp_storyteller_email <> '' AND swp_activation_timestamp IS NULL",ARRAY_A);


foreach($result as $key => $value){
	$result[$key]["Activation Link"] = get_option("custory_story_transport") .
										str_replace ( "http", "", home_url() ) . "/" .
										get_option("custory_story_pattern") .
										"?activate={$value['Activation Key']}";
	unset($result[$key]["Activation Key"]);
}

if(empty($result))
	$result = array(array("User Email"=>"","Activation Link"=>""));


download_send_headers("data_export_" . date("Y-m-d") . ".csv");
echo array2csv($result);
?>