<?php

namespace app\views\admin;

use app\views\AbstractView;
//TODO: add this as a general view in the future..
class EmailRoomSeatingView extends AbstractView {
    public function displayPage($defaultSubject, $defaultBody) {
        return $this->core->getOutput()->renderTwigTemplate("admin/EmailRoomSeating.twig", [
            "defaultSubject" => $defaultSubject,
            "defaultBody" => $defaultBody
        ]);
    }
}
