<?php
namespace stori_es;

class Collection {
	public $id;
	public $title;
	public $stories = array();

	public function __construct( $source ){
		$this->id = $source->id;
		$this->title = $source->title;
	}

	public function output( $story_count, $include_array ){
		if( count($this->stories) < $story_count )
		 	$story_count = count($this->stories);

		$output = '<div id="stori_es-collection-' . $this->id . '" class="stori_es-collection">';
		for( $index = 0; $index < $story_count; $index++ )
			$output .= $this->stories[$index]->output($include_array);
		$output .= '</div>';

		return($output);
	}
}

?>
