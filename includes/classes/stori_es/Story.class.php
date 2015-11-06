<?php
namespace stori_es;

class Story {
	public $id;
	public $owner;
	public $content;

	public function __construct( $source ){
		$this->id = $source->id;
	}

	public function output( $include_array ){
		$output = '<div id="stori_es-story-'. $this->id . '" class="stori_es-story">';
		foreach( $include_array as $include ){
			switch( $include ){
				case 'title':
					$output .=  '<div class="stori_es-story-title">' . $this->content->title . '</div>';
					break;
				case 'byline':
					$output .=  $this->owner->output();
					break;
				case 'content':
					$output .=  $this->content->output();
					break;
			}
		}
	  $output .= '</div>';

		return($output);
	}
}

?>
