<?php

namespace app\views\admin;

use app\libraries\DateUtils;
use app\views\AbstractView;
use app\libraries\FileUtils;

class NotebookBuilderView extends AbstractView {
    public function previewNotebookMarkdown($enablePreview, $content) {
        $this->core->getOutput()->disableRender();
        if (!$enablePreview) {
            return;
        }
        return $this->core->getOutput()->renderTwigTemplate("generic/Markdown.twig", [
                "content" => $content
        ]);
    }
}
