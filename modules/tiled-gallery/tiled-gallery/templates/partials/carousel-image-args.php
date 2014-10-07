data-attachment-id="<?php echo esc_attr( $item->image->ID ); ?>"
data-orig-file="<?php echo esc_url( wp_get_attachment_url( $item->image->ID ) ); ?>"
data-orig-size="<?php echo esc_attr( $item->meta_width() ); ?>,<?php echo esc_attr( $item->meta_height() ); ?>"
data-comments-opened="<?php echo esc_attr( comments_open( $item->image->ID ) ); ?>"
data-image-meta="<?php echo esc_attr( json_encode( array_map( 'strval', $item->fuzzy_image_meta() ) ) ); ?>"
data-image-title="<?php echo esc_attr( wptexturize( $item->image->post_title ) ); ?>"
data-image-description="<?php echo esc_attr( wpautop( wptexturize( $item->image->post_content ) ) ); ?>"
data-medium-file="<?php echo esc_url( $item->medium_file() ); ?>"
data-lage-file="<?php echo esc_url( $item->large_file() ); ?>"
