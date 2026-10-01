<?php

namespace Tests\Unit;

use Illuminate\Support\Arr;
use PHPUnit\Framework\TestCase;

/**
 * Chaque fichier de lang/fr existe dans lang/en (et inversement) avec
 * exactement les mêmes clés et les mêmes paramètres (:name, :amount…).
 * Une clé oubliée dans une langue fait échouer la CI.
 */
class TranslationParityTest extends TestCase
{
    private const LOCALES = ['fr', 'en'];

    public function test_all_locales_have_the_same_files_keys_and_placeholders(): void
    {
        $base = dirname(__DIR__, 2) . '/lang';
        $files = [];
        foreach (self::LOCALES as $locale) {
            $files[$locale] = array_map('basename', glob("$base/$locale/*.php"));
            sort($files[$locale]);
        }
        $this->assertSame($files['fr'], $files['en'], 'Les fichiers de traduction diffèrent entre fr et en.');

        foreach ($files['fr'] as $file) {
            $fr = Arr::dot(require "$base/fr/$file");
            $en = Arr::dot(require "$base/en/$file");

            $this->assertSame([], array_values(array_diff(array_keys($fr), array_keys($en))), "Clés absentes de en/$file");
            $this->assertSame([], array_values(array_diff(array_keys($en), array_keys($fr))), "Clés absentes de fr/$file");

            foreach ($fr as $key => $value) {
                if (!is_string($value) || !is_string($en[$key] ?? null)) {
                    continue;
                }
                $this->assertSame(
                    $this->placeholders($value),
                    $this->placeholders($en[$key]),
                    "Paramètres différents pour $file:$key"
                );
            }
        }
    }

    private function placeholders(string $text): array
    {
        preg_match_all('/:([a-z_]+)/i', $text, $matches);
        $names = array_unique(array_map('strtolower', $matches[1]));
        sort($names);

        return $names;
    }
}
