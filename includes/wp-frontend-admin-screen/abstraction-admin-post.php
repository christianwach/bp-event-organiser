<?php
/**
 * Admin post function abstraction.
 */

// bail if in the admin area
! defined( 'WP_ADMIN' ) || exit();

/** ABSTRACTION *********************************************************/

if ( ! function_exists( 'edit_post' ) ) :
/**
 * Update an existing post with values provided in $_POST.
 *
 * @since 1.5.0
 *
 * @param array $post_data Optional.
 * @return int Post ID.
 */
function edit_post( $post_data = null ) {
	global $wpdb;

	if ( empty($post_data) )
		$post_data = &$_POST;

	// Clear out any data in internal vars.
	unset( $post_data['filter'] );

	$post_ID = (int) $post_data['post_ID'];
	$post = get_post( $post_ID );
	$post_data['post_type'] = $post->post_type;
	$post_data['post_mime_type'] = $post->post_mime_type;

	if ( ! empty( $post_data['post_status'] ) ) {
		$post_data['post_status'] = sanitize_key( $post_data['post_status'] );

		if ( 'inherit' == $post_data['post_status'] ) {
			unset( $post_data['post_status'] );
		}
	}

	$ptype = get_post_type_object($post_data['post_type']);
	if ( !current_user_can( 'edit_post', $post_ID ) ) {
		if ( 'page' == $post_data['post_type'] )
			wp_die( __('You are not allowed to edit this page.' ));
		else
			wp_die( __('You are not allowed to edit this post.' ));
	}

	if ( post_type_supports( $ptype->name, 'revisions' ) ) {
		$revisions = wp_get_post_revisions( $post_ID, array( 'order' => 'ASC', 'posts_per_page' => 1 ) );
		$revision = current( $revisions );

		// Check if the revisions have been upgraded
		if ( $revisions && _wp_get_post_revision_version( $revision ) < 1 )
			_wp_upgrade_revisions_of_post( $post, wp_get_post_revisions( $post_ID ) );
	}

	if ( isset($post_data['visibility']) ) {
		switch ( $post_data['visibility'] ) {
			case 'public' :
				$post_data['post_password'] = '';
				break;
			case 'password' :
				unset( $post_data['sticky'] );
				break;
			case 'private' :
				$post_data['post_status'] = 'private';
				$post_data['post_password'] = '';
				unset( $post_data['sticky'] );
				break;
		}
	}

	$post_data = _wp_translate_postdata( true, $post_data );
	if ( is_wp_error($post_data) )
		wp_die( $post_data->get_error_message() );

	// Post Formats
	if ( isset( $post_data['post_format'] ) )
		set_post_format( $post_ID, $post_data['post_format'] );

	$format_meta_urls = array( 'url', 'link_url', 'quote_source_url' );
	foreach ( $format_meta_urls as $format_meta_url ) {
		$keyed = '_format_' . $format_meta_url;
		if ( isset( $post_data[ $keyed ] ) )
			update_post_meta( $post_ID, $keyed, wp_slash( esc_url_raw( wp_unslash( $post_data[ $keyed ] ) ) ) );
	}

	$format_keys = array( 'quote', 'quote_source_name', 'image', 'gallery', 'audio_embed', 'video_embed' );

	foreach ( $format_keys as $key ) {
		$keyed = '_format_' . $key;
		if ( isset( $post_data[ $keyed ] ) ) {
			if ( current_user_can( 'unfiltered_html' ) )
				update_post_meta( $post_ID, $keyed, $post_data[ $keyed ] );
			else
				update_post_meta( $post_ID, $keyed, wp_filter_post_kses( $post_data[ $keyed ] ) );
		}
	}

	if ( 'attachment' === $post_data['post_type'] && preg_match( '#^(audio|video)/#', $post_data['post_mime_type'] ) ) {
		$id3data = wp_get_attachment_metadata( $post_ID );
		if ( ! is_array( $id3data ) ) {
			$id3data = array();
		}

		foreach ( wp_get_attachment_id3_keys( $post, 'edit' ) as $key => $label ) {
			if ( isset( $post_data[ 'id3_' . $key ] ) ) {
				$id3data[ $key ] = sanitize_text_field( wp_unslash( $post_data[ 'id3_' . $key ] ) );
			}
		}
		wp_update_attachment_metadata( $post_ID, $id3data );
	}

	// Meta Stuff
	if ( isset($post_data['meta']) && $post_data['meta'] ) {
		foreach ( $post_data['meta'] as $key => $value ) {
			if ( !$meta = get_post_meta_by_id( $key ) )
				continue;
			if ( $meta->post_id != $post_ID )
				continue;
			if ( is_protected_meta( $value['key'], 'post' ) || ! current_user_can( 'edit_post_meta', $post_ID, $value['key'] ) )
				continue;
			update_meta( $key, $value['key'], $value['value'] );
		}
	}

	if ( isset($post_data['deletemeta']) && $post_data['deletemeta'] ) {
		foreach ( $post_data['deletemeta'] as $key => $value ) {
			if ( !$meta = get_post_meta_by_id( $key ) )
				continue;
			if ( $meta->post_id != $post_ID )
				continue;
			if ( is_protected_meta( $meta->meta_key, 'post' ) || ! current_user_can( 'delete_post_meta', $post_ID, $meta->meta_key ) )
				continue;
			delete_meta( $key );
		}
	}

	// Attachment stuff
	if ( 'attachment' == $post_data['post_type'] ) {
		if ( isset( $post_data[ '_wp_attachment_image_alt' ] ) ) {
			$image_alt = wp_unslash( $post_data['_wp_attachment_image_alt'] );
			if ( $image_alt != get_post_meta( $post_ID, '_wp_attachment_image_alt', true ) ) {
				$image_alt = wp_strip_all_tags( $image_alt, true );
				// update_meta expects slashed.
				update_post_meta( $post_ID, '_wp_attachment_image_alt', wp_slash( $image_alt ) );
			}
		}

		$attachment_data = isset( $post_data['attachments'][ $post_ID ] ) ? $post_data['attachments'][ $post_ID ] : array();

		/** This filter is documented in wp-admin/includes/media.php */
		$post_data = apply_filters( 'attachment_fields_to_save', $post_data, $attachment_data );
	}

	// Convert taxonomy input to term IDs, to avoid ambiguity.
	if ( isset( $post_data['tax_input'] ) ) {
		foreach ( (array) $post_data['tax_input'] as $taxonomy => $terms ) {
			// Hierarchical taxonomy data is already sent as term IDs, so no conversion is necessary.
			if ( is_taxonomy_hierarchical( $taxonomy ) ) {
				continue;
			}

			/*
			 * Assume that a 'tax_input' string is a comma-separated list of term names.
			 * Some languages may use a character other than a comma as a delimiter, so we standardize on
			 * commas before parsing the list.
			 */
			if ( ! is_array( $terms ) ) {
				$comma = _x( ',', 'tag delimiter' );
				if ( ',' !== $comma ) {
					$terms = str_replace( $comma, ',', $terms );
				}
				$terms = explode( ',', trim( $terms, " \n\t\r\0\x0B," ) );
			}

			$clean_terms = array();
			foreach ( $terms as $term ) {
				// Empty terms are invalid input.
				if ( empty( $term ) ) {
					continue;
				}

				$_term = get_terms( $taxonomy, array(
					'name' => $term,
					'fields' => 'ids',
					'hide_empty' => false,
				) );

				if ( ! empty( $_term ) ) {
					$clean_terms[] = intval( $_term[0] );
				} else {
					// No existing term was found, so pass the string. A new term will be created.
					$clean_terms[] = $term;
				}
			}

			$post_data['tax_input'][ $taxonomy ] = $clean_terms;
		}
	}

	add_meta( $post_ID );

	update_post_meta( $post_ID, '_edit_last', get_current_user_id() );

	$success = wp_update_post( $post_data );
	// If the save failed, see if we can sanity check the main fields and try again
	if ( ! $success && is_callable( array( $wpdb, 'strip_invalid_text_for_column' ) ) ) {
		$fields = array( 'post_title', 'post_content', 'post_excerpt' );

		foreach( $fields as $field ) {
			if ( isset( $post_data[ $field ] ) ) {
				$post_data[ $field ] = $wpdb->strip_invalid_text_for_column( $wpdb->posts, $field, $post_data[ $field ] );
			}
		}

		wp_update_post( $post_data );
	}

	// Now that we have an ID we can fix any attachment anchor hrefs
	_fix_attachment_links( $post_ID );

	wp_set_post_lock( $post_ID );

	if ( current_user_can( $ptype->cap->edit_others_posts ) ) {
		if ( ! empty( $post_data['sticky'] ) )
			stick_post( $post_ID );
		else
			unstick_post( $post_ID );
	}

	return $post_ID;
}
endif;

if ( ! function_exists( 'get_default_post_to_edit' ) ) :
/**
 * Default post information to use when populating the "Write Post" form.
 *
 * @since 2.0.0
 *
 * @param string $post_type A post type string, defaults to 'post'.
 * @return WP_Post Post object containing all the default post data as attributes
 */
function get_default_post_to_edit( $post_type = 'post', $create_in_db = false ) {
	$post_title = '';
	if ( !empty( $_REQUEST['post_title'] ) )
		$post_title = esc_html( wp_unslash( $_REQUEST['post_title'] ));

	$post_content = '';
	if ( !empty( $_REQUEST['content'] ) )
		$post_content = esc_html( wp_unslash( $_REQUEST['content'] ));

	$post_excerpt = '';
	if ( !empty( $_REQUEST['excerpt'] ) )
		$post_excerpt = esc_html( wp_unslash( $_REQUEST['excerpt'] ));

	if ( $create_in_db ) {
		$post_id = wp_insert_post( array( 'post_title' => __( 'Auto Draft' ), 'post_type' => $post_type, 'post_status' => 'auto-draft' ) );
		$post = get_post( $post_id );
		if ( current_theme_supports( 'post-formats' ) && post_type_supports( $post->post_type, 'post-formats' ) && get_option( 'default_post_format' ) )
			set_post_format( $post, get_option( 'default_post_format' ) );
	} else {
		$post = new stdClass;
		$post->ID = 0;
		$post->post_author = '';
		$post->post_date = '';
		$post->post_date_gmt = '';
		$post->post_password = '';
		$post->post_name = '';
		$post->post_type = $post_type;
		$post->post_status = 'draft';
		$post->to_ping = '';
		$post->pinged = '';
		$post->comment_status = get_option( 'default_comment_status' );
		$post->ping_status = get_option( 'default_ping_status' );
		$post->post_pingback = get_option( 'default_pingback_flag' );
		$post->post_category = get_option( 'default_category' );
		$post->page_template = 'default';
		$post->post_parent = 0;
		$post->menu_order = 0;
		$post = new WP_Post( $post );
	}

	/**
	 * Filter the default post content initially used in the "Write Post" form.
	 *
	 * @since 1.5.0
	 *
	 * @param string  $post_content Default post content.
	 * @param WP_Post $post         Post object.
	 */
	$post->post_content = apply_filters( 'default_content', $post_content, $post );

	/**
	 * Filter the default post title initially used in the "Write Post" form.
	 *
	 * @since 1.5.0
	 *
	 * @param string  $post_title Default post title.
	 * @param WP_Post $post       Post object.
	 */
	$post->post_title = apply_filters( 'default_title', $post_title, $post );

	/**
	 * Filter the default post excerpt initially used in the "Write Post" form.
	 *
	 * @since 1.5.0
	 *
	 * @param string  $post_excerpt Default post excerpt.
	 * @param WP_Post $post         Post object.
	 */
	$post->post_excerpt = apply_filters( 'default_excerpt', $post_excerpt, $post );

	return $post;
}
endif;
