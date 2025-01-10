<?php

namespace app\libraries\plagiarism;

class PlagiarismUtils {
    /**
     * This constant represents the default configuration for each language supported.  Currently, only the
     * hash size and language name are listed but more parameters can be added in the future.
     * @var int
     */
    public const SUPPORTED_LANGUAGES = [
        "plaintext" => [
            "hash_size" => 14
        ],
        "python" => [
            "hash_size" => 14
        ],
        "java" => [
            "hash_size" => 14
        ],
        "cpp" => [
            "hash_size" => 14
        ],
        "mips" => [
            "hash_size" => 5
        ]
    ];

    /**
     * This constant represents the default common code threshold listed on the plagiarism configuration form
     * @var int
     */
    public const DEFAULT_THRESHOLD = 10;

    /**
     * @param string $filename
     * @param string $user_id_2
     * @param int $version_user_2
     * @param string $source_gradeable_user_2
     * @return Interval[]
     */
    public static function constructIntervalsForUserPair(string $filename, string $user_id_2, int $version_user_2, string $source_gradeable_user_2): array {
        $content = file_get_contents($filename);
        $content = json_decode($content, true);

        $resultArray = [];
        foreach ($content as $match) {
            $interval = new Interval($match['start'], $match['end'], $match['type']);

            // loop through, checking to see if this is a specific match between the two users
            if ($match['type'] === "match" && $user_id_2 !== "") {
                foreach ($match['others'] as $other) {
                    if ($other["username"] === $user_id_2 && $other["version"] === $version_user_2 && $source_gradeable_user_2 === $other["source_gradeable"]) {
                        $interval->updateType("specific-match");
                        foreach ($other["matchingpositions"] as $mp) {
                            $interval->addOther($user_id_2, $version_user_2, $source_gradeable_user_2, $mp["start"], $mp["end"]);
                        }
                    }
                    else {
                        $interval->addOther($other["username"], $other["version"], $other["source_gradeable"]);
                    }
                }
            }

            // append interval to result array
            $resultArray[] = $interval;
        }

        // sort array before returning
        usort($resultArray, function (Interval $a, Interval $b) {
            return $a->getStart() - $b->getStart();
        });

        // prevent overlapping regions on the UI
        if (count($resultArray) > 1) {
            for ($i = 1; $i < count($resultArray); $i++) {
                if ($resultArray[$i - 1]->getEnd() >= $resultArray[$i]->getStart()) {
                    $resultArray[$i - 1]->updateEnd($resultArray[$i]->getStart() - 1);
                }
            }
        }

        return $resultArray;
    }
}
