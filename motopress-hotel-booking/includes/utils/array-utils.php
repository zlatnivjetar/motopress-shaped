<?php

namespace MPHB\Utils;

if (!defined('ABSPATH')) {
	exit;
}

/**
 * @since 5.0.0
 */
class ArrayUtils {
	public static function usortBy( &$array, $sortField, $order = 'ASC' ) {
		usort(
			$array,
			function ($a, $b) use ($sortField, $order) {
				if ( $a[ $sortField ] == $b[ $sortField ] ) {
					return 0;
				} elseif ( $order == 'ASC' ) {
					return $a[ $sortField ] < $b[ $sortField ] ? -1 : 1;
				} else {
					return $a[ $sortField ] < $b[ $sortField ] ? 1 : -1;
				}
			}
		);
	}
}
