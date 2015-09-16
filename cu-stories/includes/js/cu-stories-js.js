function doExport(){
	jQuery(window).attr("location",php_vars.export_script_url);
	return true;
}

function checkAPISection(event){
	var result = true;
	var key = event.which || event.keyCode;
	var value = event.target.value;

	if (key == jQuery.ui.keyCode.ENTER || event.type == "blur"){
        switch(event.target.name){
        	case "custory_api_url":
        		if(php_vars.api_url == value) break;
    			event.preventDefault();
        		jQuery('#check_result_apiurl').css('visibility', 'hidden');
        		jQuery('#process_id_apiurl').css('visibility','visible');
        		php_vars.api_key = "";
        		if(validateAPIURL(value))
        			php_vars.api_url = value;
        		break;
        	case "custory_api_key":
        		if(php_vars.api_key == value) break;
    			event.preventDefault();
        		jQuery('#check_result_apikey').css('visibility', 'hidden');
        		jQuery('#process_id_apikey').css('visibility','visible');
        		if(validateAPIKey(value))
        			php_vars.api_key = value;
        		break;
        	default:
        }
		result = false;
    }
	return result;
}

function validateAPIURL(api_url){
	var data = {
			'action': 'validate_api_url',
			'api_url': api_url
		};


	jQuery.post(ajax_object.ajax_url, data, function(response) {
		jQuery('#process_id_apiurl').css('visibility','hidden');

		if(response == 'SUCCESS'){
			jQuery('#check_result_apiurl').html('Verified').css('color', 'green');
		}else{
			jQuery('#check_result_apiurl').html("Invalid key, please try again").css('color', 'red');
		}

		jQuery('#check_result_apiurl').css('visibility', 'visible');
	});

	return true;
}

function validateAPIKey(api_key){
	var api_url = jQuery('#custory_api_url_id').val();
	
	if(!api_url || 0 === api_url.length){
		jQuery('#process_id_apikey').css('visibility','hidden');
		alert("API Base URL can not be empty!");
		jQuery('#custory_api_key_id').val("");
		
		return false;
	}
		
	var data = {
			'action': 'validate_api_key',
			'api_key': api_key,
			'api_url': api_url 
		};


	jQuery.post(ajax_object.ajax_url, data, function(response) {
		jQuery('#process_id_apikey').css('visibility','hidden');

		if(response == 'SUCCESS'){
			jQuery('#check_result_apikey').html('Verified').css('color', 'green');
		}else{
			jQuery('#check_result_apikey').html("Invalid key, please try again").css('color', 'red');
		}

		jQuery('#check_result_apikey').css('visibility', 'visible');
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
