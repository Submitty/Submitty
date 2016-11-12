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
	</body>
</html>
HTML;
?>