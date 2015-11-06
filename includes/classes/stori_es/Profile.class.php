<?php
namespace stori_es;

define(__NAMESPACE__ . '\GEOLOCATION_CONTACT', 'GeolocationContact');

class Profile {
	public $given_name;
	public $contacts = array();

	public function __construct($source){
		$this->given_name = empty($source->given_name) ? 'Anonymous' : $source->given_name;

		foreach( $source->contacts as $key => $contact_data ){
			if( $contact_data->contact_type == GEOLOCATION_CONTACT ){
				$this->contacts[] = new GeolocationContact($contact_data);
				break;
			}
		}
	}

	public function output(){
		$output = '<div class="stori_es-story-byline">';
		$output .= $this->given_name;
		if( count($this->contacts) == 1 )  $output .= $this->contacts[0].output();
		$output .= '</div>';
		return($output);
	}
}

?>
