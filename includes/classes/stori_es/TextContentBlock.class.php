<?php
namespace stori_es;

class TextContentBlock {
	public $value = '';
	public $image;

	public function __construct($source){
		// Precede newlines with HTML <br /> tags
		$this->value = nl2br($source->value);

		// Retrieve image if present
		if( isset($source->image) )
			$this->image = new ImageResource($source->image);
	}

	public function output(){
		$output = '';

		if( !empty($this->value) ){
			$output .= '<div class="stori_es-story-content-text">';

			// Image, if present
			if( isset($this->image) )  $output .= $this->image->output();

			// Text
			$output .= $this->value;

			$output .= '</div>';
		}

		return($output);
	}
}

?>
