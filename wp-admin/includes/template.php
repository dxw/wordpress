<?php
/**
 * Template WordPress Administration API.
 *
 * A Big Mess. Also some neat functions that are nicely written.
 *
 * @package WordPress
 * @subpackage Administration
 */

// Ugly recursive category stuff.
/**
 * {@internal Missing Short Description}}
 *
 * @since unknown
 *
 * @param unknown_type $parent
 * @param unknown_type $level
 * @param unknown_type $categories
 * @param unknown_type $page
 * @param unknown_type $per_page
 */
function cat_rows( $parent = 0, $level = 0, $categories = 0, $page = 1, $per_page = 20 ) {
	$count = 0;
	_cat_rows($categories, $count, $parent, $level, $page, $per_page);
}

/**
 * {@internal Missing Short Description}}
 *
 * @since unknown
 *
 * @param unknown_type $categories
 * @param unknown_type $count
 * @param unknown_type $parent
 * @param unknown_type $level
 * @param unknown_type $page
 * @param unknown_type $per_page
 * @return unknown
 */
function _cat_rows( $categories, &$count, $parent = 0, $level = 0, $page = 1, $per_page = 20 ) {
	if ( empty($categories) ) {
		$args = array('hide_empty' => 0);
		if ( !empty($_GET['s']) )
			$args['search'] = $_GET['s'];
		$categories = get_categories( $args );
	}

	if ( !$categories )
		return false;

	$children = _get_term_hierarchy('category');

	$start = ($page - 1) * $per_page;
	$end = $start + $per_page;
	$i = -1;
	ob_start();
	foreach ( $categories as $category ) {
		if ( $count >= $end )
			break;

		$i++;

		if ( $category->parent != $parent )
			continue;

		// If the page starts in a subtree, print the parents.
		if ( $count == $start && $category->parent > 0 ) {
			$my_parents = array();
			while ( $my_parent) {
				$my_parent = get_category($my_parent);
				$my_parents[] = $my_parent;
				if ( !$my_parent->parent )
					break;
				$my_parent = $my_parent->parent;
			}
			$num_parents = count($my_parents);
			while( $my_parent = array_pop($my_parents) ) {
				echo "\t" . _cat_row( $my_parent, $level - $num_parents );
				$num_parents--;
			}
		}

		if ( $count >= $start )
			echo "\t" . _cat_row( $category, $level );

		unset($categories[$i]); // Prune the working set
		$count++;

		if ( isset($children[$category->term_id]) )
			_cat_rows( $categories, $count, $category->term_id, $level + 1, $page, $per_page );

	}

	$output = ob_get_contents();
	ob_end_clean();

	$output = apply_filters('cat_rows', $output);

	echo $output;
}

/**
 * {@internal Missing Short Description}}
 *
 * @since unknown
 *
 * @param unknown_type $category
 * @param unknown_type $level
 * @param unknown_type $name_override
 * @return unknown
 */
function _cat_row( $category, $level, $name_override = false ) {
	global $class;

	$category = get_category( $category );

	$default_cat_id = (int) get_option( 'default_category' );
	$pad = str_repeat( '&#8212; ', $level );
	$name = ( $name_override ? $name_override : $pad . ' ' . $category->name );
	$edit_link = "categories.php?action=edit&amp;cat_ID=$category->term_id";
	if ( current_user_can( 'manage_categories' ) ) {
		$edit = "<a class='row-title' href='$edit_link' title='" . attribute_escape(sprintf(__('Edit "%s"'), $category->name)) . "'>" . attribute_escape( $name ) . '</a><br />';
		$actions = array();
		$actions['edit'] = '<a href="' . $edit_link . '">' . __('Edit') . '</a>';
		$actions['inline hide-if-no-js'] = '<a href="#" class="editinline">' . __('Quick&nbsp;Edit') . '</a>';
		if ( $default_cat_id != $category->term_id )
			$actions['delete'] = "<a class='submitdelete' href='" . wp_nonce_url("categories.php?action=delete&amp;cat_ID=$category->term_id", 'delete-category_' . $category->term_id) . "' onclick=\"if ( confirm('" . js_escape(sprintf(__("You are about to delete this category '%s'\n 'Cancel' to stop, 'OK' to delete."), $name )) . "') ) { return true;}return false;\">" . __('Delete') . "</a>";
		$action_count = count($actions);
		$i = 0;
		foreach ( $actions as $action => $link ) {
			++$i;
			( $i == $action_count ) ? $sep = '' : $sep = ' | ';
			$edit .= "<span class='$action'>$link$sep</span>";
		}
	} else {
		$edit = $name;
	}

	$class = 'alternate' == $class ? '' : 'alternate';

	$category->count = number_format_i18n( $category->count );
	$posts_count = ( $category->count > 0 ) ? "<a href='edit.php?cat=$category->term_id'>$category->count</a>" : $category->count;
	$output = "<tr id='cat-$category->term_id' class='iedit $class'>";

	$columns = get_column_headers('category');
	$hidden = (array) get_user_option( 'manage-category-columns-hidden' );
	foreach ( $columns as $column_name => $column_display_name ) {
		$class = "class=\"$column_name column-$column_name\"";

		$style = '';
		if ( in_array($column_name, $hidden) )
			$style = ' style="display:none;"';

		$attributes = "$class$style";

		switch ($column_name) {
			case 'cb':
				$output .= "<th scope='row' class='check-column'>";
				if ( $default_cat_id != $category->term_id ) {
					$output .= "<input type='checkbox' name='delete[]' value='$category->term_id' />";
				} else {
					$output .= "&nbsp;";
				}
				$output .= '</th>';
				break;
			case 'name':
				$output .= "<td $attributes>$edit";
				$output .= '<div class="hidden" id="inline_' . $category->term_id . '">';
				$output .= '<div class="name">' . attribute_escape( $category->name ) . '</div>';
				$output .= '<div class="slug">' . $category->slug . '</div>';
				$output .= '<div class="cat_parent">' . $category->parent . '</div></div></td>';
				break;
			case 'description':
				$output .= "<td $attributes>$category->description</td>";
				break;
			case 'slug':
				$output .= "<td $attributes>$category->slug</td>";
				break;
			case 'posts':
				$attributes = 'class="posts column-posts num"' . $style;
				$output .= "<td $attributes>$posts_count</td>\n";
		}
	}
	$output .= '</tr>';

	return apply_filters('cat_row', $output);
}

/**
 * {@internal Missing Short Description}}
 *
 * @since 2.7
 *
 * Outputs the HTML for the hidden table rows used in Categories, Link Caregories and Tags quick edit.
 *
 * @param string $type "tag", "category" or "link-category"
 * @return
 */
function inline_edit_term_row($type) {

	if ( ! current_user_can( 'manage_categories' ) )
		return;

	$is_tag = $type == 'tag';
	$columns = $is_tag ? get_column_headers('tag') : get_column_headers('category');
	$hidden = (array) get_user_option( "manage-$type-columns-hidden" ); ?>

<form method="get" action=""><table style="display: none"><tbody id="inlineedit">
	<tr id="inline-edit" style="display: none"><td colspan="8">
	<?php

	foreach ( $columns as $column_name => $column_display_name ) {
		$class = "class=\"$column_name column-$column_name quick-edit-div\"";
		$style = in_array($column_name, $hidden) ? ' style="display:none;"' : '';
		$attributes = "$class$style";

		switch ($column_name) {
			case 'cb':
				break;
			case 'description':
				break;
			case 'name': ?>
				<div class="tax-name quick-edit-div"<?php echo $style ?> title="<?php _e('Name'); ?>">
					<div class="title"><?php _e('Name'); ?></div>
					<div class="in">
					<input type="text" name="name" class="ptitle" value="" />
					</div>
				</div>
				<?php

				$output .= "<td $attributes>$edit</td>";
				break;
			case 'slug': ?>
				<div class="tax-slug quick-edit-div"<?php echo $style ?> title="<?php _e('Slug'); ?>">
					<div class="title"><?php _e('Slug'); ?></div>
					<div class="in">
					<input type="text" name="slug" class="ptitle" value="" />
					</div>
				</div>
				<?php

				$output .= "<td $attributes>$category->slug</td>";
				break;
			case 'posts':
				if ( 'category' == $type ) { ?>
				<div class="tax-parent quick-edit-div"<?php echo $style ?> title="<?php _e('Parent Category'); ?>">
					<div class="title"><?php _e('Parent Category'); ?></div>
						<div class="in">
						<?php wp_dropdown_categories(array('hide_empty' => 0, 'name' => 'parent', 'orderby' => 'name', 'hierarchical' => 1, 'show_option_none' => __('None'))); ?>
						</div>
				</div>
				<?php }
				break;
		}
	}
	?>
	<div class="clear"></div>
	<div class="quick-edit-save">
		<a accesskey="c" href="#inline-edit" title="<?php _e('Cancel'); ?>" class="button-secondary cancel"><?php _e('Cancel'); ?></a>
		<a accesskey="s" href="#inline-edit" title="<?php _e('Save'); ?>" class="button-secondary save"><?php _e('Save'); ?></a>
		<img class="waiting" style="display:none;" src="images/loading.gif" alt="" />
		<span class="error" style="display:none;"></span>
		<?php wp_nonce_field( 'taxinlineeditnonce', '_inline_edit', false ); ?>
	</div>
	</td></tr>
	</tbody></table></form>
<?php
}

/**
 * {@internal Missing Short Description}}
 *
 * @since unknown
 *
 * @param unknown_type $category
 * @param unknown_type $name_override
 * @return unknown
 */
function link_cat_row( $category, $name_override = false ) {
	global $class;

	if ( !$category = get_term( $category, 'link_category' ) )
		return false;
	if ( is_wp_error( $category ) )
		return $category;

	$default_cat_id = (int) get_option( 'default_link_category' );
	$name = ( $name_override ? $name_override : $category->name );
	$edit_link = "link-category.php?action=edit&amp;cat_ID=$category->term_id";
	if ( current_user_can( 'manage_categories' ) ) {
		$edit = "<a class='row-title' href='$edit_link' title='" . attribute_escape(sprintf(__('Edit "%s"'), $category->name)) . "'>$name</a><br />";
		$actions = array();
		$actions['edit'] = '<a href="' . $edit_link . '">' . __('Edit') . '</a>';
		$actions['inline hide-if-no-js'] = '<a href="#" class="editinline">' . __('Quick&nbsp;Edit') . '</a>';
		if ( $default_cat_id != $category->term_id )
			$actions['delete'] = "<a class='submitdelete' href='" . wp_nonce_url("link-category.php?action=delete&amp;cat_ID=$category->term_id", 'delete-link-category_' . $category->term_id) . "' onclick=\"if ( confirm('" . js_escape(sprintf(__("You are about to delete this category '%s'\n 'Cancel' to stop, 'OK' to delete."), $name )) . "') ) { return true;}return false;\">" . __('Delete') . "</a>";
		$action_count = count($actions);
		$i = 0;
		foreach ( $actions as $action => $link ) {
			++$i;
			( $i == $action_count ) ? $sep = '' : $sep = ' | ';
			$edit .= "<span class='$action'>$link$sep</span>";
		}
	} else {
		$edit = $name;
	}

	$class = 'alternate' == $class ? '' : 'alternate';

	$category->count = number_format_i18n( $category->count );
	$count = ( $category->count > 0 ) ? "<a href='link-manager.php?cat_id=$category->term_id'>$category->count</a>" : $category->count;
	$output = "<tr id='link-cat-$category->term_id' class='iedit $class'>";
	$columns = get_column_headers('link-category');
	$hidden = (array) get_user_option( 'manage-link-category-columns-hidden' );
	foreach ( $columns as $column_name => $column_display_name ) {
		$class = "class=\"$column_name column-$column_name\"";

		$style = '';
		if ( in_array($column_name, $hidden) )
			$style = ' style="display:none;"';

		$attributes = "$class$style";

		switch ($column_name) {
			case 'cb':
				$output .= "<th scope='row' class='check-column'>";
				if ( absint( get_option( 'default_link_category' ) ) != $category->term_id ) {
					$output .= "<input type='checkbox' name='delete[]' value='$category->term_id' />";
				} else {
					$output .= "&nbsp;";
				}
				$output .= "</th>";
				break;
			case 'name':
				$output .= "<td $attributes>$edit";
				$output .= '<div class="hidden" id="inline_' . $category->term_id . '">';
				$output .= '<div class="name">' . attribute_escape( $category->name ) . '</div>';
				$output .= '<div class="slug">' . $category->slug . '</div>';
				$output .= '<div class="cat_parent">' . $category->parent . '</div></div></td>';
				break;
			case 'description':
				$output .= "<td $attributes>$category->description</td>";
				break;
			case 'links':
				$attributes = 'class="links column-links num"' . $style;
				$output .= "<td $attributes>$count</td>";
		}
	}
	$output .= '</tr>';

	return apply_filters( 'link_cat_row', $output );
}

/**
 * {@internal Missing Short Description}}
 *
 * @since unknown
 *
 * @param unknown_type $checked
 * @param unknown_type $current
 */
function checked( $checked, $current) {
	if ( $checked == $current)
		echo ' checked="checked"';
}

/**
 * {@internal Missing Short Description}}
 *
 * @since unknown
 *
 * @param unknown_type $selected
 * @param unknown_type $current
 */
function selected( $selected, $current) {
	if ( $selected == $current)
		echo ' selected="selected"';
}

//
// Category Checklists
//

/**
 * {@internal Missing Short Description}}
 *
 * @since unknown
 * @deprecated Use {@link wp_link_category_checklist()}
 * @see wp_link_category_checklist()
 *
 * @param unknown_type $default
 * @param unknown_type $parent
 * @param unknown_type $popular_ids
 */
function dropdown_categories( $default = 0, $parent = 0, $popular_ids = array() ) {
	global $post_ID;
	wp_category_checklist($post_ID);
}

/**
 * {@internal Missing Short Description}}
 *
 * @since unknown
 */
class Walker_Category_Checklist extends Walker {
	var $tree_type = 'category';
	var $db_fields = array ('parent' => 'parent', 'id' => 'term_id'); //TODO: decouple this

	function start_lvl(&$output, $depth, $args) {
		$indent = str_repeat("\t", $depth);
		$output .= "$indent<ul class='children'>\n";
	}

	function end_lvl(&$output, $depth, $args) {
		$indent = str_repeat("\t", $depth);
		$output .= "$indent</ul>\n";
	}

	function start_el(&$output, $category, $depth, $args) {
		extract($args);

		$class = in_array( $category->term_id, $popular_cats ) ? ' class="popular-category"' : '';
		$output .= "\n<li id='category-$category->term_id'$class>" . '<label class="selectit"><input value="' . $category->term_id . '" type="checkbox" name="post_category[]" id="in-category-' . $category->term_id . '"' . (in_array( $category->term_id, $selected_cats ) ? ' checked="checked"' : "" ) . '/> ' . wp_specialchars( apply_filters('the_category', $category->name )) . '</label>';
	}

	function end_el(&$output, $category, $depth, $args) {
		$output .= "</li>\n";
	}
}

/**
 * {@internal Missing Short Description}}
 *
 * @since unknown
 *
 * @param unknown_type $post_id
 * @param unknown_type $descendants_and_self
 * @param unknown_type $selected_cats
 * @param unknown_type $popular_cats
 */
function wp_category_checklist( $post_id = 0, $descendants_and_self = 0, $selected_cats = false, $popular_cats = false ) {
	$walker = new Walker_Category_Checklist;
	$descendants_and_self = (int) $descendants_and_self;

	$args = array();

	if ( is_array( $selected_cats ) )
		$args['selected_cats'] = $selected_cats;
	elseif ( $post_id )
		$args['selected_cats'] = wp_get_post_categories($post_id);
	else
		$args['selected_cats'] = array();

	if ( is_array( $popular_cats ) )
		$args['popular_cats'] = $popular_cats;
	else
		$args['popular_cats'] = get_terms( 'category', array( 'fields' => 'ids', 'orderby' => 'count', 'order' => 'DESC', 'number' => 10, 'hierarchical' => false ) );

	if ( $descendants_and_self ) {
		$categories = get_categories( "child_of=$descendants_and_self&hierarchical=0&hide_empty=0" );
		$self = get_category( $descendants_and_self );
		array_unshift( $categories, $self );
	} else {
		$categories = get_categories('get=all');
	}

	// Post process $categories rather than adding an exclude to the get_terms() query to keep the query the same across all posts (for any query cache)
	$checked_categories = array();
	for ( $i = 0; isset($categories[$i]); $i++ ) {
		if ( in_array($categories[$i]->term_id, $args['selected_cats']) ) {
			$checked_categories[] = $categories[$i];
			unset($categories[$i]);
		}
	}

	// Put checked cats on top
	echo call_user_func_array(array(&$walker, 'walk'), array($checked_categories, 0, $args));
	// Then the rest of them
	echo call_user_func_array(array(&$walker, 'walk'), array($categories, 0, $args));
}

/**
 * {@internal Missing Short Description}}
 *
 * @since unknown
 *
 * @param unknown_type $taxonomy
 * @param unknown_type $default
 * @param unknown_type $number
 * @param unknown_type $echo
 * @return unknown
 */
function wp_popular_terms_checklist( $taxonomy, $default = 0, $number = 10, $echo = true ) {
	global $post_ID;
	if ( $post_ID )
		$checked_categories = wp_get_post_categories($post_ID);
	else
		$checked_categories = array();
	$categories = get_terms( $taxonomy, array( 'orderby' => 'count', 'order' => 'DESC', 'number' => $number, 'hierarchical' => false ) );

	$popular_ids = array();
	foreach ( (array) $categories as $category ) {
		$popular_ids[] = $category->term_id;
		if ( !$echo ) // hack for AJAX use
			continue;
		$id = "popular-category-$category->term_id";
		?>

		<li id="<?php echo $id; ?>" class="popular-category">
			<label class="selectit">
			<input id="in-<?php echo $id; ?>" type="checkbox" value="<?php echo (int) $category->term_id; ?>" />
				<?php echo wp_specialchars( apply_filters( 'the_category', $category->name ) ); ?>
			</label>
		</li>

		<?php
	}
	return $popular_ids;
}

/**
 * {@internal Missing Short Description}}
 *
 * @since unknown
 * @deprecated Use {@link wp_link_category_checklist()}
 * @see wp_link_category_checklist()
 *
 * @param unknown_type $default
 */
function dropdown_link_categories( $default = 0 ) {
	global $link_id;

	wp_link_category_checklist($link_id);
}

/**
 * {@internal Missing Short Description}}
 *
 * @since unknown
 *
 * @param unknown_type $link_id
 */
function wp_link_category_checklist( $link_id = 0 ) {
	$default = 1;

	if ( $link_id ) {
		$checked_categories = wp_get_link_cats($link_id);

		if ( count( $checked_categories ) == 0 ) {
			// No selected categories, strange
			$checked_categories[] = $default;
		}
	} else {
		$checked_categories[] = $default;
	}

	$categories = get_terms('link_category', 'orderby=count&hide_empty=0');

	if ( empty($categories) )
		return;

	foreach ( $categories as $category ) {
		$cat_id = $category->term_id;
		$name = wp_specialchars( apply_filters('the_category', $category->name));
		$checked = in_array( $cat_id, $checked_categories );
		echo '<li id="link-category-', $cat_id, '"><label for="in-link-category-', $cat_id, '" class="selectit"><input value="', $cat_id, '" type="checkbox" name="link_category[]" id="in-link-category-', $cat_id, '"', ($checked ? ' checked="checked"' : "" ), '/> ', $name, "</label></li>";
	}
}

// Tag stuff

// Returns a single tag row (see tag_rows below)
// Note: this is also used in admin-ajax.php!
/**
 * {@internal Missing Short Description}}
 *
 * @since unknown
 *
 * @param unknown_type $tag
 * @param unknown_type $class
 * @return unknown
 */
function _tag_row( $tag, $class = '' ) {
		$count = number_format_i18n( $tag->count );
		$count = ( $count > 0 ) ? "<a href='edit.php?tag=$tag->slug'>$count</a>" : $count;

		$name = apply_filters( 'term_name', $tag->name );
		$edit_link = "edit-tags.php?action=edit&amp;tag_ID=$tag->term_id";
		$out = '';
		$out .= '<tr id="tag-' . $tag->term_id . '"' . $class . '>';
		$columns = get_column_headers('tag');
		$hidden = (array) get_user_option( 'manage-tag-columns-hidden' );
		foreach ( $columns as $column_name => $column_display_name ) {
			$class = "class=\"$column_name column-$column_name\"";

			$style = '';
			if ( in_array($column_name, $hidden) )
				$style = ' style="display:none;"';

			$attributes = "$class$style";

			switch ($column_name) {
				case 'cb':
					$out .= '<th scope="row" class="check-column"> <input type="checkbox" name="delete_tags[]" value="' . $tag->term_id . '" /></th>';
					break;
				case 'name':
					$out .= '<td ' . $attributes . '><strong><a class="row-title" href="' . $edit_link . '" title="' . attribute_escape(sprintf(__('Edit "%s"'), $name)) . '">' . $name . '</a></strong><br />';
					$actions = array();
					$actions['edit'] = '<a href="' . $edit_link . '">' . __('Edit') . '</a>';
					$actions['inline hide-if-no-js'] = '<a href="#" class="editinline">' . __('Quick&nbsp;Edit') . '</a>';
					$actions['delete'] = "<a class='submitdelete' href='" . wp_nonce_url("edit-tags.php?action=delete&amp;tag_ID=$tag->term_id", 'delete-tag_' . $tag->term_id) . "' onclick=\"if ( confirm('" . js_escape(sprintf(__("You are about to delete this tag '%s'\n 'Cancel' to stop, 'OK' to delete."), $name )) . "') ) { return true;}return false;\">" . __('Delete') . "</a>";
					$action_count = count($actions);
					$i = 0;
					foreach ( $actions as $action => $link ) {
						++$i;
						( $i == $action_count ) ? $sep = '' : $sep = ' | ';
						$out .= "<span class='$action'>$link$sep</span>";
					}
					$out .= '<div class="hidden" id="inline_' . $tag->term_id . '">';
					$out .= '<div class="name">' . $name . '</div>';
					$out .= '<div class="slug">' . $tag->slug . '</div></div></td>';
					break;
				case 'slug':
					$out .= "<td $attributes>$tag->slug</td>";
					break;
				case 'posts':
					$attributes = 'class="posts column-posts num"' . $style;
					$out .= "<td $attributes>$count</td>";
					break;
			}
		}

		$out .= '</tr>';

		return $out;
}

// Outputs appropriate rows for the Nth page of the Tag Management screen,
// assuming M tags displayed at a time on the page
// Returns the number of tags displayed
/**
 * {@internal Missing Short Description}}
 *
 * @since unknown
 *
 * @param unknown_type $page
 * @param unknown_type $pagesize
 * @param unknown_type $searchterms
 * @return unknown
 */
function tag_rows( $page = 1, $pagesize = 20, $searchterms = '' ) {

	// Get a page worth of tags
	$start = ($page - 1) * $pagesize;

	$args = array('offset' => $start, 'number' => $pagesize, 'hide_empty' => 0);

	if ( !empty( $searchterms ) ) {
		$args['search'] = $searchterms;
	}

	$tags = get_terms( 'post_tag', $args );

	// convert it to table rows
	$out = '';
	$class = '';
	$count = 0;
	foreach( $tags as $tag )
		$out .= _tag_row( $tag, ++$count % 2 ? ' class="iedit alternate"' : ' class="iedit"' );

	// filter and send to screen
	$out = apply_filters('tag_rows', $out);
	echo $out;
	return $count;
}

// define the columns to display, the syntax is 'internal name' => 'display name'
/**
 * {@internal Missing Short Description}}
 *
 * @since unknown
 *
 * @return unknown
 */
function wp_manage_posts_columns() {
	$posts_columns = array();
	$posts_columns['cb'] = '<input type="checkbox" />';
	$posts_columns['title'] = __('Title');
	$posts_columns['author'] = __('Author');
	$posts_columns['categories'] = __('Categories');
	$posts_columns['tags'] = __('Tags');
	if ( !isset($_GET['post_status']) || !in_array($_GET['post_status'], array('pending', 'draft', 'future')) )
		$posts_columns['comments'] = '<div class="vers"><img alt="Comments" src="images/comment-grey-bubble.png" /></div>';
	$posts_columns['date'] = __('Date');
	$posts_columns = apply_filters('manage_posts_columns', $posts_columns);

	return $posts_columns;
}

// define the columns to display, the syntax is 'internal name' => 'display name'
/**
 * {@internal Missing Short Description}}
 *
 * @since unknown
 *
 * @return unknown
 */
function wp_manage_media_columns() {
	$posts_columns = array();
	$posts_columns['cb'] = '<input type="checkbox" />';
	$posts_columns['icon'] = '';
	$posts_columns['media'] = _c('File|media column header');
	$posts_columns['author'] = __('Author');
	$posts_columns['tags'] = _c('Tags|media column header');
	$posts_columns['parent'] = _c('Attached to|media column header');
	//$posts_columns['comments'] = '<div class="vers"><img alt="Comments" src="images/comment-grey-bubble.png" /></div>';
	$posts_columns['comments'] = __('Comments');
	$posts_columns['date'] = _c('Date|media column header');
	$posts_columns = apply_filters('manage_media_columns', $posts_columns);

	return $posts_columns;
}

/**
 * {@internal Missing Short Description}}
 *
 * @since unknown
 *
 * @return unknown
 */
function wp_manage_pages_columns() {
	$posts_columns = array();
	$posts_columns['cb'] = '<input type="checkbox" />';
	$posts_columns['title'] = __('Title');
	$posts_columns['author'] = __('Author');
	if ( !in_array($post_status, array('pending', 'draft', 'future')) )
		$posts_columns['comments'] = '<div class="vers"><img alt="" src="images/comment-grey-bubble.png" /></div>';
	$posts_columns['date'] = __('Date');
	$posts_columns = apply_filters('manage_pages_columns', $posts_columns);

	return $posts_columns;
}

/**
 * {@internal Missing Short Description}}
 *
 * @since unknown
 *
 * @param unknown_type $page
 * @return unknown
 */
function get_column_headers($page) {
	switch ($page) {
		case 'post':
			return wp_manage_posts_columns();
		case 'page':
			return wp_manage_pages_columns();
		case 'comment':
			$columns = array(
				'cb' => '<input type="checkbox" />',
				'comment' => __('Comment'),
				'author' => __('Author'),
				'date' => __('Submitted'),
				'response' => __('In Response To This Post')
			);

			return apply_filters('manage_comments_columns', $columns);
		case 'link':
			$columns = array(
				'cb' => '<input type="checkbox" />',
				'name' => __('Name'),
				'url' => __('URL'),
				'categories' => __('Categories'),
				'rel' => __('rel'),
				'visible' => __('Visible')
			);

			return apply_filters('manage_link_columns', $columns);
		case 'media':
			return wp_manage_media_columns();
		case 'category':
			$columns = array(
				'cb' => '<input type="checkbox" />',
				'name' => __('Name'),
				'description' => __('Description'),
				'slug' => __('Slug'),
				'posts' => __('Posts')
			);

			return apply_filters('manage_categories_columns', $columns);
		case 'link-category':
			$columns = array(
				'cb' => '<input type="checkbox" />',
				'name' => __('Name'),
				'description' => __('Description'),
				'links' => __('Links')
			);

			return apply_filters('manage_link_categories_columns', $columns);
		case 'tag':
			$columns = array(
				'cb' => '<input type="checkbox" />',
				'name' => __('Name'),
				'slug' => __('Slug'),
				'posts' => __('Posts')
			);

			return apply_filters('manage_link_categories_columns', $columns);
		case 'user':
			$columns = array(
				'cb' => '<input type="checkbox" />',
				'username' => __('Username'),
				'name' => __('Name'),
				'email' => __('E-mail'),
				'role' => __('Role'),
				'posts' => __('Posts')
			);
			return apply_filters('manage_users_columns', $columns);
		default :
			return apply_filters('manage_' . $page . '_columns', $columns);
	}

	return $columns;
}

/**
 * {@internal Missing Short Description}}
 *
 * @since unknown
 *
 * @param unknown_type $type
 * @param unknown_type $id
 */
function print_column_headers( $type, $id = true ) {
	$columns = get_column_headers( $type );
	$hidden = (array) get_user_option( "manage-$type-columns-hidden" );
	$styles = array();
	$styles['tag']['posts'] = 'width: 90px;';
	$styles['link-category']['links'] = 'width: 90px;';
	$styles['category']['posts'] = 'width: 90px;';
	$styles['link']['visible'] = 'text-align: center;';

	foreach ( $columns as $column_key => $column_display_name ) {
		$class = ' class="manage-column';

		$class .= " column-$column_key";

		if ( 'cb' == $column_key )
			$class .= ' check-column';
		elseif ( in_array($column_key, array('posts', 'comments', 'links')) )
			$class .= ' num';

		$class .= '"';

		$style = '';
		if ( in_array($column_key, $hidden) )
			$style = 'display:none;';

		if ( isset($styles[$type]) && isset($styles[$type][$column_key]) )
			$style .= ' ' . $styles[$type][$column_key];
		$style = ' style="' . $style . '"';
?>
	<th scope="col" <?php echo $id ? "id=\"$column_key\"" : ""; echo $class; echo $style; ?>><?php echo $column_display_name; ?></th>
<?php }
}

/**
 * {@internal Missing Short Description}}
 *
 * Outputs the quick edit and bulk edit table rows for posts and pages
 *
 * @since 2.7
 *
 * @param string $type 'post' or 'page'
 */
function inline_edit_row( $type ) {
	global $current_user, $mode;

	$is_page = 'page' == $type;
	if ( $is_page )
		$post = get_default_page_to_edit();
	else
		$post = get_default_post_to_edit();

	$columns = $is_page ? wp_manage_pages_columns() : wp_manage_posts_columns();
	$hidden = (array) get_user_option( "manage-$type-columns-hidden" );
	$hidden_count = empty($hidden[0]) ? 0 : count($hidden);
	$col_count = count($columns) - $hidden_count;
	$m = ( isset($mode) && 'excerpt' == $mode ) ? 'excerpt' : 'list';
	$can_publish = current_user_can('publish_posts'); ?>

<form method="get" action=""><table style="display: none"><tbody id="inlineedit">
	<?php
	$bulk = 0;
	while ( $bulk < 2 ) { ?>

	<tr id="<?php echo $bulk ? 'bulk-edit' : 'inline-edit'; ?>" style="display: none"><td colspan="<?php echo $col_count; ?>">
	<?php
	foreach($columns as $column_name=>$column_display_name) {
		$class = "class=\"$column_name column-$column_name quick-edit-div\"";

		$style = '';
		if ( in_array($column_name, $hidden) )
			$style = ' style="display:none;"';

		$attributes = "$class$style";

		switch($column_name) {
			case 'cb':
				break;

			case 'date':
				if ( ! $bulk ) { ?>
				<div <?php echo $attributes; ?> title="<?php _e('Timestamp'); ?>">
					<div class="title"><?php _e('Timestamp'); ?></div>
					<div class="in">
					<?php touch_time(1, 1, 4, 1); ?>
					</div>
				</div>
				<?php
				}
				break;

			case 'title':
				$attributes = "class=\"$type-title column-title quick-edit-div\"" . $style; ?>
				<?php if ( $bulk ) { ?>
				<div <?php echo $attributes; ?> id="bulk-title-div" title="<?php $is_page ? _e('Selected pages') : _e('Selected posts'); ?>">
					<div class="title"><?php $is_page ? _e('Selected pages') : _e('Selected posts'); ?></div>
					<div class="in">
					<div id="bulk-titles"></div>
					</div>
				</div>
				<?php } else { ?>
				<div <?php echo $attributes ?>>
					<div class="title"><?php _e('Title'); ?></div>
					<div class="in">
					<label title="<?php _e('Title'); ?>"><input type="text" name="post_title" class="ptitle" value="" /></label><br />
					<div class="slug">
					<label title="<?php _e('Slug'); ?>"><?php _e('Slug'); ?><input type="text" name="post_name" value="" /></label></div>
					</div>
				</div>
				<?php } ?>

				<div class="status quick-edit-div" title="<?php _e('Status'); ?>">
					<div class="title"><?php _e('Status'); ?></div>
					<div class="in">
					<select name="_status">
						<?php if ( $bulk ) { ?>
						<option value="-1"><?php _e('- No Change -'); ?></option>
							<?php if ( $can_publish ) { ?>
							<option value="private"><?php _e('Private') ?></option>
							<?php } ?>
						<?php } ?>
						<?php if ( $can_publish ) { // Contributors only get "Unpublished" and "Pending Review" ?>
						<option value="publish"><?php _e('Published') ?></option>
						<option value="future"><?php _e('Scheduled') ?></option>
						<?php } ?>
						<option value="pending"><?php _e('Pending Review') ?></option>
						<option value="draft"><?php _e('Unpublished') ?></option>
					</select>
					<?php if ( !$is_page ) { ?>
					<label title="<?php _e('Sticky') ?>">
					<input type="checkbox" name="sticky" value="sticky" /> <?php _e('Sticky') ?></label>
					<?php } ?>  
					</div>
				</div>

				<?php if ( $is_page ) { ?>
				<div class="parent quick-edit-div" title="<?php _e('Page Parent'); ?>">
					<div class="title"><?php _e('Page Parent'); ?></div>
					<div class="in">
					<select name="post_parent">
						<?php if ( $bulk ) { ?>
						<option value="-1"><?php _e('- No Change -'); ?></option>
						<?php } ?>
						<option value="0"><?php _e('Main Page (no parent)'); ?></option>
						<?php parent_dropdown(); ?>
					</select>
					</div>
				</div>

				<div class="template quick-edit-div" title="<?php _e('Page Template'); ?>">
					<div class="title"><?php _e('Page Template'); ?></div>
					<div class="in">
					<select name="page_template">
						<?php if ( $bulk ) { ?>
						<option value="-1"><?php _e('- No Change -'); ?></option>
						<?php } ?>
						<option value="default"><?php _e('Default Template'); ?></option>
						<?php page_template_dropdown() ?>
					</select>
					</div>
				</div>

				<?php if ( ! $bulk ) { ?>
				<div class="order quick-edit-div" title="<?php _e('Page Order'); ?>">
					<div class="title"><?php _e('Page Order'); ?></div>
					<div class="in">
					<input type="text" name="menu_order" value="<?php echo $post->menu_order ?>" />
					</div>
				</div>
				<?php }
				}

				break;

			case 'categories': ?>
				<?php if ( ! $bulk ) { ?>
				<div <?php echo $attributes ?> title="<?php _e('Categories'); ?>">
					<div class="title"><?php _e('Categories'); ?>
					<span class="catshow"><?php _e('(expand)'); ?></span>
					<span class="cathide" style="display:none;"><?php _e('(fold)'); ?></span></div>
					<ul class="cat-checklist">
						<?php wp_category_checklist(); ?>
					</ul>
				</div>
				<?php }
				break;

			case 'tags': ?>
				<?php if ( ! $bulk ) { ?>
				<div <?php echo $attributes ?> title="<?php _e('Tags'); ?>">
					<div class="title"><?php _e('Tags'); ?></div>
					<div class="in">
					<textarea cols="22" rows="1" name="tags_input" class="tags_input"></textarea>
					</div>
				</div>
				<?php }
				break;

			case 'comments':
				?>
				<div <?php echo $attributes ?> title="<?php _e('Comments and Pings'); ?>">
					<div class="title"><?php _e('Comments and Pings'); ?></div>
					<div class="in">
					<?php if ( $bulk ) { ?>
					<select name="comment_status">
						<option value=""><?php _e('- No Change -'); ?></option>
						<option value="open"><?php _e('Allow Comments'); ?></option>
						<option value="closed"><?php _e('Disallow Comments'); ?></option>
					</select>
					<select name="ping_status">
						<option value=""><?php _e('- No Change -'); ?></option>
						<option value="open"><?php _e('Allow Pings'); ?></option>
						<option value="closed"><?php _e('Disallow Pings'); ?></option>
					</select>
					<?php } else { ?>
					<label><input type="checkbox" name="comment_status" value="open" />
					<?php _e('Allow Comments'); ?></label><br />
					<label><input type="checkbox" name="ping_status" value="open" />
					<?php _e('Allow Pings'); ?></label>
					<?php } ?>
					</div>
				</div>
				<?php
				break;

			case 'author':
				$authors = get_editable_user_ids( $current_user->id ); // TODO: ROLE SYSTEM
				if ( $authors && count( $authors ) > 1 ) { ?>
				<div <?php echo $attributes ?> title="<?php _e('Author'); ?>">
					<div class="title"><?php _e('Author'); ?></div>
					<div class="in">
					<?php
					$users_opt = array('include' => $authors, 'name' => 'post_author', 'class'=> 'authors', 'multi' => 1);
					if ( $bulk ) $users_opt['show_option_none'] = __('- No Change -');
					wp_dropdown_users( $users_opt ); ?>
					</div>
				</div>
				<?php } ?>

				<?php if ( ! $bulk ) { ?>
				<div class="password quick-edit-div" title="<?php _e('Password'); ?>">
					<div class="title"><?php _e('Password'); ?></div>
					<div class="in">
					<input type="text" name="post_password" value="" />
					<label title="<?php _e('Privacy'); ?>">
					<input type="checkbox" name="keep_private" value="private" <?php checked($post->post_status, 'private'); ?> /> <?php echo $is_page ? __('Keep this page private') : __('Keep this post private'); ?></label>
					</div>
				</div>
				<?php }
				break;

			default:
				if ( $bulk )
					do_action('bulk_edit_custom_box', $column_name, $type);
				else
					do_action('quick_edit_custom_box', $column_name, $type);

				break;
		}
	} ?>

	<div class="clear"></div>
	<div class="quick-edit-save">
		<a accesskey="c" href="#inline-edit" title="<?php _e('Cancel'); ?>" class="button-secondary cancel"><?php _e('Cancel'); ?></a>
		<a accesskey="s" href="#inline-edit" title="<?php _e('Save'); ?>" class="button-secondary save"><?php _e('Save'); ?></a>
		<?php if ( ! $bulk ) {
			wp_nonce_field( 'inlineeditnonce', '_inline_edit', false ); ?>
			<img class="waiting" style="display:none;" src="images/loading.gif" alt="" />
		<?php } ?>
		<input type="hidden" name="post_view" value="<?php echo $m; ?>" />
	</div>
	</td></tr>
<?php
	$bulk++;
	} ?>
	</tbody></table></form>
<?php
}

// adds hidden fields with the data for use in the inline editor for posts and pages
/**
 * {@internal Missing Short Description}}
 *
 * @since unknown
 *
 * @param unknown_type $post
 */
function get_inline_data($post) {

	if ( ! current_user_can('edit_' . $post->post_type, $post->ID) )
		return;

	$title = _draft_or_post_title($post->ID);

	echo '
<div class="hidden" id="inline_' . $post->ID . '">
	<div class="post_title">' . $title . '</div>
	<div class="post_name">' . $post->post_name . '</div>
	<div class="post_author">' . $post->post_author . '</div>
	<div class="comment_status">' . $post->comment_status . '</div>
	<div class="ping_status">' . $post->ping_status . '</div>
	<div class="_status">' . $post->post_status . '</div>
	<div class="jj">' . mysql2date( 'd', $post->post_date ) . '</div>
	<div class="mm">' . mysql2date( 'm', $post->post_date ) . '</div>
	<div class="aa">' . mysql2date( 'Y', $post->post_date ) . '</div>
	<div class="hh">' . mysql2date( 'H', $post->post_date ) . '</div>
	<div class="mn">' . mysql2date( 'i', $post->post_date ) . '</div>
	<div class="post_password">' . wp_specialchars($post->post_password, 1) . '</div>';

	if( $post->post_type == 'page' )
		echo '
	<div class="post_parent">' . $post->post_parent . '</div>
	<div class="page_template">' . wp_specialchars(get_post_meta( $post->ID, '_wp_page_template', true ), 1) . '</div>
	<div class="menu_order">' . $post->menu_order . '</div>';

	if( $post->post_type == 'post' )
		echo '
	<div class="tags_input">' . wp_specialchars( str_replace( ',', ', ', get_tags_to_edit($post->ID) ), 1) . '</div>
	<div class="post_category">' . implode( ',', wp_get_post_categories( $post->ID ) ) . '</div>
	<div class="sticky">' . (is_sticky($post->ID) ? 'sticky' : '') . '</div>';

	echo '</div>';
}

/**
 * {@internal Missing Short Description}}
 *
 * @since unknown
 *
 * @param unknown_type $posts
 */
function post_rows( $posts = array() ) {
	global $wp_query, $post, $mode;

	add_filter('the_title','wp_specialchars');

	// Create array of post IDs.
	$post_ids = array();

	if ( empty($posts) )
		$posts = &$wp_query->posts;

	foreach ( $posts as $a_post )
		$post_ids[] = $a_post->ID;

	$comment_pending_count = get_pending_comments_num($post_ids);
	if ( empty($comment_pending_count) )
		$comment_pending_count = array();

	foreach ( $posts as $post ) {
		if ( empty($comment_pending_count[$post->ID]) )
			$comment_pending_count[$post->ID] = 0;

		_post_row($post, $comment_pending_count[$post->ID], $mode);
	}
}

/**
 * {@internal Missing Short Description}}
 *
 * @since unknown
 *
 * @param unknown_type $a_post
 * @param unknown_type $pending_comments
 * @param unknown_type $mode
 */
function _post_row($a_post, $pending_comments, $mode) {
	global $post;
	static $rowclass;

	$global_post = $post;
	$post = $a_post;
	setup_postdata($post);

	$rowclass = 'alternate' == $rowclass ? '' : 'alternate';
	global $current_user;
	$post_owner = ( $current_user->ID == $post->post_author ? 'self' : 'other' );
	$edit_link = get_edit_post_link( $post->ID );
	$title = _draft_or_post_title();
?>
	<tr id='post-<?php echo $post->ID; ?>' class='<?php echo trim( $rowclass . ' author-' . $post_owner . ' status-' . $post->post_status ); ?> iedit' valign="top">
<?php
	$posts_columns = wp_manage_posts_columns();
	$hidden = (array) get_user_option( 'manage-post-columns-hidden' );
	foreach ( $posts_columns as $column_name=>$column_display_name ) {
		$class = "class=\"$column_name column-$column_name\"";

		$style = '';
		if ( in_array($column_name, $hidden) )
			$style = ' style="display:none;"';

		$attributes = "$class$style";

		switch ($column_name) {

		case 'cb':
		?>
		<th scope="row" class="check-column"><?php if ( current_user_can( 'edit_post', $post->ID ) ) { ?><input type="checkbox" name="post[]" value="<?php the_ID(); ?>" /><?php } ?></th>
		<?php
		break;

		case 'date':
			if ( '0000-00-00 00:00:00' == $post->post_date && 'date' == $column_name ) {
				$t_time = $h_time = __('Unpublished');
			} else {
				$t_time = get_the_time(__('Y/m/d g:i:s A'));
				$m_time = $post->post_date;
				$time = get_post_time('G', true);

				if ( ( abs(time() - $time) ) < 86400 ) {
					if ( ( 'future' == $post->post_status) )
						$h_time = sprintf( __('%s from now'), human_time_diff( $time ) );
					else
						$h_time = sprintf( __('%s ago'), human_time_diff( $time ) );
				} else {
					$h_time = mysql2date(__('Y/m/d'), $m_time);
				}
			}

			echo '<td ' . $attributes . '>';
			if ( 'excerpt' == $mode )
				echo apply_filters('post_date_column_time', $t_time, $post, $column_name, $mode);
			else
				echo '<abbr title="' . $t_time . '">' . apply_filters('post_date_column_time', $h_time, $post, $column_name, $mode) . '</abbr>';
			echo '<br />';
			if ( 'publish' == $post->post_status || 'future' == $post->post_status )
				_e('Published');
			else
				_e('Last Modified');
			echo '</td>';
		break;

		case 'title':
			$attributes = 'class="post-title column-title"' . $style;
		?>
		<td <?php echo $attributes ?>><strong><?php if ( current_user_can( 'edit_post', $post->ID ) ) { ?><a class="row-title" href="<?php echo $edit_link; ?>" title="<?php echo attribute_escape(sprintf(__('Edit "%s"'), $title)); ?>"><?php echo $title ?></a><?php } else { echo $title; }; _post_states($post); ?></strong>
		<?php
			if ( 'excerpt' == $mode )
				the_excerpt();

			$actions = array();
			if ( current_user_can('edit_post', $post->ID) ) {
				$actions['edit'] = '<a href="' . get_edit_post_link($post->ID, true) . '" title="' . attribute_escape(__('Edit this post')) . '">' . __('Edit') . '</a>';
				$actions['inline hide-if-no-js'] = '<a href="#" class="editinline" title="' . attribute_escape(__('Edit this post inline')) . '">' . __('Quick&nbsp;Edit') . '</a>';
				$actions['delete'] = "<a class='submitdelete' title='" . attribute_escape(__('Delete this post')) . "' href='" . wp_nonce_url("post.php?action=delete&amp;post=$post->ID", 'delete-post_' . $post->ID) . "' onclick=\"if ( confirm('" . js_escape(sprintf( ('draft' == $post->post_status) ? __("You are about to delete this draft '%s'\n 'Cancel' to stop, 'OK' to delete.") : __("You are about to delete this post '%s'\n 'Cancel' to stop, 'OK' to delete."), $post->post_title )) . "') ) { return true;}return false;\">" . __('Delete') . "</a>";
			}
			if ( in_array($post->post_status, array('pending', 'draft')) )
				$actions['view'] = '<a href="' . get_permalink($post->ID) . '" title="' . attribute_escape(sprintf(__('Preview "%s"'), $title)) . '" rel="permalink">' . __('Preview') . '</a>';
			else
				$actions['view'] = '<a href="' . get_permalink($post->ID) . '" title="' . attribute_escape(sprintf(__('View "%s"'), $title)) . '" rel="permalink">' . __('View') . '</a>';
			$action_count = count($actions);
			$i = 0;
			foreach ( $actions as $action => $link ) {
				++$i;
				( $i == $action_count ) ? $sep = '' : $sep = ' | ';
				echo "<span class='$action'>$link$sep</span>";
			}

			get_inline_data($post);
		?>
		</td>
		<?php
		break;

		case 'categories':
		?>
		<td <?php echo $attributes ?>><?php
			$categories = get_the_category();
			if ( !empty( $categories ) ) {
				$out = array();
				foreach ( $categories as $c )
					$out[] = "<a href='edit.php?category_name=$c->slug'> " . wp_specialchars(sanitize_term_field('name', $c->name, $c->term_id, 'category', 'display')) . "</a>";
					echo join( ', ', $out );
			} else {
				_e('Uncategorized');
			}
		?></td>
		<?php
		break;

		case 'tags':
		?>
		<td <?php echo $attributes ?>><?php
			$tags = get_the_tags($post->ID);
			if ( !empty( $tags ) ) {
				$out = array();
				foreach ( $tags as $c )
					$out[] = "<a href='edit.php?tag=$c->slug'> " . wp_specialchars(sanitize_term_field('name', $c->name, $c->term_id, 'post_tag', 'display')) . "</a>";
				echo join( ', ', $out );
			} else {
				_e('No Tags');
			}
		?></td>
		<?php
		break;

		case 'comments':
		?>
		<td <?php echo $attributes ?>><div class="post-com-count-wrapper">
		<?php
			$pending_phrase = sprintf( __('%s pending'), number_format( $pending_comments ) );
			if ( $pending_comments )
				echo '<strong>';
				comments_number("<a href='edit.php?p=$post->ID' title='$pending_phrase' class='post-com-count'><span class='comment-count'>" . __('0') . '</span></a>', "<a href='edit.php?p=$post->ID' title='$pending_phrase' class='post-com-count'><span class='comment-count'>" . __('1') . '</span></a>', "<a href='edit.php?p=$post->ID' title='$pending_phrase' class='post-com-count'><span class='comment-count'>" . __('%') . '</span></a>');
				if ( $pending_comments )
				echo '</strong>';
		?>
		</div></td>
		<?php
		break;

		case 'author':
		?>
		<td <?php echo $attributes ?>><a href="edit.php?author=<?php the_author_ID(); ?>"><?php the_author() ?></a></td>
		<?php
		break;

		case 'control_view':
		?>
		<td><a href="<?php the_permalink(); ?>" rel="permalink" class="view"><?php _e('View'); ?></a></td>
		<?php
		break;

		case 'control_edit':
		?>
		<td><?php if ( current_user_can('edit_post', $post->ID) ) { echo "<a href='$edit_link' class='edit'>" . __('Edit') . "</a>"; } ?></td>
		<?php
		break;

		case 'control_delete':
		?>
		<td><?php if ( current_user_can('delete_post', $post->ID) ) { echo "<a href='" . wp_nonce_url("post.php?action=delete&amp;post=$id", 'delete-post_' . $post->ID) . "' class='delete'>" . __('Delete') . "</a>"; } ?></td>
		<?php
		break;

		default:
		?>
		<td <?php echo $attributes ?>><?php do_action('manage_posts_custom_column', $column_name, $post->ID); ?></td>
		<?php
		break;
	}
}
?>
	</tr>
<?php
	$post = $global_post;
}

/*
 * display one row if the page doesn't have any children
 * otherwise, display the row and its children in subsequent rows
 */
/**
 * {@internal Missing Short Description}}
 *
 * @since unknown
 *
 * @param unknown_type $page
 * @param unknown_type $level
 */
function display_page_row( $page, $level = 0 ) {
	global $post;
	static $rowclass;

	$post = $page;
	setup_postdata($page);

	$page->post_title = wp_specialchars( $page->post_title );
	$pad = str_repeat( '&#8212; ', $level );
	$id = (int) $page->ID;
	$rowclass = 'alternate' == $rowclass ? '' : 'alternate';
	$posts_columns = wp_manage_pages_columns();
	$hidden = (array) get_user_option( 'manage-page-columns-hidden' );
	$title = _draft_or_post_title();
?>
<tr id="page-<?php echo $id; ?>" class="<?php echo $rowclass; ?> iedit">
<?php

foreach ($posts_columns as $column_name=>$column_display_name) {
	$class = "class=\"$column_name column-$column_name\"";

	$style = '';
	if ( in_array($column_name, $hidden) )
		$style = ' style="display:none;"';

	$attributes = "$class$style";

	switch ($column_name) {

	case 'cb':
		?>
		<th scope="row" class="check-column"><input type="checkbox" name="post[]" value="<?php the_ID(); ?>" /></th>
		<?php
		break;
	case 'date':
		if ( '0000-00-00 00:00:00' == $page->post_date && 'date' == $column_name ) {
			$t_time = $h_time = __('Unpublished');
		} else {
			$t_time = get_the_time(__('Y/m/d g:i:s A'));
			$m_time = $page->post_date;
			$time = get_post_time('G', true);

			if ( ( abs(time() - $time) ) < 86400 ) {
				if ( ( 'future' == $page->post_status) )
					$h_time = sprintf( __('%s from now'), human_time_diff( $time ) );
				else
					$h_time = sprintf( __('%s ago'), human_time_diff( $time ) );
			} else {
				$h_time = mysql2date(__('Y/m/d'), $m_time);
			}
		}
		echo '<td ' . $attributes . '>';
		echo '<abbr title="' . $t_time . '">' . apply_filters('post_date_column_time', $h_time, $page, $column_name, $mode) . '</abbr>';
		echo '<br />';
		if ( 'publish' == $page->post_status || 'future' == $page->post_status )
			_e('Published');
		else
			_e('Last Modified');
		echo '</td>';
		break;
	case 'title':
		$attributes = 'class="post-title page-title column-title"' . $style;
		$edit_link = get_edit_post_link( $page->ID );
		?>
		<td <?php echo $attributes ?>><strong><?php if ( current_user_can( 'edit_post', $page->ID ) ) { ?><a class="row-title" href="<?php echo $edit_link; ?>" title="<?php echo attribute_escape(sprintf(__('Edit "%s"'), $title)); ?>"><?php echo $pad; echo $title ?></a><?php } else { echo $pad; echo $title; }; _post_states($page); ?></strong>
		<?php
		$actions = array();
		$actions['edit'] = '<a href="' . $edit_link . '" title="' . attribute_escape(__('Edit this page')) . '">' . __('Edit') . '</a>';
		$actions['inline'] = '<a href="#" class="editinline">' . __('Quick&nbsp;Edit') . '</a>';
		$actions['delete'] = "<a class='submitdelete' title='" . attribute_escape(__('Delete this page')) . "' href='" . wp_nonce_url("page.php?action=delete&amp;post=$page->ID", 'delete-page_' . $page->ID) . "' onclick=\"if ( confirm('" . js_escape(sprintf( ('draft' == $page->post_status) ? __("You are about to delete this draft '%s'\n 'Cancel' to stop, 'OK' to delete.") : __("You are about to delete this page '%s'\n 'Cancel' to stop, 'OK' to delete."), $page->post_title )) . "') ) { return true;}return false;\">" . __('Delete') . "</a>";
		if ( in_array($post->post_status, array('pending', 'draft')) )
			$actions['view'] = '<a href="' . get_permalink($page->ID) . '" title="' . attribute_escape(sprintf(__('Preview "%s"'), $title)) . '" rel="permalink">' . __('Preview') . '</a>';
		else
			$actions['view'] = '<a href="' . get_permalink($page->ID) . '" title="' . attribute_escape(sprintf(__('View "%s"'), $title)) . '" rel="permalink">' . __('View') . '</a>';
		$action_count = count($actions);
		$i = 0;
		foreach ( $actions as $action => $link ) {
			++$i;
			( $i == $action_count ) ? $sep = '' : $sep = ' | ';
			echo "<span class='$action'>$link$sep</span>";
		}

		get_inline_data($post);
		echo '</td>';
		break;

	case 'comments':
		?>
		<td <?php echo $attributes ?>><div class="post-com-count-wrapper">
		<?php
		$left = get_pending_comments_num( $page->ID );
		$pending_phrase = sprintf( __('%s pending'), number_format( $left ) );
		if ( $left )
			echo '<strong>';
		comments_number("<a href='edit-pages.php?page_id=$id' title='$pending_phrase' class='post-com-count'><span class='comment-count'>" . __('0') . '</span></a>', "<a href='edit-pages.php?page_id=$id' title='$pending_phrase' class='post-com-count'><span class='comment-count'>" . __('1') . '</span></a>', "<a href='edit-pages.php?page_id=$id' title='$pending_phrase' class='post-com-count'><span class='comment-count'>" . __('%') . '</span></a>');
		if ( $left )
			echo '</strong>';
		?>
		</div></td>
		<?php
		break;

	case 'author':
		?>
		<td <?php echo $attributes ?>><a href="edit-pages.php?author=<?php the_author_ID(); ?>"><?php the_author() ?></a></td>
		<?php
		break;

	default:
		?>
		<td <?php echo $attributes ?>><?php do_action('manage_pages_custom_column', $column_name, $id); ?></td>
		<?php
		break;
	}
}
?>

</tr>

<?php
}

/*
 * displays pages in hierarchical order with paging support
 */
/**
 * {@internal Missing Short Description}}
 *
 * @since unknown
 *
 * @param unknown_type $pages
 * @param unknown_type $pagenum
 * @param unknown_type $per_page
 * @return unknown
 */
function page_rows($pages, $pagenum = 1, $per_page = 20) {
	global $wpdb;

	$level = 0;

	if ( ! $pages ) {
		$pages = get_pages( array('sort_column' => 'menu_order') );

		if ( ! $pages )
			return false;
	}

	/*
	 * arrange pages into two parts: top level pages and children_pages
	 * children_pages is two dimensional array, eg.
	 * children_pages[10][] contains all sub-pages whose parent is 10.
	 * It only takes O(N) to arrange this and it takes O(1) for subsequent lookup operations
	 * If searching, ignore hierarchy and treat everything as top level
	 */
	if ( empty($_GET['s']) ) {

		$top_level_pages = array();
		$children_pages = array();

		foreach ( $pages as $page ) {

			// catch and repair bad pages
			if ( $page->post_parent == $page->ID ) {
				$page->post_parent = 0;
				$wpdb->query( $wpdb->prepare("UPDATE $wpdb->posts SET post_parent = '0' WHERE ID = %d", $page->ID) );
				clean_page_cache( $page->ID );
			}

			if ( 0 == $page->post_parent )
				$top_level_pages[] = $page;
			else
				$children_pages[ $page->post_parent ][] = $page;
		}

		$pages = &$top_level_pages;
	}

	$count = 0;
	$start = ($pagenum - 1) * $per_page;
	$end = $start + $per_page;

	foreach ( $pages as $page ) {
		if ( $count >= $end )
			break;

		if ( $count >= $start )
			echo "\t" . display_page_row( $page, $level );

		$count++;

		if ( isset($children_pages) )
			_page_rows( $children_pages, $count, $page->ID, $level + 1, $pagenum, $per_page );
	}

	// if it is the last pagenum and there are orphaned pages, display them with paging as well
	if ( isset($children_pages) && $count < $end ){
		foreach( $children_pages as $orphans ){
			foreach ( $orphans as $op ) {
				if ( $count >= $end )
					break;
				if ( $count >= $start )
					echo "\t" . display_page_row( $op, 0 );
				$count++;
			}
		}
	}
}

/*
 * Given a top level page ID, display the nested hierarchy of sub-pages
 * together with paging support
 */
/**
 * {@internal Missing Short Description}}
 *
 * @since unknown
 *
 * @param unknown_type $children_pages
 * @param unknown_type $count
 * @param unknown_type $parent
 * @param unknown_type $level
 * @param unknown_type $pagenum
 * @param unknown_type $per_page
 */
function _page_rows( &$children_pages, &$count, $parent, $level, $pagenum, $per_page ) {

	if ( ! isset( $children_pages[$parent] ) )
		return;

	$start = ($pagenum - 1) * $per_page;
	$end = $start + $per_page;

	foreach ( $children_pages[$parent] as $page ) {

		if ( $count >= $end )
			break;

		// If the page starts in a subtree, print the parents.
		if ( $count == $start && $page->post_parent > 0 ) {
			$my_parents = array();
			$my_parent = $page->post_parent;
			while ( $my_parent) {
				$my_parent = get_post($my_parent);
				$my_parents[] = $my_parent;
				if ( !$my_parent->post_parent )
					break;
				$my_parent = $my_parent->post_parent;
			}
			$num_parents = count($my_parents);
			while( $my_parent = array_pop($my_parents) ) {
				echo "\t" . display_page_row( $my_parent, $level - $num_parents );
				$num_parents--;
			}
		}

		if ( $count >= $start )
			echo "\t" . display_page_row( $page, $level );

		$count++;

		_page_rows( $children_pages, $count, $page->ID, $level + 1, $pagenum, $per_page );
	}

	unset( $children_pages[$parent] ); //required in order to keep track of orphans
}

/**
 * {@internal Missing Short Description}}
 *
 * @since unknown
 *
 * @param unknown_type $user_object
 * @param unknown_type $style
 * @param unknown_type $role
 * @return unknown
 */
function user_row( $user_object, $style = '', $role = '' ) {
	global $wp_roles;

	$current_user = wp_get_current_user();

	if ( !( is_object( $user_object) && is_a( $user_object, 'WP_User' ) ) )
		$user_object = new WP_User( (int) $user_object );
	$email = $user_object->user_email;
	$url = $user_object->user_url;
	$short_url = str_replace( 'http://', '', $url );
	$short_url = str_replace( 'www.', '', $short_url );
	if ('/' == substr( $short_url, -1 ))
		$short_url = substr( $short_url, 0, -1 );
	if ( strlen( $short_url ) > 35 )
		$short_url = substr( $short_url, 0, 32 ).'...';
	$numposts = get_usernumposts( $user_object->ID );
	if ( current_user_can( 'edit_user', $user_object->ID ) ) {
		if ($current_user->ID == $user_object->ID) {
			$edit_link = 'profile.php';
		} else {
			$edit_link = clean_url( add_query_arg( 'wp_http_referer', urlencode( clean_url( stripslashes( $_SERVER['REQUEST_URI'] ) ) ), "user-edit.php?user_id=$user_object->ID" ) );
		}
		$edit = "<strong><a href=\"$edit_link\">$user_object->user_login</a></strong><br />";
		$actions = array();
		$actions['edit'] = '<a href="' . $edit_link . '">' . __('Edit') . '</a>';
		$actions['delete'] = "<a class='submitdelete' href='" . wp_nonce_url("users.php?action=delete&amp;user=$user_object->ID", 'bulk-users') . "'>" . __('Delete') . "</a>";
		$action_count = count($actions);
		$i = 0;
		foreach ( $actions as $action => $link ) {
			++$i;
			( $i == $action_count ) ? $sep = '' : $sep = ' | ';
			$edit .= "<span class='$action'>$link$sep</span>";
		}
	} else {
		$edit = '<strong>' . $user_object->user_login . '</strong>';
	}
	$role_name = isset($wp_roles->role_names[$role]) ? translate_with_context($wp_roles->role_names[$role]) : __('None');
	$r = "<tr id='user-$user_object->ID'$style>";
	$columns = get_column_headers('user');
	$hidden = (array) get_user_option( 'manage-user-columns-hidden' );
	foreach ( $columns as $column_name => $column_display_name ) {
		$class = "class=\"$column_name column-$column_name\"";

		$style = '';
		if ( in_array($column_name, $hidden) )
			$style = ' style="display:none;"';

		$attributes = "$class$style";

		switch ($column_name) {
			case 'cb':
				$r .= "<th scope='row' class='check-column'><input type='checkbox' name='users[]' id='user_{$user_object->ID}' class='$role' value='{$user_object->ID}' /></th>";
				break;
			case 'username':
				$r .= "<td $attributes>$edit</td>";
				break;
			case 'name':
				$r .= "<td $attributes>$user_object->first_name $user_object->last_name</td>";
				break;
			case 'email':
				$r .= "<td $attributes><a href='mailto:$email' title='" . sprintf( __('e-mail: %s' ), $email ) . "'>$email</a></td>";
				break;
			case 'role':
				$r .= "<td $attributes>$role_name</td>";
				break;
			case 'posts':
				$attributes = 'class="posts column-posts num"' . $style;
				$r .= "<td $attributes>";
				if ( $numposts > 0 ) {
					$r .= "<a href='edit.php?author=$user_object->ID' title='" . __( 'View posts by this author' ) . "' class='edit'>";
					$r .= $numposts;
					$r .= '</a>';
				} else {
					$r .= 0;
				}
				$r .= "</td>";
		}
	}
	$r .= '</tr>';

	return $r;
}

/**
 * {@internal Missing Short Description}}
 *
 * @since unknown
 *
 * @param unknown_type $status
 * @param unknown_type $s
 * @param unknown_type $start
 * @param unknown_type $num
 * @param unknown_type $post
 * @param unknown_type $type
 * @return unknown
 */
function _wp_get_comment_list( $status = '', $s = false, $start, $num, $post = 0, $type = '' ) {
	global $wpdb;

	$start = abs( (int) $start );
	$num = (int) $num;
	$post = (int) $post;

	if ( 'moderated' == $status )
		$approved = "comment_approved = '0'";
	elseif ( 'approved' == $status )
		$approved = "comment_approved = '1'";
	elseif ( 'spam' == $status )
		$approved = "comment_approved = 'spam'";
	else
		$approved = "( comment_approved = '0' OR comment_approved = '1' )";

	if ( $post )
		$post = " AND comment_post_ID = '$post'";
	else
		$post = '';

	if ( 'comment' == $type )
		$typesql = "AND comment_type = ''";
	elseif ( 'pingback' == $type )
		$typesql = "AND comment_type = 'pingback'";
	elseif ( 'trackback' == $type )
		$typesql = "AND comment_type = 'trackback'";
	elseif ( 'pings' == $type )
		$typesql = "AND ( comment_type = 'pingback' OR comment_type = 'trackback' )";
	else
		$typesql = '';

	if ( $s ) {
		$s = $wpdb->escape($s);
		$comments = $wpdb->get_results("SELECT SQL_CALC_FOUND_ROWS * FROM $wpdb->comments WHERE
			(comment_author LIKE '%$s%' OR
			comment_author_email LIKE '%$s%' OR
			comment_author_url LIKE ('%$s%') OR
			comment_author_IP LIKE ('%$s%') OR
			comment_content LIKE ('%$s%') ) AND
			$approved
			$typesql
			ORDER BY comment_date_gmt DESC LIMIT $start, $num");
	} else {
		$comments = $wpdb->get_results( "SELECT SQL_CALC_FOUND_ROWS * FROM $wpdb->comments WHERE $approved $post $typesql ORDER BY comment_date_gmt DESC LIMIT $start, $num" );
	}

	update_comment_cache($comments);

	$total = $wpdb->get_var( "SELECT FOUND_ROWS()" );

	return array($comments, $total);
}

/**
 * {@internal Missing Short Description}}
 *
 * @since unknown
 *
 * @param unknown_type $comment_id
 * @param unknown_type $mode
 * @param unknown_type $comment_status
 * @param unknown_type $checkbox
 */
function _wp_comment_row( $comment_id, $mode, $comment_status, $checkbox = true ) {
	global $comment, $post;
	$comment = get_comment( $comment_id );
	$post = get_post($comment->comment_post_ID);
	$authordata = get_userdata($post->post_author);
	$the_comment_status = wp_get_comment_status($comment->comment_ID);

	if ( current_user_can( 'edit_post', $post->ID ) ) {
		$post_link = "<a href='" . get_edit_post_link($post->ID) . "'>";
		$post_link .= get_the_title($comment->comment_post_ID) . '</a>';
	} else {
		$post_link = get_the_title($comment->comment_post_ID);
	}

	$author_url = get_comment_author_url();
	if ( 'http://' == $author_url )
		$author_url = '';
	$author_url_display = $author_url;

	$ptime = date('G', strtotime( $comment->comment_date ) );
	if ( ( abs(time() - $ptime) ) < 86400 )
		$ptime = sprintf( __('%s ago'), human_time_diff( $ptime ) );
	else
		$ptime = mysql2date(__('Y/m/d \a\t g:i A'), $comment->comment_date );

	$delete_url = clean_url( wp_nonce_url( "comment.php?action=deletecomment&p=$comment->comment_post_ID&c=$comment->comment_ID", "delete-comment_$comment->comment_ID" ) );
	$approve_url = clean_url( wp_nonce_url( "comment.php?action=approvecomment&p=$comment->comment_post_ID&c=$comment->comment_ID", "approve-comment_$comment->comment_ID" ) );
	$unapprove_url = clean_url( wp_nonce_url( "comment.php?action=unapprovecomment&p=$comment->comment_post_ID&c=$comment->comment_ID", "unapprove-comment_$comment->comment_ID" ) );
	$spam_url = clean_url( wp_nonce_url( "comment.php?action=deletecomment&dt=spam&p=$comment->comment_post_ID&c=$comment->comment_ID", "delete-comment_$comment->comment_ID" ) );
?>
<li id='comment-<?php echo $comment->comment_ID; ?>' class='<?php echo $the_comment_status; ?> comment-list-item'>
<p class="comment-author"><strong><?php comment_author(); ?></strong> 
<?php if ( current_user_can( 'edit_post', $post->ID ) && !empty( $comment->comment_author_email ) ) { echo ' | '; comment_author_email_link(); } ?> 
<span class="sepa">|</span> <a href="edit-comments.php?s=<?php comment_author_IP(); ?>"><?php comment_author_IP(); ?></a> <span class="sepa">|</span> <a href="<?php echo get_permalink( $post->ID ) . '#comment-' . $comment->comment_ID; ?>"><?php echo get_comment_date( __('Y/m/d \a\t g:ia') ); ?></a><br />
<?php if ( !empty($author_url) ) echo "<a href='$author_url'>$author_url_display</a>"; ?>
</p>

<?php if ( 'detail' == $mode || 'single' == $mode ) comment_text(); ?>
<div id="inline-<?php echo $comment->comment_ID; ?>" class="hidden">
<textarea class="comment" rows="3" cols="10"><?php echo $comment->comment_content; ?></textarea>
<div class="author-email"><?php echo attribute_escape( $comment->comment_author_email ); ?></div>
<div class="author"><?php echo attribute_escape( $comment->comment_author ); ?></div>
<div class="author-url"><?php echo attribute_escape( $comment->comment_author_url ); ?></div>
<div class="comment_status"><?php echo $comment->comment_approved; ?></div>
</div>
<p class="comment-actions">
<?php
// TODO: I don't think checkboxes really matter anymore
//if ( $checkbox && current_user_can( 'edit_post', $comment->comment_post_ID ) )
//	echo "<input type='checkbox' name='delete_comments[]' value='$comment->comment_ID' /> &nbsp;";
?>

<?php
$actions = array();

if ( current_user_can('edit_post', $comment->comment_post_ID) ) {
	$actions['approve'] = "<span class='comment-action-link'><a href='$approve_url' class='dim:the-comment-list:comment-$comment->comment_ID:unapproved:e7e7d3:e7e7d3:new=approved vim-a' title='" . __( 'Approve this comment' ) . "'>" . __( 'Approve' ) . '</a></span>';
	$actions['unapprove'] = "<span class='comment-action-link'><a href='$unapprove_url' class='dim:the-comment-list:comment-$comment->comment_ID:unapproved:e7e7d3:e7e7d3:new=unapproved vim-u' title='" . __( 'Unapprove this comment' ) . "'>" . __( 'Unapprove' ) . '</a></span>';
	if ( $comment_status ) { // not looking at all comments
		if ( 'approved' == $the_comment_status ) {
			$actions['unapprove'] = "<span class='comment-action-link'><a href='$unapprove_url' class='delete:the-comment-list:comment-$comment->comment_ID:e7e7d3:action=dim-comment vim-u vim-destructive' title='" . __( 'Unapprove this comment' ) . "'>" . __( 'Unapprove' ) . '</a></span>';
			unset($actions['approve']);
		} else {
			$actions['approve'] = "<span class='comment-action-link'><a href='$approve_url' class='delete:the-comment-list:comment-$comment->comment_ID:e7e7d3:action=dim-comment vim-a vim-destructive' title='" . __( 'Approve this comment' ) . "'>" . __( 'Approve' ) . '</a></span>';
			unset($actions['unapprove']);
		}
	}
	if ( 'spam' != $the_comment_status )
		$actions['spam'] = "<a href='$spam_url' class='delete:the-comment-list:comment-$comment->comment_ID::spam=1 vim-s vim-destructive' title='" . __( 'Mark this comment as spam' ) . "'>" . __( 'Spam' ) . '</a>';
	$actions['delete'] = "<a href='$delete_url' class='delete:the-comment-list:comment-$comment->comment_ID delete vim-d vim-destructive'>" . __('Delete') . '</a>';
	$actions['edit'] = "<a href='comment.php?action=editcomment&amp;c={$comment->comment_ID}' title='" . __('Edit comment') . "'>". __('Edit') . '</a>';
	$actions['quickedit'] = '<a onclick="commentReply.open(\''.$comment->comment_ID.'\',\''.$post->ID.'\',\'edit\');return false;" class="vim-q" title="'.__('Quick Edit').'" href="#">' . __('Quick Edit') . '</a>';
	if ( 'spam' != $the_comment_status )
		$actions['reply'] = '<a onclick="commentReply.open(\''.$comment->comment_ID.'\',\''.$post->ID.'\');return false;" class="vim-r" title="'.__('Reply to this comment').'" href="#">' . __('Reply') . '</a>';

	$actions = apply_filters( 'comment_row_actions', $actions, $comment );

	$i = 0;
	foreach ( $actions as $action => $link ) {
		++$i;
		( ( ('approve' == $action || 'unapprove' == $action) && 2 === $i ) || 1 === $i ) ? $sep = '' : $sep = ' <span class="sepa">|</span> ';

		// Reply and quickedit need a hide-if-no-js span
		if ( 'reply' == $action || 'quickedit' == $action )
			$action .= ' hide-if-no-js';

		echo "<span class='$action'>$sep$link</span>";
	}
}
?>
 &nbsp;<span class="sepa">&#8212;</span>&nbsp; <a href="<?php echo get_permalink( $post->ID ); ?>" style="text-decoration: none;">&para;</a> <?php echo $post_link; ?> <a href="edit.php?p=<?php echo $post->ID; ?>">(<?php echo number_format( $post->comment_count ); ?>)</a></p>
</li>
<?php
}

/**
 * {@internal Missing Short Description}}
 *
 * @since unknown
 *
 * @param unknown_type $position
 * @param unknown_type $checkbox
 * @param unknown_type $mode
 */
function wp_comment_reply($position = '1', $checkbox = false, $mode = 'single', $table_row = false) {
	global $current_user;

	// allow plugin to replace the popup content
	$content = apply_filters( 'wp_comment_reply', '', array('position' => $position, 'checkbox' => $checkbox, 'mode' => $mode) );

	if ( ! empty($content) ) {
		echo $content;
		return;
	}
?>
<form method="get" action="">
<?php if ( $table_row ) : ?>
<table style="display:none;"><tbody id="com-reply"><tr id="replyrow"><td colspan="6">
<?php else : ?>
<ul id="com-reply" style="display:none;"><li id="replyrow">
<?php endif; ?>
	<div id="replyhead" style="display:none;"><?php _e('Reply to Comment'); ?></div>

	<div id="edithead" style="display:none;">
		<div id="edittitle"><?php _e('Edit Comment'); ?></div>

		<div class="inside">
		<label for="author"><?php _e('Name') ?></label>
		<input type="text" name="newcomment_author" size="50" value="" tabindex="101" id="author" />
		</div>

		<div class="inside">
		<label for="author-email"><?php _e('E-mail') ?></label>
		<input type="text" name="newcomment_author_email" size="50" value="" tabindex="102" id="author-email" />
		</div>

		<div class="inside">
		<label for="author-url"><?php _e('URL') ?></label>
		<input type="text" id="author-url" name="newcomment_author_url" size="103" value="" tabindex="103" />
		</div>
		<div style="clear:both;"></div>
	</div>

	<div id="replycontainer"><textarea rows="8" cols="40" name="replycontent" tabindex="104" id="replycontent"></textarea></div>

	<p id="replysubmit">
	<a href="#comments-form" class="cancel button" tabindex="106"><?php _e('Cancel'); ?></a>
	<a href="#comments-form" class="save button" tabindex="105">
	<span id="savebtn" style="display:none;"><?php _e('Save'); ?></span>
	<span id="replybtn" style="display:none;"><?php _e('Submit Reply'); ?></span></a>
	<img class="waiting" style="display:none;" src="images/loading.gif" alt="" />
	<span class="error" style="display:none;"></span>
	</p>

	<input type="hidden" name="user_ID" id="user_ID" value="<?php echo $current_user->ID; ?>" />
	<input type="hidden" name="action" id="action" value="" />
	<input type="hidden" name="comment_ID" id="comment_ID" value="" />
	<input type="hidden" name="comment_post_ID" id="comment_post_ID" value="" />
	<input type="hidden" name="status" id="status" value="" />
	<input type="hidden" name="position" id="position" value="<?php echo $position; ?>" />
	<input type="hidden" name="checkbox" id="checkbox" value="<?php echo $checkbox ? 1 : 0; ?>" />
	<input type="hidden" name="mode" id="mode" value="<?php echo $mode; ?>" />
	<?php wp_nonce_field( 'replyto-comment', '_ajax_nonce', false ); ?>
	<?php wp_comment_form_unfiltered_html_nonce(); ?>
<?php if ( $table_row ) : ?>
</td></tr></tbody></table>
<?php else : ?>
</li></ul>
<?php endif; ?>
</form>
<?php
}

/**
 * {@internal Missing Short Description}}
 *
 * @since unknown
 *
 * @param unknown_type $currentcat
 * @param unknown_type $currentparent
 * @param unknown_type $parent
 * @param unknown_type $level
 * @param unknown_type $categories
 * @return unknown
 */
function wp_dropdown_cats( $currentcat = 0, $currentparent = 0, $parent = 0, $level = 0, $categories = 0 ) {
	if (!$categories )
		$categories = get_categories( array('hide_empty' => 0) );

	if ( $categories ) {
		foreach ( $categories as $category ) {
			if ( $currentcat != $category->term_id && $parent == $category->parent) {
				$pad = str_repeat( '&#8211; ', $level );
				$category->name = wp_specialchars( $category->name );
				echo "\n\t<option value='$category->term_id'";
				if ( $currentparent == $category->term_id )
					echo " selected='selected'";
				echo ">$pad$category->name</option>";
				wp_dropdown_cats( $currentcat, $currentparent, $category->term_id, $level +1, $categories );
			}
		}
	} else {
		return false;
	}
}

/**
 * {@internal Missing Short Description}}
 *
 * @since unknown
 *
 * @param unknown_type $meta
 */
function list_meta( $meta ) {
	// Exit if no meta
	if (!$meta ) {
		echo '<tbody id="the-list" class="list:meta"><tr style="display: none;"><td>&nbsp;</td></tr></tbody>'; //TBODY needed for list-manipulation JS
		return;
	}
	$count = 0;
?>
	<thead>
	<tr>
		<th><?php _e( 'Key' ) ?></th>
		<th><?php _e( 'Value' ) ?></th>
		<th colspan='2'><?php _e( 'Action' ) ?></th>
	</tr>
	</thead>
	<tbody id='the-list' class='list:meta'>
<?php
	foreach ( $meta as $entry )
		echo _list_meta_row( $entry, $count );
	echo "\n\t</tbody>";
}

/**
 * {@internal Missing Short Description}}
 *
 * @since unknown
 *
 * @param unknown_type $entry
 * @param unknown_type $count
 * @return unknown
 */
function _list_meta_row( $entry, &$count ) {
	static $update_nonce = false;
	if ( !$update_nonce )
		$update_nonce = wp_create_nonce( 'add-meta' );

	$r = '';
	++ $count;
	if ( $count % 2 )
		$style = 'alternate';
	else
		$style = '';
	if ('_' == $entry['meta_key'] { 0 } )
		$style .= ' hidden';

	if ( is_serialized( $entry['meta_value'] ) ) {
		if ( is_serialized_string( $entry['meta_value'] ) ) {
			// this is a serialized string, so we should display it
			$entry['meta_value'] = maybe_unserialize( $entry['meta_value'] );
		} else {
			// this is a serialized array/object so we should NOT display it
			--$count;
			return;
		}
	}

	$entry['meta_key'] = attribute_escape($entry['meta_key']);
	$entry['meta_value'] = htmlspecialchars($entry['meta_value']); // using a <textarea />
	$entry['meta_id'] = (int) $entry['meta_id'];

	$delete_nonce = wp_create_nonce( 'delete-meta_' . $entry['meta_id'] );

	$r .= "\n\t<tr id='meta-{$entry['meta_id']}' class='$style'>";
	$r .= "\n\t\t<td valign='top'><label class='hidden' for='meta[{$entry['meta_id']}][key]'>" . __( 'Key' ) . "</label><input name='meta[{$entry['meta_id']}][key]' id='meta[{$entry['meta_id']}][key]' tabindex='6' type='text' size='20' value='{$entry['meta_key']}' /></td>";
	$r .= "\n\t\t<td><label class='hidden' for='meta[{$entry['meta_id']}][value]'>" . __( 'Value' ) . "</label><textarea name='meta[{$entry['meta_id']}][value]' id='meta[{$entry['meta_id']}][value]' tabindex='6' rows='2' cols='30'>{$entry['meta_value']}</textarea></td>";
	$r .= "\n\t\t<td style='text-align: center;'><input name='updatemeta' type='submit' tabindex='6' value='".attribute_escape(__( 'Update' ))."' class='add:the-list:meta-{$entry['meta_id']}::_ajax_nonce=$update_nonce updatemeta' /><br />";
	$r .= "\n\t\t<input name='deletemeta[{$entry['meta_id']}]' type='submit' ";
	$r .= "class='delete:the-list:meta-{$entry['meta_id']}::_ajax_nonce=$delete_nonce deletemeta' tabindex='6' value='".attribute_escape(__( 'Delete' ))."' />";
	$r .= wp_nonce_field( 'change-meta', '_ajax_nonce', false, false );
	$r .= "</td>\n\t</tr>";
	return $r;
}

/**
 * {@internal Missing Short Description}}
 *
 * @since unknown
 */
function meta_form() {
	global $wpdb;
	$limit = (int) apply_filters( 'postmeta_form_limit', 30 );
	$keys = $wpdb->get_col( "
		SELECT meta_key
		FROM $wpdb->postmeta
		WHERE meta_key NOT LIKE '\_%'
		GROUP BY meta_key
		ORDER BY meta_id DESC
		LIMIT $limit" );
	if ( $keys )
		natcasesort($keys);
?>
<p><strong><?php _e( 'Add a new custom field:' ) ?></strong></p>
<table id="newmeta" cellspacing="3" cellpadding="3">
	<tr>
<th colspan="2"><label <?php if ( $keys ) : ?> for="metakeyselect" <?php else : ?> for="metakeyinput" <?php endif; ?>><?php _e( 'Key' ) ?></label></th>
<th><label for="metavalue"><?php _e( 'Value' ) ?></label></th>
</tr>
	<tr valign="top">
		<td style="width: 18%;" class="textright">
<?php if ( $keys ) : ?>
<select id="metakeyselect" name="metakeyselect" tabindex="7">
<option value="#NONE#"><?php _e( '- Select -' ); ?></option>
<?php

	foreach ( $keys as $key ) {
		$key = attribute_escape( $key );
		echo "\n\t<option value='$key'>$key</option>";
	}
?>
</select> <label for="metakeyinput"><?php _e( 'or' ); ?></label>
<?php endif; ?>
</td>
<td><input type="text" id="metakeyinput" name="metakeyinput" tabindex="7" /></td>
		<td><textarea id="metavalue" name="metavalue" rows="3" cols="25" tabindex="8"></textarea></td>
	</tr>
<tr class="submit"><td colspan="3">
	<?php wp_nonce_field( 'add-meta', '_ajax_nonce', false ); ?>
	<input type="submit" id="addmetasub" name="addmeta" class="add:the-list:newmeta" tabindex="9" value="<?php _e( 'Add Custom Field' ) ?>" />
</td></tr>
</table>
<?php

}

/**
 * {@internal Missing Short Description}}
 *
 * @since unknown
 *
 * @param unknown_type $edit
 * @param unknown_type $for_post
 * @param unknown_type $tab_index
 * @param unknown_type $multi
 */
function touch_time( $edit = 1, $for_post = 1, $tab_index = 0, $multi = 0 ) {
	global $wp_locale, $post, $comment;

	if ( $for_post )
		$edit = ( in_array($post->post_status, array('draft', 'pending') ) && (!$post->post_date || '0000-00-00 00:00:00' == $post->post_date ) ) ? false : true;

	$tab_index_attribute = '';
	if ( (int) $tab_index > 0 )
		$tab_index_attribute = " tabindex=\"$tab_index\"";

	// echo '<label for="timestamp" style="display: block;"><input type="checkbox" class="checkbox" name="edit_date" value="1" id="timestamp"'.$tab_index_attribute.' /> '.__( 'Edit timestamp' ).'</label><br />';

	$time_adj = time() + (get_option( 'gmt_offset' ) * 3600 );
	$post_date = ($for_post) ? $post->post_date : $comment->comment_date;
	$jj = ($edit) ? mysql2date( 'd', $post_date ) : gmdate( 'd', $time_adj );
	$mm = ($edit) ? mysql2date( 'm', $post_date ) : gmdate( 'm', $time_adj );
	$aa = ($edit) ? mysql2date( 'Y', $post_date ) : gmdate( 'Y', $time_adj );
	$hh = ($edit) ? mysql2date( 'H', $post_date ) : gmdate( 'H', $time_adj );
	$mn = ($edit) ? mysql2date( 'i', $post_date ) : gmdate( 'i', $time_adj );
	$ss = ($edit) ? mysql2date( 's', $post_date ) : gmdate( 's', $time_adj );

	$cur_jj = gmdate( 'd', $time_adj );
	$cur_mm = gmdate( 'm', $time_adj );
	$cur_aa = gmdate( 'Y', $time_adj );
	$cur_hh = gmdate( 'H', $time_adj );
	$cur_mn = gmdate( 'i', $time_adj );

	$month = "<select " . ( $multi ? '' : 'id="mm" ' ) . "name=\"mm\"$tab_index_attribute>\n";
	for ( $i = 1; $i < 13; $i = $i +1 ) {
		$month .= "\t\t\t" . '<option value="' . zeroise($i, 2) . '"';
		if ( $i == $mm )
			$month .= ' selected="selected"';
		$month .= '>' . $wp_locale->get_month_abbrev( $wp_locale->get_month( $i ) ) . "</option>\n";
	}
	$month .= '</select>';

	$day = '<input type="text" ' . ( $multi ? '' : 'id="jj" ' ) . 'name="jj" value="' . $jj . '" size="2" maxlength="2"' . $tab_index_attribute . ' autocomplete="off" />';
	$year = '<input type="text" ' . ( $multi ? '' : 'id="aa" ' ) . 'name="aa" value="' . $aa . '" size="4" maxlength="5"' . $tab_index_attribute . ' autocomplete="off" />';
	$hour = '<input type="text" ' . ( $multi ? '' : 'id="hh" ' ) . 'name="hh" value="' . $hh . '" size="2" maxlength="2"' . $tab_index_attribute . ' autocomplete="off" />';
	$minute = '<input type="text" ' . ( $multi ? '' : 'id="mn" ' ) . 'name="mn" value="' . $mn . '" size="2" maxlength="2"' . $tab_index_attribute . ' autocomplete="off" />';
	printf(_c('%1$s%2$s, %3$s <br />@ %4$s : %5$s|1: month input, 2: day input, 3: year input, 4: hour input, 5: minute input'), $month, $day, $year, $hour, $minute);

	if ( $multi ) return;

	echo "\n\n";
	foreach ( array('mm', 'jj', 'aa', 'hh', 'mn') as $timeunit ) {
		echo '<input type="hidden" id="hidden_' . $timeunit . '" name="hidden_' . $timeunit . '" value="' . $$timeunit . '" />' . "\n";
		$cur_timeunit = 'cur_' . $timeunit;
		echo '<input type="hidden" id="'. $cur_timeunit . '" name="'. $cur_timeunit . '" value="' . $$cur_timeunit . '" />' . "\n";
	}
?>

<input type="hidden" id="ss" name="ss" value="<?php echo $ss ?>" size="2" maxlength="2" />

<p>
<a href="#edit_timestamp" class="save-timestamp hide-if-no-js button"><?php _e('OK'); ?></a>
<a href="#edit_timestamp" class="cancel-timestamp hide-if-no-js"><?php _e('Cancel'); ?></a>
</p>
<?php
}

/**
 * {@internal Missing Short Description}}
 *
 * @since unknown
 *
 * @param unknown_type $default
 */
function page_template_dropdown( $default = '' ) {
	$templates = get_page_templates();
	ksort( $templates );
	foreach (array_keys( $templates ) as $template )
		: if ( $default == $templates[$template] )
			$selected = " selected='selected'";
		else
			$selected = '';
	echo "\n\t<option value='".$templates[$template]."' $selected>$template</option>";
	endforeach;
}

/**
 * {@internal Missing Short Description}}
 *
 * @since unknown
 *
 * @param unknown_type $default
 * @param unknown_type $parent
 * @param unknown_type $level
 * @return unknown
 */
function parent_dropdown( $default = 0, $parent = 0, $level = 0 ) {
	global $wpdb, $post_ID;
	$items = $wpdb->get_results( $wpdb->prepare("SELECT ID, post_parent, post_title FROM $wpdb->posts WHERE post_parent = %d AND post_type = 'page' ORDER BY menu_order", $parent) );

	if ( $items ) {
		foreach ( $items as $item ) {
			// A page cannot be its own parent.
			if (!empty ( $post_ID ) ) {
				if ( $item->ID == $post_ID ) {
					continue;
				}
			}
			$pad = str_repeat( '&nbsp;', $level * 3 );
			if ( $item->ID == $default)
				$current = ' selected="selected"';
			else
				$current = '';

			echo "\n\t<option class='level-$level' value='$item->ID'$current>$pad " . wp_specialchars($item->post_title) . "</option>";
			parent_dropdown( $default, $item->ID, $level +1 );
		}
	} else {
		return false;
	}
}

/**
 * {@internal Missing Short Description}}
 *
 * @since unknown
 */
function browse_happy() {
	$getit = __( 'WordPress recommends a better browser' );
	echo '
		<div id="bh"><a href="http://browsehappy.com/" title="'.$getit.'"><img src="images/browse-happy.gif" alt="Browse Happy" /></a></div>
';
}

/**
 * {@internal Missing Short Description}}
 *
 * @since unknown
 *
 * @param unknown_type $id
 * @return unknown
 */
function the_attachment_links( $id = false ) {
	$id = (int) $id;
	$post = & get_post( $id );

	if ( $post->post_type != 'attachment' )
		return false;

	$icon = get_attachment_icon( $post->ID );
	$attachment_data = wp_get_attachment_metadata( $id );
	$thumb = isset( $attachment_data['thumb'] );
?>
<form id="the-attachment-links">
<table>
	<col />
	<col class="widefat" />
	<tr>
		<th scope="row"><?php _e( 'URL' ) ?></th>
		<td><textarea rows="1" cols="40" type="text" class="attachmentlinks" readonly="readonly"><?php echo wp_get_attachment_url(); ?></textarea></td>
	</tr>
<?php if ( $icon ) : ?>
	<tr>
		<th scope="row"><?php $thumb ? _e( 'Thumbnail linked to file' ) : _e( 'Image linked to file' ); ?></th>
		<td><textarea rows="1" cols="40" type="text" class="attachmentlinks" readonly="readonly"><a href="<?php echo wp_get_attachment_url(); ?>"><?php echo $icon ?></a></textarea></td>
	</tr>
	<tr>
		<th scope="row"><?php $thumb ? _e( 'Thumbnail linked to page' ) : _e( 'Image linked to page' ); ?></th>
		<td><textarea rows="1" cols="40" type="text" class="attachmentlinks" readonly="readonly"><a href="<?php echo get_attachment_link( $post->ID ) ?>" rel="attachment wp-att-<?php echo $post->ID; ?>"><?php echo $icon ?></a></textarea></td>
	</tr>
<?php else : ?>
	<tr>
		<th scope="row"><?php _e( 'Link to file' ) ?></th>
		<td><textarea rows="1" cols="40" type="text" class="attachmentlinks" readonly="readonly"><a href="<?php echo wp_get_attachment_url(); ?>" class="attachmentlink"><?php echo basename( wp_get_attachment_url() ); ?></a></textarea></td>
	</tr>
	<tr>
		<th scope="row"><?php _e( 'Link to page' ) ?></th>
		<td><textarea rows="1" cols="40" type="text" class="attachmentlinks" readonly="readonly"><a href="<?php echo get_attachment_link( $post->ID ) ?>" rel="attachment wp-att-<?php echo $post->ID ?>"><?php the_title(); ?></a></textarea></td>
	</tr>
<?php endif; ?>
</table>
</form>
<?php
}

/**
 * {@internal Missing Short Description}}
 *
 * @since unknown
 *
 * @param unknown_type $default
 */
function wp_dropdown_roles( $default = false ) {
	global $wp_roles;
	$p = '';
	$r = '';
	foreach( $wp_roles->role_names as $role => $name ) {
		$name = translate_with_context($name);
		if ( $default == $role ) // Make default first in list
			$p = "\n\t<option selected='selected' value='$role'>$name</option>";
		else
			$r .= "\n\t<option value='$role'>$name</option>";
	}
	echo $p . $r;
}

/**
 * {@internal Missing Short Description}}
 *
 * @since unknown
 *
 * @param unknown_type $size
 * @return unknown
 */
function wp_convert_hr_to_bytes( $size ) {
	$size = strtolower($size);
	$bytes = (int) $size;
	if ( strpos($size, 'k') !== false )
		$bytes = intval($size) * 1024;
	elseif ( strpos($size, 'm') !== false )
		$bytes = intval($size) * 1024 * 1024;
	elseif ( strpos($size, 'g') !== false )
		$bytes = intval($size) * 1024 * 1024 * 1024;
	return $bytes;
}

/**
 * {@internal Missing Short Description}}
 *
 * @since unknown
 *
 * @param unknown_type $bytes
 * @return unknown
 */
function wp_convert_bytes_to_hr( $bytes ) {
	$units = array( 0 => 'B', 1 => 'kB', 2 => 'MB', 3 => 'GB' );
	$log = log( $bytes, 1024 );
	$power = (int) $log;
	$size = pow(1024, $log - $power);
	return $size . $units[$power];
}

/**
 * {@internal Missing Short Description}}
 *
 * @since unknown
 *
 * @return unknown
 */
function wp_max_upload_size() {
	$u_bytes = wp_convert_hr_to_bytes( ini_get( 'upload_max_filesize' ) );
	$p_bytes = wp_convert_hr_to_bytes( ini_get( 'post_max_size' ) );
	$bytes = apply_filters( 'upload_size_limit', min($u_bytes, $p_bytes), $u_bytes, $p_bytes );
	return $bytes;
}

/**
 * {@internal Missing Short Description}}
 *
 * @since unknown
 *
 * @param unknown_type $action
 */
function wp_import_upload_form( $action ) {
	$bytes = apply_filters( 'import_upload_size_limit', wp_max_upload_size() );
	$size = wp_convert_bytes_to_hr( $bytes );
?>
<form enctype="multipart/form-data" id="import-upload-form" method="post" action="<?php echo attribute_escape($action) ?>">
<p>
<?php wp_nonce_field('import-upload'); ?>
<label for="upload"><?php _e( 'Choose a file from your computer:' ); ?></label> (<?php printf( __('Maximum size: %s' ), $size ); ?>)
<input type="file" id="upload" name="import" size="25" />
<input type="hidden" name="action" value="save" />
<input type="hidden" name="max_file_size" value="<?php echo $bytes; ?>" />
</p>
<p class="submit">
<input type="submit" class="button" value="<?php _e( 'Upload file and import' ); ?>" />
</p>
</form>
<?php
}

/**
 * {@internal Missing Short Description}}
 *
 * @since unknown
 */
function wp_remember_old_slug() {
	global $post;
	$name = attribute_escape($post->post_name); // just in case
	if ( strlen($name) )
		echo '<input type="hidden" id="wp-old-slug" name="wp-old-slug" value="' . $name . '" />';
}

/**
 * Add a meta box to an edit form.
 *
 * @since 2.5.0
 *
 * @param string $id String for use in the 'id' attribute of tags.
 * @param string $title Title of the meta box.
 * @param string $callback Function that fills the box with the desired content. The function should echo its output.
 * @param string $page The type of edit page on which to show the box (post, page, link).
 * @param string $context The context within the page where the boxes should show ('normal', 'advanced').
 * @param string $priority The priority within the context where the boxes should show ('high', 'low').
 */
function add_meta_box($id, $title, $callback, $page, $context = 'advanced', $priority = 'default') {
	global $wp_meta_boxes;

	if ( !isset($wp_meta_boxes) )
		$wp_meta_boxes = array();
	if ( !isset($wp_meta_boxes[$page]) )
		$wp_meta_boxes[$page] = array();
	if ( !isset($wp_meta_boxes[$page][$context]) )
		$wp_meta_boxes[$page][$context] = array();

	foreach ( array_keys($wp_meta_boxes[$page]) as $a_context ) {
	foreach ( array('high', 'core', 'default', 'low') as $a_priority ) {
		if ( !isset($wp_meta_boxes[$page][$a_context][$a_priority][$id]) )
			continue;

		// If a core box was previously added or removed by a plugin, don't add.
		if ( 'core' == $priority ) {
			// If core box previously deleted, don't add
			if ( false === $wp_meta_boxes[$page][$a_context][$a_priority][$id] )
				return;
			// If box was added with default priority, give it core priority to maintain sort order
			if ( 'default' == $a_priority ) {
				$wp_meta_boxes[$page][$a_context]['core'][$id] = $wp_meta_boxes[$page][$a_context]['default'][$id];
				unset($wp_meta_boxes[$page][$a_context]['default'][$id]);
			}
			return;
		}
		// If no priority given and id already present, use existing priority
		if ( empty($priority) ) {
			$priority = $a_priority;
		// else if we're adding to the sorted priortiy, we don't know the title or callback. Glab them from the previously added context/priority.
		} elseif ( 'sorted' == $priority ) {
			$title = $wp_meta_boxes[$page][$a_context][$a_priority][$id]['title'];
			$callback = $wp_meta_boxes[$page][$a_context][$a_priority][$id]['callback'];
		}
		// An id can be in only one priority and one context
		if ( $priority != $a_priority || $context != $a_context )
			unset($wp_meta_boxes[$page][$a_context][$a_priority][$id]);
	}
	}

	if ( empty($priority) )
		$priority = 'low';

	if ( !isset($wp_meta_boxes[$page][$context][$priority]) )
		$wp_meta_boxes[$page][$context][$priority] = array();

	$wp_meta_boxes[$page][$context][$priority][$id] = array('id' => $id, 'title' => $title, 'callback' => $callback);
}

/**
 * {@internal Missing Short Description}}
 *
 * @since unknown
 *
 * @param unknown_type $page
 * @param unknown_type $context
 * @param unknown_type $object
 * @return int number of meta_boxes
 */
function do_meta_boxes($page, $context, $object) {
	global $wp_meta_boxes;
	static $already_sorted = false;

	do_action('do_meta_boxes', $page, $context, $object);

	$hidden = (array) get_user_option( "meta-box-hidden_$page" );

	echo "<div id='$context-sortables' class='meta-box-sortables'>\n";

	$i = 0;
	do {
		// Grab the ones the user has manually sorted. Pull them out of their previous context/priority and into the one the user chose
		if ( !$already_sorted && $sorted = get_user_option( "meta-box-order_$page" ) ) {
			foreach ( $sorted as $box_context => $ids )
				foreach ( explode(',', $ids) as $id )
					if ( $id )
						add_meta_box( $id, null, null, $page, $box_context, 'sorted' );
		}
		$already_sorted = true;

		if ( !isset($wp_meta_boxes) || !isset($wp_meta_boxes[$page]) || !isset($wp_meta_boxes[$page][$context]) )
			break;

		foreach ( array('high', 'sorted', 'core', 'default', 'low') as $priority ) {
			if ( isset($wp_meta_boxes[$page][$context][$priority]) ) {
				foreach ( (array) $wp_meta_boxes[$page][$context][$priority] as $box ) {
					if ( false == $box || ! $box['title'] )
						continue;
					$i++;
					$style = '';
					if ( in_array($box['id'], $hidden) )
						$style = 'style="display:none;"';
					echo '<div id="' . $box['id'] . '" class="postbox ' . postbox_classes($box['id'], $page) . '" ' . $style . '>' . "\n";
					echo "<h3 class='hndle'><span>{$box['title']}</span></h3>\n";
					echo '<div class="inside">' . "\n";
					call_user_func($box['callback'], $object, $box);
					echo "</div>\n";
					echo "</div>\n";
				}
			}
		}
	} while(0);

	echo "</div>";

	return $i;

}

/**
 * Remove a meta box from an edit form.
 *
 * @since 2.6.0
 *
 * @param string $id String for use in the 'id' attribute of tags.
 * @param string $page The type of edit page on which to show the box (post, page, link).
 * @param string $context The context within the page where the boxes should show ('normal', 'advanced').
 */
function remove_meta_box($id, $page, $context) {
	global $wp_meta_boxes;

	if ( !isset($wp_meta_boxes) )
		$wp_meta_boxes = array();
	if ( !isset($wp_meta_boxes[$page]) )
		$wp_meta_boxes[$page] = array();
	if ( !isset($wp_meta_boxes[$page][$context]) )
		$wp_meta_boxes[$page][$context] = array();

	foreach ( array('high', 'core', 'default', 'low') as $priority )
		$wp_meta_boxes[$page][$context][$priority][$id] = false;
}

/**
 * {@internal Missing Short Description}}
 *
 * @since unknown
 *
 * @param unknown_type $page
 */
function meta_box_prefs($page) {
	global $wp_meta_boxes;

	if ( empty($wp_meta_boxes[$page]) )
		return;

	$hidden = (array) get_user_option( "meta-box-hidden_$page" );

	foreach ( array_keys($wp_meta_boxes[$page]) as $context ) {
		foreach ( array_keys($wp_meta_boxes[$page][$context]) as $priority ) {
			foreach ( $wp_meta_boxes[$page][$context][$priority] as $box ) {
				if ( false == $box || ! $box['title'] )
					continue;
				// Submit box cannot be hidden
				if ( 'submitdiv' == $box['id'] )
					continue;
				$box_id = $box['id'];
				echo '<label for="' . $box_id . '-hide">';
				echo '<input class="hide-postbox-tog" name="' . $box_id . '-hide" type="checkbox" id="' . $box_id . '-hide" value="' . $box_id . '"' . (! in_array($box_id, $hidden) ? ' checked="checked"' : '') . ' />';
				echo "{$box['title']}</label>\n";
			}
		}
	}
}

/**
 * Add a new section to a settings page.
 *
 * @since 2.7.0
 *
 * @param string $id String for use in the 'id' attribute of tags.
 * @param string $title Title of the section.
 * @param string $callback Function that fills the section with the desired content. The function should echo its output.
 * @param string $page The type of settings page on which to show the section (general, reading, writing, ...).
 */
function add_settings_section($id, $title, $callback, $page) {
	global $wp_settings_sections;

	if ( !isset($wp_settings_sections) )
		$wp_settings_sections = array();
	if ( !isset($wp_settings_sections[$page]) )
		$wp_settings_sections[$page] = array();
	if ( !isset($wp_settings_sections[$page][$id]) )
		$wp_settings_sections[$page][$id] = array();

	$wp_settings_sections[$page][$id] = array('id' => $id, 'title' => $title, 'callback' => $callback);
}

/**
 * Add a new field to a settings page.
 *
 * @since 2.7.0
 *
 * @param string $id String for use in the 'id' attribute of tags.
 * @param string $title Title of the field.
 * @param string $callback Function that fills the field with the desired content. The function should echo its output.
 * @param string $page The type of settings page on which to show the field (general, reading, writing, ...).
 * @param string $section The section of the settingss page in which to show the box (default, ...).
 * @param array $args Additional arguments
 */
function add_settings_field($id, $title, $callback, $page, $section = 'default', $args = array()) {
	global $wp_settings_fields;

	if ( !isset($wp_settings_fields) )
		$wp_settings_fields = array();
	if ( !isset($wp_settings_fields[$page]) )
		$wp_settings_fields[$page] = array();
	if ( !isset($wp_settings_fields[$page][$section]) )
		$wp_settings_fields[$page][$section] = array();

	$wp_settings_fields[$page][$section][$id] = array('id' => $id, 'title' => $title, 'callback' => $callback, 'args' => $args);
}

/**
 * {@internal Missing Short Description}}
 *
 * @since unknown
 *
 * @param unknown_type $page
 */
function do_settings_sections($page) {
	global $wp_settings_sections, $wp_settings_fields;

	if ( !isset($wp_settings_sections) || !isset($wp_settings_sections[$page]) )
		return;

	foreach ( (array) $wp_settings_sections[$page] as $section ) {
		echo "<h3>{$section['title']}</h3>\n";
		call_user_func($section['callback'], $section);
		if ( !isset($wp_settings_fields) || !isset($wp_settings_fields[$page]) || !isset($wp_settings_fields[$page][$section['id']]) )
			continue;
		echo '<table class="form-table">';
		do_settings_fields($page, $section['id']);
		echo '</table>';
	}
}

/**
 * {@internal Missing Short Description}}
 *
 * @since unknown
 *
 * @param unknown_type $page
 * @param unknown_type $section
 */
function do_settings_fields($page, $section) {
	global $wp_settings_fields;

	if ( !isset($wp_settings_fields) || !isset($wp_settings_fields[$page]) || !isset($wp_settings_fields[$page][$section]) )
		return;

	foreach ( (array) $wp_settings_fields[$page][$section] as $field ) {
		echo '<tr valign="top">';
		if ( !empty($field['args']['label_for']) )
			echo '<th scope="row"><label for="' . $field['args']['label_for'] . '">' . $field['title'] . '</label></th>';
		else
			echo '<th scope="row">' . $field['title'] . '</th>';
		echo '<td>';
		call_user_func($field['callback']);
		echo '</td>';
		echo '</tr>';
	}
}

/**
 * {@internal Missing Short Description}}
 *
 * @since unknown
 *
 * @param unknown_type $page
 */
function manage_columns_prefs($page) {
	$columns = get_column_headers($page);

	$hidden = (array) get_user_option( "manage-$page-columns-hidden" );

	foreach ( $columns as $column => $title ) {
		// Can't hide these
		if ( 'cb' == $column || 'title' == $column || 'name' == $column || 'username' == $column || 'media' == $column || 'comment' == $column )
			continue;
		if ( empty($title) )
			continue;

		if ( 'comments' == $column )
			$title = __('Comments');
		$id = "$column-hide";
		echo '<label for="' . $id . '">';
		echo '<input class="hide-column-tog" name="' . $id . '" type="checkbox" id="' . $id . '" value="' . $column . '"' . (! in_array($column, $hidden) ? ' checked="checked"' : '') . ' />';
		echo "$title</label>\n";
	}
}

/**
 * {@internal Missing Short Description}}
 *
 * @since unknown
 *
 * @param unknown_type $found_action
 */
function find_posts_div($found_action = '') {
?>
	<div id="find-posts" class="find-box" style="display:none;">
		<div id="find-posts-head" class="find-box-head"><?php _e('Find Posts or Pages'); ?></div>
		<div class="find-box-inside">
			<div class="find-box-search">
				<?php if ( $found_action ) { ?>
					<input type="hidden" name="found_action" value="<?php echo $found_action; ?>" />
				<?php } ?>

				<input type="hidden" name="affected" id="affected" value="" />
				<?php wp_nonce_field( 'find-posts', '_ajax_nonce', false ); ?>
				<label class="hidden" for="find-posts-input"><?php _e( 'Search' ); ?></label>
				<input type="text" id="find-posts-input" class="search-input" name="ps" value="" />
				<input type="button" onClick="findPosts.send();" value="<?php _e( 'Search' ); ?>" class="button" /><br />

				<input type="radio" name="find-posts-what" id="find-posts-posts" checked="checked" value="posts" />
				<label for="find-posts-posts"><?php _e( 'Posts' ); ?></label>
				<input type="radio" name="find-posts-what" id="find-posts-pages" value="pages" />
				<label for="find-posts-pages"><?php _e( 'Pages' ); ?></label>
			</div>
			<div id="find-posts-response"></div>
		</div>
		<div class="find-box-buttons">
			<input type="button" class="button" onClick="findPosts.close();" value="<?php _e('Close'); ?>" />
			<input id="find-posts-submit" type="submit" class="button" value="<?php _e('Select'); ?>" />
		</div>
	</div>
	<script type="text/javascript">
	(function($){
		findPosts = {
			open : function(af_name, af_val) {
				var st = document.documentElement.scrollTop || $(document).scrollTop();

				if ( af_name && af_val )
					$('#affected').attr('name', af_name).val(af_val);

				$('#find-posts').show().draggable({
					handle: '#find-posts-head'
				}).resizable({
					handles: 'all',
					minHeight: 150,
					minWidth: 280
				}).css({'top':st+'px','left':'50%','marginLeft':'-200px'});

				$('.ui-resizable-handle').css({
					'backgroundColor': '#e5e5e5'
				});

				$('.ui-resizable-se').css({
					'border': '0 none',
					'width': '15px',
					'height': '16px',
					'background': 'transparent url(images/se.png) no-repeat scroll 0 0'
				});

				$('#find-posts-input').focus().keyup(function(e){
					if (e.which == 27) findPosts.close(); // close on Escape
				});

				return false;
			},

			close : function() {
				$('#find-posts-response').html('');
				$('#find-posts').draggable('destroy').resizable('destroy').hide();
			},

			send : function() {
				var post = {};

				post['ps'] = $('#find-posts-input').val();
				post['action'] = 'find_posts';
				post['_ajax_nonce'] = $('#_ajax_nonce').val();

				if ( $('#find-posts-pages:checked').val() )
					post['pages'] = 1;
				else
					post['posts'] = 1;

				$.ajax({
					type : 'POST',
					url : '<?php echo admin_url('admin-ajax.php'); ?>',
					data : post,
					success : function(x) { findPosts.show(x); },
					error : function(r) { findPosts.error(r); }
				});
			},

			show : function(x) {

				if ( typeof(x) == 'string' ) {
					this.error({'responseText': x});
					return;
				}

				var r = wpAjax.parseAjaxResponse(x);

				if ( r.errors )
					this.error({'responseText': wpAjax.broken});

				r = r.responses[0];
				$('#find-posts-response').html(r.data);
			},

			error : function(r) {
				var er = r.statusText;

				if ( r.responseText )
					er = r.responseText.replace( /<.[^<>]*?>/g, '' );

				if ( er )
					$('#find-posts-response').html(er);
			}
		};

		$(document).ready(function(){
			$('#find-posts-submit').click(function(e) {
				if ( '' == $('#find-posts-response').html() )
					e.preventDefault();
			});
		});
	})(jQuery);
	</script>
<?php
}

/**
 * Display the post password.
 *
 * The password is passed through {@link attribute_escape()} to ensure that it
 * is safe for placing in an html attribute.
 *
 * @uses attribute_escape
 * @since 2.7.0
 */
function the_post_password() {
	global $post;
	if ( isset( $post->post_password ) ) echo attribute_escape( $post->post_password );
}

/**
 * {@internal Missing Short Description}}
 *
 * @since unknown
 */
function favorite_actions() {
	$actions = array(
		'post-new.php' => array(__('Add New Post'), 'edit_posts'),
		'page-new.php' => array(__('Add New Page'), 'edit_pages'),
		'edit-comments.php' => array(__('Manage Comments'), 'moderate_comments')
		);

	$actions = apply_filters('favorite_actions', $actions);

	$allowed_actions = array();
	foreach ( $actions as $action => $data ) {
		if ( current_user_can($data[1]) )
			$allowed_actions[$action] = $data[0];
	}

	if ( empty($allowed_actions) )
		return;

	$first = array_keys($allowed_actions);
	$first = $first[0];
	echo '<div id="favorite-actions">';
	echo '<div id="favorite-first"><a href="' . $first . '">' . $allowed_actions[$first] . '</a></div><div id="favorite-toggle"><br /></div>';
	echo '<div id="favorite-inside">';

	array_shift($allowed_actions);

	foreach ( $allowed_actions as $action => $label) {
		echo "<div class='favorite-action'><a href='$action'>";
		echo $label;
		echo "</a></div>\n";
	}
	echo "</div></div>\n";
}

/**
 * Get the post title.
 *
 * The post title is fetched and if it is blank then a default string is
 * returned.
 *
 * @since 2.7.0
 * @param int $id The post id. If not supplied the global $post is used.
 *
 */
function _draft_or_post_title($post_id = 0)
{
	$title = get_the_title($post_id);
	if ( empty($title) )
		$title = __('(no title)');
	return $title;
}

/**
 * Display the search query.
 *
 * A simple wrapper to display the "s" parameter in a GET URI. This function
 * should only be used when {@link the_search_query()} cannot.
 *
 * @uses attribute_escape
 * @since 2.7.0
 *
 */
function _admin_search_query() {
	echo isset($_GET['s']) ? attribute_escape( stripslashes( $_GET['s'] ) ) : '';
}

/**
 * Generic Iframe header for use with Thickbox
 *
 * @since 2.7.0
 * @param string $title Title of the Iframe page.
 * @param bool $limit_styles Limit styles to colour-related styles only (unless others are enqueued).
 *
 */
function iframe_header( $title = '', $limit_styles = false) {
?><!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" <?php do_action('admin_xml_ns'); ?> <?php language_attributes(); ?>>
<head>
<meta http-equiv="Content-Type" content="<?php bloginfo('html_type'); ?>; charset=<?php echo get_option('blog_charset'); ?>" />
<title><?php bloginfo('name') ?> &rsaquo; <?php echo $title ?> &#8212; <?php _e('WordPress'); ?></title>
<?php
wp_enqueue_style( 'global' );
wp_enqueue_style( 'colors' );
if ( ! $limit_styles )
	wp_enqueue_style( 'wp-admin' );
?>
<script type="text/javascript">
//<![CDATA[
function addLoadEvent(func) {if ( typeof wpOnload!='function'){wpOnload=func;}else{ var oldonload=wpOnload;wpOnload=function(){oldonload();func();}}}
//]]>
</script>
<?php
do_action('admin_print_styles');
do_action('admin_print_scripts');
do_action('admin_head');
?>
</head>
<body<?php if ( isset($GLOBALS['body_id']) ) echo ' id="' . $GLOBALS['body_id'] . '"'; ?>>
<?php
}

/**
 * Generic Iframe footer for use with Thickbox
 *
 * @since 2.7.0
 *
 */
function iframe_footer() {
	echo '
	</body>
</html>';
}

function _post_states($post) {
	$post_states = array();
	if ( isset($_GET['post_status']) )
		$post_status = $_GET['post_status'];
	else
		$post_status = '';

	if ( !empty($post->post_password) )
		$post_states[] = __('Protected');
	if ( 'private' == $post->post_status && 'private' != $post_status )
		$post_states[] = __('Private');
	if ( 'draft' == $post->post_status && 'draft' != $post_status )
		$post_states[] = __('Draft');
	if ( 'pending' == $post->post_status && 'pending' != $post_status )
		$post_states[] = __('Pending');

	if ( ! empty($post_states) ) {
		$state_count = count($post_states);
		$i = 0;
		echo ' - ';
		foreach ( $post_states as $state ) {
			++$i;
			( $i == $state_count ) ? $sep = '' : $sep = ', ';
			echo "<span class='post-state'>$state$sep</span>";
		}
	}
}

function screen_options($screen, $metabox = false) {
?>
<div id="screen-options">
	<div id="screen-options-wrap" class="hidden">
	<h5><?php _e('Show on screen') ?></h5>
	<form id="adv-settings" action="" method="get">
	<div class="metabox-prefs">
<?php 
	if ( $metabox ) {
		meta_box_prefs($screen);
	} else {
		manage_columns_prefs($screen);
		wp_nonce_field( 'hiddencolumns', 'hiddencolumnsnonce', false ); 
	}
?>
	<br class="clear" />
	</div></form>
	</div>

	<div id="screen-options-link-wrap" class="hide-if-no-js screen-options-closed">
	<a href="#screen-options" id="show-settings-link" class="show-settings"><?php _e('Screen Options') ?></a>
	<a href="#screen-options" id="hide-settings-link" class="show-settings" style="display:none;"><?php _e('Hide Options') ?></a>
	</div>
</div>
<?php
}

?>
