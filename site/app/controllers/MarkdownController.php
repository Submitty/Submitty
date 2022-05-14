<?php

namespace app\controllers;

use app\libraries\response\WebResponse;
use app\views\MarkdownView;
use Symfony\Component\Routing\Annotation\Route;

class MarkdownController extends AbstractController {
    /**
     * @Route("/markdown", methods={"POST"})
     * @return WebResponse
     */
    public function displayMarkdown() {
        return new WebResponse(MarkdownView::class, 'renderMarkdown', $_POST['content']);
    }

    /**
     * @Route("/markdown/area", methods={"POST"})
     * @return WebResponse
     */
    public function displayMarkdownArea() {
        return new WebResponse(MarkdownView::class, 'renderMarkdownArea', $_POST['data']);
    }
}
