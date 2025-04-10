<?php

namespace app\libraries;

/**
 * Class FileUtils
 *
 * Contains various useful functions for the polling system.
 */
class PollUtils {
    /**
     * Generates a PHP array containing the all the poll data used in the export procedure
     * @param \app\entities\poll\Poll[] $polls the array of polls to be summarized
     */
    public static function getPollExportData(array $polls): array {
        $export_data = [];
        foreach ($polls as $poll) {
            $poll_data = [
                'id' => $poll->getId(),
                'name' => $poll->getName(),
                'question' => $poll->getQuestion(),
                'question_type' => $poll->getQuestionType(),
                'responses' => [],
                'correct_responses' => [],
                'duration' => $poll->getDuration()->format('P%yY%mM%dDT%hH%iM%sS'),
                'end_time' => $poll->getEndTime()?->format('Y-m-d'),
                'release_date' => $poll->getReleaseDate()->format('Y-m-d'),
                'release_histogram' => $poll->getReleaseHistogram(),
                'release_answer' => $poll->getReleaseAnswer(),
                'image_path' => $poll->getImagePath(),
                'allows_custom' => $poll->getAllowsCustomResponses()
            ];
            foreach ($poll->getOptions() as $option) {
                $poll_data['responses'][$option->getOrderId()] = $option->getResponse();
                if ($option->isCorrect()) {
                    $poll_data['correct_responses'][] = $option->getOrderId();
                }
            }
            $export_data[] = $poll_data;
        }
        return $export_data;
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

    public static function getReleaseHistogramSettings(): array {
        return [
            "never",
            "when_ended",
            "always"
        ];
    }

    public static function getReleaseAnswerSettings(): array {
        return [
            "never",
            "when_ended",
            "always"
        ];
    }
}
