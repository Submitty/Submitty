<?php 
?>

<div class="col-md-10 col-sm-10 blog-main">
    <div class="blog-header">
            <div class="panel panel-default">
                <!-- Default panel contents -->
                <div class="panel-body">
                    <h2>Grade summary</h2>
                    <p>The cutoffs are not exact and are used for final grade cutoffs or something like that...</p>
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
                            <th>Lab</th>
                            <th>Homework</th>
                            <th>Tests</th>
                            <th>Final</th>
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
                        foreach ($scale as $ar) {
                            ?>
                        <tr>
                            <td><?php echo $ar["username"]; ?></td>
                            <td><?php echo $ar["section"]; ?></td>
                            <td><?php echo $ar["last"]; ?></td>
                            <td><?php echo $ar["first"]; ?></td>
                            <td><?php echo $ar["overall"]; ?></td>
                            <td><?php echo $ar["lab"]; ?></td>
                            <td><?php echo $ar["homework"]; ?></td>
                            <td><?php echo $ar["tests"]; ?></td>
                            <td><?php echo $ar["final"]; ?></td>
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
                        $count = 0;
                        foreach ($lab_scales as $lab_scale) {?>
                            <tr>
                                <td><?php echo $scale[$count]["username"]; ?></td>
                                <?php foreach($lab_scale as $score) {?>
                                    <td><?php echo $score; ?></td>
                                <?php }
                                for ($i = 0; $i < $num_labs - count($labs); $i++) {?>
                                    <td></td>
                                <?php } ?>

                            </tr>
                        <?php 
                            $count++;
                        } ?>
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
                        $count = 0;
                        foreach ($homework_scales as $homework_scale) {?>
                            <tr>
                                <td><?php echo $scale[$count]["username"]; ?></td>
                                <?php foreach($homework_scale as $score) {?>
                                    <td><?php echo $score; ?></td>
                                <?php }
                                for ($i = 0; $i < $num_homeworks - count($homework_scale); $i++) {?>
                                    <td></td>
                                <?php } ?>

                            </tr>
                        <?php 
                            $count++;
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
                        $count = 0;
                        foreach ($test_scales as $test_scale) {?>
                            <tr>
                                <td><?php echo $scale[$count]["username"]; ?></td>
                                <?php foreach($test_scale as $score) {?>
                                    <td><?php echo $score; ?></td>
                                <?php }
                                for ($i = 0; $i < $num_tests - count($test_scale); $i++) {?>
                                    <td></td>
                                <?php } ?>

                            </tr>
                        <?php 
                            $count++;
                        } ?>
                    </tbody>
                </table>
                </div>
            </div>
    </div>
</div>
