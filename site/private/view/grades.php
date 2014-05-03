<?php 
?>

<div class="col-md-12 col-sm-12 blog-main">
    <div class="blog-header">
            <div class="panel panel-default">
                <!-- Default panel contents -->
                <div class="panel-body">
                    <h2>Grade summary</h2>
                    <p>Please contact your graduate lab TA if there is a missing or incorrect grade.</p>
                    <p>Last Updated: <?php echo $grades_last_updated;?></p>
                    <h3>Overall</h3>
                </div>
                <!-- Table -->
                <div style="overflow: auto">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Username</th>
                            <th>Section</th>
                            <th>Last</th>
                            <th>First</th>
                            <th>Overall</th>
                            <th>Lab %</th>
                            <th>Homework %</th>
                            <th>Tests %</th>
                            <th>Final %</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><?php echo $user_data["username"]; ?></td>
                            <td><?php echo $user_data["section"]; ?></td>
                            <td><?php echo $user_data["last"]; ?></td>
                            <td><?php echo $user_data["first"]; ?></td>
                            <td><?php echo $user_data["overall"]; ?></td>
                            <td><?php echo $user_data["lab"]; ?></td>
                            <td><?php echo $user_data["homework"]; ?></td>
                            <td><?php echo $user_data["tests"]; ?></td>
                            <td><?php echo $user_data["final"]; ?></td>
                        </tr>
                    </tbody>
                    <tbody>
                        <?php 
                        for ($i = 0; $i < count($scale); $i++) {
                            ?>
                        <?php if ($i == 0) {
                            $class = "success";
                        } else if ($i == 1) {
                            $class = "info";
                        } else if ($i == 2) {
                            $class = "warning";
                        } else if ($i == 3) {
                            $class = "danger";
                        } else if ($i == 4) {
                            $class = "red";
                        } else {
                            $class = "";
                        }?>
                        <tr class="<?php echo $class;?>">
                            <td><?php echo $scale[$i]["username"]; ?></td>
                            <td><?php echo $scale[$i]["section"]; ?></td>
                            <td><?php echo $scale[$i]["last"]; ?></td>
                            <td><?php echo $scale[$i]["first"]; ?></td>
                            <td><?php echo $scale[$i]["overall"]; ?></td>
                            <td><?php echo $scale[$i]["lab"]; ?></td>
                            <td><?php echo $scale[$i]["homework"]; ?></td>
                            <td><?php echo $scale[$i]["tests"]; ?></td>
                            <td><?php echo $scale[$i]["final"]; ?></td>
                        </tr>
                        <?php } ?>
                    </tbody>
                </table>
                </div>
                <div style="overflow: auto">
                <div class="panel-body">
                    <h3>Labs</h3>
                </div>
                <table class="table">
                    <thead>
                        <tr>
                            <th>Username</th>
                            <?php for ($i = 1; $i <= $num_labs; $i++) {?>
                                <th><?php echo $i; ?></th>
                            <?php } ?>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><?php echo $user_data["username"];?>
                            <?php foreach ($labs as $score) {?>
                                <td><?php echo $score; ?></td>
                            <?php }
                            for ($i = 0; $i < $num_labs - count($labs); $i++) {?>
                                <td></td>
                            <?php } ?>
                        </tr>
                    </tbody>
                    <tbody>
                        <?php
                        for ($i = 0; $i < count($lab_scales); $i++) {?>
                            <?php if ($i == 0) {
                            $class = "success";
                            } else if ($i == 1) {
                                $class = "info";
                            } else if ($i == 2) {
                                $class = "warning";
                            } else if ($i == 3) {
                                $class = "danger";
                            } else if ($i == 4) {
                                $class = "red";
                            } else {
                                $class = "";
                            }?>
                            <tr class="<?php echo $class;?>">
                                <td><?php echo $scale[$i]["username"]; ?></td>
                                <?php foreach($lab_scales[$i] as $score) {?>
                                    <td><?php echo $score; ?></td>
                                <?php }
                                for ($j = 0; $j < $num_labs - count($labs); $j++) {?>
                                    <td></td>
                                <?php } ?>

                            </tr>
                        <?php } ?>
                    </tbody>
                </table>
                </div>
                <div style="overflow: auto">
                <div class="panel-body">
                    <h3>Homeworks</h3>
                </div>
                <table class="table">
                    <thead>
                        <tr>
                            <th>Username</th>
                            <?php for ($i = 1; $i <= $num_homeworks; $i++) {?>
                                <th><?php echo $i; ?></th>
                            <?php } ?>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><?php echo $user_data["username"];?>
                            <?php foreach ($homeworks as $score) {?>
                                <td><?php echo $score; ?></td>
                            <?php }
                            for ($i = 0; $i < $num_homeworks - count($labs); $i++) {?>
                                <td></td>
                            <?php } ?>
                        </tr>
                    </tbody>
                    <tbody>
                        <?php
                        for ($i = 0; $i < count($homework_scales); $i++) {?>
                            <?php if ($i == 0) {
                                $class = "success";
                            } else if ($i == 1) {
                                $class = "info";
                            } else if ($i == 2) {
                                $class = "warning";
                            } else if ($i == 3) {
                                $class = "danger";
                            } else if ($i == 4) {
                                $class = "red";
                            } else {
                                $class = "";
                            }?>
                            <tr class="<?php echo $class;?>">
                                <td><?php echo $scale[$i]["username"]; ?></td>
                                <?php foreach($homework_scales[$i] as $score) {?>
                                    <td><?php echo $score; ?></td>
                                <?php }
                                for ($j = 0; $j < $num_homeworks - count($homework_scales[$i]); $j++) {?>
                                    <td></td>
                                <?php } ?>

                            </tr>
                        <?php 
                        } ?>
                    </tbody>
                </table>
                </div>
<div style="overflow: auto">
                <div class="panel-body"> 
                    <h3>Tests</h3>
                </div>
                <table class="table">
                    <thead>
                        <tr>
                            <th>Username</th>
                            <?php for ($i = 1; $i <= $num_tests; $i++) {?>
                                <th><?php echo $i; ?></th>
                            <?php } ?>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><?php echo $user_data["username"];?>
                            <?php foreach ($tests as $score) {?>
                                <td><?php echo $score; ?></td>
                            <?php }
                            for ($i = 0; $i < $num_tests - count($tests); $i++) {?>
                                <td></td>
                            <?php } ?>
                        </tr>
                    </tbody>
                    <tbody>
                        <?php
                        for ($i = 0; $i < count($test_scales); $i++) { ?>
                            <?php if ($i == 0) {
                                $class = "success";
                            } else if ($i == 1) {
                                $class = "info";
                            } else if ($i == 2) {
                                $class = "warning";
                            } else if ($i == 3) {
                                $class = "danger";
                            } else if ($i == 4) {
                                $class = "red";
                            } else {
                                $class = "";
                            }?>

                            <tr class="<?php echo $class;?>">
                                <td><?php echo $scale[$i]["username"]; ?></td>
                                <?php foreach($test_scales[$i] as $score) {?>
                                    <td><?php echo $score; ?></td>
                                <?php }
                                for ($j = 0; $j < $num_tests - count($test_scales[$i]); $j++) {?>
                                    <td></td>
                                <?php } ?>

                            </tr>
                        <?php 
                        } ?>
                    </tbody>
                </table>
                </div>
            </div>
    </div>
</div>
