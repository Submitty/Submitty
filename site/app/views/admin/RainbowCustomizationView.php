<?php
namespace app\views\admin;

use app\views\AbstractView;

class RainbowCustomizationView extends AbstractView{
    public function printForm($customization_data){
        $return = "";
        $return .= <<<HTML
<script src="js/Sortable.js"></script>

<script type="text/javascript">
    function ExtractBuckets(){
        var x = new Array();
        var bucket_list = $("#buckets_used_list li");
        bucket_list.each(function(idx,li){
            x.push($(li).text());
        })        
        
        //$("#generate_json").val(x.toString());
        $("#generate_json").val(JSON.stringify(x));
        $("#custom_form").submit();
        return true;
    }
    
    function BalanceLists(){
        var max_h = Math.max($("#buckets_used").height(),$("#buckets_available").height());
        $("#list_wrapper").height(max_h);
        console.log("Fired balance lists");
    }
    
    $(document).ready(function(){
        BalanceLists();
    })
    
    //$("#buckets_used_list").change(function(){BalanceLists();});
    //$("#buckets_available_list").change(function(){BalanceLists();});
</script>

<div class="content">
Form would be printed here. Right now the data received is:<br />
<pre>
HTML;
        $return .= print_r($customization_data,true);
        $return .= <<< HTML
</pre>
<br />
If you'd like to try submitting something...
<form id="custom_form" method="post" action="">
<input type="hidden" id="generate_json" name="generate_json" value="true" />
Fake text box: <input type="text" name="demo_text" value="" /><br />
HTML;
        $return .= <<< HTML
<input type="submit" name="generate_json2" value="Submit" onclick="ExtractBuckets();"/>
</form>
<div id="list_wrapper">
<!--<div id="buckets_used">-->
<div style="width50%;float:left" id="buckets_used">
<h3>Assigned Buckets</h3>
<ol id="buckets_used_list" style="min-height:50px">
</ol>
</div>
HTML;

        $return .= <<< HTML
<!--<div style="float:right;position: relative; right: 50px;" id="buckets_available">-->
<div style="width:50%;float:right;" id="buckets_available">
<h3>Available Buckets</h3>
<ol id="buckets_available_list" style="min-height:50px">
HTML;
        foreach(array_keys($customization_data) as $bucket){
            $return .= "<li>$bucket</li>";
        }
        $return .= <<< HTML
</ol>
</div>
</div>
</div>

<style type="text/css">
#buckets li{
    font-weight: bold;
}
</style>

<script type="text/javascript">
    var el_available = document.getElementById('buckets_available_list');
    var sortable_available = Sortable.create(el_available,{group:"bucket_group",onSort:function (evt){BalanceLists();}});
    var el_used = document.getElementById('buckets_used_list');
    var sortable_used = Sortable.create(el_used,{group:"bucket_group",onSort:function (evt){BalanceLists();}});
</script>

HTML;
        return $return;
    }

    public function printCompletedCustomization($filename){
        $return = "";
        $return .= <<<HTML
<div class="content">
Success message would be printed here, and the file transfered.
HTML;
        return $return;
    }

    public function printError($error_messages){
        //TODO: This should eventually be scrapped in favor of reprinting the form with the input it was given, maybe marking erroneous parts in red.
        //TODO: Could use $_SESSION instead of outright printing an error, depends on if we want to report multiple problems at once.
        $return = "";
        $return .= <<<HTML
<div class="content">
The following errors occurred while processing your input:
HTML;

        assert(is_array($error_messages) && count($error_messages)>0);
        foreach($error_messages as $error){
            $return .= "<p>$error</p>";
        }

        $return .= '</div>';

        return $return;

    }
}