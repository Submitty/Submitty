<?php

namespace tests\app\templates;

use app\libraries\FileUtils;
use app\libraries\Utils;

/**
 * Checks that the localize() calls in the Twig templates agree with each other.
 *
 * The base en_US.json lives in the Submitty/Localization repository and is regenerated
 * from these calls on each release (bin/update-version.py). A conflicting call there
 * either raises an error or silently replaces another string, so catch it here instead.
 */
class LocalizeTester extends \PHPUnit\Framework\TestCase {
    private const LOCALIZE_PATTERN = '/localize\s*?\(\s*?(?<q1>[\'"])(?<key>[\w\.]+?)\s*?\k<q1>,\s*?(?<q2>[\'"])(?<val>.+?)(?<!\\\\)\k<q2>\s*?.*?\)/';

    private ?string $path = null;

    public function tearDown(): void {
        if ($this->path !== null && file_exists($this->path)) {
            $this->assertTrue(FileUtils::recursiveRmdir($this->path), "Could not clean up {$this->path}");
        }
    }

    /**
     * Collect every localize() call in the Twig templates under $dir.
     *
     * @return array{0: array<string, mixed>, 1: string[]} the nested key => default string tree, and any conflicts found
     */
    private function readTemplateStrings(string $dir): array {
        $data = [];
        $conflicts = [];

        foreach (FileUtils::getAllFiles($dir, [], true) as $file) {
            if (!str_ends_with($file['name'], ".twig")) {
                continue;
            }

            $body = file_get_contents($file['path']);

            $blocks = array_map(fn($s) => explode('}}', $s)[0], array_slice(explode('{{', $body), 1));

            foreach ($blocks as $block) {
                preg_match_all(self::LOCALIZE_PATTERN, $block, $matches, PREG_SET_ORDER);

                foreach ($matches as $match) {
                    $key = $match['key'];
                    $parts = explode('.', $key);
                    $last_part = array_pop($parts);
                    $loc = &$data;
                    foreach ($parts as $part) {
                        if (!isset($loc[$part])) {
                            $loc[$part] = [];
                        }
                        elseif (!is_array($loc[$part])) {
                            $conflicts[] = "'{$key}' in {$file['name']}: '{$part}' is already a string, not a group of keys";
                            continue 2;
                        }
                        $loc = &$loc[$part];
                    }

                    if (!isset($loc[$last_part])) {
                        $loc[$last_part] = $match['val'];
                    }
                    elseif (is_array($loc[$last_part])) {
                        $conflicts[] = "'{$key}' in {$file['name']}: already a group of keys, not a string";
                    }
                    elseif ($loc[$last_part] !== $match['val']) {
                        $conflicts[] = "'{$key}' in {$file['name']}: default \"{$match['val']}\" differs from \"{$loc[$last_part]}\"";
                    }
                }
            }
        }

        return [$data, $conflicts];
    }

    public function testTemplateStringsAreConsistent(): void {
        [$data, $conflicts] = $this->readTemplateStrings(FileUtils::joinPaths(__DIR__, '..', '..', '..', 'app', 'templates'));

        $this->assertNotEmpty($data, "No localize() calls were found in the templates");
        $this->assertSame([], $conflicts);
    }

    public function testConflictingStringsAreReported(): void {
        $this->path = FileUtils::joinPaths(sys_get_temp_dir(), Utils::generateRandomString());
        $this->assertTrue(FileUtils::createDir(FileUtils::joinPaths($this->path, 'sub'), true));

        FileUtils::writeFile(FileUtils::joinPaths($this->path, 'Page.twig'), <<<'TWIG'
            <h1>{{ localize('Page.Title', "Title") }}</h1>
            <h2>{{ localize('Page.Title', "Title") }}</h2>
            <h3>{{ localize('Page.Title', "Other title") }}</h3>
            <h4>{{ localize('Page.Title.Sub', "Sub") }}</h4>
            <h5>{{ localize('Page', "Page") }}</h5>
            TWIG);
        FileUtils::writeFile(FileUtils::joinPaths($this->path, 'sub', 'Name.twig'), "{{ localize('Page.Name', \"Name\") }}");
        FileUtils::writeFile(FileUtils::joinPaths($this->path, 'notes.txt'), "{{ localize('Ignored.Key', \"Ignored\") }}");

        [$data, $conflicts] = $this->readTemplateStrings($this->path);

        $this->assertEquals(['Page' => ['Title' => 'Title', 'Name' => 'Name']], $data);
        $this->assertSame([
            "'Page.Title' in Page.twig: default \"Other title\" differs from \"Title\"",
            "'Page.Title.Sub' in Page.twig: 'Title' is already a string, not a group of keys",
            "'Page' in Page.twig: already a group of keys, not a string",
        ], $conflicts);
    }
}
