<?php

namespace app\models;

use app\libraries\Core;

class PDFGenerator extends AbstractModel {
    public function __construct(Core $core) {
        parent::__construct($core);
//        $pdf = new Fpdi();
//        $test = FileUtils::joinPaths($this->core->getConfig()->getCoursePath(), 'submissions/open_homework/instructor/6/upload.pdf');
//        $pageCount = $pdf->setSourceFile($test);
    }
}
