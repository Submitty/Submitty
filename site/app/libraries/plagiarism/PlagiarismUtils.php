<?php

namespace app\libraries\plagiarism;

use Ds\Stack;

class PlagiarismUtils {
    /**
     * @return Interval[]
     */
    public static function constructIntervals($filename): array {
        $content = file_get_contents($filename);
        $arr = json_decode($content, true);
        $resultArray = [];
        foreach ($arr as $match) {
            $interval = new Interval($match['start'], $match['end'], $match['type']);

            // common code and provided code don't have an "others" array
            if (isset($match['others'])) {
                foreach ($match['others'] as $o) {
                    $interval->addUser(new Submission(
                        $o['username'],
                        $o['version'],
                        $o['matchingpositions'],
                        $match['start'],
                        $match['end']
                    ));
                }
            }

            $resultArray[] = $interval;
        }
        usort($resultArray, function (Interval $a, Interval $b) {
            return $a->getStart() > $b->getStart();
        });
        return $resultArray;
    }

    /**
     * @param Interval[] $intervalArray
     */
    public static function mergeIntervals(array $intervalArray): Stack {
        $stack = new Stack();
        $stack->push($intervalArray[0]);
        for ($i = 1; $i < count($intervalArray); $i++) {
            $current = $stack->peek();
            if ($current->getEnd() < $intervalArray[$i]->getStart()) {
                $stack->push($intervalArray[$i]);
            }
            elseif ($current->getEnd() < $intervalArray[$i]->getEnd()) {
                $current->updateEnd($intervalArray[$i]->getEnd());
                foreach ($intervalArray[$i]->getUsers() as $user) {
                    $current->addUser($user);
                }
                $stack->pop();
                $stack->push($current);
            }
        }
        return $stack;
    }
}
