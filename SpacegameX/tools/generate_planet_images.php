<?php
// Script to generate planet images with their resource indicators
namespace Tools;

// Set up paths
$baseDir = realpath(__DIR__ . '/../public/images/planets');
$planetBaseDir = $baseDir . '/base';
$outputDir = $baseDir;

// Check if directories exist, create if not
if (!is_dir($outputDir)) {
    mkdir($outputDir, 0755, true);
}

// Define planet types and resource indicators
$planetTypes = range(1, 10);
$resourceIndicators = [
    'normal' => null,
    'metal-rich' => '#95a5a6',
    'crystal-rich' => '#3498db',
    'h2-rich' => '#2ecc71',
    'balanced' => '#f1c40f',
    'barren' => '#e74c3c'
];

echo "Generating planet images with resource indicators...\n";

// Generate planet images for each type and resource indicator
foreach ($planetTypes as $type) {
    $basePlanetFile = $planetBaseDir . "/planet" . $type . ".png";
    
    // Skip if base planet image doesn't exist
    if (!file_exists($basePlanetFile)) {
        echo "Base planet image not found: $basePlanetFile\n";
        continue;
    }
    
    // Create basic planet image
    copy($basePlanetFile, $outputDir . "/planet" . $type . ".png");
    echo "Created base planet image: planet" . $type . ".png\n";
    
    // Generate planet images with resource indicators
    foreach ($resourceIndicators as $indicator => $color) {
        if ($indicator == 'normal') continue; // Skip normal, it uses the base image
        
        // Create image with resource indicator
        $img = imagecreatefrompng($basePlanetFile);
        
        // Add resource indicator (a small colored dot in the corner)
        if ($color) {
            $rgbColor = sscanf($color, "#%02x%02x%02x");
            $indicatorColor = imagecolorallocate($img, $rgbColor[0], $rgbColor[1], $rgbColor[2]);
            
            // Get image dimensions
            $width = imagesx($img);
            $height = imagesy($img);
            
            // Draw indicator (small circle in top-right)
            $indicatorSize = max(5, min($width, $height) / 10);
            $indicatorX = $width - $indicatorSize - 2;
            $indicatorY = $indicatorSize + 2;
            imagefilledellipse($img, $indicatorX, $indicatorY, $indicatorSize, $indicatorSize, $indicatorColor);
        }
        
        // Save modified image
        $outputFile = $outputDir . "/planet" . $type . "_" . $indicator . ".png";
        imagepng($img, $outputFile);
        imagedestroy($img);
        
        echo "Created planet image: " . basename($outputFile) . "\n";
    }
}

echo "Planet image generation complete.\n";
