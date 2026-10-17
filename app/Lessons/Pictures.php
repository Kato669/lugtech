<?php

namespace App\Lessons;

/**
 * Built-in pictures a lesson can show without uploading images. Drawn in the
 * browser (resources/js/pictures.js); keep the two lists in step.
 */
class Pictures
{
    public const NAMES = [
        'cowrie', 'matooke', 'mango', 'egg', 'cup', 'ball', 'drum', 'star', 'house', 'person',
        // Phase 6: time of day, people, the house, food, market goods, drinks
        'sun', 'moon',
        'man', 'woman', 'child',
        'door', 'window', 'chair', 'table', 'bed',
        'beans', 'rice', 'meat', 'sweet-potato', 'cassava',
        'orange', 'tomato', 'onion', 'groundnut', 'bag',
        'water', 'milk', 'juice',
    ];

    public const MAX_COUNT = 10;

    /** @return string[] */
    public static function validate(mixed $picture): array
    {
        $p = self::normalise($picture);
        if (! $p || ! in_array($p['name'], self::NAMES, true)) {
            return ['picture must be one of: '.implode(', ', self::NAMES)];
        }
        if ($p['count'] < 1 || $p['count'] > self::MAX_COUNT) {
            return ['picture count must be 1 to '.self::MAX_COUNT];
        }

        return [];
    }

    /** "cowrie" or { name: "cowrie", count: 3 } → { name, count } */
    public static function normalise(mixed $picture): ?array
    {
        if (is_string($picture)) {
            return ['name' => $picture, 'count' => 1];
        }
        if (is_array($picture) && isset($picture['name'])) {
            return ['name' => (string) $picture['name'], 'count' => (int) ($picture['count'] ?? 1)];
        }

        return null;
    }
}
