<?php 
namespace Media_Mechanics;

/*
 * Plugin Name: WordPress GitHub Sync Extra
 * Version: 0.1
 * Plugin URI: http://www.mediamechanics.com/wp/plugins/wp-guthub-sync-extra
 * Description: 
 * Author: Media Mechanics
 * Author URI: http://www.mediamechanics.com/
 * Requires at least: 4.4
 * Tested up to: 4.4
 *
 * @package WordPress
 * @author Media Mechanics
 * @since 0.0.0
 */

if ( ! defined( 'ABSPATH' ) ) exit;

// Load plugin class files
require_once( 'classes/wp-github-sync-extra.php' );

// generate class instance
global $github_sync_extra;
$github_sync_extra = new GitHub_Sync_Extra();

