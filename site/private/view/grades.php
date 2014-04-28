<?php 
$section = 1;
$username = "sengs";
$last = "Seng";
$first = "Samuel";
$overall = 40.0;
$lab = 10.0;
$homework = 20.0;
$tests = 10.0;
$final = "";
//User overall data
$user_data = array("section"=>$section, "username"=>$username, "last"=>$last, "first"=>$first, "overall"=>$overall, "lab"=>$lab, "homework"=>$homework, "tests"=>$tests, "final"=>$final);
//Overall scales
$perfect = array("section"=>"", "username"=>"Perfect", "last"=>"", "first"=>"", "overall"=>70.9, "lab"=>20, "homework"=>20, "tests"=>20, "final"=>"");
$a = array("section"=>"", "username"=>"Lowest A-", "last"=>"approximate", "first"=>"", "overall"=>70.9, "lab"=>20, "homework"=>20, "tests"=>20, "final"=>"");
$b = array("section"=>"", "username"=>"Lowest B-", "last"=>"approximate", "first"=>"", "overall"=>70.9, "lab"=>20, "homework"=>20, "tests"=>20, "final"=>"");
$c = array("section"=>"", "username"=>"Lowest C-", "last"=>"approximate", "first"=>"", "overall"=>70.9, "lab"=>20, "homework"=>20, "tests"=>20, "final"=>"");
$d = array("section"=>"", "username"=>"Lowest D-", "last"=>"approximate", "first"=>"", "overall"=>70.9, "lab"=>20, "homework"=>20, "tests"=>20, "final"=>"");
$scale = array($perfect, $a, $b, $c, $d);
//User Lab data
$num_labs = 12;
$labs = array(3.0,3.0,3.0,3.0,3.0);
//Lab scales
$l1 = array(3.0,3.0,3.0,3.0,3.0);
$l2 = array(2.5,2.5,2.5,2.5,2.5);
$l3 = array(2.0,2.0,2.0,2.0,2.0);
$l4 = array(1.5,1.5,1.5,1.5,1.5);
$lab_scales = array($l1,$l2,$l3,$l4);
//User homework data
$num_homeworks = 10;
$homeworks = array(40,40,40,40,40);
//Homework scales
$l1 = array(50,50,50,50,50);
$l2 = array(45,45,45,45,45);
$l3 = array(40,40,40,40,40);
$l4 = array(35,35,35,35,35);
$homework_scales = array($l1,$l2,$l3,$l4);
//User Test data
$num_tests = 3;
$tests = array(90,90,90);
//Test scales
$t1 = array(100,100,100);
$t2 = array(80,80,80);
$t3 = array(60,60,60);
$t4 = array(40,40,40);
$test_scales = array($t1,$t2,$t3,$t4);

?>

<div class="col-md-10 col-sm-9 blog-main">
    <div class="blog-header">
        <h1 class="blog-title">Grades</h1>
            <div class="panel panel-default">
                <!-- Default panel contents -->
                <div class="panel-heading">Grade summary</div>
                <div class="panel-body">
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
