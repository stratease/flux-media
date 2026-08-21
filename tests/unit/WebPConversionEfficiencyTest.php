<?php
/**
 * Unit tests for WebP conversion efficiency and file size validation.
 *
 * @package FluxMedia\Tests\Unit
 * @since 0.1.0
 */

namespace FluxMedia\Tests\Unit;

use FluxMedia\App\Services\ImageConverter;
use FluxMedia\FluxPlugins\Common\Logger\Logger;
use FluxMedia\App\Services\Converter;
use PHPUnit\Framework\TestCase;

/**
 * WebP conversion efficiency unit tests.
 *
 * @since 0.1.0
 */
class WebPConversionEfficiencyTest extends TestCase {

    /**
     * ImageConverter instance.
     *
     * @since 0.1.0
     * @var ImageConverter
     */
    private $image_converter;

    /**
     * Logger instance.
     *
     * @since 0.1.0
     * @var Logger
     */
    private $logger;

    /**
     * Test files directory.
     *
     * @since 0.1.0
     * @var string
     */
    private $test_files_dir;

    /**
     * Set up test environment.
     *
     * @since 0.1.0
     * @return void
     */
    protected function setUp(): void {
        // Create ImageConverter instance (pure business logic, no WordPress dependencies)
        $this->logger = Logger::get_instance();
        $this->image_converter = new ImageConverter( $this->logger );

        // Set test files directory
        $this->test_files_dir = __DIR__ . '/../_support/files/';
    }

    /**
     * Data provider for WebP conversion efficiency tests with real files.
     *
     * @since 0.1.0
     * @return array Test data with source files, options arrays, and expected reductions.
     */
    public function efficiencyDataProvider() {
        return [
            // JPG to WebP with different quality levels
            'JPG to WebP Quality 60' => [
                'source_file' => __DIR__ . '/../_support/files/file_example_JPG_2500kB.jpg',
                'options' => [
                    'quality' => 60,
                    'image_hybrid_approach' => false,
                ],
                'expected_reduction' => 90, // Minimum expected reduction percentage
            ],
            'JPG to WebP Quality 75' => [
                'source_file' => __DIR__ . '/../_support/files/file_example_JPG_2500kB.jpg',
                'options' => [
                    'quality' => 75,
                    'image_hybrid_approach' => false,
                ],
                'expected_reduction' => 80, // Minimum expected reduction percentage
            ],
            'JPG to WebP Quality 90' => [
                'source_file' => __DIR__ . '/../_support/files/file_example_JPG_2500kB.jpg',
                'options' => [
                    'quality' => 90,
                    'image_hybrid_approach' => false,
                ],
                'expected_reduction' => 40, // May not reduce size at high quality
            ],
            // PNG to WebP with different quality levels
            'PNG to WebP Quality 60' => [
                'source_file' => __DIR__ . '/../_support/files/file_example_PNG_3MB.png',
                'options' => [
                    'quality' => 60,
                    'image_hybrid_approach' => false,
                ],
                'expected_reduction' => 90, // PNG should have significant reduction
            ],
            'PNG to WebP Quality 75' => [
                'source_file' => __DIR__ . '/../_support/files/file_example_PNG_3MB.png',
                'options' => [
                    'quality' => 75,
                    'image_hybrid_approach' => false,
                ],
                'expected_reduction' => 90, // PNG should have significant reduction
            ],
        ];
    }

    /**
     * Test WebP conversion efficiency with real files and quality variations.
     *
     * @since 0.1.0
     * @dataProvider efficiencyDataProvider
     * @param string $source_file Source image file path.
     * @param array  $options Conversion options array.
     * @param int    $expected_reduction Minimum expected size reduction percentage.
     * @return void
     */
    public function testConversionEfficiency( $source_file, $options, $expected_reduction ) {
        // Fail test if processor is not available
        $this->assertTrue( $this->image_converter->is_available(), 'Image processor must be available for conversion tests' );

        // Fail test if source file doesn't exist
        $this->assertFileExists( $source_file, "Source file must exist: {$source_file}" );

        // Fail test if WebP format is not supported
        $this->assertTrue( $this->image_converter->is_format_supported( Converter::FORMAT_WEBP ), 'WebP format must be supported for conversion tests' );

        // Create temporary output file
        $output_file = TEST_TEMP_DIR . '/webp_efficiency_' . uniqid() . '.webp';
        
        // Arrange
        $settings = [
            'webp_quality' => $options['quality'],
            'image_hybrid_approach' => $options['image_hybrid_approach'],
        ];

        $destination_paths = [
            Converter::FORMAT_WEBP => $output_file,
        ];

        // Act - Time the conversion
        $start_time = microtime( true );
        $result = $this->image_converter->process_image( $source_file, $destination_paths, $settings );
        $end_time = microtime( true );
        $conversion_time = $end_time - $start_time;

        // Assert - Verify conversion was successful
        $this->assertTrue( $result['success'], 'WebP conversion should succeed. Errors: ' . implode( ', ', $result['errors'] ?? [] ) );
        $this->assertContains( Converter::FORMAT_WEBP, $result['converted_formats'] );
        $this->assertArrayHasKey( Converter::FORMAT_WEBP, $result['converted_files'] );

        // Verify output file exists and has content
        $this->assertFileExists( $output_file );
        $this->assertGreaterThan( 0, filesize( $output_file ) );

        // Calculate file size reduction
        $original_size = filesize( $source_file );
        $converted_size = filesize( $output_file );
        $reduction_percentage = ( ( $original_size - $converted_size ) / $original_size ) * 100;

        // Debug output
        echo "\n=== WebP Conversion Debug Info ===\n";
        echo "Source file: " . basename( $source_file ) . "\n";
        echo "Quality setting: {$options['quality']}\n";
        echo "Original size: " . number_format( $original_size ) . " bytes (" . $this->formatBytes( $original_size ) . ")\n";
        echo "Converted size: " . number_format( $converted_size ) . " bytes (" . $this->formatBytes( $converted_size ) . ")\n";
        echo "Size reduction: " . number_format( $original_size - $converted_size ) . " bytes\n";
        echo "Reduction percentage: " . round( $reduction_percentage, 2 ) . "%\n";
        echo "Conversion time: " . round( $conversion_time, 4 ) . " seconds\n";
        echo "Expected minimum reduction: {$expected_reduction}%\n";
        echo "Conversion options used:\n";
        echo "  - quality: {$options['quality']}\n";
        echo "  - image_hybrid_approach: " . ( $options['image_hybrid_approach'] ? 'true' : 'false' ) . "\n";
        echo "  - processor: " . ( $this->image_converter->is_available() ? 'Imagick/GD' : 'None' ) . "\n";
        echo "Full options array: " . json_encode( $options, JSON_PRETTY_PRINT ) . "\n";
        echo "Conversion result: " . json_encode( $result, JSON_PRETTY_PRINT ) . "\n";
        echo "===================================\n";

        // Verify file size reduction meets expectations
        $this->assertGreaterThanOrEqual( 
            $expected_reduction, 
            $reduction_percentage,
            "WebP file size reduction should be at least {$expected_reduction}%. " .
            "Original: {$original_size} bytes, Converted: {$converted_size} bytes, " .
            "Reduction: {$reduction_percentage}%"
        );

        // Clean up
        if ( file_exists( $output_file ) ) {
            unlink( $output_file );
        }
    }

    /**
     * Test WebP quality vs file size trade-off using data provider.
     *
     * @since 0.1.0
     * @dataProvider efficiencyDataProvider
     * @param string $source_file Source image file path.
     * @param array  $options Conversion options array.
     * @param int    $expected_reduction Minimum expected size reduction percentage.
     * @return void
     */
    public function testQualityVsFileSize( $source_file, $options, $expected_reduction ) {
        // Fail test if processor is not available
        $this->assertTrue( $this->image_converter->is_available(), 'Image processor must be available for quality tests' );

        // Fail test if source file doesn't exist
        $this->assertFileExists( $source_file, "Source file must exist: {$source_file}" );

        // Test multiple quality levels for the same source file
        $quality_levels = [60, 75, 85, 95];
        $file_sizes = [];

        foreach ( $quality_levels as $quality ) {
            $destination_file = TEST_TEMP_DIR . "/test-webp-quality-{$quality}-" . uniqid() . ".webp";
            
            // Time the conversion
            $start_time = microtime( true );
            $result = $this->image_converter->convert_to_webp( 
                $source_file, 
                $destination_file, 
                ['quality' => $quality] 
            );
            $end_time = microtime( true );
            $conversion_time = $end_time - $start_time;
            
            if ( $result ) {
                $file_size = $this->getFileSize( $destination_file );
                $file_sizes[ $quality ] = $file_size;
                
                // Debug output
                echo "\n--- Quality Test Debug ---\n";
                echo "Source file: " . basename( $source_file ) . "\n";
                echo "Quality: {$quality}\n";
                echo "File size: " . number_format( $file_size ) . " bytes (" . $this->formatBytes( $file_size ) . ")\n";
                echo "Conversion time: " . round( $conversion_time, 4 ) . " seconds\n";
                echo "Conversion options used:\n";
                echo "  - quality: {$quality}\n";
                echo "  - processor: " . ( $this->image_converter->is_available() ? 'Imagick/GD' : 'None' ) . "\n";
                echo "Conversion result: " . ( $result ? 'SUCCESS' : 'FAILED' ) . "\n";
                echo "------------------------\n";
                
                unlink( $destination_file );
            }
        }

        if ( count( $file_sizes ) >= 2 ) {
            // Higher quality should generally result in larger file sizes
            $qualities = array_keys( $file_sizes );
            sort( $qualities );
            
            for ( $i = 1; $i < count( $qualities ); $i++ ) {
                $lower_quality = $qualities[ $i - 1 ];
                $higher_quality = $qualities[ $i ];
                
                $this->assertLessThanOrEqual( 
                    $file_sizes[ $higher_quality ], 
                    $file_sizes[ $lower_quality ],
                    "Higher WebP quality ({$higher_quality}) should produce larger or equal file size than lower quality ({$lower_quality}) for " . basename( $source_file )
                );
            }
        } else {
            $this->fail( 'WebP conversion must be supported and functional for quality testing with ' . basename( $source_file ) );
        }
    }

    /**
     * Get file size in bytes.
     *
     * @since 0.1.0
     * @param string $filepath Path to file.
     * @return int File size in bytes.
     */
    private function getFileSize( $filepath ) {
        return file_exists( $filepath ) ? filesize( $filepath ) : 0;
    }

    /**
     * Format bytes into human readable format.
     *
     * @since 0.1.0
     * @param int $bytes Number of bytes.
     * @return string Formatted string.
     */
    private function formatBytes( $bytes ) {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $bytes = max( $bytes, 0 );
        $pow = floor( ( $bytes ? log( $bytes ) : 0 ) / log( 1024 ) );
        $pow = min( $pow, count( $units ) - 1 );
        
        $bytes /= pow( 1024, $pow );
        
        return round( $bytes, 2 ) . ' ' . $units[ $pow ];
    }

    /**
     * Clean up test files.
     *
     * @since 0.1.0
     * @param array $files Array of file paths to clean up.
     * @return void
     */
    private function cleanupTestFiles( $files ) {
        foreach ( $files as $file ) {
            if ( file_exists( $file ) ) {
                unlink( $file );
            }
        }
    }

    /**
     * Assert that a file exists and has content.
     *
     * @since 0.1.0
     * @param string $filepath Path to file.
     * @param string $message Optional assertion message.
     * @return void
     */
    private function assertFileExistsAndHasContent( $filepath, $message = '' ) {
        $this->assertTrue( file_exists( $filepath ), $message ?: "File should exist: {$filepath}" );
        $this->assertGreaterThan( 0, filesize( $filepath ), $message ?: "File should have content: {$filepath}" );
    }
}
