function doExport(){
	jQuery(window).attr("location",php_vars.export_script_url);
	return true;
}

function checkAPIKey(event){
	var result = true;
	var key = event.which || event.keyCode;
	var api_key = event.target.value;

	if (key == jQuery.ui.keyCode.ENTER || event.type == "blur"){
		if(php_vars.api_key != api_key) {
	        switch(event.target.name){
	        	case "custory_api_key":
	    			event.preventDefault();
	        		jQuery('#check_result_usr').css('visibility', 'hidden');
	        		jQuery('#process_id_usr').css('visibility','visible');
	        		validateAPIKey(api_key);
	        		php_vars.api_key = api_key;
	        		break;
	        	default:
	        }
		}
		result = false;
    }
	return result;
}

function validateAPIKey(api_key){
	var data = {
			'action': 'validate_api_key',
			'api_key': api_key
		};


	jQuery.post(ajax_object.ajax_url, data, function(response) {
		jQuery('#process_id_usr').css('visibility','hidden');

		if(response == 'SUCCESS'){
			jQuery('#check_result_usr').html('Verified').css('color', 'green');
		}else{
			jQuery('#check_result_usr').html("Invalid key, please try again").css('color', 'red');
		}

		jQuery('#check_result_usr').css('visibility', 'visible');
	});

	return true;
}

function checkInput(event){
	var result = true;
	var key = event.which || event.keyCode;
	var arrAllowedChars = [jQuery.ui.keyCode.LEFT,
	                       jQuery.ui.keyCode.RIGHT,
	                       jQuery.ui.keyCode.HOME,
	                       jQuery.ui.keyCode.END,
	                       jQuery.ui.keyCode.BACKSPACE,
	                       jQuery.ui.keyCode.DELETE];

	if (key == jQuery.ui.keyCode.ENTER || event.type == "blur"){
		if(php_vars.collection_id != event.target.value){
	        switch(event.target.name){
	        	case "custory_collection_id":
	    			event.preventDefault();
	        		jQuery('#check_result').css('visibility', 'hidden');
	        		jQuery('#process_ind_id').css('visibility','visible');
	        		validateCollection(event.target.value);
	        		php_vars.collection_id = event.target.value;
	        		break;
	        	default:
	        }
		}
		result = false;
    }else if(! jQuery.isNumeric(String.fromCharCode(key)) && arrAllowedChars.indexOf(key) < 0) {
		alert("Only digits allowed!");
		result = false;
	}
	return result;
}

function validateCollection(collection_id){
	var data = {
			'action': 'validate_collection',
			'collection_id': collection_id
		};


	jQuery.post(ajax_object.ajax_url, data, function(response) {
		jQuery('#process_ind_id').css('visibility','hidden');

		if(response == 'SUCCESS'){
			jQuery('#check_result').html('Verified').css('color', 'green');
		}else{
			jQuery('#check_result').html("Doesn't exist").css('color', 'red');
		}

		jQuery('#check_result').css('visibility', 'visible');
	});

	return true;
}

function test(){
	var data = {
			'action': 'test'
		};

	jQuery.post(ajax_object.ajax_url, data, function(response) {
		alert(response);
	});

	return true;
}

function actionStartSync(){

	var sync_time = jQuery('#custory_refresh_rate_id').val();

	var data = {
			'action': 'ctrl_sync',
			'state': 'start',
			'time': sync_time
		};

	jQuery.post(ajax_object.ajax_url, data, function(response) {
		if(response == 'SUCCESS')
			jQuery('#sync_state').text( "Scheduled" );
		alert('Synchronization start - ' + response);
	});

	return true;
}

function actionStopSync(){
	var data = {
			'action': 'ctrl_sync',
			'state': 'stop'
		};

	jQuery.post(ajax_object.ajax_url, data, function(response) {
		jQuery('#sync_state').text( "Unscheduled" );
		alert('Synchronization stop - ' + response);
	});

	return true;
}
