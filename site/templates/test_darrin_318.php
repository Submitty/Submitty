<?php
    // RUN: php test_darrin_318.php > test_darrin_318.html
?>
<!DOCTYPE html>
<html>
    <body>

        <!-- COPY ME -->

        <div id="config-student" style="display:none;">
            <?php include 'test/template_darrin_318.config.student.parhaj.json'; ?>
        </div>
        <div id="config-room"    style="display:none;">
            <?php include 'darrin_318/template_darrin_318.config.room.json'; ?>
        </div>
        <?php include 'darrin_318/template_darrin_318.definition.html' ?>

        <!-- ! COPY ME -->

    </body>
</html>