<?php

namespace app\models;

use app\libraries\Core;
use app\libraries\FileUtils;

/**
 * Class Locale
 *
 * Class to manage localization data for the language used
 * on the site frontend.
 */
class Locale extends AbstractModel {
    /** @prop
     * @var string */
    private string $name;

    /** @prop
     * @var array<mixed> */
    private array $lang_data = [];

    public function __construct(Core $core, string $lang_path, string $name) {
        parent::__construct($core);

        $this->name = $name;

        $lang_file = FileUtils::joinPaths($lang_path, $name . ".php");
        if (is_file($lang_file)) {
            $lang_data = include $lang_file;
            if (gettype($lang_data) === "array") {
                $this->lang_data = $lang_data;
            }
        }
    }

    public function getName(): string {
        return $this->name;
    }

    /** @return array<mixed> */
    public function getLangData(): array {
        return $this->lang_data;
    }

    /**
     * @param array<string> $vals
     */
    public function fetchKey(string $key, array $vals = []): ?string {
        preg_match_all('/\w+/', $key, $parts, PREG_PATTERN_ORDER);

        $val = $this->lang_data;

        foreach ($parts[0] as $part) {
            if (gettype($val) !== "array") {
                break;
            }

            $val = $val[$part] ?? null;
        }

        if (gettype($val) !== "string") {
            return null;
        }

        return msgfmt_format_message($this->name, $val, $vals);
    }
}
