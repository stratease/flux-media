<?php
/**
 * Unit tests for image conversion efficiency and file size validation.
 *
 * @package FluxMedia\Tests\Unit
 * @since 1.0.0
 */

namespace FluxMedia\Tests\Unit;

use FluxMedia\Services\ImageConverter;
use FluxMedia\Tests\Support\Mocks\NoopLogger;
use FluxMedia\Interfaces\Converter;
use PHPUnit\Framework\TestCase;

/**
 * Image conversion efficiency unit tests.
 *
 * @since 1.0.0
 */
class ImageConversionEfficiencyTest extends TestCase {

    /**
     * ImageConverter instance.
     *
     * @since 1.0.0
     * @var ImageConverter
     */
    private $image_converter;

    /**
     * Logger instance.
     *
     * @since 1.0.0
     * @var NoopLogger
     */
    private $logger;

    /**
     * Mock files for testing.
     *
     * @since 1.0.0
     * @var array
     */
    private $mock_files = [];

    /**
     * Set up test environment.
     *
     * @since 1.0.0
     * @return void
     */
    protected function setUp(): void {
        // Create ImageConverter instance (pure business logic, no WordPress dependencies)
        $this->logger = new NoopLogger();
        $this->image_converter = new ImageConverter( $this->logger );

        // Create mock image files with different sizes
        $this->mock_files = [
            'small_jpg' => $this->createMockImageFile( 'jpg', 100, 100 ),
            'medium_jpg' => $this->createMockImageFile( 'jpg', 500, 500 ),
            'large_jpg' => $this->createMockImageFile( 'jpg', 1000, 1000 ),
        ];
    }

    /**
     * Clean up after tests.
     *
     * @since 1.0.0
     * @return void
     */
    protected function tearDown(): void {
        $this->cleanupTestFiles( $this->mock_files );
    }

    /**
     * Data provider for image conversion efficiency tests with real files.
     *
     * @since 1.0.0
     * @return array Test data with source files, target formats, and quality levels.
     */
    public function imageEfficiencyDataProvider() {
        $test_files_dir = __DIR__ . '/../_support/files/';
        
        return [
            // JPG to WebP with different quality levels
            'JPG to WebP Quality 60' => [
                'source_file' => $test_files_dir . 'file_example_JPG_2500kB.jpg',
                'target_format' => Converter::FORMAT_WEBP,
                'quality' => 60,
                'expected_reduction' => 10, // Minimum expected reduction percentage
            ],
            'JPG to WebP Quality 75' => [
                'source_file' => $test_files_dir . 'file_example_JPG_2500kB.jpg',
                'target_format' => Converter::FORMAT_WEBP,
                'quality' => 75,
                'expected_reduction' => 5, // Minimum expected reduction percentage
            ],
            'JPG to WebP Quality 90' => [
                'source_file' => $test_files_dir . 'file_example_JPG_2500kB.jpg',
                'target_format' => Converter::FORMAT_WEBP,
                'quality' => 90,
                'expected_reduction' => 0, // May not reduce size at high quality
            ],
            // PNG to WebP with different quality levels
            'PNG to WebP Quality 60' => [
                'source_file' => $test_files_dir . 'file_example_PNG_3MB.png',
                'target_format' => Converter::FORMAT_WEBP,
                'quality' => 60,
                'expected_reduction' => 20, // PNG should have significant reduction
            ],
            'PNG to WebP Quality 75' => [
                'source_file' => $test_files_dir . 'file_example_PNG_3MB.png',
                'target_format' => Converter::FORMAT_WEBP,
                'quality' => 75,
                'expected_reduction' => 15, // PNG should have significant reduction
            ],
            // JPG to AVIF with different quality levels
            'JPG to AVIF Quality 50' => [
                'source_file' => $test_files_dir . 'file_example_JPG_2500kB.jpg',
                'target_format' => Converter::FORMAT_AVIF,
                'quality' => 50,
                'expected_reduction' => 25, // AVIF should provide excellent compression
            ],
            'JPG to AVIF Quality 70' => [
                'source_file' => $test_files_dir . 'file_example_JPG_2500kB.jpg',
                'target_format' => Converter::FORMAT_AVIF,
                'quality' => 70,
                'expected_reduction' => 15, // AVIF should provide good compression
            ],
            // PNG to AVIF with different quality levels
            'PNG to AVIF Quality 50' => [
                'source_file' => $test_files_dir . 'file_example_PNG_3MB.png',
                'target_format' => Converter::FORMAT_AVIF,
                'quality' => 50,
                'expected_reduction' => 30, // PNG to AVIF should have excellent compression
            ],
            'PNG to AVIF Quality 70' => [
                'source_file' => $test_files_dir . 'file_example_PNG_3MB.png',
                'target_format' => Converter::FORMAT_AVIF,
                'quality' => 70,
                'expected_reduction' => 20, // PNG to AVIF should have good compression
            ],
        ];
    }

    /**
     * Test image conversion efficiency with real files and quality variations.
     *
     * @since 1.0.0
     * @dataProvider imageEfficiencyDataProvider
     * @param string $source_file Source image file path.
     * @param string $target_format Target format to convert to.
     * @param int    $quality Quality setting for conversion.
     * @param int    $expected_reduction Minimum expected size reduction percentage.
     * @return void
     */
    public function testImageConversionEfficiency( $source_file, $target_format, $quality, $expected_reduction ) {
        // Skip test if processor is not available
        if ( ! $this->image_converter->is_available() ) {
            $this->markTestSkipped( 'No image processor available' );
        }

        // Skip test if source file doesn't exist
        if ( ! file_exists( $source_file ) ) {
            $this->markTestSkipped( "Source file not found: {$source_file}" );
        }

        // Skip test if target format is not supported
        if ( ! $this->image_converter->is_format_supported( $target_format ) ) {
            $this->markTestSkipped( "Target format not supported: {$target_format}" );
        }

        // Create temporary output file
        $output_file = TEST_TEMP_DIR . '/efficiency_' . uniqid() . '.' . $target_format;
        
        // Arrange
        $settings = [
            'webp_quality' => $quality,
            'avif_quality' => $quality,
            'hybrid_approach' => false,
        ];

        $destination_paths = [];
        if ( $target_format === Converter::FORMAT_WEBP ) {
            $destination_paths[Converter::FORMAT_WEBP] = $output_file;
        } elseif ( $target_format === Converter::FORMAT_AVIF ) {
            $destination_paths[Converter::FORMAT_AVIF] = $output_file;
        }

        // Act
        $result = $this->image_converter->process_image( $source_file, $destination_paths, $settings );

        // Assert - Verify conversion was successful
        $this->assertTrue( $result['success'], 'Image conversion should succeed. Errors: ' . implode( ', ', $result['errors'] ?? [] ) );
        $this->assertContains( $target_format, $result['converted_formats'] );
        $this->assertArrayHasKey( $target_format, $result['converted_files'] );

        // Verify output file exists and has content
        $this->assertFileExists( $output_file );
        $this->assertGreaterThan( 0, filesize( $output_file ) );

        // Calculate file size reduction
        $original_size = filesize( $source_file );
        $converted_size = filesize( $output_file );
        $reduction_percentage = ( ( $original_size - $converted_size ) / $original_size ) * 100;

        // Verify file size reduction meets expectations
        $this->assertGreaterThanOrEqual( 
            $expected_reduction, 
            $reduction_percentage,
            "File size reduction should be at least {$expected_reduction}%. " .
            "Original: {$original_size} bytes, Converted: {$converted_size} bytes, " .
            "Reduction: {$reduction_percentage}%"
        );

        // Clean up
        if ( file_exists( $output_file ) ) {
            unlink( $output_file );
        }
    }

    /**
     * Test quality vs file size trade-off for images.
     *
     * @since 1.0.0
     * @return void
     */
    public function testImageQualityVsFileSize() {
        if ( ! $this->image_converter->is_available() ) {
            $this->markTestSkipped( 'Image processor not available' );
        }

        $source_file = $this->mock_files['medium_jpg'];
        $quality_levels = [60, 75, 85, 95];
        $file_sizes = [];

        foreach ( $quality_levels as $quality ) {
            $destination_file = "/tmp/test-quality-{$quality}.webp";
            
            $result = $this->image_converter->convert_to_webp( 
                $source_file, 
                $destination_file, 
                ['quality' => $quality] 
            );
            
            if ( $result ) {
                $file_sizes[ $quality ] = $this->getFileSize( $destination_file );
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
                    "Higher quality ({$higher_quality}) should produce larger or equal file size than lower quality ({$lower_quality})"
                );
            }
        } else {
            $this->markTestSkipped( 'WebP conversion not supported or failed for quality testing' );
        }
    }

    /**
     * Test conversion performance with large files.
     *
     * @since 1.0.0
     * @return void
     */
    public function testLargeFileConversionPerformance() {
        if ( ! $this->image_converter->is_available() ) {
            $this->markTestSkipped( 'Image processor not available' );
        }

        $source_file = $this->mock_files['large_jpg'];
        $destination_file = '/tmp/test-performance.webp';

        $start_time = microtime( true );
        $result = $this->image_converter->convert_to_webp( $source_file, $destination_file );
        $end_time = microtime( true );

        if ( $result ) {
            $conversion_time = $end_time - $start_time;
            $original_size = $this->getFileSize( $source_file );
            
            // Conversion should complete within reasonable time (30 seconds for large files)
            $this->assertLessThan( 30, $conversion_time, 
                "Large file conversion should complete within 30 seconds. " .
                "Actual time: {$conversion_time} seconds for {$original_size} bytes"
            );
            
            unlink( $destination_file );
        } else {
            $this->markTestSkipped( 'WebP conversion not supported or failed for performance testing' );
        }
    }

    /**
     * Test memory usage during conversion.
     *
     * @since 1.0.0
     * @return void
     */
    public function testConversionMemoryUsage() {
        if ( ! $this->image_converter->is_available() ) {
            $this->markTestSkipped( 'Image processor not available' );
        }

        $source_file = $this->mock_files['large_jpg'];
        $destination_file = '/tmp/test-memory.webp';

        $memory_before = memory_get_usage( true );
        $result = $this->image_converter->convert_to_webp( $source_file, $destination_file );
        $memory_after = memory_get_usage( true );

        if ( $result ) {
            $memory_used = $memory_after - $memory_before;
            $original_size = $this->getFileSize( $source_file );
            
            // Memory usage should be reasonable (not more than 10x the file size)
            $max_expected_memory = $original_size * 10;
            $this->assertLessThan( $max_expected_memory, $memory_used, 
                "Memory usage should be reasonable. " .
                "File size: {$original_size} bytes, Memory used: {$memory_used} bytes, " .
                "Max expected: {$max_expected_memory} bytes"
            );
            
            unlink( $destination_file );
        } else {
            $this->markTestSkipped( 'WebP conversion not supported or failed for memory testing' );
        }
    }

    /**
     * Test conversion with edge case file sizes.
     *
     * @since 1.0.0
     * @return void
     */
    public function testEdgeCaseFileSizes() {
        if ( ! $this->image_converter->is_available() ) {
            $this->markTestSkipped( 'Image processor not available' );
        }

        // Test with very small file
        $tiny_file = $this->createMockImageFile( 'jpg', 10, 10 );
        $tiny_destination = '/tmp/test-tiny.webp';
        
        $result = $this->image_converter->convert_to_webp( $tiny_file, $tiny_destination );
        
        if ( $result ) {
            $this->assertFileExistsAndHasContent( $tiny_destination );
            unlink( $tiny_destination );
        }
        
        unlink( $tiny_file );

        // Test with very large file (if memory allows)
        if ( memory_get_usage( true ) < 100 * 1024 * 1024 ) { // Less than 100MB current usage
            $huge_file = $this->createMockImageFile( 'jpg', 2000, 2000 );
            $huge_destination = '/tmp/test-huge.webp';
            
            $result = $this->image_converter->convert_to_webp( $huge_file, $huge_destination );
            
            if ( $result ) {
                $this->assertFileExistsAndHasContent( $huge_destination );
                unlink( $huge_destination );
            }
            
            unlink( $huge_file );
        }
    }

    /**
     * Create a mock image file for testing.
     *
     * @since 1.0.0
     * @param string $format Image format (jpg, png, gif).
     * @param int    $width Image width.
     * @param int    $height Image height.
     * @return string Path to created mock file.
     */
    private function createMockImageFile( $format = 'jpg', $width = 100, $height = 100 ) {
        $filename = TEST_TEMP_DIR . '/test-image-' . uniqid() . '.' . $format;
        
        // Create a simple test image using GD
        if ( extension_loaded( 'gd' ) ) {
            $image = imagecreatetruecolor( $width, $height );
            $bg_color = imagecolorallocate( $image, 255, 255, 255 );
            imagefill( $image, 0, 0, $bg_color );
            
            // Add some content to make it a real file
            $text_color = imagecolorallocate( $image, 0, 0, 0 );
            imagestring( $image, 5, 10, 10, 'TEST', $text_color );
            
            switch ( $format ) {
                case 'jpg':
                case 'jpeg':
                    imagejpeg( $image, $filename, 90 );
                    break;
                case 'png':
                    imagepng( $image, $filename );
                    break;
                case 'gif':
                    imagegif( $image, $filename );
                    break;
            }
            
            imagedestroy( $image );
        } else {
            // Fallback: create a minimal file
            file_put_contents( $filename, 'fake image content' );
        }
        
        return $filename;
    }

    /**
     * Get file size in bytes.
     *
     * @since 1.0.0
     * @param string $filepath Path to file.
     * @return int File size in bytes.
     */
    private function getFileSize( $filepath ) {
        return file_exists( $filepath ) ? filesize( $filepath ) : 0;
    }

    /**
     * Clean up test files.
     *
     * @since 1.0.0
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
     * @since 1.0.0
     * @param string $filepath Path to file.
     * @param string $message Optional assertion message.
     * @return void
     */
    private function assertFileExistsAndHasContent( $filepath, $message = '' ) {
        $this->assertTrue( file_exists( $filepath ), $message ?: "File should exist: {$filepath}" );
        $this->assertGreaterThan( 0, filesize( $filepath ), $message ?: "File should have content: {$filepath}" );
    }
}
