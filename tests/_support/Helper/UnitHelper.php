<?php
/**
 * Unit test helper for Flux Media plugin.
 *
 * @package FluxMedia\Tests\Support\Helper
 * @since 1.0.0
 */

namespace FluxMedia\Tests\Support\Helper;

use Codeception\Module;

/**
 * Unit test helper class.
 *
 * @since 1.0.0
 */
class UnitHelper extends Module {

    /**
     * Create a mock image file for testing.
     *
     * @since 1.0.0
     * @param string $format Image format (jpg, png, gif).
     * @param int    $width Image width.
     * @param int    $height Image height.
     * @return string Path to created mock file.
     */
    public function createMockImageFile( $format = 'jpg', $width = 100, $height = 100 ) {
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
     * Create a mock video file for testing.
     *
     * @since 1.0.0
     * @param string $format Video format (mp4, avi, mov).
     * @param int    $size File size in bytes.
     * @return string Path to created mock file.
     */
    public function createMockVideoFile( $format = 'mp4', $size = 1024 ) {
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
    public function getFileSize( $filepath ) {
        return file_exists( $filepath ) ? filesize( $filepath ) : 0;
    }

    /**
     * Clean up test files.
     *
     * @since 1.0.0
     * @param array $files Array of file paths to clean up.
     * @return void
     */
    public function cleanupTestFiles( $files ) {
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
    public function assertFileExistsAndHasContent( $filepath, $message = '' ) {
        $this->assertTrue( file_exists( $filepath ), $message ?: "File should exist: {$filepath}" );
        $this->assertGreaterThan( 0, filesize( $filepath ), $message ?: "File should have content: {$filepath}" );
    }

    /**
     * Assert that a conversion resulted in a smaller file size.
     *
     * @since 1.0.0
     * @param string $original_file Path to original file.
     * @param string $converted_file Path to converted file.
     * @param string $message Optional assertion message.
     * @return void
     */
    public function assertFileSizeReduction( $original_file, $converted_file, $message = '' ) {
        $original_size = $this->getFileSize( $original_file );
        $converted_size = $this->getFileSize( $converted_file );
        
        $this->assertFileExistsAndHasContent( $converted_file );
        $this->assertLessThan( $original_size, $converted_size, 
            $message ?: "Converted file should be smaller than original. Original: {$original_size} bytes, Converted: {$converted_size} bytes" 
        );
    }

    /**
     * Assert that a conversion resulted in a larger file size (for testing edge cases).
     *
     * @since 1.0.0
     * @param string $original_file Path to original file.
     * @param string $converted_file Path to converted file.
     * @param string $message Optional assertion message.
     * @return void
     */
    public function assertFileSizeIncrease( $original_file, $converted_file, $message = '' ) {
        $original_size = $this->getFileSize( $original_file );
        $converted_size = $this->getFileSize( $converted_file );
        
        $this->assertFileExistsAndHasContent( $converted_file );
        $this->assertGreaterThan( $original_size, $converted_size, 
            $message ?: "Converted file should be larger than original. Original: {$original_size} bytes, Converted: {$converted_size} bytes" 
        );
    }
}
