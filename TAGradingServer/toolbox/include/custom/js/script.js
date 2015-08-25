function parseScript(strcode) 
{
	var scripts = new Array();
	
	// Strip out tags
	while(strcode.indexOf("<script") > -1 || strcode.indexOf("</script") > -1) 
	{
		var s = strcode.indexOf("<script");
		var s_e = strcode.indexOf(">", s);
		var e = strcode.indexOf("</script", s);
		var e_e = strcode.indexOf(">", e);
		
		scripts.push(strcode.substring(s_e+1, e));
		strcode = strcode.substring(0, s) + strcode.substring(e_e+1);
	}
	
	// Loop through every script collected and eval it
	for(var i=0; i<scripts.length; i++) 
	{
		try 
		{
		  eval(scripts[i]);
		}
		catch(ex) 
		{
			// Nothing
		}
	}
}

function createCookie(name,value,seconds) 
{
    if(seconds) 
    {
        var date = new Date();
        date.setTime(date.getTime()+(seconds*1000));
        var expires = "; expires="+date.toGMTString();
    }
    else var expires = "";
    document.cookie = name+"="+value+expires+"; domain=."+document.domain+"; path=/";
}

function readCookie(name) 
{
    var nameEQ = name + "=";
    var ca = document.cookie.split(';');
    for(var i=0;i < ca.length;i++) 
    {
        var c = ca[i];
        while (c.charAt(0)==' ') c = c.substring(1,c.length);
        if (c.indexOf(nameEQ) == 0) return c.substring(nameEQ.length,c.length);
    }
    return null;
}

function eraseCookie(name) 
{
    createCookie(name,"",-3600);
}