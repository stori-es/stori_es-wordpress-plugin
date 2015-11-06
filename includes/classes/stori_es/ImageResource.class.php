<?php
namespace stori_es;

class ImageResource {
	public $href = '';
	public $horizontal_position = 'left';
	public $size = 'small';
	public $caption;
	public $alt_text;

	public function __construct($source){
		$this->href = $source->href;
		$this->horizontal_position = $source->horizontal_position;
		$this->size = $source->size;
		$this->caption = $source->caption;
		$this->alt_text = $source->alt_text;
	}

	public function output(){
		$output = '';

		if( !empty($this->href) ){
			// Image container
			$output .= '<div class="stori_es-story-content-text-image ';
			$output .= 'stori_es-story-image-' . $this->horizontal_position . ' ';
			$output .= 'stori_es-story-image-' . $this->size . '">';

			// Image
			$output .= '<img class="stori_es-story-image" ';
			$output .= 'src="' . $this->href . '" ';

			// Image alternative text
			if( !empty($this->alt_text) )
				$output .= 'alt="' . $this->alt_text . '" ';

			$output .= '/>';

			// Image caption
			if( !empty($this->caption) )
				$output .= '<div class="stori_es-story-image-caption">' . $this->caption . '</div>';

			$output .= '</div>';
		}

		return($output);
	}
}

?>
