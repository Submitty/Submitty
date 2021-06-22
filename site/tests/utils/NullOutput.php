<?php

declare(strict_types=1);

namespace tests\utils;

use app\libraries\Core;
use app\libraries\Output;

class NullOutput extends Output {
    private $twig_output = [];

    public function loadTwig($full_load = true) {
    }

    public function renderOutput($view, string $function, ...$args) {
    }

    public function renderTemplate($view, string $function, ...$args) {
        return null;
    }

    public function renderString($string) {
    }

    public function renderFile($contents, $filename, $filetype = "text/plain") {
        $this->useFooter(false);
        $this->useHeader(false);
        $this->output_buffer = $contents;
    }

    public function renderTwigTemplate(string $filename, array $context = []): string {
        $this->twigOutput[] = [$filename, $context];
        return '';
    }

    public function renderTwigOutput(string $filename, array $context = []): void {
        $this->renderTwigTemplate($filename, $context);
    }

    protected function renderHeader() {
    }

    protected function renderFooter() {
    }

    public function bufferOutput() {
        return $this->buffer_output;
    }

    public function showException($exception = "", $die = true) {
    }

    public function showError($error = "", $die = true) {
    }

    public function addRoomTemplatesTwigPath() {
    }

    public function getTwigOutput(): array {
        return $this->twig_output;
    }
}
