<?php
/**
 * Unit tests for VideoConverter class.
 *
 * @package FluxMedia\Tests\Unit
 * @since 1.0.0
 */

namespace FluxMedia\Tests\Unit;

use FluxMedia\Services\VideoConverter;
use FluxMedia\Tests\Support\Mocks\NoopLogger;
use PHPUnit\Framework\TestCase;

/**
 * VideoConverter unit tests.
 *
 * @since 1.0.0
 */
class VideoConverterTest extends TestCase {

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
     * Set up test environment.
     *
     * @since 1.0.0
     * @return void
     */
    protected function setUp(): void {
        // Create VideoConverter instance (pure business logic, no WordPress dependencies)
        $this->logger = new NoopLogger();
        $this->video_converter = new VideoConverter( $this->logger );
    }

    /**
     * Test VideoConverter instantiation.
     *
     * @since 1.0.0
     * @return void
     */
    public function testVideoConverterInstantiation() {
        $this->assertInstanceOf( VideoConverter::class, $this->video_converter );
    }

    /**
     * Test processor availability check.
     *
     * @since 1.0.0
     * @return void
     */
    public function testIsAvailable() {
        $available = $this->video_converter->is_available();
        $this->assertIsBool( $available );
    }

    /**
     * Test processor info retrieval.
     *
     * @since 1.0.0
     * @return void
     */
    public function testGetProcessorInfo() {
        $info = $this->video_converter->get_processor_info();
        
        $this->assertIsArray( $info );
        $this->assertArrayHasKey( 'available', $info );
        $this->assertArrayHasKey( 'type', $info );
        $this->assertArrayHasKey( 'av1_support', $info );
        $this->assertArrayHasKey( 'webm_support', $info );
        
        $this->assertIsBool( $info['available'] );
        $this->assertIsString( $info['type'] );
        $this->assertIsBool( $info['av1_support'] );
        $this->assertIsBool( $info['webm_support'] );
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
     * Test supported MIME types retrieval.
     *
     * @since 1.0.0
     * @return void
     */
    public function testGetSupportedMimeTypes() {
        $mime_types = $this->video_converter->get_supported_mime_types();
        
        $this->assertIsArray( $mime_types );
        $this->assertContains( 'video/mp4', $mime_types );
        $this->assertContains( 'video/avi', $mime_types );
        $this->assertContains( 'video/mov', $mime_types );
        $this->assertContains( 'video/webm', $mime_types );
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
     * Test conversion statistics.
     *
     * @since 1.0.0
     * @return void
     */
    public function testGetConversionStats() {
        $stats = $this->video_converter->get_conversion_stats();
        
        $this->assertIsArray( $stats );
        $this->assertArrayHasKey( 'total_conversions', $stats );
        $this->assertArrayHasKey( 'successful_conversions', $stats );
        $this->assertArrayHasKey( 'failed_conversions', $stats );
        $this->assertArrayHasKey( 'av1_conversions', $stats );
        $this->assertArrayHasKey( 'webm_conversions', $stats );
        
        $this->assertIsInt( $stats['total_conversions'] );
        $this->assertIsInt( $stats['successful_conversions'] );
        $this->assertIsInt( $stats['failed_conversions'] );
        $this->assertIsInt( $stats['av1_conversions'] );
        $this->assertIsInt( $stats['webm_conversions'] );
    }


    /**
     * Test error handling for invalid inputs.
     *
     * @since 1.0.0
     * @return void
     */
    public function testErrorHandling() {
        // Test with non-existent file
        $this->video_converter->from( 'non-existent.mp4' )->to( 'output.webm' );
        $result = $this->video_converter->convert();
        
        $this->assertFalse( $result );
        $this->assertNotEmpty( $this->video_converter->get_errors() );
        $this->assertIsString( $this->video_converter->get_last_error() );
    }

    /**
     * Test cleanup temp files functionality.
     *
     * @since 1.0.0
     * @return void
     */
    public function testCleanupTempFiles() {
        // Create a temporary directory with some files
        $temp_dir = TEST_TEMP_DIR . '/test-cleanup-' . uniqid();
        mkdir( $temp_dir, 0755, true );
        
        // Create some test files
        $test_files = [
            $temp_dir . '/file1.txt',
            $temp_dir . '/file2.txt',
            $temp_dir . '/file3.txt',
        ];
        
        foreach ( $test_files as $file ) {
            file_put_contents( $file, 'test content' );
        }
        
        // Test cleanup
        $result = $this->video_converter->cleanup_temp_files( $temp_dir );
        
        $this->assertTrue( $result );
        
        // Verify files are deleted
        foreach ( $test_files as $file ) {
            $this->assertFalse( file_exists( $file ) );
        }
        
        // Clean up directory
        rmdir( $temp_dir );
    }

    /**
     * Test cleanup with non-existent directory.
     *
     * @since 1.0.0
     * @return void
     */
    public function testCleanupNonExistentDirectory() {
        $result = $this->video_converter->cleanup_temp_files( '/non/existent/directory' );
        $this->assertTrue( $result );
    }
}