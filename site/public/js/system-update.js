function getReleases(latest_tag) {
    var xmlHttp = new XMLHttpRequest();
    xmlHttp.open( "GET", "https://api.github.com/repos/Submitty/Submitty/releases", false ); // false for synchronous request
    xmlHttp.send( null );
    //console.log(xmlHttp.responseText);
    var myArr = JSON.parse(xmlHttp.responseText);
    console.log(myArr[0]);
    if(myArr[0]["tag_name"] == latest_tag) {
        document.getElementById('text').innerHTML = "Submitty is up to date!";

    } else {
        document.getElementById('text').innerHTML = "A newer version of Submitty is available";
        document.getElementById('tag').innerHTML = "Current Version Installed: " + latest_tag + "\n" + "Most Recent Version: " + myArr[0]["tag_name"];
    }

    document.getElementById('release_notes').innerHTML = parseReleaseNotes(myArr[0]["body"]);
    document.getElementById('release_notes_container').style.display = "block"
    document.getElementById('update_status').style.display = "block"
}

function parseReleaseNotes(raw_str) {
        str = raw_str.replace(/\n/g, "<br />").replace(/\r/g, "").replace("*", "").replace(RegExp("\[(.*?)\]"), "");
        return str;
    }