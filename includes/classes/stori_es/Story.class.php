<?php
namespace stori_es;

class Story {
	public $id;
	public $owner;
	public $content;

	public function __construct( $source ){
		$this->id = $source->id;
	}

	public function output( $story_elements ){
		$output = '<div id="stori_es-story-'. $this->id . '" class="stori_es-story">';
		foreach( $story_elements as $include ){
			switch( $include ){
				case 'title':
				  if( isset($this->content) )
					  $output .= '<div class="stori_es-story-title">' . $this->content->title . '</div>';
					break;
				case 'byline':
				  if( isset($this->owner) )
					  $output .= $this->owner->output();
					break;
				case 'content':
				  if( isset($this->content) )
					  $output .= $this->content->output();
					else
						$output .= '<em>Content temporarily unavailable</em>';
					break;
			}
		}
	  $output .= '</div>';

		return($output);
	}
}

?>
