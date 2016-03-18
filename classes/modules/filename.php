<?php 
namespace Media_Mechanics;

if( !defined( 'ABSPATH' ) ) exit;

class GitHub_Sync_Extra_File_Name {

  /**
   * Ties the filename in git to the slug in Wordpress
   * TODO: Update git filename when the slug changes in WP
   */

	/**
	 * Constructor function.
	 * @access  public
	 * @since   1.0.0
	 * @return  void
	 */
	public function __construct($app) {

    add_filter('wpghs_filename',array($this,'wpghs_filename'), 100, 2);
    add_action('wpghs_pre_import_args', array($this,'wpghs_pre_import_args'), 10, 2);

	}

  /**
   * wpghs_filename hook
   *
   * Sets file name based on _wpghs_github_path
   * 
   * @param  string
   * @param  \WordPress_GitHub_Sync_Post
   * @return string
   */
  public function wpghs_filename($filename, \WordPress_GitHub_Sync_Post $post){

    $gitname = get_post_meta( $post->id, '_wpghs_github_path', true );
    if($gitname){
      $filename = basename($gitname);
    }
    return $filename;

  }

  /**
   * wpghs_pre_import_args hook
   *
   * Set WP_Post slug based on filename in git
   * 
   * @param  array
   * @param  \WordPress_GitHub_Sync_Post
   * @return \WordPress_GitHub_Sync_Post
   */
  public function wpghs_pre_import_args($args, \WordPress_GitHub_Sync_Post $post){

    $meta = $post->get_meta();

    if(isset($meta['_wpghs_github_path'])){

      $gitpath = $meta['_wpghs_github_path'];
      $filename = basename($gitpath,'.md');

      $args['post_name'] = $filename;


    }

    return $args;

  }


}

new GitHub_Sync_Extra_File_Name($app);