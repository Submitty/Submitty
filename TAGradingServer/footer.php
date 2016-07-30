	<footer>

	</footer>
    <script>
        function toggle() {
            if (document.getElementById('page-info').style.visibility == 'visible') {
                document.getElementById('page-info').style.visibility = 'hidden';
            }
            else {
                document.getElementById('page-info').style.visibility = 'visible';
            }
        }
    </script>
<?php
    if ($DEVELOPER) {
        $total_time = microtime_float()-$start_time;
        echo <<<HTML
        <div id='page-info'>
            Total Queries: {$db->totalQueries()}<br />
            Runtime: {$total_time}<br />
            Queries: <br /> {$db->getQueries()}
        </div>
HTML;

    }
    echo <<<HTML
    <!-- JQuery -->
    <script>
        $(document).ready(function() {

            $('a').each(function() {
                if ($(this).attr('href') == undefined) {
                    return;
                }
                if ($(this).attr('href') == "{$BASE_URL}") {
                    $(this).attr('href', $(this).attr('href') + '/index.php?course={$_GET['course']}&semester={$_GET['semester']}');
                }
                else if ($(this).attr('href').indexOf("{$BASE_URL}") > -1) {
                    if ($(this).attr('href').substr(-4) == '.php' || $(this).attr('href').substr(-4) == '.cgi') {
                        $(this).attr('href', $(this).attr('href') + '?course={$_GET['course']}&semester={$_GET['semester']}');
                    }
                    else {
                        $(this).attr('href', $(this).attr('href') + '&course={$_GET['course']}&semester={$_GET['semester']}');
                    }
                }
            });

            $('form').each(function() {
                if ($(this).attr('action') == undefined) {
                    return;
                }
                if ($(this).attr('method').toLowerCase() == 'get') {
                    $(this).append("<input type='hidden' name='course' value='{$_GET['course']}' />");
                    $(this).append("<input type='hidden' name='semester' value='{$_GET['semester']}' />");
                }
                else if ($(this).attr('method').toLowerCase() == 'post') {
                    if ($(this).attr('action').substr(-4) == '.php') {
                        $(this).attr('action', $(this).attr('action') + '?course={$_GET['course']}&semester={$_GET['semester']}');
                    }
                    else {
                        $(this).attr('action', $(this).attr('action') + '&course={$_GET['course']}&semester={$_GET['semester']}');
                    }
                }
            });

        });
    </script>
	</body>
</html>
HTML;
?>