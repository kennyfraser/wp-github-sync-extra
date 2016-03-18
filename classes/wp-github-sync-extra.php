<?php 
namespace Media_Mechanics;

if( !defined( 'ABSPATH' ) ) exit;

class GitHub_Sync_Extra {

  /**
   * Modifies WordPress_GitHub_Sync functionality
   *
   * TODO: Fully merge with plugin?
   * 
   */

  protected $app; 

  /**
   * Constructor function.
   * @access  public
   * @since   1.0.0
   * @return  void
   */
	public function __construct() {

    add_action( 'wpghs_boot', array( $this, 'init_modules' ) );

	}

  /**
   * wpghs_boot hook
   *
   * Loads modules to modify WordPress_GitHub_Sync
   * functionality
   * 
   * @param  /WordPress_GitHub_Sync
   * @return view
   */
  public function init_modules($app) {

    $this->app = $app;

    // Adds a setting field that allows a git branch to be specifed for syncing
    $this->load_module('sync-branch');

    // Imports images from git hub and translates paths  
    $this->load_module('image-path');

    // Updates and filers post meta flowing in both directions
    $this->load_module('post-meta');

    // Sets page parent based on git uri
    $this->load_module('parent-path');

    // match file name in git to slug
    $this->load_module('filename');

  }

  /**
   * Loads module by path
   *
   * Module will instantiate itself
   * 
   * @param  /WordPress_GitHub_Sync
   * @return view
   */
  public function load_module($module) {

      $app = $this->app;
      require_once( dirname(__FILE__)."/modules/$module.php");

  }

}