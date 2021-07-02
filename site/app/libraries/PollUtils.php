<?php

namespace app\libraries;

use app\libraries\Core;
use app\libraries\FileUtils;

/**
 * Class FileUtils
 *
 * Contains various useful functions for the polling system.
 */
class PollUtils {
    /**
     * Generates a PHP array containing the all the poll data used in the export procedure
     * @param array $polls the array of polls to be summarized
     */
    public static function getPollExportData(array $polls): array {
        $data = [];
        foreach ($polls as $poll) {
            $responses = [];
            foreach ($poll->getResponses() as $response) {
                $responses[$response] = $poll->getResponseString($response);
            }
            $data[] = [
                "id" => $poll->getId(),
                "name" => $poll->getName(),
                "question" => $poll->getQuestion(),
                "question_type" => $poll->getQuestionType(),
                "responses" => $responses,
                "correct_responses" => $poll->getAnswers(),
                "release_date" => $poll->getReleaseDate(),
                "status" => $poll->getStatus(),
                "image_path" => $poll->getImagePath()
            ];
        }
        return $data;
    }

    public static function getPollTypes(): array {
        return [
            "single-response-single-correct",
            "single-response-multiple-correct",
            "single-response-survey",
            "multiple-response-exact",
            "multiple-response-flexible",
            "multiple-response-survey"
        ];
    }

    public static function isSingleResponse(string $poll_type): bool {
        return (($poll_type == "single-response-single-correct")
                || ($poll_type == "single-response-multiple-correct")
                || ($poll_type == "single-response-survey"));
    }
}
