<?php
/**
 * IQS FRAMEWORK CORE FILE
 *
 * READ ONLY for downstream project programmers.
 * Do not modify this file directly in template or cloned projects.
 * Custom changes must be implemented in project-specific files
 * or approved extension points.
 */// Semak jika fail gambar wujud dan valid
function is_valid_image($path) {
    return file_exists($path) && exif_imagetype($path) !== false;
}

// Dapatkan dimensi gambar
function get_image_dimensions($path) {
    return file_exists($path) ? getimagesize($path) : [0, 0];
}

// Dapatkan saiz fail (dalam KB)
function get_file_size_kb($path) {
    return file_exists($path) ? round(filesize($path) / 1024, 2) : 0;
}
