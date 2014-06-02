<?php
/*
Plugin Name: Submit User Events
Plugin URI: http://ewebidentity.com
Description: Allows registered users to submit future events or advertisement.
Version: 1.0
License: EWEBv1
Author: Ayo
Author URI: http://ewebidentity.com
*/

define('MAX_UPLOAD_SIZE', 200000);
define('TYPE_WHITELIST', serialize(array(
  'image/jpeg',
  'image/png',
  'image/gif'
  )));


add_shortcode('sue_form', 'sue_form_shortcode');


function sue_form_shortcode(){

  if(!is_user_logged_in()){
  
    return '<p>You need to be logged in to submit an Event.</p>';    

  }

  global $current_user;
    
  if(isset( $_POST['sue_upload_image_form_submitted'] ) && wp_verify_nonce($_POST['sue_upload_image_form_submitted'], 'sue_upload_image_form') ){  

    $sue_image_text = trim($_POST['sue_image_text']);
    $location = trim($_POST['location']);
    $result = sue_parse_file_errors($_FILES['sue_image_file'], $_POST['sue_image_caption'], $_POST['sue_image_text'], $_POST['location']);
    
    if($result['error'] && $sue_image_text != '' && $location != ''){
    
      echo '<p>ERROR: ' . $result['error'] . '</p>';
    
    }else{

      $user_image_data = array(
      	'post_title' => $result['caption'],
      	'post_content' => $sue_image_text,
        'tax_input'    => array( 'sue_image_location' => array( $location ) ),
        'post_status' => 'pending',
        'post_author' => $current_user->ID,
        'post_type' => 'user_images'     
      );
      
      if($post_id = wp_insert_post($user_image_data)){
      
        sue_process_image('sue_image_file', $post_id, $result['caption']);
      
        wp_set_object_terms($post_id, (int)$_POST['sue_image_category'], 'sue_image_category');
        wp_set_object_terms($post_id, (int)$_POST['location'], 'location');
      
      }
    }
  }  

  if (isset( $_POST['sue_form_delete_submitted'] ) && wp_verify_nonce($_POST['sue_form_delete_submitted'], 'sue_form_delete')){

    if(isset($_POST['sue_image_delete_id'])){
    
      if($user_images_deleted = sue_delete_user_images($_POST['sue_image_delete_id'])){        
      
        echo '<p>' . $user_images_deleted . ' images(s) deleted!</p>';
        
      }
    }
  }
  

  echo sue_get_upload_image_form($sue_image_caption = $_POST['sue_image_caption'], $sue_image_text = $_POST['sue_image_text'], $location = $_POST['location'], $sue_image_category = $_POST['sue_image_category']);
  
  if($user_images_table = sue_get_user_images_table($current_user->ID)){
  
    echo $user_images_table;
    
  }

}


function sue_delete_user_images($images_to_delete){

  $images_deleted = 0;

  foreach($images_to_delete as $user_image){

    if (isset($_POST['sue_image_delete_id_' . $user_image]) && wp_verify_nonce($_POST['sue_image_delete_id_' . $user_image], 'sue_image_delete_' . $user_image)){
    
      if($post_thumbnail_id = get_post_thumbnail_id($user_image)){

        wp_delete_attachment($post_thumbnail_id);      

      }  

      wp_trash_post($user_image);
      
      $images_deleted ++;

    }
  }

  return $images_deleted;

}


function sue_get_user_images_table($user_id){

  $args = array(
    'author' => $user_id,
    'tax_input' => $location,
    'post_type' => 'user_images',
    'post_status' => 'pending'    
  );
  
  $user_images = new WP_Query($args);

  if(!$user_images->post_count) return 0;
  
  $out = '';
  $out .= '<p>Your unpublished events - Click on image to see full size</p>';
  
  $out .= '<form method="post" action="">';
  
  $out .= wp_nonce_field('sue_form_delete', 'sue_form_delete_submitted');  
  
  $out .= '<table id="user_images">';
  $out .= '<thead><th>Image</th><th>Title</th><th>Event</th><th>Category</th><th>Location</th><th>Delete</th></thead>';
    
  foreach($user_images->posts as $user_image){
  
    $user_image_cats = get_the_terms($user_image->ID, 'sue_image_category');
    $user_image_locations = get_the_terms($user_image->ID, 'sue_image_location');
        
    foreach($user_image_cats as $cat){
    
      $user_image_cat = $cat->name;
      
    
    }
    foreach($user_image_locations as $location){
    
      $user_image_location = $location->name;
      
    
    }
    
      
    
    
    $post_thumbnail_id = get_post_thumbnail_id($user_image->ID);   

    $out .= wp_nonce_field('sue_image_delete_' . $user_image->ID, 'sue_image_delete_id_' . $user_image->ID, false); 
       
    $out .= '<tr>';
    $out .= '<td>' . wp_get_attachment_link($post_thumbnail_id, 'thumbnail') . '</td>';    
    $out .= '<td>' . $user_image->post_title . '</td>';
    $out .= '<td>' . $user_image->post_content . '</td>';
    $out .= '<td>' . $user_image_cat . '</td>';  
    $out .= '<td>' . $user_image_location . '</td>'; 
    //$out .= '<td>' . $sue_image_location->$location->tax_input . '<td>';
    $out .= '<td><input type="checkbox" name="sue_image_delete_id[]" value="' . $user_image->ID . '" /></td>';          
    $out .= '</tr>';
    
  }

  $out .= '</table>';
    
  $out .= '<input type="submit" name="sue_delete" value="Delete Selected Events" />';
  $out .= '</form>';  
  
  return $out;

}


function sue_process_image($file, $post_id, $caption){
 
  require_once(ABSPATH . "wp-admin" . '/includes/image.php');
  require_once(ABSPATH . "wp-admin" . '/includes/file.php');
  require_once(ABSPATH . "wp-admin" . '/includes/media.php');
 
  $attachment_id = media_handle_upload($file, $post_id);
 
  update_post_meta($post_id, '_thumbnail_id', $attachment_id);

  $attachment_data = array(
  	'ID' => $attachment_id,
    'post_excerpt' => $caption
  );
  
  wp_update_post($attachment_data);

  return $attachment_id;

}


function sue_parse_file_errors($file = '', $image_caption){

  $result = array();
  $result['error'] = 0;
  
  if($file['error']){
  
    $result['error'] = "No file uploaded or there was an upload error!";
    
    return $result;
  
  }

  $image_caption = trim(preg_replace('/[^a-zA-Z0-9\s]+/', ' ', $image_caption));
  
  if($image_caption == ''){

    $result['error'] = "Your title may only contain letters, numbers and spaces!";
    
    return $result;
  
  }
  
  $result['caption'] = $image_caption;  

  $image_data = getimagesize($file['tmp_name']);
  
  if(!in_array($image_data['mime'], unserialize(TYPE_WHITELIST))){
  
    $result['error'] = 'Your image must be a jpeg, png or gif!';
    
  }elseif(($file['size'] > MAX_UPLOAD_SIZE)){
  
    $result['error'] = 'Your image was ' . $file['size'] . ' bytes! It must not exceed ' . MAX_UPLOAD_SIZE . ' bytes.';
    
  }
    
  return $result;

}



function sue_get_upload_image_form($sue_image_caption = '', $sue_image_category = 0, $location = ''){

  $out = '';
  $out .= '<form id="sue_upload_image_form" method="post" action="" enctype="multipart/form-data">';

  $out .= wp_nonce_field('sue_upload_image_form', 'sue_upload_image_form_submitted');
  
  $out .= '<label for="sue_image_caption">Event Title - Letters, Numbers and Spaces</label><br/>';
  $out .= '<input type="text" id="sue_image_caption" name="sue_image_caption" value="' . $sue_image_caption . '"/><br/><br/>';
  $out .= '<label for="sue_image_category">Event Category</label><br/>';  
  $out .= sue_get_image_categories_dropdown('sue_image_category', $sue_image_category) . '<br/><br/>';
  
  $out .= '<label for="sue_image_location">Event Location</label><br/>';  
  $out .= sue_get_image_locations_dropdown('sue_image_location', $location) . '<br/><br/>';
  $out .= '<label for="sue_image_text">Event</label><br/>';          
  $out .= '<textarea id="sue_image_text" name="sue_image_text" />' . $sue_image_text . '</textarea><br/><br/>'; 
  $out .= '<label for="sue_image_file">Select Your Image - ' . MAX_UPLOAD_SIZE . ' bytes maximum</label><br/>';  
  $out .= '<input type="file" size="60" name="sue_image_file" id="sue_image_file"><br/><br/>';
    
  $out .= '<input type="submit" id="sue_submit" name="sue_submit" value="Publish Event">';

  $out .= '</form>';

  return $out;
  
}

function sue_get_image_categories_dropdown($taxonomy, $selected){

  return wp_dropdown_categories(array('taxonomy' => $taxonomy, 'name' => 'sue_image_category', 'selected' => $selected, 'hide_empty' => 0, 'echo' => 0));

}

function sue_get_image_locations_dropdown($taxonomy, $selected){

  return wp_dropdown_categories(array('taxonomy' => $taxonomy, 'name' => 'location', 'selected' => $selected, 'hide_empty' => 0, 'echo' => 0));

}





add_action('init', 'sue_plugin_init');

function sue_plugin_init(){

  $image_type_labels = array(
    'name' => _x('User event', 'post type general name'),
    'singular_name' => _x('User Event', 'post type singular name'),
    'add_new' => _x('Add New User Event', 'image'),
    'add_new_item' => __('Add New User Event'),
    'edit_item' => __('Edit User Event'),
    'new_item' => __('Add New User Event'),
    'all_items' => __('View User Events'),
    'view_item' => __('View User Event'),
    'search_items' => __('Search User Events'),
    'not_found' =>  __('No User Events found'),
    'not_found_in_trash' => __('No User Events found in Trash'), 
    'parent_item_colon' => '',
    'menu_name' => 'User Events'
  );
  
  $image_type_args = array(
    'labels' => $image_type_labels,
    'public' => true,
    'query_var' => true,
    'rewrite' => true,
    'capability_type' => 'post',
    'has_archive' => true, 
    'hierarchical' => false,
    'map_meta_cap' => true,
    'menu_position' => null,
    'supports' => array('title', 'editor', 'author', 'thumbnail')
  ); 
  
  register_post_type('user_images', $image_type_args);

  $image_category_labels = array(
    'name' => _x( 'User Event Categories', 'taxonomy general name' ),
    'singular_name' => _x( 'User Event', 'taxonomy singular name' ),
    'search_items' =>  __( 'Search User Event Categories' ),
    'all_items' => __( 'All User Event Categories' ),
    'parent_item' => __( 'Parent User Event Category' ),
    'parent_item_colon' => __( 'Parent User Event Category:' ),
    'edit_item' => __( 'Edit User Event Category' ), 
    'update_item' => __( 'Update User Event Category' ),
    'add_new_item' => __( 'Add New User Event Category' ),
    'new_item_name' => __( 'New User Event Name' ),
    'menu_name' => __( 'User Event Categories' ),
  ); 	

  $image_category_args = array(
    'hierarchical' => true,
    'labels' => $image_category_labels,
    'show_ui' => true,
    'query_var' => true,
    'rewrite' => array( 'slug' => 'user_image_category' ),
  );
  
  register_taxonomy('sue_image_category', array('user_images'), $image_category_args);
  
  $default_image_cats = array('category-1', 'category-2', 'category-3', 'category-4');
  
  foreach($default_image_cats as $cat){
  
    if(!term_exists($cat, 'sue_image_category')) wp_insert_term($cat, 'sue_image_category');
    
  }
  
  $image_location_labels = array(
    'name' => _x( 'User Event Locations', 'taxonomy general name' ),
    'singular_name' => _x( 'User Event', 'taxonomy singular name' ),
    'search_items' =>  __( 'Search User Event Locations' ),
    'all_items' => __( 'All User Event Locations' ),
    'parent_item' => __( 'Parent User Event Location' ),
    'parent_item_colon' => __( 'Parent User Event Location:' ),
    'edit_item' => __( 'Edit User Event Location' ), 
    'update_item' => __( 'Update User Event Location' ),
    'add_new_item' => __( 'Add New User Event Location' ),
    'new_item_name' => __( 'New User Event Name' ),
    'menu_name' => __( 'User Event Locations' ),
  ); 	

  $image_location_args = array(
    'hierarchical' => true,
    'labels' => $image_location_labels,
    'show_ui' => true,
    'query_var' => true,
    'rewrite' => array( 'slug' => 'user_image_location' ),
  );
  
  register_taxonomy('sue_image_location', array('user_images'), $image_location_args);
  
  $default_image_locations = array('United States', 'Canada', 'France', 'United Kingdom');
  
  foreach($default_image_locations as $location){
  
    if(!term_exists($location, 'sue_image_location')) wp_insert_term($location, 'sue_image_location');
    
  }
    
}