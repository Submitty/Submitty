<?php
namespace app\views;

class MarkdownView extends AbstractView {
    
    private function preprocessMarkdown($markdown) {
        $lines = explode("\n", $markdown);
        $inCodeBlock = false;
        $processedLines = [];

        foreach ($lines as $line) {
            if (strpos($line, '```') === 0) {
                $inCodeBlock = !$inCodeBlock;
                $processedLines[] = $line;
                continue;
            }

            if ($inCodeBlock) {
                $processedLines[] = $line;
                continue;
            }

            $processedLines[] = ltrim($line);
        }

        return implode("\n", $processedLines);
    }

    public function renderMarkdown($content) {
        $this->core->getOutput()->disableRender();
        // Preprocess the content before passing it to the Twig template
        $preprocessedContent = $this->preprocessMarkdown($content);
        return $this->core->getOutput()->renderTwigTemplate("misc/Markdown.twig", [
                "content" => $preprocessedContent
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
        
        // If there's a markdown_area_value, preprocess it
        if (isset($args['markdown_area_value'])) {
            $args['markdown_area_value'] = $this->preprocessMarkdown($args['markdown_area_value']);
        }
        
        return $this->core->getOutput()->renderTwigTemplate("misc/MarkdownArea.twig", $args);
    }
    
}
