<?php
/*
Plugin Name: Wellbeing Liverpool API Directory Import (test)
Plugin URI: https://www.jnragency.co.uk/
Description: A rough test of the Wellbeing API import plugin
Version: 0.1
Author: GM / Roy Duineveld
Author URI: http://royduineveld.nl
*/

// Create a action from our importIt function
add_action('import_demo', 'importIt');

// The functions which is going to do the job
function importIt()
{
	// Disable a time limit
	set_time_limit(0);

	// Require some Wordpress core files for processing images
	require_once(ABSPATH . 'wp-admin/includes/media.php');
	require_once(ABSPATH . 'wp-admin/includes/file.php');
	require_once(ABSPATH . 'wp-admin/includes/image.php');

	// Download and parse the xml
	$xml = simplexml_load_file(file_get_contents('https://www.thelivewelldirectory.com/api/search?apikey=X59WU602uf&Keywords=Wellbeing-Calm'));

	// Succesfully loaded?
	if($xml !== FALSE){

		// First remove all previous imported posts
		$currentPosts = get_posts(array( 
			'post_type' 		=> 'activities', // Or "page" or some custom post type
			'post_status' 		=> 'publish',
			'meta_key'			=> 'imported', // Our post options to determined
			'posts_per_page'   	=> 1000 // Just to make sure we've got all our posts, the default is just 5
		));

		// Loop through them
		foreach($currentPosts as $post){

			// Get the featured image id
			if($thumbId = get_post_meta($post->ID,'_thumbnail_id',true)){

				// Remove the featured image
				wp_delete_attachment($thumbId,true);
			}

			// Remove the post
			wp_delete_post( $post->ID, true);
		}

		// Loop through some items in the xml
		foreach($xml->item as $item){

			// Let's start with creating the post itself
			$postCreated = array(
				'post_title' 	=> $item->Service,
				'post_content' 	=> $item->Description,
				'post_excerpt' 	=> $item->Organisation,
				'post_status' 	=> 'publish',
				'post_type' 	=> 'activities', // Or "page" or some custom post type
			);

			// Get the increment id from the inserted post
			$postInsertId = wp_insert_post( $postCreated );

			// Our custom post options, for now only some meta's for the
			// Yoast SEO plugin and a "flag" to determined if a
			// post was imported or not
			$postOptions = array(
				'imported'				=> true
			);

			// Loop through the post options
			foreach($postOptions as $key=>$value){

				// Add the post options
				update_post_meta($postInsertId,$key,$value);
			}

			// This is a little trick to "catch" the image id
			// Attach/upload the "sideloaded" image
			// And remove the little trick
			// add_action('add_attachment','featuredImageTrick');
			// media_sideload_image($item->image, $postInsertId, $item->title);
			// remove_action('add_attachment','featuredImageTrick');
		}
	}
}

// A little hack to "catch" and save the image id with the post
function featuredImageTrick($att_id){
    $p = get_post($att_id);
    update_post_meta($p->post_parent,'_thumbnail_id',$att_id);
}

// Register our cronjob to run this task tomorrow midnight (0:00, so that's a new day)
// for the first time and daily at the same time after that
register_activation_hook(__FILE__, 'activateCron');
function activateCron() {
	wp_schedule_event(strtotime('tomorrow midnight'), 'daily', 'import_demo');
}

// Deactive the cron when the plugins is disabled or removed
register_deactivation_hook(__FILE__, 'deactivateCron');
function deactivateCron() {
	wp_clear_scheduled_hook('import_demo');
}