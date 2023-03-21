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
    private $lang;

    /** @prop @var array */
    private $lang_data;

    public function __construct(Core $core, string $lang) {
        parent::__construct($core);

        $this->lang = $lang;

        $lang_path = $core->getConfig()->getLangPath();
        if (!$lang_path) return;

        $lang_data = FileUtils::readJsonFile(FileUtils::joinPaths($lang_path, $lang + ".json"));
        if ($lang_data) {
            $this->lang_data = $lang_data;
            $core->lang = $lang_data;
        }
    }

    public function getLang(): string {
        return $this->lang;
    }

    public function getLangData(): array {
        return $this->lang_data;
    }
}
