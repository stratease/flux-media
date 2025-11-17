<?php
/**
 * Fix PHP 8.1+ deprecation warnings in vendor-prefixed code.
 *
 * This script adds #[\ReturnTypeWillChange] attributes to ArrayAccess
 * and IteratorAggregate methods that need them for PHP 8.1+ compatibility.
 *
 * @since 1.0.0
 */

$plugin_dir = dirname( __DIR__ );
$vendor_prefixed_dir = $plugin_dir . '/vendor-prefixed';

// Files to fix and their methods
$files_to_fix = [
	'alchemy/binary-driver/src/Alchemy/BinaryDriver/Configuration.php' => [
		'getIterator',
		'offsetExists',
		'offsetGet',
		'offsetSet',
		'offsetUnset',
	],
];

foreach ( $files_to_fix as $relative_path => $methods ) {
	$file_path = $vendor_prefixed_dir . '/' . $relative_path;
	
	if ( ! file_exists( $file_path ) ) {
		continue;
	}
	
	$content = file_get_contents( $file_path );
	$modified = false;
	
	foreach ( $methods as $method ) {
		// Check if attribute is already present
		if ( preg_match( '/#\[\\\\?ReturnTypeWillChange\]\s+public function ' . preg_quote( $method, '/' ) . '\s*\(/', $content ) ) {
			continue; // Already fixed
		}
		
		// Pattern to match method declarations without the attribute
		// Matches: /** ... * {@inheritdoc} ... */ \n    public function methodName(
		// Pattern captures: (docblock with {@inheritdoc} and closing */) (indentation) public function methodName(
		$pattern = '/(\s+\*\s+\{@inheritdoc\}\s*\n\s+\*\/\s*\n)(\s+)public function ' . preg_quote( $method, '/' ) . '\s*\(/';
		
		// Add the attribute before the method (preserve indentation from capture group $2)
		$replacement = '$1$2#[\ReturnTypeWillChange]' . "\n$2public function {$method}(";
		
		$new_content = preg_replace( $pattern, $replacement, $content );
		
		if ( $new_content !== $content ) {
			$content = $new_content;
			$modified = true;
		}
	}
	
	if ( $modified ) {
		file_put_contents( $file_path, $content );
		echo "Fixed deprecation warnings in: {$relative_path}\n";
	}
}

echo "PHP 8.1+ deprecation fixes applied.\n";

