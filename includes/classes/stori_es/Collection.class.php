<?php
namespace stori_es;

class Collection {
	public $id;
	public $title;
	public $story_count;
	public $stories = array();

	public function __construct( $source ){
		$this->id = $source->id;
		$this->title = $source->title;
		$this->story_count = count($source->links->stories);
	}

	public function output( $collection_elements, $story_elements, $story_limit ){
		$output = '<div id="stori_es-collection-' . $this->id . '" class="stori_es-collection">';
		foreach( $collection_elements as $include ){
			switch( $include ){
				case 'count':
					$output .= '<div class="stori_es-collection-count">' . $this->story_count . '</div>';
					break;
				case 'stories':
					if( count($this->stories) < $story_limit )  $story_limit = count($this->stories);
					for( $index = 0; $index < $story_limit; $index++ )
						$output .= $this->stories[$index]->output($story_elements);
					break;
			}
		}
		$output .= '</div>';

		return($output);
	}
}

?>
