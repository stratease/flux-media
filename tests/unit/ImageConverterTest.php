<?php
/**
 * Unit tests for ImageConverter class.
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
 * ImageConverter unit tests.
 *
 * @since 1.0.0
 */
class ImageConverterTest extends TestCase {

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
     * Set up test environment.
     *
     * @since 1.0.0
     * @return void
     */
    protected function setUp(): void {
        // Create ImageConverter instance (pure business logic, no WordPress dependencies)
        $this->logger = new NoopLogger();
        $this->image_converter = new ImageConverter( $this->logger );
    }

    /**
     * Test ImageConverter instantiation.
     *
     * @since 1.0.0
     * @return void
     */
    public function testImageConverterInstantiation() {
        $this->assertInstanceOf( ImageConverter::class, $this->image_converter );
    }

    /**
     * Test processor availability check.
     *
     * @since 1.0.0
     * @return void
     */
    public function testIsAvailable() {
        $available = $this->image_converter->is_available();
        $this->assertIsBool( $available );
    }

    /**
     * Test processor info retrieval.
     *
     * @since 1.0.0
     * @return void
     */
    public function testGetProcessorInfo() {
        $info = $this->image_converter->get_processor_info();
        
        $this->assertIsArray( $info );
        $this->assertArrayHasKey( 'available', $info );
        $this->assertArrayHasKey( 'type', $info );
        $this->assertArrayHasKey( 'webp_support', $info );
        $this->assertArrayHasKey( 'avif_support', $info );
        
        $this->assertIsBool( $info['available'] );
        $this->assertIsString( $info['type'] );
        $this->assertIsBool( $info['webp_support'] );
        $this->assertIsBool( $info['avif_support'] );
    }

    /**
     * Test supported image format check.
     *
     * @since 1.0.0
     * @return void
     */
    public function testIsSupportedImage() {
        // Test supported formats
        $this->assertTrue( $this->image_converter->is_supported_image( 'test.jpg' ) );
        $this->assertTrue( $this->image_converter->is_supported_image( 'test.jpeg' ) );
        $this->assertTrue( $this->image_converter->is_supported_image( 'test.png' ) );
        $this->assertTrue( $this->image_converter->is_supported_image( 'test.gif' ) );
        $this->assertTrue( $this->image_converter->is_supported_image( 'test.webp' ) );
        
        // Test unsupported formats
        $this->assertFalse( $this->image_converter->is_supported_image( 'test.txt' ) );
        $this->assertFalse( $this->image_converter->is_supported_image( 'test.pdf' ) );
        $this->assertFalse( $this->image_converter->is_supported_image( 'test' ) );
    }

    /**
     * Test supported formats retrieval.
     *
     * @since 1.0.0
     * @return void
     */
    public function testGetSupportedFormats() {
        $formats = $this->image_converter->get_supported_formats();
        
        $this->assertIsArray( $formats );
        $this->assertContains( Converter::FORMAT_WEBP, $formats );
        $this->assertContains( Converter::FORMAT_AVIF, $formats );
    }

    /**
     * Test format support check.
     *
     * @since 1.0.0
     * @return void
     */
    public function testIsFormatSupported() {
        $this->assertTrue( $this->image_converter->is_format_supported( Converter::FORMAT_WEBP ) );
        $this->assertTrue( $this->image_converter->is_format_supported( Converter::FORMAT_AVIF ) );
        $this->assertFalse( $this->image_converter->is_format_supported( Converter::FORMAT_JPEG ) );
        $this->assertFalse( $this->image_converter->is_format_supported( Converter::FORMAT_PNG ) );
    }

    /**
     * Test converter type.
     *
     * @since 1.0.0
     * @return void
     */
    public function testGetType() {
        $type = $this->image_converter->get_type();
        $this->assertEquals( Converter::TYPE_IMAGE, $type );
    }

    /**
     * Data provider for image conversion tests.
     *
     * @since 1.0.0
     * @return array Test data with source files and target formats.
     */
    public function imageConversionDataProvider() {
        $test_files_dir = __DIR__ . '/../_support/files/';
        
        return [
            // JPG to WebP
            'JPG to WebP' => [
                'source_file' => $test_files_dir . 'file_example_JPG_2500kB.jpg',
                'target_format' => Converter::FORMAT_WEBP,
            ],
            // JPG to AVIF
            'JPG to AVIF' => [
                'source_file' => $test_files_dir . 'file_example_JPG_2500kB.jpg',
                'target_format' => Converter::FORMAT_AVIF,
            ],
            // PNG to WebP
            'PNG to WebP' => [
                'source_file' => $test_files_dir . 'file_example_PNG_3MB.png',
                'target_format' => Converter::FORMAT_WEBP,
            ],
            // PNG to AVIF
            'PNG to AVIF' => [
                'source_file' => $test_files_dir . 'file_example_PNG_3MB.png',
                'target_format' => Converter::FORMAT_AVIF,
            ],
            // WebP to WebP (re-encoding)
            'WebP to WebP' => [
                'source_file' => $test_files_dir . 'file_example_WEBP_1500kB.webp',
                'target_format' => Converter::FORMAT_WEBP,
            ],
        ];
    }

    /**
     * Test image conversion with real files.
     *
     * @since 1.0.0
     * @dataProvider imageConversionDataProvider
     * @param string $source_file Source image file path.
     * @param string $target_format Target format to convert to.
     * @return void
     */
    public function testImageConversion( $source_file, $target_format ) {
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
        $output_file = TEST_TEMP_DIR . '/converted_' . uniqid() . '.' . $target_format;
        
        // Arrange - use default settings from Settings class
        $settings = [
            'webp_quality' => 75, // Default from Settings
            'avif_quality' => 70, // Default from Settings
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

        // Assert
        $this->assertIsArray( $result );
        $this->assertArrayHasKey( 'success', $result );
        $this->assertArrayHasKey( 'converted_formats', $result );
        $this->assertArrayHasKey( 'converted_files', $result );
        $this->assertArrayHasKey( 'errors', $result );

        // Verify conversion was successful
        $this->assertTrue( $result['success'], 'Image conversion should succeed. Errors: ' . implode( ', ', $result['errors'] ?? [] ) );
        $this->assertContains( $target_format, $result['converted_formats'] );
        $this->assertArrayHasKey( $target_format, $result['converted_files'] );
        $this->assertEquals( $output_file, $result['converted_files'][ $target_format ] );

        // Verify output file exists and has content
        $this->assertFileExists( $output_file );
        $this->assertGreaterThan( 0, filesize( $output_file ) );

        // Clean up
        if ( file_exists( $output_file ) ) {
            unlink( $output_file );
        }
    }

    /**
     * Test hybrid conversion approach with real files.
     *
     * @since 1.0.0
     * @return void
     */
    public function testHybridConversion() {
        // Skip test if processor is not available
        if ( ! $this->image_converter->is_available() ) {
            $this->markTestSkipped( 'No image processor available' );
        }

        $test_files_dir = __DIR__ . '/../_support/files/';
        $source_file = $test_files_dir . 'file_example_JPG_2500kB.jpg';

        // Skip test if source file doesn't exist
        if ( ! file_exists( $source_file ) ) {
            $this->markTestSkipped( "Source file not found: {$source_file}" );
        }

        // Create temporary output files
        $webp_output = TEST_TEMP_DIR . '/hybrid_' . uniqid() . '.webp';
        $avif_output = TEST_TEMP_DIR . '/hybrid_' . uniqid() . '.avif';

        // Arrange - use default settings from Settings class
        $settings = [
            'webp_quality' => 75, // Default from Settings
            'avif_quality' => 70, // Default from Settings
            'hybrid_approach' => true,
        ];

        $destination_paths = [
            Converter::FORMAT_WEBP => $webp_output,
            Converter::FORMAT_AVIF => $avif_output,
        ];

        // Act
        $result = $this->image_converter->process_image( $source_file, $destination_paths, $settings );

        // Assert
        $this->assertIsArray( $result );
        $this->assertArrayHasKey( 'success', $result );
        $this->assertArrayHasKey( 'converted_formats', $result );
        $this->assertArrayHasKey( 'converted_files', $result );

        // Verify conversion was successful
        $this->assertTrue( $result['success'], 'Hybrid image conversion should succeed. Errors: ' . implode( ', ', $result['errors'] ?? [] ) );
        $this->assertNotEmpty( $result['converted_formats'] );

        // Check which formats were successfully converted
        if ( in_array( Converter::FORMAT_WEBP, $result['converted_formats'], true ) ) {
            $this->assertFileExists( $webp_output );
            $this->assertGreaterThan( 0, filesize( $webp_output ) );
        }

        if ( in_array( Converter::FORMAT_AVIF, $result['converted_formats'], true ) ) {
            $this->assertFileExists( $avif_output );
            $this->assertGreaterThan( 0, filesize( $avif_output ) );
        }

        // Clean up
        if ( file_exists( $webp_output ) ) {
            unlink( $webp_output );
        }
        if ( file_exists( $avif_output ) ) {
            unlink( $avif_output );
        }
    }

    /**
     * Test file size reduction calculation.
     *
     * @since 1.0.0
     * @return void
     */
    public function testFileSizeReduction() {
        $test_files_dir = __DIR__ . '/../_support/files/';
        $source_file = $test_files_dir . 'file_example_JPG_2500kB.jpg';

        // Skip test if source file doesn't exist
        if ( ! file_exists( $source_file ) ) {
            $this->markTestSkipped( "Source file not found: {$source_file}" );
        }

        // Create a temporary file for testing
        $temp_file = TEST_TEMP_DIR . '/size_test_' . uniqid() . '.webp';
        file_put_contents( $temp_file, 'test content' );

        // Test size reduction calculation
        $reduction = $this->image_converter->get_size_reduction( $source_file, $temp_file );
        $this->assertIsFloat( $reduction );

        // Clean up
        if ( file_exists( $temp_file ) ) {
            unlink( $temp_file );
        }
    }

    /**
     * Test error handling for invalid inputs.
     *
     * @since 1.0.0
     * @return void
     */
    public function testErrorHandling() {
        // Test with non-existent file
        $this->image_converter->from( 'non-existent.jpg' )->to( 'output.webp' );
        $result = $this->image_converter->convert();
        
        $this->assertFalse( $result );
        $this->assertNotEmpty( $this->image_converter->get_errors() );
        $this->assertIsString( $this->image_converter->get_last_error() );
    }
}