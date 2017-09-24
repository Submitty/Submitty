<?php
    // RUN: php test_darrin_308.php > test_darrin_308.html
?>
<!DOCTYPE html>
<html>
    <body>

        <!-- COPY ME -->

        <div id="config-student" style="display:none;">
            <?php include 'test/template_darrin_308.config.student.parhaj.json'; ?>
        </div>
        <div id="config-room"    style="display:none;">
            <?php include 'darrin_308/template_darrin_308.config.room.json'; ?>
        </div>
        <?php include 'darrin_308/template_darrin_308.definition.html' ?>

        <!-- ! COPY ME -->

    </body>
</html>