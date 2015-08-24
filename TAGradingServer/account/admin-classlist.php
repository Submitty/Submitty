<?php
include "../header.php";

if($user_is_administrator)
{
    $account_subpages_unlock = true;
    echo <<<HTML

    <div id="container" style="width:100%; margin-top:40px;">
        <div class="modal hide fade in" tabindex="-1" role="dialog" aria-labelledby="classlist" aria-hidden="false" style="display: block; margin-top:5%; z-index:100;">
            <form action="{$BASE_URL}/account/submit/admin-classlist.php" method="post" enctype="multipart/form-data">
                <div class="modal-header">
                    <h3 id="myModalLabel">Upload Classlist</h3>
                </div>

                <div class="modal-body" style="padding-top:20px; padding-bottom:20px;">
                    <input type="file" name="classlist" id="classlist">
                </div>

                <div class="modal-footer">
                    <div style="width:50%; float:right; margin-top:5px;">
                        <input class="btn btn-primary" type="submit" value="Upload Classlist" />
                    </div>
                </div>
            </form>
        </div>
    </div>
HTML;

    include "../footer.php";
}
?> 
        
        