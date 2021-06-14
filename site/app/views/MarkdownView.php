<?php

namespace app\views;

class MarkdownView extends AbstractView {

    public function renderMarkdownPreview($enablePreview, $content) {
        $this->core->getOutput()->disableRender();
        if (!$enablePreview) {
            return;
        }
        return $this->core->getOutput()->renderTwigTemplate("misc/Markdown.twig", [
                "content" => $content
        ]);
    }

    public function renderMarkdownArea($data) {
        $this->core->getOutput()->disableRender();
        return $this->core->getOutput()->renderTwigTemplate("misc/MarkdownArea.twig", [
            "markdown_area_id"    => $data['markdown_area_id'],
            "markdown_area_value" => $data['markdown_area_value'],
            "placeholder"         => $data['placeholder'],
            "preview_div_id"      => $data['preview_div_id'],
            "preview_div_name"    => $data['preview_div_name'],
            "preview_button_id"   => $data['preview_button_id'],
            "onclick"             => $data['onclick'],
            "render_buttons"      => $data['render_buttons'],
            "min_height"          => $data['min_height']
        ]);
    }
}