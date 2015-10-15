function checkAPISection( event ){
  var result = true;
  var key = event.which || event.keyCode;
  var value = event.target.value;

  if( (key == jQuery.ui.keyCode.ENTER) || (event.type == 'blur') ){
    switch( event.target.name ){
      case 'stori_es_api_url':
        if( php_vars.api_url == value )  break;
        event.preventDefault();
        jQuery('#check_result_apiurl').css('visibility', 'hidden');
        jQuery('#process_id_apiurl').css('visibility', 'visible');
        php_vars.api_key = "";
        if( validateAPIURL(value) )  php_vars.api_url = value;
        break;
      case 'stori_es_api_key':
        if( php_vars.api_key == value )  break;
        event.preventDefault();
        jQuery('#check_result_apikey').css('visibility', 'hidden');
        jQuery('#process_id_apikey').css('visibility', 'visible');
        if( validateAPIKey(value) )  php_vars.api_key = value;
        break;
      default:
    }
    result = false;
  }
  return result;
}


function validateAPIURL( api_url ){
  var data = {
    'action': 'validate_api_url',
    'api_url': api_url
  };

  jQuery.post(ajax_object.ajax_url, data, function( response ){
    jQuery('#process_id_apiurl').css('visibility', 'hidden');

    if( response == 'SUCCESS' ){
      jQuery('#check_result_apiurl').html('Verified').css('color', 'green');
    } else {
      jQuery('#check_result_apiurl').html("Invalid URL, please try again").css('color', 'red');
    }

    jQuery('#check_result_apiurl').css('visibility', 'visible');
  });

  return true;
}

function validateAPIKey( api_key ){
  var api_url = jQuery('#custory_api_url_id').val();

  if (!api_url || 0 === api_url.length) {
    jQuery('#process_id_apikey').css('visibility', 'hidden');
    alert("API Base URL can not be empty!");
    jQuery('#custory_api_key_id').val('');

    return false;
  }

  var data = {
    'action': 'validate_api_key',
    'api_key': api_key,
    'api_url': api_url
  };

  jQuery.post(ajax_object.ajax_url, data, function(response) {
    jQuery('#process_id_apikey').css('visibility', 'hidden');

    if( response == 'SUCCESS' ){
      jQuery('#check_result_apikey').html('Verified').css('color', 'green');
    } else if( response == 'INVALID' ){
      jQuery('#check_result_apikey').html("Invalid URL, please try again").css('color', 'red');
    } else {
      jQuery('#check_result_apikey').html("Invalid key, please try again").css('color', 'red');
    }

    jQuery('#check_result_apikey').css('visibility', 'visible');
  });

  return true;
}


function test() {
  var data = {
    'action': 'test'
  };

  jQuery.post(ajax_object.ajax_url, data, function(response) {
    alert(response);
  });

  return true;
}
