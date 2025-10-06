<?php
/**
 * Video Conversion Example using PHP-FFmpeg
 * 
 * This example demonstrates how to use the updated FFmpegProcessor
 * with PHP-FFmpeg library for video conversion.
 *
 * @package FluxMedia
 * @since 1.0.0
 */

// Include WordPress
require_once '../../../wp-load.php';

// Include the processor
require_once '../src/Processors/FFmpegProcessor.php';
require_once '../src/Utils/Logger.php';

use FluxMedia\Processors\FFmpegProcessor;
use FluxMedia\Utils\Logger;

// Initialize logger
$logger = new Logger();

// Initialize video processor
$processor = new FFmpegProcessor($logger);

// Check if FFmpeg is available
$info = $processor->get_info();
if (!$info['available']) {
    echo "FFmpeg is not available on this system.\n";
    exit(1);
}

echo "FFmpeg Processor Information:\n";
echo "- Type: " . $info['type'] . "\n";
echo "- Version: " . $info['version'] . "\n";
echo "- AV1 Support: " . ($info['av1_support'] ? 'Yes' : 'No') . "\n";
echo "- WebM Support: " . ($info['webm_support'] ? 'Yes' : 'No') . "\n";
echo "\n";

// Example: Convert MP4 to WebM
$source_file = '/path/to/your/input.mp4';
$webm_output = '/path/to/your/output.webm';
$av1_output = '/path/to/your/output.av1';

if (file_exists($source_file)) {
    echo "Converting video: $source_file\n";
    
    // Get video metadata
    $metadata = $processor->get_metadata($source_file);
    if ($metadata) {
        echo "Video Metadata:\n";
        echo "- Duration: " . $metadata['duration'] . " seconds\n";
        echo "- Resolution: " . $metadata['width'] . "x" . $metadata['height'] . "\n";
        echo "- Codec: " . $metadata['codec'] . "\n";
        echo "- Bitrate: " . $metadata['bitrate'] . " bps\n";
        echo "\n";
    }
    
    // Convert to WebM
    if ($info['webm_support']) {
        echo "Converting to WebM...\n";
        $webm_options = [
            'crf' => 30,
            'preset' => 'medium'
        ];
        
        if ($processor->convert_to_webm($source_file, $webm_output, $webm_options)) {
            echo "WebM conversion successful: $webm_output\n";
        } else {
            echo "WebM conversion failed\n";
        }
    }
    
    // Convert to AV1
    if ($info['av1_support']) {
        echo "Converting to AV1...\n";
        $av1_options = [
            'crf' => 28,
            'preset' => 'medium',
            'cpu_used' => 4
        ];
        
        if ($processor->convert_to_av1($source_file, $av1_output, $av1_options)) {
            echo "AV1 conversion successful: $av1_output\n";
        } else {
            echo "AV1 conversion failed\n";
        }
    }
} else {
    echo "Source file not found: $source_file\n";
    echo "Please update the file path in this example.\n";
}

echo "\nExample completed.\n";
