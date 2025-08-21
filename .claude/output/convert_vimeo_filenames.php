#!/usr/bin/env php
<?php

/**
 * Script to convert downloaded Vimeo video filenames to match metadata format
 * Usage: php convert_vimeo_filenames.php [--dry-run] [--move]
 */

$videosDir = __DIR__ . '/videos';
$metadataDir = __DIR__ . '/import/metadata';
$importVideosDir = __DIR__ . '/import/videos';

// Parse command line options
$options = getopt('', ['dry-run', 'move', 'help']);
$dryRun = isset($options['dry-run']);
$moveFiles = isset($options['move']);

if (isset($options['help'])) {
    echo "Usage: php convert_vimeo_filenames.php [options]\n";
    echo "Options:\n";
    echo "  --dry-run    Show what would be renamed without making changes\n";
    echo "  --move       Move files to import/videos directory (default: rename in place)\n";
    echo "  --help       Show this help message\n";
    exit(0);
}

// Create import/videos directory if needed
if (!file_exists($importVideosDir)) {
    mkdir($importVideosDir, 0755, true);
    echo "Created directory: $importVideosDir\n";
}

// Load all metadata files
$metadataFiles = glob($metadataDir . '/*.json');
$metadataIndex = [];

foreach ($metadataFiles as $metaFile) {
    $json = json_decode(file_get_contents($metaFile), true);
    if ($json && isset($json['title']) && isset($json['vimeo_id'])) {
        // Store multiple variations of the title for matching
        $title = $json['title'];
        $metadataIndex[strtolower($title)] = [
            'title' => $title,
            'vimeo_id' => $json['vimeo_id'],
            'filename' => basename($metaFile, '.json')
        ];
        
        // Also store with underscores replaced by spaces
        $titleWithSpaces = str_replace('_', ' ', $title);
        $metadataIndex[strtolower($titleWithSpaces)] = [
            'title' => $title,
            'vimeo_id' => $json['vimeo_id'],
            'filename' => basename($metaFile, '.json')
        ];
    }
}

echo "Loaded " . count($metadataFiles) . " metadata files\n";
echo "Created " . count($metadataIndex) . " title variations for matching\n\n";

// Find video files
$videoFiles = glob($videosDir . '/*.mp4');
$videoFiles = array_merge($videoFiles, glob($videosDir . '/*.mov'));
$videoFiles = array_merge($videoFiles, glob($videosDir . '/*.avi'));

if (empty($videoFiles)) {
    echo "No video files found in $videosDir\n";
    exit(0);
}

echo "Found " . count($videoFiles) . " video files\n\n";

$matched = 0;
$unmatched = [];

foreach ($videoFiles as $videoFile) {
    $basename = basename($videoFile);
    $nameWithoutExt = pathinfo($basename, PATHINFO_FILENAME);
    
    // Remove quality indicators like (720p), (1080p), etc.
    $cleanName = preg_replace('/\s*\(\d+p\)\s*/', '', $nameWithoutExt);
    
    // Try different matching strategies
    $matchFound = false;
    $matchedMeta = null;
    
    // Strategy 1: Direct match (case-insensitive)
    if (isset($metadataIndex[strtolower($cleanName)])) {
        $matchedMeta = $metadataIndex[strtolower($cleanName)];
        $matchFound = true;
    }
    
    // Strategy 2: Replace underscores with spaces
    if (!$matchFound) {
        $nameWithSpaces = str_replace('_', ' ', $cleanName);
        if (isset($metadataIndex[strtolower($nameWithSpaces)])) {
            $matchedMeta = $metadataIndex[strtolower($nameWithSpaces)];
            $matchFound = true;
        }
    }
    
    // Strategy 3: Replace spaces with underscores  
    if (!$matchFound) {
        $nameWithUnderscores = str_replace(' ', '_', $cleanName);
        if (isset($metadataIndex[strtolower($nameWithUnderscores)])) {
            $matchedMeta = $metadataIndex[strtolower($nameWithUnderscores)];
            $matchFound = true;
        }
    }
    
    // Strategy 4: Fuzzy match - find best match based on similarity
    if (!$matchFound) {
        $bestMatch = null;
        $bestSimilarity = 0;
        
        foreach ($metadataIndex as $key => $meta) {
            similar_text(strtolower($cleanName), $key, $percent);
            if ($percent > $bestSimilarity && $percent > 70) { // 70% similarity threshold
                $bestSimilarity = $percent;
                $bestMatch = $meta;
            }
        }
        
        if ($bestMatch) {
            $matchedMeta = $bestMatch;
            $matchFound = true;
            echo "Fuzzy match ({$bestSimilarity}%): '$basename' => '{$matchedMeta['title']}'\n";
        }
    }
    
    if ($matchFound) {
        $extension = pathinfo($videoFile, PATHINFO_EXTENSION);
        $newFilename = $matchedMeta['filename'] . '.' . $extension;
        
        if ($moveFiles) {
            $newPath = $importVideosDir . '/' . $newFilename;
            $action = "Move";
        } else {
            $newPath = $videosDir . '/' . $newFilename;
            $action = "Rename";
        }
        
        echo "$action: \n";
        echo "  From: $basename\n";
        echo "  To:   $newFilename\n";
        echo "  Vimeo ID: {$matchedMeta['vimeo_id']}\n";
        
        if (!$dryRun) {
            if (file_exists($newPath)) {
                echo "  ⚠️  Target file already exists, skipping\n";
            } else {
                if (rename($videoFile, $newPath)) {
                    echo "  ✅ Success\n";
                    $matched++;
                } else {
                    echo "  ❌ Failed\n";
                }
            }
        } else {
            echo "  🔍 Dry run - no changes made\n";
            $matched++;
        }
        echo "\n";
    } else {
        $unmatched[] = $basename;
    }
}

// Summary
echo "=====================================\n";
echo "Summary:\n";
echo "  Matched: $matched files\n";
echo "  Unmatched: " . count($unmatched) . " files\n";

if (!empty($unmatched)) {
    echo "\nUnmatched files:\n";
    foreach ($unmatched as $file) {
        echo "  - $file\n";
    }
    echo "\nTip: Check if metadata files exist for these videos\n";
}

if ($dryRun) {
    echo "\n⚠️  This was a dry run. Use without --dry-run to apply changes.\n";
}