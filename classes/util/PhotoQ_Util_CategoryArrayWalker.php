<?php
/**
 * Category walker visitor object that outputs categories in array syntax such that we can
 * have multiple category dropdown lists on the same page.
 */
class PhotoQ_Util_CategoryArrayWalker extends Walker {
	
	private $_photoID;
	
	function __construct($photoID)
	{
		$this->tree_type = 'category';
		$this->db_fields = array ('parent' => 'parent', 'id' => 'term_id'); //TODO: decouple this
		$this->_photoID = $photoID;
	}

	function start_lvl($output, $depth, $args) {
		$indent = str_repeat("\t", $depth);
		$output .= "$indent<ul class='wimpq_subcats'>\n";
	}

	function end_lvl($output, $depth, $args) {
		$indent = str_repeat("\t", $depth);
		$output .= "$indent</ul>\n";
	}

	function start_el($output, $category, $depth, $args) {
		extract($args);

		$class = in_array( $category->term_id, $popular_cats ) ? ' class="popular-category"' : '';
		$output .= "\n<li id='category-$category->term_id-".$this->_photoID."'$class>" . '<label for="in-category-' . $category->term_id . '-'.$this->_photoID.'" class="selectit"><input value="' . $category->term_id . '" type="checkbox" name="post_category['.$this->_photoID.'][]" id="in-category-' . $category->term_id . '-'.$this->_photoID.'"' . (in_array( $category->term_id, $selected_cats ) ? ' checked="checked"' : "" ) . '/> ' . wp_specialchars( apply_filters('the_category', $category->name )) . '</label>';
	}

	function end_el($output, $category, $depth, $args) {
		$output .= "</li>\n";
	}
}