<?php 
namespace Media_Mechanics;

if( !defined( 'ABSPATH' ) ) exit;

class GitHub_Sync_Extra_Image_Path {

  /**
   * Imports images from git hub and translates paths  
   */
  
  protected $app;

	/**
	 * Constructor function.
	 * @access  public
	 * @since   1.0.0
	 * @return  void
	 */
	public function __construct($app) {

    $this->app = $app;

    add_filter('the_content',array($this,'the_content'));
 
    add_filter('wpghs_get_blob_processor',array($this,'wpghs_get_blob_processor'), null, 2);

    add_action( 'wp_ajax_wpgs_regenerate_thumbs', array($this,'ajax_regenerate_thumbs') );
    add_action( 'wp_ajax_nopriv_wpgs_regenerate_thumbs', array($this,'ajax_regenerate_thumbs') );

	}

  /**
   * wpghs_get_blob_processor hook
   *
   * Assigns a function to be used when 
   * processing blogs of mine type "image"
   * 
   * @param  function
   * @param  blob
   * @return function
   */
  public function wpghs_get_blob_processor($processor, $blob){

    if ( $blob && strpos($blob->mimetype(),"image") === 0 ) {
      $processor = array($this,'blob_to_image_attachment');
    }

    return $processor;

  }

  /**
   * the_content hook
   *
   * Overrites image tags with the tag of a media 
   * attachment where the attachemnt meta data _wpghs_github_path
   * matches the path.
   *
   * i.e, Replaces images set in git with the associated image 
   * in the media library
   * 
   * @param  function
   * @param  blob
   * @return function
   */
  public function the_content( $content ) {
    if ( ! preg_match_all( '/<img [^>]+>/', $content, $matches ) ) {
      return $content;
    }

    $selected_images = $attachment_ids = array();

    foreach( $matches[0] as $image ) {
      if ( false === strpos( $image, ' srcset=' ) && !preg_match( '/wp-image-([0-9]+)/i', $image ) ) {

        /*
         * If exactly the same image tag is used more than once, overwrite it.
         * All identical tags will be replaced later with 'str_replace()'.
         */
        if( preg_match('/src=["\']\/([^"]+)["\']/', $image, $src) ){
          if( $attachment_id = $this->get_attachment_by_path($src[1]) ){
            $selected_images[$image] = $attachment_id;
          }
        }

      }
    }

    if ( count( $attachment_ids ) > 1 ) {
      /*
       * Warm object cache for use with 'get_post_meta()'.
       *
       * To avoid making a database call for each image, a single query
       * warms the object cache with the meta information for all images.
       */
      update_meta_cache( 'post', array_keys( $attachment_ids ) );
    }

    foreach ( $selected_images as $image => $attachment_id ) {
      $content = str_replace( $image, wp_get_attachment_image($attachment_id, "jumbo"), $content );
    }

    return $content;
  }


  /**
   * Blog processor for images.
   *
   * Save blob content as image and sideloads
   * in to the media library. 
   *
   * Images are updated if they exist. 
   *
   * Thumbnails are regenerated, but asynchronously
   * via ajax call
   * 
   * @param  blob
   * @return boolean
   */
  public function blob_to_image_attachment($blob, $import) {

    $path = $blob->path();
    $sha = $blob->sha();

    // check assets/images/ path
    // is this an uploadable image?
    if ( stripos($blob->path(),'assets/images/') === 0 ){

        $upload_path = trailingslashit(WP_CONTENT_DIR)."uploads/";
        // ensure that the path exists
        wp_mkdir_p(dirname($upload_path));

        // log for debug
        file_put_contents($upload_path.'assets/'.'log.txt',$path. "\n" , FILE_APPEND);

        // does the attachment already exist?
        if( $post_id = $this->get_attachment_by_path($path)){

            // update and generate the images
            $server_path = get_attached_file( $post_id );

            wp_mkdir_p(dirname($server_path));
            file_put_contents($server_path,$blob->content());

            $this->regenerate_thumbs_async($post_id);

            return 'updated';

        }else{

            // save the image to the asset path 
            wp_mkdir_p(dirname($upload_path.$path));
            file_put_contents($upload_path.$path,$blob->content());

            $file_array = array();
            $file_array['name'] = basename($path);
            $file_array['tmp_name'] = $upload_path.$path;

            // upload attachment
            $post_id = $this->media_handle_sideload($file_array);

            update_post_meta($post_id,'_wpghs_github_path', $path);
            update_post_meta($post_id,'_sha', $sha);

            $this->regenerate_thumbs_async($post_id);

            return 'uploaded';

        }

    }

    return true;

  }

  /**
   * Gets attachment post id by _wpghs_github_path
   * 
   * @param  string
   * @return int
   */
  public function get_attachment_by_path($path){

    $args = array(
      'meta_key' => '_wpghs_github_path',
      'meta_value' => $path,
      'post_type' => 'attachment',
      'posts_per_page' => 1
    );

    $posts = get_posts($args);

    if(!is_wp_error($posts) && count($posts)){
      return $posts[0]->ID;
    }

    return false;

  }

  /**
   * wp_ajax_wpgs_ hook
   *
   * Ajax callback to trigger thumb re-generation
   * for a specific attachment
   * 
   * @return void
   */
  public function ajax_regenerate_thumbs(){

    $id = $_GET['id'];

    $this->delete_thumbs($id);
    $this->regenerate_thumbs($id);

    die();

  }

  /**
   * Delete previously generated thumbs for a specific attachment
   *
   * @return void
   */
  public function delete_thumbs($attachment_id){
    $thumb_paths = $this->get_probable_thumb_paths($attachment_id);
    foreach ($thumb_paths as $path) {
      if(file_exists($path)){
        @unlink($path);
      }
    }
  }

  /**
   * Regenerate thumbs for a specific attachment
   *
   * @return void
   */
  public function regenerate_thumbs($attachment_id){
    $server_path = get_attached_file( $attachment_id  );
    $metadata = wp_generate_attachment_metadata( $attachment_id , $server_path );
    wp_update_attachment_metadata($attachment_id , $metadata);
  }

  /**
   * Get list of probable thumb paths based on 
   * attachment image sizes
   *
   * @return array
   */
  public function get_probable_thumb_paths($attachment_id){

    $thumb_paths = array();
    $metadata = wp_get_attachment_metadata($attachment_id);

    if($metadata && $metadata!=''){

      $pathinfo = pathinfo($metadata['file']); 
      $upload_dir = wp_upload_dir();
      $path = $upload_dir['basedir'].'/'.$pathinfo['dirname'];
      foreach ($metadata['sizes'] as $key => $size) {
        $pathinfo = pathinfo($size['file']);
        $thumb_paths[] = $path.'/'.$size['file'];
        $thumb_paths[] = $path.'/'.$pathinfo['filename'].'@2x'.'.'.$pathinfo['extension'];
      }

    }

    return $thumb_paths;
  
  }

  /**
   * Trigger ajax call to regenerate thumbs for 
   * a specific attachment
   * 
   * @param  init
   * @return void
   */
  public function regenerate_thumbs_async($attachment_id){
    return $this->get_async(admin_url('admin-ajax.php'),array(
      'action' => 'wpgs_regenerate_thumbs',
      'id'=> $attachment_id
    ));
  }

  /**
   * Makes an sync request to a url, adds
   * auth if nessesary
   * 
   * @param  string
   * @param  array
   * @return request
   */
  public function get_async($url,$data){

    // dev environment, need auth
    // TODO: get auth from wp-config
    $headers = array();
    if( strpos($url,'.pantheon.io/') ){
      $headers['Authorization'] = 'Basic ' . base64_encode( 'mesosphere' . ':' . 'capebretonwhiskey');
    }

    $url = $url.'?'.http_build_query($data);

    // var_dump($url);

    return wp_safe_remote_get($url,array(
      'timeout'  => 0,
      'blocking' => false,
      'headers'  => $headers
    ));

  }

  /**
   *  Duplicate of wordpress core function with thumb generation removed, 
   *  that willbe done async
   */
  public function media_handle_sideload($file_array, $post_id = null, $desc = null, $post_data = array()) {

    $overrides = array('test_form'=>false);

    $time = current_time( 'mysql' );
    if ( $post = get_post( $post_id ) ) {
        if ( substr( $post->post_date, 0, 4 ) > 0 )
            $time = $post->post_date;
    }

    $file = wp_handle_sideload( $file_array, $overrides, $time );
    if ( isset($file['error']) )
        return new WP_Error( 'upload_error', $file['error'] );

    $url = $file['url'];
    $type = $file['type'];
    $file = $file['file'];
    $title = preg_replace('/\.[^.]+$/', '', basename($file));
    $content = '';

    // Use image exif/iptc data for title and caption defaults if possible.
    if ( $image_meta = @wp_read_image_metadata($file) ) {
        if ( trim( $image_meta['title'] ) && ! is_numeric( sanitize_title( $image_meta['title'] ) ) )
            $title = $image_meta['title'];
        if ( trim( $image_meta['caption'] ) )
            $content = $image_meta['caption'];
    }

    if ( isset( $desc ) )
        $title = $desc;

    // Construct the attachment array.
    $attachment = array_merge( array(
        'post_mime_type' => $type,
        'guid' => $url,
        'post_parent' => $post_id,
        'post_title' => $title,
        'post_content' => $content,
    ), $post_data );

    // This should never be set as it would then overwrite an existing attachment.
    unset( $attachment['ID'] );

    // Save the attachment metadata
    $id = wp_insert_attachment($attachment, $file, $post_id);

    /*
    if ( !is_wp_error($id) )
        wp_update_attachment_metadata( $id, wp_generate_attachment_metadata( $id, $file ) );
    */

    return $id;

  }

}

// instantiate
new GitHub_Sync_Extra_Image_Path($app);