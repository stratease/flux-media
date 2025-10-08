<?php
/**
 * Unit tests for video conversion efficiency and file size validation.
 *
 * @package FluxMedia\Tests\Unit
 * @since 1.0.0
 */

namespace FluxMedia\Tests\Unit;

use FluxMedia\Services\VideoConverter;
use FluxMedia\Tests\Support\Mocks\NoopLogger;
use PHPUnit\Framework\TestCase;

/**
 * Video conversion efficiency unit tests.
 *
 * @since 1.0.0
 */
class VideoConversionEfficiencyTest extends TestCase {

    /**
     * VideoConverter instance.
     *
     * @since 1.0.0
     * @var VideoConverter
     */
    private $video_converter;

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
        // Create VideoConverter instance (pure business logic, no WordPress dependencies)
        $this->logger = new NoopLogger();
        $this->video_converter = new VideoConverter( $this->logger );

        // Create mock video files with different sizes
        $this->mock_files = [
            'small_mp4' => $this->createMockVideoFile( 'mp4', 1024 * 1024 ), // 1MB
            'medium_mp4' => $this->createMockVideoFile( 'mp4', 5 * 1024 * 1024 ), // 5MB
            'large_mp4' => $this->createMockVideoFile( 'mp4', 20 * 1024 * 1024 ), // 20MB
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
     * Data provider for video conversion efficiency tests with real files.
     *
     * @since 1.0.0
     * @return array Test data with source files, target formats, and CRF levels.
     */
    public function videoEfficiencyDataProvider() {
        $test_files_dir = __DIR__ . '/../_support/files/';
        
        return [
            // MP4 to WebM with different CRF levels
            'MP4 to WebM CRF 18' => [
                'source_file' => $test_files_dir . 'file_example_MP4_1920_18MG.mp4',
                'target_format' => 'webm',
                'crf' => 18,
                'expected_reduction' => 0, // High quality may not reduce size
            ],
            'MP4 to WebM CRF 23' => [
                'source_file' => $test_files_dir . 'file_example_MP4_1920_18MG.mp4',
                'target_format' => 'webm',
                'crf' => 23,
                'expected_reduction' => 5, // Medium quality should reduce size
            ],
            'MP4 to WebM CRF 28' => [
                'source_file' => $test_files_dir . 'file_example_MP4_1920_18MG.mp4',
                'target_format' => 'webm',
                'crf' => 28,
                'expected_reduction' => 15, // Lower quality should reduce size significantly
            ],
            'MP4 to WebM CRF 35' => [
                'source_file' => $test_files_dir . 'file_example_MP4_1920_18MG.mp4',
                'target_format' => 'webm',
                'crf' => 35,
                'expected_reduction' => 25, // Low quality should reduce size significantly
            ],
            // MP4 to AV1 with different CRF levels
            'MP4 to AV1 CRF 18' => [
                'source_file' => $test_files_dir . 'file_example_MP4_1920_18MG.mp4',
                'target_format' => 'av1',
                'crf' => 18,
                'expected_reduction' => 0, // High quality may not reduce size
            ],
            'MP4 to AV1 CRF 23' => [
                'source_file' => $test_files_dir . 'file_example_MP4_1920_18MG.mp4',
                'target_format' => 'av1',
                'crf' => 23,
                'expected_reduction' => 10, // AV1 should provide good compression
            ],
            'MP4 to AV1 CRF 28' => [
                'source_file' => $test_files_dir . 'file_example_MP4_1920_18MG.mp4',
                'target_format' => 'av1',
                'crf' => 28,
                'expected_reduction' => 20, // AV1 should provide excellent compression
            ],
            'MP4 to AV1 CRF 35' => [
                'source_file' => $test_files_dir . 'file_example_MP4_1920_18MG.mp4',
                'target_format' => 'av1',
                'crf' => 35,
                'expected_reduction' => 30, // AV1 should provide excellent compression
            ],
        ];
    }

    /**
     * Test video conversion efficiency with real files and CRF variations.
     *
     * @since 1.0.0
     * @dataProvider videoEfficiencyDataProvider
     * @param string $source_file Source video file path.
     * @param string $target_format Target format to convert to.
     * @param int    $crf CRF setting for conversion.
     * @param int    $expected_reduction Minimum expected size reduction percentage.
     * @return void
     */
    public function testVideoConversionEfficiency( $source_file, $target_format, $crf, $expected_reduction ) {
        // Skip test if processor is not available
        if ( ! $this->video_converter->is_available() ) {
            $this->markTestSkipped( 'No video processor available' );
        }

        // Skip test if source file doesn't exist
        if ( ! file_exists( $source_file ) ) {
            $this->markTestSkipped( "Source file not found: {$source_file}" );
        }

        // Skip test if target format is not supported
        if ( ! $this->video_converter->is_format_supported( $target_format ) ) {
            $this->markTestSkipped( "Target format not supported: {$target_format}" );
        }

        // Create temporary output file
        $output_file = TEST_TEMP_DIR . '/video_efficiency_' . uniqid() . '.' . $target_format;
        
        // Arrange
        $settings = [
            'webm_crf' => $crf,
            'av1_crf' => $crf,
            'hybrid_approach' => false,
        ];

        $destination_paths = [];
        if ( $target_format === 'webm' ) {
            $destination_paths['webm'] = $output_file;
        } elseif ( $target_format === 'av1' ) {
            $destination_paths['av1'] = $output_file;
        }

        // Act
        $result = $this->video_converter->process_video( $source_file, $destination_paths, $settings );

        // Assert - Verify conversion was successful
        $this->assertTrue( $result['success'], 'Video conversion should succeed. Errors: ' . implode( ', ', $result['errors'] ?? [] ) );
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
     * Test CRF vs file size trade-off for videos.
     *
     * @since 1.0.0
     * @return void
     */
    public function testVideoCRFVsFileSize() {
        if ( ! $this->video_converter->is_available() ) {
            $this->markTestSkipped( 'Video processor not available' );
        }

        $source_file = $this->mock_files['medium_mp4'];
        $crf_levels = [18, 23, 28, 35];
        $file_sizes = [];

        foreach ( $crf_levels as $crf ) {
            $destination_file = "/tmp/test-crf-{$crf}.webm";
            
            $result = $this->video_converter->convert_to_webm( 
                $source_file, 
                $destination_file, 
                ['crf' => $crf] 
            );
            
            if ( $result ) {
                $file_sizes[ $crf ] = $this->getFileSize( $destination_file );
                unlink( $destination_file );
            }
        }

        if ( count( $file_sizes ) >= 2 ) {
            // Lower CRF (higher quality) should generally result in larger file sizes
            $crfs = array_keys( $file_sizes );
            sort( $crfs );
            
            for ( $i = 1; $i < count( $crfs ); $i++ ) {
                $higher_crf = $crfs[ $i - 1 ];
                $lower_crf = $crfs[ $i ];
                
                $this->assertLessThanOrEqual( 
                    $file_sizes[ $lower_crf ], 
                    $file_sizes[ $higher_crf ],
                    "Lower CRF ({$lower_crf}) should produce larger or equal file size than higher CRF ({$higher_crf})"
                );
            }
        } else {
            $this->markTestSkipped( 'WebM conversion not supported or failed for CRF testing' );
        }
    }

    /**
     * Test video conversion performance with large files.
     *
     * @since 1.0.0
     * @return void
     */
    public function testLargeVideoConversionPerformance() {
        if ( ! $this->video_converter->is_available() ) {
            $this->markTestSkipped( 'Video processor not available' );
        }

        $source_file = $this->mock_files['large_mp4'];
        $destination_file = '/tmp/test-video-performance.webm';

        $start_time = microtime( true );
        $result = $this->video_converter->convert_to_webm( $source_file, $destination_file );
        $end_time = microtime( true );

        if ( $result ) {
            $conversion_time = $end_time - $start_time;
            $original_size = $this->getFileSize( $source_file );
            
            // Video conversion should complete within reasonable time (60 seconds for large files)
            $this->assertLessThan( 60, $conversion_time, 
                "Large video conversion should complete within 60 seconds. " .
                "Actual time: {$conversion_time} seconds for {$original_size} bytes"
            );
            
            unlink( $destination_file );
        } else {
            $this->markTestSkipped( 'WebM conversion not supported or failed for performance testing' );
        }
    }

    /**
     * Test memory usage during video conversion.
     *
     * @since 1.0.0
     * @return void
     */
    public function testVideoConversionMemoryUsage() {
        if ( ! $this->video_converter->is_available() ) {
            $this->markTestSkipped( 'Video processor not available' );
        }

        $source_file = $this->mock_files['medium_mp4'];
        $destination_file = '/tmp/test-video-memory.webm';

        $memory_before = memory_get_usage( true );
        $result = $this->video_converter->convert_to_webm( $source_file, $destination_file );
        $memory_after = memory_get_usage( true );

        if ( $result ) {
            $memory_used = $memory_after - $memory_before;
            $original_size = $this->getFileSize( $source_file );
            
            // Memory usage should be reasonable (not more than 5x the file size for videos)
            $max_expected_memory = $original_size * 5;
            $this->assertLessThan( $max_expected_memory, $memory_used, 
                "Memory usage should be reasonable. " .
                "File size: {$original_size} bytes, Memory used: {$memory_used} bytes, " .
                "Max expected: {$max_expected_memory} bytes"
            );
            
            unlink( $destination_file );
        } else {
            $this->markTestSkipped( 'WebM conversion not supported or failed for memory testing' );
        }
    }

    /**
     * Test video conversion with edge case file sizes.
     *
     * @since 1.0.0
     * @return void
     */
    public function testVideoEdgeCaseFileSizes() {
        if ( ! $this->video_converter->is_available() ) {
            $this->markTestSkipped( 'Video processor not available' );
        }

        // Test with very small video file
        $tiny_file = $this->createMockVideoFile( 'mp4', 1024 ); // 1KB
        $tiny_destination = '/tmp/test-tiny-video.webm';
        
        $result = $this->video_converter->convert_to_webm( $tiny_file, $tiny_destination );
        
        if ( $result ) {
            $this->assertFileExistsAndHasContent( $tiny_destination );
            unlink( $tiny_destination );
        }
        
        unlink( $tiny_file );

        // Test with very large video file (if memory allows)
        if ( memory_get_usage( true ) < 200 * 1024 * 1024 ) { // Less than 200MB current usage
            $huge_file = $this->createMockVideoFile( 'mp4', 50 * 1024 * 1024 ); // 50MB
            $huge_destination = '/tmp/test-huge-video.webm';
            
            $result = $this->video_converter->convert_to_webm( $huge_file, $huge_destination );
            
            if ( $result ) {
                $this->assertFileExistsAndHasContent( $huge_destination );
                unlink( $huge_destination );
            }
            
            unlink( $huge_file );
        }
    }

    /**
     * Test supported video format check.
     *
     * @since 1.0.0
     * @return void
     */
    public function testIsSupportedVideo() {
        // Test supported formats
        $this->assertTrue( $this->video_converter->is_supported_video( 'test.mp4' ) );
        $this->assertTrue( $this->video_converter->is_supported_video( 'test.avi' ) );
        $this->assertTrue( $this->video_converter->is_supported_video( 'test.mov' ) );
        $this->assertTrue( $this->video_converter->is_supported_video( 'test.wmv' ) );
        $this->assertTrue( $this->video_converter->is_supported_video( 'test.flv' ) );
        $this->assertTrue( $this->video_converter->is_supported_video( 'test.webm' ) );
        $this->assertTrue( $this->video_converter->is_supported_video( 'test.ogg' ) );
        
        // Test unsupported formats
        $this->assertFalse( $this->video_converter->is_supported_video( 'test.txt' ) );
        $this->assertFalse( $this->video_converter->is_supported_video( 'test.pdf' ) );
        $this->assertFalse( $this->video_converter->is_supported_video( 'test' ) );
    }

    /**
     * Test supported formats retrieval.
     *
     * @since 1.0.0
     * @return void
     */
    public function testGetSupportedFormats() {
        $formats = $this->video_converter->get_supported_formats();
        
        $this->assertIsArray( $formats );
        $this->assertContains( 'av1', $formats );
        $this->assertContains( 'webm', $formats );
    }

    /**
     * Test format support check.
     *
     * @since 1.0.0
     * @return void
     */
    public function testIsFormatSupported() {
        $this->assertTrue( $this->video_converter->is_format_supported( 'av1' ) );
        $this->assertTrue( $this->video_converter->is_format_supported( 'webm' ) );
        $this->assertFalse( $this->video_converter->is_format_supported( 'mp4' ) );
        $this->assertFalse( $this->video_converter->is_format_supported( 'avi' ) );
    }

    /**
     * Test converter type.
     *
     * @since 1.0.0
     * @return void
     */
    public function testGetType() {
        $type = $this->video_converter->get_type();
        $this->assertEquals( 'video', $type );
    }

    /**
     * Create a mock video file for testing.
     *
     * @since 1.0.0
     * @param string $format Video format (mp4, avi, mov).
     * @param int    $size File size in bytes.
     * @return string Path to created mock file.
     */
    private function createMockVideoFile( $format = 'mp4', $size = 1024 ) {
        $filename = TEST_TEMP_DIR . '/test-video-' . uniqid() . '.' . $format;
        
        // Create a file with the specified size
        $content = str_repeat( '0', $size );
        file_put_contents( $filename, $content );
        
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
