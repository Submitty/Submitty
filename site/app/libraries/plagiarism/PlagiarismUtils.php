<?php

namespace app\libraries\plagiarism;

class PlagiarismUtils {

    public static function compareInterval($intervalOne, $intervalTwo) {
        return $intervalOne->getStart() > $intervalTwo->getStart();
    }

    public static function constructIntervals($filename) {
        $content = file_get_contents($filename);
        $arr = json_decode($content, true);
        $resultArray = [];
        foreach ($arr as $match) {
            $i = new Interval($match['start'], $match['end']);
            foreach ($match['others'] as $o) {
                $s = new Submission($o['username'], $o['version'], $o['matchingpositions'], $match['start'], $match['end']);
                $i->addUser($s);
            }
            $resultArray[] = $i;
        }
        usort($resultArray, "self::compareInterval");
        return $resultArray;
    }

    public static function mergeIntervals($iArr) {
        $stack = new Stack();
        $stack->push($iArr[0]);
        for ($i = 1; $i < count($iArr); $i++) {
            $cI = $stack->top();
            if ($cI->getEnd() < $iArr[$i]->getStart()) {
                $stack->push($iArr[$i]);
            }
            elseif ($cI->getEnd() < $iArr[$i]->getEnd()) {
                $cI->updateEnd($iArr[$i]->getEnd());
                foreach ($iArr[$i]->getUsers() as $u) {
                    $cI->addUser($u);
                }
                $stack->pop();
                $stack->push($cI);
            }
        }
        return $stack;
    }
}
