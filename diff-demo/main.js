var base_test = "3 copies of \"Into the Wild\" added\n2 copies of \"Forrest Gump\" added\n2 copies of \"Gone with the Wind\" added\n1 copy of \"Raiders of the Lost Ark\" added\n3 copies of \"Toy Story\" added\nnew customer: Carol Adams\nnew customer: Kim Smith\nnew customer: Wayne Evans\nShip DVDs\n  Carol Adams receives \"Raiders of the Lost Ark\"\n  Kim Smith receives \"Into the Wild\"\n  Carol Adams receives \"Gone with the Wind\"\n  Kim Smith receives \"Gone with the Wind\"\n  Carol Adams receives \"Forrest Gump\"\nCarol Adams returns \"Forrest Gump\"\nKim Smith returns \"Into the Wild\"\nShip DVDs\n  Carol Adams receives \"Into the Wild\"\nCarol Adams returns \"Into the Wild\"\nShip DVDs\n  Carol Adams receives \"Toy Story\"\nCarol Adams has 3 movies:\n    \"Raiders of the Lost Ark\"\n    \"Gone with the Wind\"\n    \"Toy Story\"\nWayne Evans has no movies\n  preference list:\n    \"Raiders of the Lost Ark\"\n\"Toy Story\":\n  1 copy checked out and 2 copies available\n\"Gone with the Wind\":\n  2 copies checked out\n";

window.onload = function(){
    initEvents();
};

function initEvents(){
    $("#assignment").click(function(){
        var pos = $("#assignment").offset();
        $("#dropdown").offset({
            top : pos.top + $("#assignment").height(),
            left : pos.left + $("#assignment").width() - $("#dropdown").width()
        });
        $("#dropdown").css({opacity:1});
        $("#dropdown").hover(null,function(e){
            $("#dropdown").css({opacity:0});
        },false)
        $(".dropdown-item").click(function(e){
            $("#dropdown").css({opacity:0});
            var assignment_name = $(this)[0].innerHTML;
            console.log(assignment_name);
            var id = $(this).attr("id");
            var num = parseInt(id[2])+1;
            $.get("tests/test" + num + ".txt",function(data){
                $.getJSON("tests/test" + num + ".json", function (json){
                    $("#assignment").text(assignment_name);
                    diff.load(data, base_test);
                    diff.evalDifferences(json.differences);
                    diff.display("diff0","diff1");
                });
            });
        });
    })
}
