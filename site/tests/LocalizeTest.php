<?php

namespace tests;

use app\libraries\FileUtils;

class LocalizeTest extends \PHPUnit\Framework\TestCase {
    private function readBaseData() {
        $data = FileUtils::readJsonFile(FileUtils::joinPaths(__DIR__, "/../lang/en_US.json"));
        $this->assertIsArray($data, "Failed to read base lang file.");
        return $data;
    }

    private function buildDataFromTemplates() {
        $files = FileUtils::getAllFiles(FileUtils::joinPaths(__DIR__, "/../app/templates"), [], true);

        $expression = '/localize\s*?\(\s*?(?<q1>[\'"])(?<key>[\w\.]+?)\s*?\k<q1>,\s*?(?<q2>[\'"])(?<val>.+?)(?<!\\\\)\k<q2>\s*?.*?\)/';

        $data = [];

        foreach ($files as $file) {
            if (!str_ends_with($file['name'], ".twig")) {
                return;
            }

            $body = file_get_contents($file['path']);

            $blocks = array_map(fn ($s) => explode('}}', $s)[0], array_slice(explode('{{', $body), 1));

            foreach ($blocks as $block) {
                preg_match_all($expression, $block, $matches, PREG_SET_ORDER);

                foreach ($matches as $match) {
                    $parts = explode('.', $match['key']);
                    $last_part = array_pop($parts);
                    $loc = &$data;
                    foreach ($parts as $part) {
                        if (isset($loc[$part])) {
                            $this->assertIsArray($loc[$part], "Conflicting key '" . $match['key'] . "' found in " . $file['name']);
                            $loc = &$loc[$part];
                        }
                        else {
                            $loc[$part] = [];
                            $loc = &$loc[$part];
                        }
                    }
                    if (isset($loc[$part])) {
                        $this->assertEquals($loc[$part], $match['val'], "Conflicting key '" . $match['key'] . "' found in " . $file['name']);
                    }
                    else {
                        $loc[$last_part] = $match['val'];
                    }
                }
            }
        }

        return $data;
    }

    public function testTemplates() {
        $base_data = $this->readBaseData();
        $data = $this->buildDataFromTemplates();

        ksort($base_data);
        ksort($data);

        $this->assertEquals($base_data, $data);
    }
}
