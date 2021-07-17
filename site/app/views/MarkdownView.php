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
        $args = [];
        $keys = [
            'markdown_area_id',
            'markdown_area_name',
            'markdown_area_value',
            'class',
            'onclick',
            'markdown_buttons_id',
            'other_textarea_attributes',
            'render_buttons',
            'placeholder',
            'preview_div_id',
            'textarea_onchange',
            'textarea_onkeydown',
            'textarea_onpaste',
            'textarea_maxlength',
            'data_previous_comment',
            'min_height'
        ];
        foreach ($keys as $key) {
            if (isset($data[$key])) {
                $args[$key] = $data[$key];
            }
        }
        return $this->core->getOutput()->renderTwigTemplate("misc/MarkdownArea.twig", $args);
    }
}
