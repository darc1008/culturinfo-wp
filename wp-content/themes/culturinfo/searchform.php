<?php
/**
 * Formulario de búsqueda.
 *
 * @package Culturinfo
 */
 $search_id = wp_unique_id('culturinfo-search-');
?>
<form role="search" method="get" class="search-form" action="<?php echo esc_url(home_url('/')); ?>">
    <label class="screen-reader-text" for="<?php echo esc_attr($search_id); ?>"><?php esc_html_e('Buscar', 'culturinfo'); ?></label>
    <input id="<?php echo esc_attr($search_id); ?>" type="search" class="search-field" placeholder="<?php esc_attr_e('Buscar artículos, autores o temas…', 'culturinfo'); ?>" value="<?php echo esc_attr(get_search_query()); ?>" name="s">
    <button type="submit" class="search-submit"><?php esc_html_e('Buscar', 'culturinfo'); ?></button>
</form>
