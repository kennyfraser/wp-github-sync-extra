<?php 
namespace Media_Mechanics;

if( !defined( 'ABSPATH' ) ) exit;

class GitHub_Sync_Extra_Parent_Path {

  /**
   * Sync hierarchy in WP to folder sturcture in git
   * 
   * TODO:Fix 
   * "Hierarchy should only be changed in Wordpress. 
   * It may be necessary to trigger 'Export to GitHub' to bring the repo fully in sync after altering the path of a page with children."
   * 
   */

	/**
	 * Constructor function.
	 * @access  public
	 * @since   1.0.0
	 * @return  void
	 */
  public function __construct($app) {

    // wpghs_filename
    add_filter('wpghs_directory_unpublished',array($this,'wpghs_directory_published'), 10, 2);
    add_filter('wpghs_directory_published',array($this,'wpghs_directory_published'), 10, 2);

    add_action('wpghs_blob_to_post_meta', array($this,'wpghs_blob_to_post_meta'), 10, 2);
    add_action('wpghs_blob_to_post_args', array($this,'wpghs_blob_to_post_args'), 10, 3);

    add_action('wpghs_pre_import_args', array($this,'wpghs_pre_import_args'), 10, 2);

    add_action('save_post', array($this,'save_post'), 5, 1);

    add_action('wpghs_post_new_post_import', array($this,'wpghs_post_new_post_import'));

  }

  /**
   * wpghs_post_new_post_import hook
   *
   * This hooks receives an array of new posts on 
   * import from git after they have been added/updated
   * in Wordpress. 
   *
   * Depending on import order, a child page may be created 
   * before it's parent. To resolve this, we are going to re-set 
   * the parent of each page imported. 
   * 
   * @param  array
   * @return void
   */
  public function wpghs_post_new_post_import($new_posts) {

    // sort posts based on number of path segments,
    // to ensure the parent pages are set  first
    usort($new_posts, array($this, "compare_path_depth"));

    // reset the parent for each post based on 
    // git path
    foreach ($new_posts as $new_post) {

      $post_id = $new_post->post->ID;

      $meta = $new_post->get_meta();
      $git_path = $meta['_wpghs_github_path'];

      if($parent = $this->get_post_parent_by_github_path($git_path) ){

        wp_update_post(
          array(
            'ID' => $post_id, 
            'post_parent' => $parent->ID
          )
        );

      }
      
    }

  }

  /**
   * Sort function for usort.
   *
   * Compares on number of path segments ( child depth ) 
   * 
   * @param  WordPress_GitHub_Sync_Post
   * @param  WordPress_GitHub_Sync_Post
   * @return int
   */
  public function compare_path_depth($a, $b){
    $meta_a = $a->get_meta();
    $meta_b = $b->get_meta();
    return substr_count($meta_a['_wpghs_github_path'],"/") > substr_count($meta_b['_wpghs_github_path'],"/");
  }

  /**
   * Match the git hub uri to the uri in wordpress
   * 
   * @param  string
   * @param  post
   * @return string
   */
  public function wpghs_directory_published($directory, $post) {

    $uri = dirname(get_page_uri( $post->id ));
    if( $uri == "." ) $uri = "";

    return trailingslashit($directory.$uri);

  }

  /**
   * Get Wp_Post parent by _wpghs_github_path
   * 
   * @param  post id
   */
  public function get_post_parent_by_github_path($gitpath){

    if( !$gitpath || trim($gitpath) == ""){
      return false;
    }

    $parent_uri = rtrim(dirname(str_replace("_pages/", "", $gitpath)),"/");
    
    if( !$parent_uri || trim($parent_uri,".") == ""){
      return false;
    }

    $post = get_page_by_path($parent_uri);

    if( is_wp_error($post)){
      return false;
    }
    if (wp_is_post_revision($post)){
      return false;
    }

    return $post;

  }

  /**
   * Assign a uid to this post on save
   *
   * TODO: Move this to post meta module?
   * 
   * @param  post id
   */
  public function save_post($post_id) {

    if (wp_is_post_revision($post_id)) return;

    // assign a uid to this post 
    $uid = get_post_meta( $post_id, 'UID', true );
    if( !$uid ){
      update_post_meta( $post_id, 'UID', uniqid() ); 
    }

  }

  /**
   * wpghs_blob_to_post_meta  hook
   * 
   * Assign _wpghs_github_path meta form blob path
   *
   * TODO: Move this to post meta module?
   * 
   * @param  post id
   */
  public function wpghs_blob_to_post_meta($meta, $blob) {
    $meta['_wpghs_github_path'] = $blob->path();
    return $meta;
  }

  /**
   * wpghs_blob_to_post_args hook
   * 
   * Assign post_id arg by getting the post id 
   * from UID
   *
   * TODO: Move this to post meta module?
   * 
   * @param  post id
   */
  public function wpghs_blob_to_post_args($args, $meta, $blob) {

    if(isset($meta['UID'])){

      if( $post = $this->get_post_by_uid( $meta['UID'] ) ){
        if ( !is_wp_error( $post ) ) {
          $args['ID'] = $post->ID;
        }
      }

    }

    return $args;
  }

  /**
   * wpghs_pre_import_args hook
   * 
   * Assign post_id arg by getting the post id 
   * from UID
   * 
   * Assign post_parent arg by getting the post_parent 
   * from _wpghs_github_path
   * 
   * TODO: Move this to post meta module?
   * 
   * @param  post id
   */
  public function wpghs_pre_import_args($args, \WordPress_GitHub_Sync_Post $post){

    $meta = $post->get_meta();

    // get the post based on uid

    if( isset($meta['UID']) ){
      if( $post = $this->get_post_by_uid( $meta['UID'] ) ){
        $args['ID'] = $post->ID;
      }
    }

    // get the parent post based on the uri

    if(isset($meta['_wpghs_github_path'])){

      if( $parent = $this->get_post_parent_by_github_path($meta['_wpghs_github_path']) ){
        $args['post_parent'] = $parent->ID;
      }else{
        $args['post_parent'] = 0;
      }

    }

    return $args;

  }

  /**
   * Get Wp_Post by UID
   * 
   * TODO: Move this to post meta module?
   * 
   * @param  post id
   */
  public function get_post_by_uid($uid){

    global $wpdb;

    $post_id = $wpdb->get_var(
      $wpdb->prepare("
          SELECT post_id FROM $wpdb->postmeta
          WHERE meta_key = 'UID'
          AND meta_value = %s
        ", 
        $uid
    ));

    if( $post_id ){
      $post = get_post($post_id);
      if( $post ){
        return $post;
      }
    }

    return false;

  }


}

// instantiate
new GitHub_Sync_Extra_Parent_Path($app);