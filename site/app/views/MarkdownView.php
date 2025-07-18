<?php

namespace app\views;

class MarkdownView extends AbstractView {
    public function renderMarkdown($content) {
        $this->core->getOutput()->disableRender();
        return $this->core->getOutput()->renderTwigTemplate("misc/Markdown.twig", [
                "content" => $content
        ]);
    }
}
