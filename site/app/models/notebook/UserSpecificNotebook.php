<?php

namespace app\models\notebook;

use app\libraries\Core;
use app\libraries\Utils;
use app\libraries\FileUtils;
use app\models\AbstractModel;

/**
 * Class UserSpecificNotebook
 * @package app\models\notebook
 *
 * @method array getNotebookConfig()
 * @method array getTestCases()
 * @method array getHashes()
 * @method array getSelectedQuestions()
 * @method void setNotebookConfig($new_notebook)
 * @method bool getWarning()
 */

class UserSpecificNotebook extends AbstractModel {

    /** @prop @var array array of items where the notebook selects from */
    protected $item_pool = [];
    /** @prop @var array notebook config */
    protected $notebook_config;
    /** @prop @var array testcases config */
    protected $test_cases = [];
    /** @prop @var array hashes generated for student's notebook */
    protected $hashes = [];
    /** @prop @var array of item_pool names selected */
    protected $selected_questions = [];
    /** @prop @var string warning if this notebook has potentially overlapping questions picked */
    protected $warning = null;

    private $gradeable_id;
    private $user_id;

    public function __construct(Core $core, array $details, string $gradeable_id, string $user_id) {

        parent::__construct($core);

        $tgt_dir = FileUtils::joinPaths(
            $this->core->getConfig()->getCoursePath(),
            "config/complete_config",
            "complete_config_" . $gradeable_id . ".json"
        );
        $json = FileUtils::readJsonFile($tgt_dir);

        if ($json !== false && isset($json['item_pool'])) {
            $this->item_pool = $json['item_pool'];
        }

        $this->gradeable_id = $gradeable_id;
        $this->user_id = $user_id;

        $this->notebook_config = $this->replaceNotebookItemsWithQuestions($details);
    }


    /** Collect items from a notebook and replace them with the actual notebook values
    */
    private function replaceNotebookItemsWithQuestions(array $raw_notebook): array {
        $new_notebook = [];
        $seen_items = [];
        $tests = [];

        foreach ($raw_notebook['notebook'] as $notebook_cell) {
            if (isset($notebook_cell['type']) && $notebook_cell['type'] === 'item') {
                //see if theres a target item pool and replace this with the actual notebook
                $tgt_item = $this->getItemFromPool($notebook_cell);

                $item_data = $this->searchForItemPool($tgt_item);
                if (count($item_data['notebook']) > 0) {
                    $new_notebook = array_merge($new_notebook, $item_data['notebook']);
                    $tests = array_merge($tests, $item_data['testcases']);
                }

                //record if we potentially grabbed the same question
                $seen_items = array_merge($seen_items, $notebook_cell['from_pool']);
            }
            else {
                $new_notebook[] = $notebook_cell;
            }
        }
        $this->test_cases = $tests;
        $multiple_selected = [];
        foreach (array_count_values($seen_items) as $seen => $value) {
            if ($value > 1) {
                $multiple_selected[] = $seen;
            }
        }

        if (count($multiple_selected) > 0) {
            $seen = implode($multiple_selected, ",");
            $this->warning = "Warning: The item_pool \"{$seen}\" has been selected multiple times, this could lead to notebooks with the same question given more than once.";
        }

        return $new_notebook;
    }


    private function getItemFromPool(array $item): string {
        $item_label = $item['item_label'];
        $selected = $this->getNotebookHash($item_label, count($item['from_pool']));
        $item_from_pool = $item['from_pool'][$selected];
        $this->selected_questions[] = $item_from_pool;

        return $item_from_pool;
    }

    private function getNotebookHash(string $item_label, int $from_pool_count): int {
    
        $gid = $this->gradeable_id;
        $uid = $this->user_id;

        $semester = $this->core->getConfig()->getSemester();
        $course = $this->core->getConfig()->getCourse();
        
        $hash = hexdec(substr(md5("{$item_label}|{$gid}|{$uid}|{$semester}|{$course}"), 24, 8));

        $selected = $hash % $from_pool_count;
        $this->hashes[] = $hash;

        return $selected;
    }


    private function searchForItemPool(string $tgt_name): array {
        $ret = ["notebook" => [], "testcases" => []];
        foreach ($this->item_pool as $item) {
            if ($item['item_name'] === $tgt_name) {
                $ret["notebook"] = array_merge($ret["notebook"], $item["notebook"]);
                $ret["testcases"] = array_merge($ret["testcases"], $item["testcases"]);
            }
        }

        return $ret;
    }
}
