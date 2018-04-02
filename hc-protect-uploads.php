<?php
/**
 * Plugin Name:     HC Protect Uploads
 * Plugin URI:      https://github.com/mlaa/hc-protect-uploads
 * Description:     Require authentication to access some uploaded files.
 * Author:          MLA
 * Author URI:      https://github.com/mlaa
 * Text Domain:     hc-protect-uploads
 * Domain Path:     /languages
 * Version:         0.1.0
 *
 * @package         HC_Protect_Uploads
 */


/**
 * Helper function to generate headers when serving uploaded files.
 * Mostly copied from BP_Docs_Attachments::generate_headers().
 *
 * @param string $filename Full path to file
 * @return array Headers in key=>value format
 */
function hcpu_generate_headers( $filename ) {
	$headers = wp_get_nocache_headers();

	// Content-Type
	$filetype = wp_check_filetype( $filename );
	$headers['Content-Type'] = $filetype['type'];

	// Content-Length
	$filesize = filesize( $filename );
	$headers['Content-Length'] = $filesize;

	return $headers;
}

/**
 * Serve the requested file & exit.
 * Mostly copied from wp/ms-files.php.
 */
function hcpu_serve_file() {
	$file = rtrim( BLOGUPLOADDIR, '/' ) . '/' . str_replace( '..', '', $_GET[ 'hc-get-file' ] );
	$current_blog = get_blog_details();

	if ( $current_blog->archived == '1' || $current_blog->spam == '1' || $current_blog->deleted == '1' ) {
		status_header( 404 );
		die( '404 &#8212; File not found.' );
	}

	if ( !is_file( $file ) ) {
		status_header( 404 );
		die( '404 &#8212; File not found.' );
	}

	$mime = wp_check_filetype( $file );
	if ( false === $mime[ 'type' ] && function_exists( 'mime_content_type' ) )
		$mime[ 'type' ] = mime_content_type( $file );

	if ( $mime[ 'type' ] )
		$mimetype = $mime[ 'type' ];
	else
		$mimetype = 'image/' . substr( $file, strrpos( $file, '.' ) + 1 );

	header( 'Content-Type: ' . $mimetype ); // always send this
	if ( false === strpos( $_SERVER['SERVER_SOFTWARE'], 'Microsoft-IIS' ) )
		header( 'Content-Length: ' . filesize( $file ) );

	$last_modified = gmdate( 'D, d M Y H:i:s', filemtime( $file ) );
	$etag = '"' . md5( $last_modified ) . '"';
	header( "Last-Modified: $last_modified GMT" );
	header( 'ETag: ' . $etag );
	header( 'Expires: ' . gmdate( 'D, d M Y H:i:s', time() + 100000000 ) . ' GMT' );

	// Support for Conditional GET - use stripslashes to avoid formatting.php dependency
	$client_etag = isset( $_SERVER['HTTP_IF_NONE_MATCH'] ) ? stripslashes( $_SERVER['HTTP_IF_NONE_MATCH'] ) : false;

	if ( ! isset( $_SERVER['HTTP_IF_MODIFIED_SINCE'] ) )
		$_SERVER['HTTP_IF_MODIFIED_SINCE'] = false;

	$client_last_modified = trim( $_SERVER['HTTP_IF_MODIFIED_SINCE'] );
	// If string is empty, return 0. If not, attempt to parse into a timestamp
	$client_modified_timestamp = $client_last_modified ? strtotime( $client_last_modified ) : 0;

	// Make a timestamp for our most recent modification...
	$modified_timestamp = strtotime($last_modified);

	if ( ( $client_last_modified && $client_etag )
		? ( ( $client_modified_timestamp >= $modified_timestamp) && ( $client_etag == $etag ) )
		: ( ( $client_modified_timestamp >= $modified_timestamp) || ( $client_etag == $etag ) )
	) {
		status_header( 304 );
		exit;
	}

	// If we made it this far, just serve the file
	readfile( $file );
	flush();
}

/**
 * Require authentication to serve file.
 *
 * @uses bp_do_404()
 */
function hcpu_catch_file_request() {
	if ( ! isset( $_GET['hc-get-file'] ) ) {
		return;
	}

	// Serve file or redirect to login.
	if ( is_user_member_of_blog() ) {
		hcpu_serve_file();
	} else {
		bp_do_404();
	}
}
add_filter( 'init', 'hcpu_catch_file_request' );
