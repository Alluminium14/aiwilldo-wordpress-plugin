<?php
/** Return the existing GloriaFood schema through the module contract. */

$definitions = function_exists( 'awd_lm_definitions' ) ? awd_lm_definitions() : array();
return $definitions['gloriafood'] ?? array();

