<?php ?>
 <?php if ($assignment_message != "") { echo "<p><b><em><font size=+1 color=green>".$assignment_message."</font></b></p>"; } ?>
        <!-- DETAILS ON INDIVIDUAL TESTS -->

      <div class="row" style="margin-left: 10px; margin-right: 10px">
        <div class="box2" style="border-radius: 3px;    padding: 0px;    border: 1px solid #cccccc;    height: 100%;  width: 100%;   margin: 5px; position: relative; float: left;    background:rgba(255,255,255,0.8);">
            <div>
                  <h4 style="margin-left: 10px; text-align: left;display:inline-block;">
                        Total
                  </h4>
                  <span class="badge">
                        <?php echo $viewing_version_score." / ".$points_visible;?>
                  </span>
            </div>
        </div>

        <?php $counter = 0;
        foreach($homework_tests as $test) {?>
        <br clear="all">

        <div class="box2" style="border-radius: 3px;    padding: 0px;    border: 1px solid #cccccc;    height: 100%;  width: 100%;   margin: 5px; position: relative; float: left;    background:rgba(255,255,255,0.8);">
          <?php //score, points, and points possible are set.  Is not hidden and is not extra credit
            if (  isset($test["score"]) &&
                  isset($test["points_possible"]) &&
                  $test["points_possible"] != 0 &&
                  $test["is_hidden"] == false &&
                  $test["is_extra_credit"] == false ) {


                    if (!($test["points_possible"] > 0)) {
                       $part_percent = 1;
                    } else {
                       $part_percent = $test["score"] / $test["points_possible"];
                    }
                    if ($part_percent == 1) {
                       $class = "badge green-background";
                    } else if ($part_percent >= 0.5) {
                       $class = "badge yellow-background";
                    } else {
                       $class = "badge red-background";
                    }
              } else {
                    $class = "badge";
              } ?>

        <div>
          <h4 style="margin-left: 10px; text-align: left;display:inline-block;">
            <?php echo $test["title"];?>
            <?php if (isset ($test["details"])) { if ($test["details"] != "") { echo " <tt>".$test["details"]."</tt>"; } } ?>
          </h4>
          <!-- BADGE TEST SCORE -->
          <span class="<?php echo $class;?>">
            <?php
                if ($test["is_hidden"] === true) {?>
                    Hidden Test Case
                    </span>
                    </div><!-- End div -->
                    </div><!-- End Box2 -->
                    <?php continue;
                }?>
                <?php echo $test["score"]." / ".$test["points_possible"];
                if ($test["is_extra_credit"] === true) {
                    echo " Extra Credit";
                }
            ?>
          </span>


          <?php if (/* (isset($test["diff"]) && $test["diff"] != "") || */
                 (isset($test["diffs"]) && count($test["diffs"]) > 0) ||
                     (isset($test["compilation_output"]))
                   ) {?>

              <span>
                <a href="#" onclick="return toggleDiv('sidebysidediff<?php echo $counter;?>');">Details</a>
              </span>
          <?php }?>
          <?php if ($test["message"] != "") {?>
          <!--<div>-->
          <!--<span>&nbsp;&nbsp;--><em><?php echo $test["message"]; ?></em><!--</span>-->
          <!--</div>-->
          <?php } ?>
        </div>
        <div id="sidebysidediff<?php echo $counter;?>" style="display:none">

          <?php if (isset($test["compilation_output"])){?>
              <b>Compilation output:</b>
              <pre><?php echo $test["compilation_output"]; ?></pre>
          <?php }?>

          <!-- MULTIPLE DIFFS -->
          <table border="0">
          <?php
         if (isset($test["diffs"])) {

         foreach ($test["diffs"] as $diff) {
              if (   isset($diff["student"])      &&
                 !isset($diff["instructor"])  &&
                     !isset($diff["description"]) &&
                     !isset($diff["message"])
                 ) {
                  continue;
              } ?>
              <div style="margin-left:20px;">
                  <?php if (0) /*isset($diff["description"]))*/ {?>
                      <b><?php echo $diff["description"];?></b>
                      <br />
                       <?php }
                  ?>

                  <?php if (isset($diff["message"])) {?>
                      <tr><td><em><?php echo $diff["message"]; ?></em></td></tr>
                  <?php }?>
              </div>
              <?php if (!isset($diff["student"]) && !isset($diff["instructor"])) {
                    continue;
                }
                if (isset($diff["instructor"])) {
                    $instructor_row_class = "diff-row";
                } else {
                    $instructor_row_class = "diff-row-none";
                }
              ?>
                    <tr>
                        <td class="diff-row">
                            <div style="margin-left: 20px;"><b>Student <?php if (isset($diff["description"])) { echo $diff["description"]; } ?></b></div>
                        </td>
                        <td class="<?php echo $instructor_row_class;?>">
                            <div style="margin-left: 20px;"><b>Expected <?php if (isset($diff["description"])) { echo $diff["description"]; } ?></b></div>
                        </td>
                    </tr>
                    <tr>
    <?php /* EXTRA NEWLINES & SPACES HERE CAUSE MISFORMATTING  in the diffviewer  */ ?>
                        <td class="diff-row">
                            <div style="margin-left: 20px;" class="panel panel-default" id="<?php echo $diff["diff_id"]; ?>_student">
    <?php if (isset($diff["student"])) { echo str_replace(" ", "&nbsp;", $diff["student"]); } else { echo ""; }?></div>
                        </td>
                        <td class="<?php echo $instructor_row_class;?>">
                             <div class="panel panel-default" id="<?php echo $diff["diff_id"]; ?>_instructor">
    <?php if (isset($diff["instructor"])) { echo str_replace(" ", "&nbsp;", $diff["instructor"]); } else { echo ""; }?></div>
                        </td>
                    </tr>
                <script>
                    diff_queue.push("<?php echo $diff["diff_id"]; ?>");
                    diff_objects["<?php echo $diff["diff_id"]; ?>"] = <?php echo $diff["difference"]; ?>;
                </script>
            <?php } } ?><!-- first one ends foreach diff in diffs. Second ends if is set of diff -->
            <!-- END MULTIPLE DIFFS -->

    </table>
    </div><!-- end sidebysidediff# -->
      <?php $counter++;?>
   </div><!-- end box2 -->
   <?php }?><!-- end foreach homework_tests as test-->
