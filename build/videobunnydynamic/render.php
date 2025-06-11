<?php
/**
 * @see https://github.com/WordPress/gutenberg/blob/trunk/docs/reference-guides/block-api/block-metadata.md#render
 */
?>

<div data-wp-interactive="create-block">
	<button data-wp-on--click="actions.test">
		Click Me
	</button>


	<p <?php echo get_block_wrapper_attributes(); ?>>
		<?php esc_html_e( 'Video Bunny Dynamic – hello from a dynamic block', 'videobunnydynamic' ); ?>
	</p>
</div>