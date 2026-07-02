function reportAttendance(viewName)
{
    // Call to report attendance detaill
    $("#form" + viewName).attr("target", "_blank");
    $("#form" + viewName + " :input[name=\"action\"]").val('export');
    $("#form" + viewName + " :input[name=\"activetab\"]").val('ReportAttendance');    
    $("#form" + viewName).append('<input type="hidden" name="option" value="show"/>');
    $("#form" + viewName).submit();

    // Restore old values
    $("#form" + viewName).attr("target", "");
    $("#form" + viewName + " :input[name=\"action\"]").val('');
    $("#form" + viewName + " :input[name=\"activetab\"]").val(viewName);    
}
