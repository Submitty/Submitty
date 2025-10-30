<?php

namespace app\controllers;

use app\libraries\response\WebResponse;
use app\views\MarkdownView;
use Symfony\Component\Routing\Annotation\Route;

class MarkdownController extends AbstractController {
    /**
     * @return WebResponse
     */
    #[Route("/markdown", methods: ["POST"])]
    public function displayMarkdown() {
        return new WebResponse(MarkdownView::class, 'renderMarkdown', $_POST['content']);
    }
}
