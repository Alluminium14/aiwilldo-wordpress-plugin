<?php
/** Minimal module-owned body; the shared renderer will provide the document shell. */

defined( 'ABSPATH' ) || exit;
$module_copy = isset( $module_copy ) && is_array( $module_copy ) ? $module_copy : array();
?>
<main class="awd-landing awd-landing--second-landing wtg-page" data-awd-landing="second-landing">
	<section class="awd-second-hero" data-awd-section-key="hero">
		<div class="awd-second-container">
			<p class="awd-second-eyebrow<?php echo ! empty( $editor ) ? ' awd-lm-inline' : ''; ?>" data-awd-content-path="hero.eyebrow"<?php echo ! empty( $editor ) ? ' contenteditable="true"' : ''; ?>><?php echo esc_html( $module_copy['eyebrow'] ?? '' ); ?></p>
			<h1 class="<?php echo ! empty( $editor ) ? 'awd-lm-inline' : ''; ?>" data-awd-content-path="hero.title"<?php echo ! empty( $editor ) ? ' contenteditable="true"' : ''; ?>><?php echo esc_html( $module_copy['title'] ?? '' ); ?></h1>
			<p class="awd-second-copy<?php echo ! empty( $editor ) ? ' awd-lm-inline awd-lm-inline-multiline' : ''; ?>" data-awd-content-path="hero.text"<?php echo ! empty( $editor ) ? ' contenteditable="true"' : ''; ?>><?php echo esc_html( $module_copy['text'] ?? '' ); ?></p>
			<?php if ( ! empty( $module_copy['button_text'] ) ) : ?>
				<a class="awd-second-button<?php echo ! empty( $editor ) ? ' awd-lm-inline' : ''; ?>" data-awd-content-path="hero.button_text" href="<?php echo esc_url( $module_copy['button_url'] ?? '#contact' ); ?>"<?php echo ! empty( $editor ) ? ' contenteditable="true"' : ''; ?>><?php echo esc_html( $module_copy['button_text'] ); ?></a>
			<?php endif; ?>
		</div>
	</section>
</main>
