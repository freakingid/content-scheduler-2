<?php
			$options = get_option('ContentScheduler_Options');
			// Now, make the array we would pass to wp_update_post
			// This is a local variable, so each time process_post is called, it will be new
			$update_post = array('ID' => $postid);

			// =============================================================
  	        // STATUS AND VISIBILITY
  	        switch( $options['chg-status'] )
  	        {
				case '0':
					// we do not need a post_status key
					break;
				case '1':
					$update_post['post_status'] = 'pending';
					break;
				case '2':
					$update_post['post_status'] = 'draft';
					break;
				case '3':
					$update_post['post_status'] = 'private';
					break;
				// default:
					// if it is anything else, let's make sure the post_status key is just gone from the array
					// NOTE: It would be better if we could just not make the array in the first place
					// unset( $update_post['post_status'] );
  	        } // end switch

			// =============================================================
  	        // CATEGORIES
  	        // First, let's check and see if we want to do Category changing or not.
  	        if( $options['chg-cat-method'] != '0' )
  	        {
  	        	// We do want category changes, so let's procees
				// list of categories we want to work with, as set in Content Scheduler > Options panel
				$category_switch = $options['selcats'];
				// list of categories the post is CURRENTLY in
				$current_category_objs = get_the_category($postid);
				// build a list of the post's current category ID's
				$current_category_ids = array();
				foreach( $current_category_objs as $object )
				{
					$current_category_ids[] = $object->term_id;
				} // end foreach
				
				switch( $options['chg-cat-method'] )
				{
					case '1':
						// we want to have the current categories
						// PLUS the selected categories
						$category_switch = array_merge( $current_category_ids, $category_switch );
						$category_switch = array_unique( $category_switch );
						break;
					case '2':
						// we want to have the current categories
						// MINUS the selected categories
						$category_switch = array_diff( $current_category_ids, $category_switch );
						break;
					case '3':
						// we want the categories to MATCH the selected categories
						// $category_switch is already set just fine
						break;
					default:
						unset( $update_post['post_category'] );
				} // end switch
				
				// set the 'post_category' part of update_post array
				$update_post['post_category'] = $category_switch;
			} // end if - checking chg-cat-method

			// =============================================================
			// TAGS (Check to see if the post type support post_tag first)
			$proceed = false;
			// Get the post type (we're using this same file for Posts and Custom Post Types)
			$post_type = get_post_type( $postid );
			if( ! empty( $post_type ) )
			{
				// See if the post_type is built-in Post
				if( $post_type == 'post' )
				{
					$proceed = true;
				}
				else
				{
					// If it is not built-in Post, then we need to find out its taxonomies (does it support post_tag)
					// Get the post type's capabilities
					$post_type_object = get_post_type_object( $post_type );
					// Get the array of supported taxonomies for this post_type
					$supported_taxos = $post_type_object->taxonomies;
					// Find out if post_tag is in $supported_taxos
					if( in_array( 'post_tag', $supported_taxos ) )
					{
						$proceed = true;
					} // end if for post_tag support
				} // end if for $post_type != post
				
				if( $proceed == true )
				{					
					// ===========================================================
					// First, check to see if we even want to do tags
					// $tags_to_add = $options['tags-to-add']; // this is a comma-delimited string
					// turn $tags_to_add into an array
					// $tags_to_add = explode( ", ", $tags_to_add );
					$tags_to_add = explode( ", ", $options['tags-to-add'] );					
					// make sure we just have a comma-separated list of alphanumeric entries
					$tags_to_add = filter_var_array( $tags_to_add, FILTER_SANITIZE_STRING );
					if( !empty( $tags_to_add ) )
					{
						// get the current tags list for this post
						$cur_post_tags = get_the_tags( $postid ); // returns an array of objects
						
						if( !empty( $cur_post_tags ) )
						{
							// Make a new array to keep just the current tag list in
							$new_cur_post_tags = array();
							// Step through $cur_post_tags objects, getting the 'name' parameter from each
							foreach( $cur_post_tags as $tag_object )
							{
								$new_cur_post_tags[] = $tag_object->name;
							}
						} // endif for cur_post_tags not empty
						
						if( !empty( $new_cur_post_tags ) )
						{
							// Combine the original list of tags with the new list of tags
							$final_tag_list = array_merge( $new_cur_post_tags, $tags_to_add );
							// Get rid of potential duplicate values from the array of tags
							$final_tag_list = array_unique( $final_tag_list );
						}
						else
						{
							$final_tag_list = $tags_to_add;
						}
						
						// now I need all those tags comma delimited again (did we have to go into the array and back out of it to handle the duplicates?)
						$final_tag_list = implode( ", ", $final_tag_list );
						
						// add the tag list to our $update_post
						$update_post['tags_input'] = $final_tag_list;
						
					} // endif for tags_to_add not empty
				} // endif for $proceed == true

			} // end if for post_type existing
			
			// =============================================================
			// NOW ACTUALLY UPDATE THE POST RECORD
  	        // Use the update array to actually update the post
  	        if( !wp_update_post( $update_post ) )
  	        {
  	        	// NOTE: We don't really want this to die
  	        	// Need to properly handle error on updating post, for 1.0
  	        	// for debug
  	        	// $this->log_to_file('debug', 'We failed to update a post');
  	        }
  	        else
  	        {
  	        	// update the post_meta so it won't end up here in our update query again
  	        	// We're not changing the expiration date, so we can look back and know when it expired.
  	        	update_post_meta( $postid, '_cs-enable-schedule', 'Disable' );
  	        }
  	        
  	        // The rest of this stuff is not actually stored in _posts
			// STICKINESS (Pages do not have STICKY ability)
			// Note: This is stored in the options table, and is not part of post_update
			// get the array of sticky posts
			// What do we want to do with stickiness?
			$sticky_change = $options['chg-sticky'];
			if( $sticky_change == '1' )
			{
				$sticky_posts = get_option('sticky_posts');
				if( ! empty( $sticky_posts ) )
				{
					// Remove $postid from the $sticky_posts[] array
					foreach( $sticky_posts as $key => $stuck_id )
					{
						if( $stuck_id == $postid )
						{
							// remove $key from $sticky_posts
							unset( $sticky_posts[$key] );
							break;
						} // end if
					} // end foreach
					// Get the new array of stickies back into WP
					update_option('sticky_posts', $sticky_posts);
				} // end if
			} // end if
?>
