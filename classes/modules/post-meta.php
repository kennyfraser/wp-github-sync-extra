<?php 
namespace Media_Mechanics;

if( !defined( 'ABSPATH' ) ) exit;

class GitHub_Sync_Extra_Sync_Meta {

  /**
   * Updates and filers post meta flowing in both directions
   * 
   * Works arounds issues with serilized AFC meta data not being transfered
   * to git and back correctly. 
   * 
   * Meta and fields have to be specifically allowed before being synced
   * 
   * TODO: Create filter to set allowed meta outside of this pluign 
   *    
   */

  private $allowed_meta;
  private $allowed_fields;

	/**
	 * Constructor function.
	 * @access  public
	 * @since   1.0.0
	 * @return  void
	 */
	public function __construct($app) {

    // default allowed meta
    // TODO: Create filter to set allowed meta outside of this pluign
    $this->allowed_meta = array(
      // 'ID' => null,
      'UID' => null,
      'post_title' => null,
     // 'post_date' => null,
      'post_excerpt' => null,
      'layout' => null,
     // 'permalink' => null,
      'published' => null,
     // 'post_parent' => null,
      'menu_order' => null,
      '_sha' => null,
     //  '_gitpath' => null,
      '_wpghs_github_path' => null,
    );

    // allowed boolean fields
    $this->allowed_fields = array(
      'page_options_require_authentication' => false,
      'page_options_show_link_unauthenticated' => false,
      'hide_from_navigation' => false,
      'hide_from_related' => false,
    );

    // export
    add_action('wpghs_post_meta', array($this,'wpghs_post_meta'), 10, 2);

    // import
    add_action('wpghs_pre_import_args', array($this,'wpghs_pre_import_args'), 10, 2);
    add_action('wpghs_pre_import_meta', array($this,'wpghs_pre_import_meta'), 10, 2);

	}

  /**
   * Meta creation hook
   * 
   * @param  array
   * @param  \WordPress_GitHub_Sync_Post
   * @return array
   */
  public function wpghs_post_meta($meta, \WordPress_GitHub_Sync_Post $post) {

    $new_meta = array(); 

    // copy only the specifically allowed meta keys
    foreach ($this->allowed_meta as $key => $value) {
      if(isset($meta[$key])){
        $new_meta[$key] = $meta[$key];
      }
    }

    // update current post settings
    // $new_meta['post_parent'] = $post->post->post_parent;
    $new_meta['menu_order'] = $post->post->menu_order;

    // copy only the specifically allowed boolean fields
    foreach ($this->allowed_fields as $key => $value) {
      // force boolean
      $new_meta[$key] = !!get_field($key,$post->id);
    }

    if( !isset($new_meta['UID'])){
      // assign a uid to this post 
      $new_meta['UID'] = get_post_meta( $post->id, 'UID', true );
      if( !$new_meta['UID'] ){
        $new_meta['UID'] = uniqid();
        update_post_meta( $post->id, 'UID', $new_meta['UID'] ); 
      }
    }

    if(is_array($new_meta['UID'])){
      $new_meta['UID'] = $new_meta['UID'][0];
    }

    return $new_meta;
  }

  /**
   * WP_Post argument import hook
   *
   * Transfer post meta to post args where nessesary
   * 
   * @param  array
   * @param  \WordPress_GitHub_Sync_Post
   * @return array
   */
  public function wpghs_pre_import_args($args, \WordPress_GitHub_Sync_Post $post){

    $meta = $post->get_meta();

    // set post args from meta
    $meta_as_arg = array('menu_order');

    foreach ($meta_as_arg as $value) {
      if( isset($meta[$value]) ){
        $args[$value] = $meta[$value];
      }
    }

    return $args;

  }

  /**
   * WP_Post meta import hook
   *
   * Filter out meta that has not been specifically allowed
   * 
   * @param  array
   * @param  \WordPress_GitHub_Sync_Post
   * @return array
   */
  public function wpghs_pre_import_meta($meta, \WordPress_GitHub_Sync_Post $post){

    $new_meta = array(); 

    // copy only the specifically allowed meta keys
    foreach ($this->allowed_meta as $key => $value) {
      if(isset($meta[$key])){
        $new_meta[$key] = $meta[$key];
      }
    }

    // copy only the specifically allowed boolean fields
    foreach ($this->allowed_fields as $key => $value) {
      if( isset($meta[$key]) ){
        update_field($key,$meta[$key],$post->id);
      }
    }

    return $new_meta;

  }

}

// instantiate
new GitHub_Sync_Extra_Sync_Meta($app);