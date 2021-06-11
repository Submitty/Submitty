<?php

namespace app\libraries\plagiarism;

use Ds\Stack;

class PlagiarismUtils {
    /**
     * @param string $filename
     * @param string $user_id_2
     * @param int $version_user_2
     * @return array
     */
    public static function constructIntervalsForUserPair(string $filename, string $user_id_2, int $version_user_2): array {
        $content = file_get_contents($filename);
        $content = json_decode($content, true);

        $resultArray = [];
        foreach ($content as $match) {
            $interval = new Interval($match['start'], $match['end'], $match['type']);

            // loop through, checking to see if this is a specific match between the two users
            if ($match['type'] === "match" && $user_id_2 != "") {
                foreach ($match['others'] as $other) {
                    if ($other["username"] === $user_id_2 && $other["version"] === $version_user_2) {
                        $interval->updateType("specific-match");
                        foreach ($other["matchingpositions"] as $mp) {
                            $interval->addOther($user_id_2, $version_user_2, $mp["start"], $mp["end"]);
                        }
                        // this user+version pair will only every occur once so we break
                        break;
                    }
                }
            }

            // append interval to result array
            $resultArray[] = $interval;
        }

        // sort array before we merge
        usort($resultArray, function (Interval $a, Interval $b) {
            return $a->getStart() > $b->getStart();
        });

        // merge regions if possible
        for ($i = 1; $i < count($resultArray); $i++) {
            if ($resultArray[$i]->getType() !== $resultArray[$i - 1]->getType() ||
                $resultArray[$i]->getStart() > $resultArray[$i - 1]->getEnd()) {
                continue;
            }

            // check to make sure the matchingpositions arrays are the same, merge if so
            $matchingPosCanBeMerged = true;
            $difference = $resultArray[$i]->getEnd() - $resultArray[$i - 1]->getEnd();

            // if there is no user 2, there are no matching positions
            if ($user_id_2 != "") {
                $prevOthers = $resultArray[$i - 1]->getOthers();
                $currOthers = $resultArray[$i]->getOthers();

                if (count($currOthers) !== count($prevOthers)) {
                    continue;
                }

                for ($j = 0; $j < count($prevOthers[$user_id_2 . "_" . $version_user_2]["matchingpositions"]); $j++) {
                    if (intval($currOthers[$user_id_2 . "_" . $version_user_2]["matchingpositions"][$j]["end"]) !== intval($prevOthers[$user_id_2 . "_" . $version_user_2]["matchingpositions"][$j]["end"]) - $difference) {
                        // we cannot merge these two regions so move on
                        $matchingPosCanBeMerged = false;
                        break;
                    }
                }
            }
            if ($matchingPosCanBeMerged) {
                $resultArray[$i - 1]->updateEnd($resultArray[$i]->getEnd());

                if ($user_id_2 != "") {
                    $resultArray[$i - 1]->updateOthersEndPositions($user_id_2, $version_user_2, $difference);
                }

                // delete next interval
                array_splice($resultArray, $i, 1);

                // we merged these two so we have to check the newly merged interval against the next one
                $i--;
            }
        }

        return $resultArray;
    }
}
