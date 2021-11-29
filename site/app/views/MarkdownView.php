<?php

namespace app\views;

class MarkdownView extends AbstractView {

    public function renderMarkdown($content) {
        $this->core->getOutput()->disableRender();
        return $this->core->getOutput()->renderTwigTemplate("misc/Markdown.twig", [
                "content" => $content
        ]);
    }

    public function renderMarkdownArea($data) {
        $this->core->getOutput()->disableRender();
        $args = [];
        $keys = [
            'class',
            'data_previous_comment',
            'initialize_preview',
            'markdown_area_id',
            'markdown_area_name',
            'markdown_area_value',
            'markdown_header_id',
            'max_height',
            'min_height',
            'no_maxlength',
            'onclick',
            'other_textarea_attributes',
            'placeholder',
            'preview_div_id',
            'render_header',
            'root_class',
            'textarea_maxlength',
            'textarea_onchange',
            'textarea_onkeydown',
            'textarea_onpaste',
        ];
        foreach ($keys as $key) {
            if (isset($data[$key])) {
                $args[$key] = $data[$key];
            }
        }
        return $this->core->getOutput()->renderTwigTemplate("misc/MarkdownArea.twig", $args);
    }
}
