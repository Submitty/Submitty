<?php

namespace app\views;

class MiscView extends AbstractView {
    public function displayFile($file_contents) {
        return <<<HTML
<pre>
{$file_contents}
</pre>
HTML;
    }

}