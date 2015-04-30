<?php

if( ! defined( 'MAX_IMAGE_SIZE' ) ) {
    define( 'MAX_IMAGE_SIZE', 2600 );
}

// set Imagick as default mage lib
add_filter( 'wp_image_editors', function( $implementations ) {
    return array_merge( [ 'WP_Image_Editor_Imagick' ], (array) $implementations );
}, 999 );


// set quality for images
add_filter( 'wp_editor_set_quality', function( $quality, $mime_type ) {
    if( 'image/jpeg' == $mime_type ) {
        $quality = 80;
    }
    return $quality;
}, 999, 2 );

// create pngcrush action
add_action( 'pngcrush', function( $filename ) {
    $filename = escapeshellarg( $filename );

    if( false === ( $command = wp_cache_get( 'pngcrush-bin' ) ) ) {
        $command = @exec('which pngquant');

        if( $command ) {
            wp_cache_set( 'pngcrush-bin', $command, '', DAY_IN_SECONDS );;
        }
        else {
            $command = false;
        }
    }

    if( ! $command ) {
        return false;
    }

    $command = escapeshellarg( $command );
    exec("$command --quality=65-80 $filename --output $filename --force");
    return true;
} );


// compress original image
add_filter( 'wp_handle_upload', function( $args ) {

    if( 'image/jpeg' == $args[ 'type' ] || 'image/png' == $args[ 'type' ] ) {

        $img = wp_get_image_editor( $args[ 'file' ] );
        if ( ! is_wp_error( $img ) ) {
            $size = $img->get_size();
            if( $size[ 'width' ] > MAX_IMAGE_SIZE  || $size[ 'height' ] > MAX_IMAGE_SIZE ) {
                $img->resize(MAX_IMAGE_SIZE, MAX_IMAGE_SIZE);
            }
            $img->save( $args[ 'file' ] );
        }
    }

    if( 'image/png' == $args[ 'type' ] ) {
        do_action( 'pngcrush', $args[ 'file' ] );
    }

    return $args;
}, 999 );


// compress image sizes
add_filter( 'wp_generate_attachment_metadata', function( $metadata ) {

    $uploads = wp_upload_dir();
    $dir = dirname( $uploads[ 'basedir' ] . DIRECTORY_SEPARATOR . $metadata[ 'file' ] );

    foreach( $metadata[ 'sizes' ] as $size ) {
        if( 'image/png' == $size[ 'mime-type' ] ) {
            $filename = $dir . DIRECTORY_SEPARATOR . $size[ 'file' ];
            do_action( 'pngcrush', $filename );
        }
    }
    return $metadata;
}, 999 );
