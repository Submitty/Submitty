<?php

namespace app\views;

use app\models\User;
use app\models\gradeable\Gradeable;
use app\views\ErrorView;

class LeaderboardView extends AbstractView {
    public function showLeaderboardPage(Gradeable $gradeable, array $leaderboards, bool $user_is_anonymous, string $leaderboard_tag, string $gradeable_id): string {
        $this->core->getOutput()->addBreadcrumb($gradeable->getTitle(), $this->core->buildCourseUrl(["gradeable", $gradeable_id]));

        if (
            !$this->core->getUser()->accessGrading()
            && (
                !$gradeable->isSubmissionOpen()
                || !$gradeable->isStudentView()
                || $gradeable->isStudentViewAfterGrades()
                && !$gradeable->isTaGradeReleased()
            )
        ) {
            return new WebResponse(ErrorView::class, "noGradeable", $gradeable_id);
        }


        $this->core->getOutput()->addBreadcrumb("Leaderboard");
        $this->core->getOutput()->addInternalCss('leaderboard.css');
        return $this->core->getOutput()->renderTwigTemplate('submission/homework/leaderboard/Leaderboard.twig', [
            "gradeable_name" => $gradeable->getTitle(),
            "leaderboards" => $leaderboards,
            "studentIsAnonymous" => $user_is_anonymous,
            "initial_leaderboard_tag" => $leaderboard_tag,
            "base_url" => $this->core->buildCourseUrl(["gradeable", $gradeable_id])
        ]);
    }

    public function showLeaderboardTable(array $leaderboard_data, int $top_visible_students, string $user_id, int $user_index, string $description, string $user_is_anonymous): string {
        // Remove the extra submitty html as this route is just for getting the html for the leaderboard
        $this->core->getOutput()->useHeader(false);
        $this->core->getOutput()->useFooter(false);

        return $this->core->getOutput()->renderTwigTemplate('submission/homework/leaderboard/LeaderboardTable.twig', [
            "leaderboard" => $leaderboard_data,
            "accessFullGrading" => $this->core->getUser()->accessFullGrading(),
            "top_visible_students" => $top_visible_students,
            "user_id" => $user_id,
            "user_index" => $user_index,
            "description" => $description,
            "user_name" => $this->core->getUser()->getDisplayedGivenName() . " " . $this->core->getUser()->getDisplayedFamilyName(),
            "studentIsAnonymous" => $user_is_anonymous,
            "grader_value" => User::GROUP_LIMITED_ACCESS_GRADER
        ]);
    }
}
