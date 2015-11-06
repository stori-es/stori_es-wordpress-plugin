<?php
namespace stori_es;

class GeolocationContact extends Contact {
	public $city;
	public $state;

	public function __construct($source){
		parent::__construct($source);
		$this->city = trim($source->location->city);
		$this->state = trim($source->location->state);
	}

	public function output(){
		$output = '';
		if( !empty($this->city) || !empty($this->state) )  $output .= ' of ';
		if( !empty($this->city) )  $output .= ucfirst(strtolower($this->city));
		if( !empty($this->state) ){
			if( !empty($this->city) )  $output .= ', ';
			$output .= strtoupper($this->state);
		}

		return($output);
	}
}

?>
