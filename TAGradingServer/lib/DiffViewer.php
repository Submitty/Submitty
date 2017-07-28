<?php

namespace lib;

/**
 * Class DiffViewer
 *
 * Given an expected, actual, and differences file,
 * will generate the display for them (in either
 * HTML or plain-text)
 */
class DiffViewer {
    /**
     * @var bool
     */
    private $display_actual = false;

    /**
     * @var array
     */
    private $actual = array();

    /**
     * @var bool
     */
    private $display_expected = false;

    /**
     * @var array
     */
    private $expected = array();

    /**
     * @var array
     */
    private $diff = array();

    /**
     * @var array
     */
    private $add = array();

    /**
     * @var string
     */
    private $expected_name;

    /**
     * @var string
     */
    private $actual_name;

    /**
     * @var array
     */
    private $link = array();

    /**
     * @var string
     */
    private $id = "id";

    /**
     * @param string $expected name of expected in difference file
     * @param string $actual name of actual in difference file
     */
    public function __construct($expected="expected", $actual="actual") {
        $this->expected_name = $expected;
        $this->actual_name = $actual;
    }

    /**
     * Reset the DiffViewer to its starting values.
     */
    public function reset() {
        $this->display_actual = false;
        $this->actual = array();
        $this->display_expected = false;
        $this->expected = array();
        $this->diff = array();
        $this->add = array();
        $this->link = array();
        $this->id = "id";
    }

    /**
     * Load the actual file, expected file, and diff json,
     * using them to populate the necessary arrays for
     * display them later back to the user
     *
     * @param $actual_file
     * @param $expected_file
     * @param $diff_file
     * @param $id_prepend
     *
     * @throws \Exception
     */
    public function load($actual_file, $expected_file, $diff_file, $id_prepend="id") {
        $this->reset();
        $this->id = rtrim($id_prepend,"_")."_";
        if (!file_exists($actual_file) && $actual_file != "") {
            throw new \Exception("'{$actual_file}' could not be found.");
        }
        else if ($actual_file != "") {
            $this->actual = explode("\n", file_get_contents($actual_file));
            $this->display_actual = true;
        }
        if (!file_exists($expected_file) && $expected_file != "") {
            throw new \Exception("'{$expected_file}' could not be found.");
        }
        else if ($expected_file != "") {
            $this->expected = explode("\n", file_get_contents($expected_file));
            $this->display_expected = true;
        }
        if (!file_exists($diff_file) && $diff_file != "") {
            throw new \Exception("'{$diff_file}' could not be found.");
        }
        else if ($diff_file != "") {
            $diff = json_decode(file_get_contents($diff_file), true);
        }

        /*
        $this->actual = ($actual_file != "") ? explode("\n", file_get_contents($actual_file)) : "";
        $this->expected = ($expected_file != "") ? explode("\n", file_get_contents($expected_file)) : "";
        $diff = ($diff_file != "") ? json_decode(file_get_contents($diff_file), true) : array();
        */

        $this->diff = array("expected" => array(), "actual" => array());
        $this->add = array("expected" => array(), "actual" => array());

        if (isset($diff['differences'])) {
            $diffs = $diff['differences'];
            /*
             * Types of things we need to worry about:
             * lines are highlighted
             * lines are highlighted with character sequence
             * need to insert lines into other diff while some lines are highlighted
             */
            foreach ($diffs as $diff) {
                $act_ins = 0;
                $exp_ins = 0;
                $act_start = $diff[$this->actual_name]['start'];
                $act_final = $act_start;
                if (isset($diff[$this->actual_name]['line'])) {
                    $act_ins = count($diff[$this->actual_name]['line']);
                    foreach ($diff[$this->actual_name]['line'] as $line) {
                        $line_num = $line['line_number'];
                        if (isset($line['char_number'])) {
                            $this->diff["actual"][$line_num] = $this->compress_range($line['char_number']);
                        } else {
                            $this->diff["actual"][$line_num] = array();
                        }
                        $act_final = $line_num;
                    }
                }

                $exp_start = $diff[$this->expected_name]['start'];
                $exp_final = $exp_start;
                if (isset($diff[$this->expected_name]['line'])) {
                    $exp_ins = count($diff[$this->expected_name]['line']);
                    foreach ($diff[$this->expected_name]['line'] as $line) {
                        $line_num = $line['line_number'];
                        if (isset($line['char_number'])) {
                            $this->diff["expected"][$line_num] = $this->compress_range($line['char_number']);
                        } else {
                            $this->diff["expected"][$line_num] = array();
                        }
                        $exp_final = $line_num;
                    }
                }

                $this->link["actual"][($act_start)] = (isset($this->link["actual"])) ? count($this->link["actual"]) : 0;
                $this->link["expected"][($exp_start)] = (isset($this->link["expected"])) ? count($this->link["expected"]) : 0;

                // Do we need to insert blank lines into actual?
                if ($act_ins < $exp_ins) {
                    $this->add["actual"][($act_final)] = $exp_ins - $act_ins;
                } // Or into expected?
                else if ($act_ins > $exp_ins) {
                    $this->add["expected"][($exp_final)] = $act_ins - $exp_ins;
                }
            }
        }
    }

    /**
     * Return the output HTML for the actual display
     *
     * @return string actual html
     */
    public function getDisplayActual() {
        if ($this->display_actual) {
            return $this->getDisplay($this->actual, "actual");
        }
        else {
            return "";
        }

    }

    /**
     * Return the HTML for the expected display
     *
     * @return string expected html
     */
    public function getDisplayExpected() {
        if ($this->display_expected) {
            return $this->getDisplay($this->expected, "expected");
        }
        else {
            return "";
        }
    }

    /**
     * Prints out the $lines parameter
     *
     * Prints out the actual codebox with diff view applied
     * using the $this->diff global based off which
     * type we're interested in
     *
     * @param array $lines array of strings (each line)
     * @param string $type which diff we use while printing
     *
     * @return string html to be displayed to user
     */
    private function getDisplay($lines, $type="expected") {
        $start = null;
        $html = "<div class='diff-container'><div class='diff-code'>\n";

        if (isset($this->add[$type]) && count($this->add[$type]) > 0) {
            if (array_key_exists(-1, $this->add[$type])) {
                $html .= "\t<div class='highlight' id='{$this->id}{$type}_{$this->link[$type][-1]}'>\n";
                for ($k = 0; $k < $this->add[$type][-1]; $k++) {
                    $html .= "\t<div class='row bad'><div class='empty_line'>&nbsp;</div></div>\n";
                }
                $html .= "\t</div>\n";
            }
        }

        /*
         * Run through every line, starting a highlight around any group of mismatched lines that exist (whether
         * there's a difference on that line or that the line doesn't exist.
         */
        for ($i = 0; $i < count($lines); $i++) {
            $j = $i + 1;
            if ($start === null && isset($this->diff[$type][$i])) {
                $start = $i;
                $html .= "\t<div class='highlight' id='{$this->id}{$type}_{$this->link[$type][$start]}'>\n";
            }
            if (isset($this->diff[$type][$i])) {
                $html .= "\t<div class='bad'>";
            }
            else {
                $html .= "\t<div>";
            }
            $html .= "<span class='line_number'>{$j}</span>";
            $html .= "<span class='line_code'>";
            if (isset($this->diff[$type][$i])) {
                // highlight the line
                $current = 0;
                // character highlighting
                foreach ($this->diff[$type][$i] as $diff) {
                    $html .= htmlentities(substr($lines[$i], $current, ($diff[0] - $current)));
                    $html .= "<span class='highlight-char'>".htmlentities(substr($lines[$i], $diff[0], ($diff[1] - $diff[0] + 1)))."</span>";
                    $current = $diff[1]+1;
                }
                $html .= "<span class='line_code_inner'>".htmlentities(substr($lines[$i], $current))."</span>";
            }
            else {
                if (isset($lines[$i])) {
                    $html .= htmlentities($lines[$i]);
                }
            }
            $html .= "</span></div>\n";

            if (isset($this->add[$type][$i])) {
                if ($start === null) {
                    $html .= "\t<div class='highlight' id='{$this->id}{$type}_{$this->link[$type][$i]}'>\n";
                }
                for ($k = 0; $k < $this->add[$type][$i]; $k++) {
                    $html .= "\t<div class='bad'><td class='empty_line' colspan='2'>&nbsp;</td></div>\n";
                }
                if ($start === null) {
                    $html .= "\t</div>\n";
                }
            }

            if ($start !== null && !isset($this->diff[$type][($i+1)])) {
                $start = null;
                $html .= "\t</div>\n";
            }
        }

        $html .= "</div></div>\n";
        return $html;
    }

    /**
     * Compress an array of numbers into ranges
     *
     * Given some array of numbers, it sorts the array, then condenses
     * adjacent numbers into a range.
     *
     * Ex: Given [0,1,2,5,6,9,100] -> [[0,2],[5,6],[9,9],[100,100]]
     *
     * @param array $range original flat array
     *
     * @return array A condensed array with ranges
     */
    private function compress_range($range) {
        sort($range);
        $range[] = -100;
        $last = -100;
        $return = array();
        $temp = array();
        foreach ($range as $number) {
            if ($number != $last+1) {
                if (count($temp) > 0) {
                    $return[] = array($temp[0], end($temp));
                    $temp = array();
                }
            }
            $temp[] = $number;
            $last = $number;
        }
        return $return;
    }

    /**
     * Returns true if there's an actual difference between actual and expected, else will
     * return false
     *
     * @return bool
     */
    public function exists_difference() {
        $return = false;
        foreach(array("expected", "actual") as $key) {
            if(count($this->diff[$key]) > 0 || count($this->add[$key]) > 0) {
                $return = true;
            }
        }
        return $return;
    }
}


// Test out diffs directly below
/*
ini_set('display_errors', 1);
error_reporting(E_ALL);
$diff = new DiffViewer();
$diff->load("../tests/data/diffs/diff_test_04/input_actual.txt",
            "../tests/data/diffs/diff_test_04/input_expected.txt",
            "../tests/data/diffs/diff_test_04/input_differences.json");
print <<<HTML
<html>
    <head>
        <title>DiffViewer Test</title>
        <script src="https://ajax.googleapis.com/ajax/libs/jquery/2.1.3/jquery.min.js"></script>
        {$diff->getCSS()}
        {$diff->getJavascript()}
    </head>
    <body>
HTML;

print "Actual&emsp;Expected<br />\n";
print $diff->getDisplayActual()."&emsp;&emsp;\n".$diff->getDisplayExpected();
print "<br />";
print "<br />";

print <<<HTML
    </body>
</html>
HTML;
*/
?>