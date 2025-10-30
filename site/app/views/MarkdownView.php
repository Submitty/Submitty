<?php

namespace app\views;

class MarkdownView extends AbstractView {
    public function renderMarkdown($content) {
        $this->core->getOutput()->disableRender();
        return $this->core->getOutput()->renderTwigTemplate("Vue.twig", [
            "type" => "component",
            "name" => "Markdown",
            "args" => [
                "content" => $content
            ]
        ]);
    }
}
