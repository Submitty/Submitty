<?php

namespace app\controllers;

use app\libraries\response\WebResponse;
use app\views\MarkdownView;
use Symfony\Component\Routing\Annotation\Route;

class MarkdownController extends AbstractController {

    /**
     * @Route("/courses/{_semester}/{_course}/markdown/preview", methods={"POST"})
     * @return WebResponse
     */
    public function displayMarkdownPreview() {
        return new WebResponse(MarkdownView::class, 'renderMarkdownPreview', $_POST['enablePreview'], $_POST['content']);
    }

    /**
     * @Route("/markdown", methods={"POST"})
     * @return WebResponse
     */
    public function displayMarkdown() {
        return new WebResponse(MarkdownView::class, 'renderMarkdownPreview', true, $_POST['content']);
    }

    /**
     * @Route("/courses/{_semester}/{_course}/markdown/area", methods={"POST"})
     * @return WebResponse
     */
    public function displayMarkdownArea() {
        return new WebResponse(MarkdownView::class, 'renderMarkdownArea', $_POST['data']);
    }
}
