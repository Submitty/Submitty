<?php

namespace app\views;

use app\libraries\FileUtils;
use app\views\Date;

class StatsView extends AbstractView {

    /**
     * @param $file
     * @param int $whatstat 0 for wait time, 1 for run time
     * @param array $contains
     * @param array $notcontains
     * @return array
     */

    private function convertFileToArray($file, $whatstat = 0, $contains = array(), $notcontains = array()) {
        $lines = explode(PHP_EOL, $file);
        $array = array();
        $notcontains[] = "zip";
        $notcontains[] = "generated_output";
        foreach ($lines as $line) {
            $next = $line;
            foreach ($contains as $contain) {
                $contain = "/".$contain."/";
                if (gettype($next) != "String" && $next == array()) {
                    continue;
                }
                $next = preg_grep($contain, array($next));
                foreach ($next as $k => $v) {
                    $next = $v;
                    break;
                }
            }
            foreach ($notcontains as $contain) {
                $contain = "/".$contain."/";
                if (gettype($next) != "String" && $next == array()) {
                    continue;
                }
                $next = preg_grep($contain,array($next),PREG_GREP_INVERT);
                foreach ($next as $k=>$v) {
                    $next = $v;
                    break;
                }
            }
            if (gettype($next) != "String" && $next == array()) {
                continue;
            }

            $next = str_getcsv($next,"|");
            $date = $next[0];
            if ($whatstat == 0) {
                $next = preg_grep("/wait/",$next);
            } else if ($whatstat == 1) {
                $next = preg_grep("/grade/",$next);
            }
            foreach ($next as $k=>$v) {
                $next = explode(" ", $v);
                foreach ($next as $value) {
                    if ($value != 0) {
                        $next = $value;
                        break;
                    }
                }
            }
            if (gettype($next) != "String" && $next == array()) {
                continue;
            }
            $next = floatval($next);
            if($next != 0)
                $array[] = array($date,$next);

        }
        return $array;
    }

    private function formatdate($date) {
        return $date->format('Ymd') . ".txt";
    }

    private function limitDataByThisSemester($maxdays = 9999, $type = 0, $whiltelist = array(), $blacklist = array()) {
        $currentdate = new \DateTime('now', $this->core->getConfig()->getTimezone());
        $semester = ($currentdate->format('n') < 7 ? "s" : "f") . $currentdate->format('y');
        $newsem = $semester;
        $y = array();
        $x = array();
        $hour = null;
        while ($semester == $newsem) {
            if (file_exists("/var/local/submitty/logs/autograding/".$this->formatdate($currentdate))) {
                $file = file_get_contents("/var/local/submitty/logs/autograding/" . $this->formatdate($currentdate));
                $tmpy = $this->convertFileToArray($file, $type, array_merge($whiltelist, array($semester)), $blacklist);
                $houravg = $this->groupbyhour($tmpy);
                $y = array_merge($houravg[1], $y);
                $x = array_merge($houravg[0], $x);
                if ($hour == null) {
                    $hour = $tmpy;
                }
            }

            $currentdate->modify("-1 day");
            $newsem = ($currentdate->format('n') < 7 ? "s" : "f") . $currentdate->format('y');

        }
        return array($x,$y,$hour);
    }

    private function groupbyhour($array) {
        $res = array();
        $time_values = array();
        if (count($array) == 0) {

            return array(array(),array());
        }
        for ($i = 0;$i < 24;++$i) {
            $sum = 0;
            $count = 0;
            $time = null;
            $time = new \DateTime($array[0][0]);
            for ($j = 0;$j < count($array);++$j) {
                if (preg_match_all("/ ".$i.":/",$array[$j][0]) != 0 ||
                    preg_match_all("/ 0".$i.":/",$array[$j][0]) != 0) {

                    $sum += $array[$j][1];
                    ++$count;

                }
            }
            $time->setTime($i,0);
            $time_values[] = $time->format('m-d-Y G:00');
            if ($count ==0) {
                $res[] = 0;
                continue;
            }


            $res[] = floatval($sum)/floatval($count);
        }
        return array($time_values,$res);



    }


    public function showStats() {

        $files = scandir("/var/local/submitty/logs/autograding");
        $gradetimesall = $this->limitDataByThisSemester(9999, 1);
        $waittimesall = $this->limitDataByThisSemester();
        $gradetimesme = $this->limitDataByThisSemester(9999, 1, array($this->core->getUser()->getId()), array("BATCH"));
        $waittimesme = $this->limitDataByThisSemester(9999, 0, array($this->core->getUser()->getId()), array("BATCH"));

        $currentdate = new \DateTime('now', $this->core->getConfig()->getTimezone());

        $this->core->getOutput()->addInternalCss('admin-gradeable.css');
        $this->core->getOutput()->addVendorJs(FileUtils::joinPaths('plotly', 'plotly.js'));
        return $this->core->getOutput()->renderTwigTemplate("Stats.twig", [
            'test'=> $this->formatdate($currentdate),
            'files'=> $files,
            'waittimeally'=> $waittimesall[1],
            "waittimeallx"=> $waittimesall[0],
            'gradetimeally'=> $gradetimesall[1],
            "gradetimeallx"=> $gradetimesall[0],
            'waittimemey'=> $waittimesme[1],
            "waittimemex"=> $waittimesme[0],
            'gradetimemey'=> $gradetimesme[1],
            "gradetimemex"=> $gradetimesme[0],
            "hour"=> [
                $gradetimesall[2],
                $waittimesall[2],
                $gradetimesme[2],
                $waittimesme[2],


            ],


            "today"=>$currentdate->format("m-d-Y G:00"),
        ]);
    }
}
