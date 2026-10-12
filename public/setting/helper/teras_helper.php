<?php
/**
 * IQS FRAMEWORK CORE FILE
 *
 * READ ONLY for downstream project programmers.
 * Do not modify this file directly in template or cloned projects.
 * Custom changes must be implemented in project-specific files
 * or approved extension points.
 *//**
 * Helper Functions for Teras Strategik Color Coding
 * 
 * This file provides centralized color mapping for teras strategik kod (TS-01, TS-02, etc.)
 * to ensure consistency across all pages displaying the kod.
 */

/**
 * Get color style for a given teras kod
 * 
 * @param string $kod The teras kod (e.g., 'TS-01', 'TS-02')
 * @return string Inline CSS style string
 */
function getTerasKodColorStyle(string $kod): string {
    $colors = [
        'TS-01' => 'background: rgba(59, 130, 246, 0.15); color: #2563eb;',  // Blue
        'TS-02' => 'background: rgba(16, 185, 129, 0.15); color: #059669;',  // Green
        'SP-01' => 'background: rgba(245, 158, 11, 0.15); color: #d97706;',  // Orange
        'TS-04' => 'background: rgba(139, 92, 246, 0.15); color: #7c3aed;',  // Purple
        'TS-03' => 'background: rgba(236, 72, 153, 0.15); color: #db2777;',  // Pink
        'TS-06' => 'background: rgba(14, 165, 233, 0.15); color: #0284c7;',  // Cyan
        'TS-07' => 'background: rgba(239, 68, 68, 0.15); color: #dc2626;',   // Red
        'TS-08' => 'background: rgba(132, 204, 22, 0.15); color: #65a30d;',  // Lime
    ];
    
    return $colors[$kod] ?? 'background: rgba(107, 114, 128, 0.15); color: #4b5563;'; // Default gray
}

/**
 * Get complete HTML badge for a given teras kod with color styling
 * 
 * @param string $kod The teras kod (e.g., 'TS-01', 'TS-02')
 * @param string $additionalClasses Additional CSS classes to add to the badge
 * @return string HTML markup for the colored badge
 */
function getTerasKodBadgeHtml(string $kod, string $additionalClasses = 'neo-badge'): string {
    $style = getTerasKodColorStyle($kod);
    $escapedKod = htmlspecialchars($kod, ENT_QUOTES, 'UTF-8');
    
    return "<span class=\"{$additionalClasses}\" style=\"{$style}\">{$escapedKod}</span>";
}
