<?php
$id = wp_unique_id( 'tsbTeamMembers-' );
extract( $attributes );
?>
<div <?php echo get_block_wrapper_attributes( [ 'class' => "align$align" ] ); ?> id='<?php echo esc_attr( $id ); ?>' data-attributes='<?php echo esc_attr( wp_json_encode( $attributes ) ); ?>'></div>