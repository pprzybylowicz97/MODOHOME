<?php
/**
 * Strażnik regresji: zmienne --modohome-catalog-* muszą być deklarowane wyłącznie
 * w :root. Deklaracja na elemencie przesłania wartość dziedziczoną z :root,
 * czyli unieważnia cały panel ustawień wyglądu (błąd naprawiony w 1.2.1).
 */

declare( strict_types = 1 );

$dir    = dirname( __DIR__ ) . '/modohome-katalog-produktow/assets/css/';
$errors = 0;

foreach ( glob( $dir . '*.css' ) as $file ) {
	$css = (string) file_get_contents( $file );

	preg_match_all( '/([^{}]+)\{([^{}]*)\}/s', $css, $blocks, PREG_SET_ORDER );

	foreach ( $blocks as $block ) {
		$lines    = explode( "\n", trim( $block[1] ) );
		$selector = trim( end( $lines ) );

		if ( ':root' === $selector ) {
			continue;
		}

		preg_match_all( '/(--modohome-catalog-[a-z-]+)\s*:/', $block[2], $vars );

		foreach ( $vars[1] as $var ) {
			printf( "  BŁĄD %s: %s zadeklarowana w \"%s\" zamiast w :root\n", basename( $file ), $var, $selector );
			++$errors;
		}
	}
}

printf( "  %s\n", 0 === $errors ? 'OK — wszystkie zmienne deklarowane w :root' : "znaleziono problemów: {$errors}" );

exit( $errors > 0 ? 1 : 0 );
