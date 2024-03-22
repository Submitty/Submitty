<?php

namespace app\views;

use app\models\User;
use app\models\gradeable\Gradeable;

class LeaderboardView extends AbstractView {
    public function showLeaderboardPage(Gradeable $gradeable, array $leaderboards, bool $user_is_anonymous, string $leaderboard_tag, string $gradeable_id): string {
        $this->core->getOutput()->addBreadcrumb($gradeable->getTitle(), $this->core->buildCourseUrl(["gradeable", $gradeable_id]));
        $this->core->getOutput()->addBreadcrumb("Leaderboard");
        $this->core->getOutput()->addInternalCss('leaderboard.css');
        return $this->core->getOutput()->renderTwigTemplate('submission/homework/leaderboard/Leaderboard.twig', [
            "gradeable_name" => $gradeable->getTitle(),
            "leaderboards" => $leaderboards,
            "studentIsAnonymous" => $user_is_anonymous,
            "initial_leaderboard_tag" => $leaderboard_tag,
            "base_url" => $this->core->buildCourseUrl(["gradeable", $gradeable_id])
            "scatter_plot_image" => $scatter_plot_image // Add scatter plot image to Twig context
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

public function showLeaderboardPageWithScatterPlot(Gradeable $gradeable, array $leaderboards, bool $user_is_anonymous, string $leaderboard_tag, string $gradeable_id, array $scatter_plot_data): string {
    // Prepare scatter plot data
    $scatter_plot_image = $this->generateScatterPlot($scatter_plot_data);

    // Add scatter plot data to Twig template context
    $twig_context = [
        "gradeable_name" => $gradeable->getTitle(),
        "leaderboards" => $leaderboards,
        "studentIsAnonymous" => $user_is_anonymous,
        "initial_leaderboard_tag" => $leaderboard_tag,
        "base_url" => $this->core->buildCourseUrl(["gradeable", $gradeable_id]),
        "scatter_plot_image" => $scatter_plot_image // Add scatter plot image to Twig context
    ];

    // Render Twig template with scatter plot
    return $this->core->getOutput()->renderTwigTemplate('submission/homework/leaderboard/LeaderboardWithScatterPlot.twig', $twig_context);
}

private function generateScatterPlot(array $scatter_plot_data): string {
    // Prepare data for scatter plot
    $linesOfCode = [];
    $grades = [];

    // Extract data from scatter plot data array
    foreach ($scatter_plot_data as $data_point) {
        $linesOfCode[] = $data_point['lines_of_code'];
        $grades[] = $data_point['grade'];
    }

    // Create a new graph
    $graph = new Graph(800, 600);

    // Set up the graph title and margins
    $graph->title->Set("Scatter Plot: Lines of Code vs Grade");
    $graph->SetMargin(50, 50, 50, 100);

    // Create a scatter plot
    $scatterplot = new ScatterPlot($grades, $linesOfCode);

    // Set the plot's title and axis labels
    $scatterplot->title->Set("Grades vs Lines of Code");
    $scatterplot->SetYTitle('Number of Lines of Code');
    $scatterplot->SetXTitle('Grade (0-100)');

    // Add the plot to the graph
    $graph->Add($scatterplot);

    // Generate the scatter plot image and save it to a file
    $plot_image_path = '/path/to/plot/image.png';
    $graph->Stroke($plot_image_path);

    // Return the URL of the scatter plot image
    return $plot_image_path;
}
