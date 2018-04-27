<?php

namespace DonationManager\imagesizes;

function add_image_sizes(){
    add_image_size( 'donor-email', 600 ); // 400px wide and unlimited height
}
add_action( 'after_setup_theme', __NAMESPACE__ . '\\add_image_sizes' );