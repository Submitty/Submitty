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
            if ($match['type'] === "match" && $user_id_2 !== "") {
                foreach ($match['others'] as $other) {
                    if ($other["username"] === $user_id_2 && $other["version"] === $version_user_2) {
                        $interval->updateType("specific-match");
                        foreach ($other["matchingpositions"] as $mp) {
                            $interval->addOther($user_id_2, $version_user_2, $mp["start"], $mp["end"]);
                        }
                    }
                    else {
                        $interval->addOther($other["username"], $other["version"], -1, -1);
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

        return $resultArray;
    }

    /**
     * @return string[]
     */
    public static function getSupportedLanguages(): array {
        return ["plaintext", "python", "java", "cpp", "mips"];
    }
}
