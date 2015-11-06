<?php
namespace stori_es;

class VideoContentBlock {
	public $href = '';
	public $title;
	public $caption;

	public function __construct($source){
		$this->href = $source->video->href;
		$this->title = $source->video->title;
		$this->caption = $source->video->caption;
	}

	public function embed_href(){
		return(str_replace('watch?v=', 'embed/', $this->href));
	}

	public function output(){
		$output = '';

		if( !empty($this->href) ){
			$output .= '<div class="stori_es-story-content-video">';

			/*
			 * Do not display the video title for now
			if( !empty($this->title) )
				$output .= '<div class="stori_es-story-content-video-title">' . $this->title . '</div>';
			*/

			$output .= '<iframe width="420" height="315" src="';
			$output .= $this->embed_href();
			$output .= '" frameborder="0" allowfullscreen></iframe>';

			if( !empty($this->caption) )
				$output .= '<div class="stori_es-story-content-video-caption">' . $this->caption . '</div>';

			$output .= '</div>';
		}

		return($output);
	}
}

?>
