<?php 
namespace Media_Mechanics;

if( !defined( 'ABSPATH' ) ) exit;

class GitHub_Sync_Extra_Sync_Branch {

  /**
   * Adds a setting field that allows a git branch
   * to be specifed for syncing
   */

	/**
	 * Constructor function.
	 * @access  public
	 * @since   1.0.0
	 * @return  void
	 */
	public function __construct($app) {

    add_action('admin_init', array($this,'register_settings'));
    add_filter('wpghs_sync_branch',array($this,'wpghs_sync_branch'));

	}

  /**
   * admin_init hook
   *
   * Register the branch field
   * 
   * @return void
   */
  public function register_settings() {

    register_setting( \WordPress_GitHub_Sync::$text_domain, 'wpghs_repository_branch' );
    add_settings_field( 'wpghs_repository_branch', __( 'Branch', 'wordpress-github-sync' ),array($this,'wpghs_field_callback'), \WordPress_GitHub_Sync::$text_domain, 'general', array(
        'default'   => '',
        'name'      => 'wpghs_repository_branch',
        'help_text' => __( 'The GitHub repository branch to commit to and pull from.', 'wordpress-github-sync' ),
      )
    );

  }

  /**
   * User wp-github-sync setting-field to render the field
   * 
   * @return void
   */
  public function wpghs_field_callback( $args ) {
    include WP_PLUGIN_DIR . '/wp-github-sync/views/setting-field.php';
  }

  /**
   * wpghs_sync_branch hook
   *
   * Return the user specified branch from settings
   * 
   * @return string
   */
  public function wpghs_sync_branch($branch){
    if( $branch_override = get_option('wpghs_repository_branch')){
      $branch = $branch_override;
    }
    return $branch;
  }
 
}

// instantiate
new GitHub_Sync_Extra_Sync_Branch($app);