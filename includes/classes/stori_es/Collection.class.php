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

	public function output( $collection_elements, $story_elements, $story_count ){
		$output = '<div id="stori_es-collection-' . $this->id . '" class="stori_es-collection">';
		foreach( $collection_elements as $include ){
			switch( $include ){
				case 'stories':
					if( count($this->stories) < $story_count )  $story_count = count($this->stories);
					for( $index = 0; $index < $story_count; $index++ )
						$output .= $this->stories[$index]->output($story_elements);
					break;
			}
		}
		$output .= '</div>';

		return($output);
	}
}

?>
