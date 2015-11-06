<?php
namespace stori_es;

define(__NAMESPACE__ . '\TEXT_CONTENT_BLOCK', 'TextContentBlock');
define(__NAMESPACE__ . '\VIDEO_CONTENT_BLOCK', 'VideoContentBlock');

class Document {
	public $title;
	public $blocks = array();

	public function __construct($source){
		$this->title = empty($source->title) ? 'Untitled' : wp_strip_all_tags($source->title);

		foreach( $source->blocks as $key => $block ){
			switch( $block->block_type ){
				case TEXT_CONTENT_BLOCK:
					$this->blocks[] = new TextContentBlock($block);
					break;
				case VIDEO_CONTENT_BLOCK:
					$this->blocks[] = new VideoContentBlock($block);
					break;
			}
		}
	}

	public function output(){
		$output = '<div class="stori_es-story-content">';
		foreach( $this->blocks as $key => $block )  $output .= $block->output();
		$output .= '</div>';
		return($output);
	}
}

?>
