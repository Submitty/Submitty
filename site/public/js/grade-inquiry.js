function onComponentTabClicked(tab) {
  var component_id = tab.data("component_id");
  // deselect previous selected tab and select clicked tab
  if (tab.attr('id') === "component-tab-selected") {
    tab.removeAttr('id');
    component_id = null;
  } else {
    $("#component-tab-selected").removeAttr('id');
    tab.attr("id","component-tab-selected");
  }


  // show posts that pertain to this component_id
  $(".post_box").each(function(){
    if ($(this).data("component_id") !== component_id) {
      $(this).hide();
    } else {
      $(this).show();
    }
  });

  // change form's hidden gc_id parameter
  $("#gc_id").val(component_id);
}
