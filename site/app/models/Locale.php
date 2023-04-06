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
    /** @prop @var string */
    private $name;

    /** @prop @var array */
    private $lang_data = [];

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

    public function getLangData(): array {
        return $this->lang_data;
    }
}
