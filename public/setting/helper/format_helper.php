<?php
/**
 * IQS FRAMEWORK CORE FILE
 *
 * READ ONLY for downstream project programmers.
 * Do not modify this file directly in template or cloned projects.
 * Custom changes must be implemented in project-specific files
 * or approved extension points.
 */function bulanToText(string $kodBulan, string $lang = 'ms'): string
{
    // ✅ Normalize input bulan (contoh: '7' atau ' 07 ') kepada '07'
    $kodBulan = str_pad(trim((string)(int)$kodBulan), 2, '0', STR_PAD_LEFT);

    $bulanMap = [
        'ms' => [
            '01' => 'Januari', '02' => 'Februari', '03' => 'Mac',
            '04' => 'April', '05' => 'Mei', '06' => 'Jun',
            '07' => 'Julai', '08' => 'Ogos', '09' => 'September',
            '10' => 'Oktober', '11' => 'November', '12' => 'Disember',
        ],
        'en' => [
            '01' => 'January', '02' => 'February', '03' => 'March',
            '04' => 'April', '05' => 'May', '06' => 'June',
            '07' => 'July', '08' => 'August', '09' => 'September',
            '10' => 'October', '11' => 'November', '12' => 'December',
        ],
        'zh' => [
            '01' => '一月', '02' => '二月', '03' => '三月',
            '04' => '四月', '05' => '五月', '06' => '六月',
            '07' => '七月', '08' => '八月', '09' => '九月',
            '10' => '十月', '11' => '十一月', '12' => '十二月',
        ],
        'ta' => [
            '01' => 'ஜனவரி', '02' => 'பிப்ரவரி', '03' => 'மார்ச்',
            '04' => 'ஏப்ரல்', '05' => 'மே', '06' => 'ஜூன்',
            '07' => 'ஜூலை', '08' => 'ஆகஸ்ட்', '09' => 'செப்டம்பர்',
            '10' => 'அக்டோபர்', '11' => 'நவம்பர்', '12' => 'டிசம்பர்',
        ],
    ];

    return $bulanMap[$lang][$kodBulan] ?? $kodBulan;
}
