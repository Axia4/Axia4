var urlParams = new URLSearchParams(window.location.search);
$(document).ready(function() {

    // on form submit, upload file:
    $("form#upload").submit(function(event) {
        event.preventDefault();
        $.ajax({
            type: "POST",
            url: "upload.php?pw=" + urlParams.get("p") + "&folder=/IMG/" + urlParams.get("f") + "/" + urlParams.get("n") + "/",
            data: new FormData($(this)[0]),
            cache: false,
            contentType: false,
            processData: false,
            xhr: function() {
                var my_xhr = $.ajaxSettings.xhr();
                document.getElementById('alert').innerText = "Subiendo...";

                if (my_xhr.upload) {
                    my_xhr.upload.addEventListener("progress", function(event) {
                        Progress(event.loaded, event.total);
                    });
                }

                return my_xhr;
            },
            success: function() {
                document.getElementById('alert').innerText = "Subido!";
                Finished();
                
            },
            error: function(xhr, status, message) {
                document.getElementById('alert').innerText = "Error! " + xhr.status + " " + status + " - " + message;
            }
        });
    });

});

// progress bar:
function Progress(current, total) {
    var percent = ((current / total) * 100).toFixed(0) + "%";

    document.getElementById('fileuploaderprog').style.width = percent;
    document.getElementById('fileuploaderprog').innerText = percent;
}

// upload finished:
function Finished() {
    setTimeout(function() {
        $("form#upload input[type='file']").val("");
        document.getElementById('uploaderfileinp').value = "";

        document.getElementById('fileuploaderprog').innerText = "Subido!"
        document.getElementById('fileuploaderprog').style.width = "100%";
        
        setTimeout(function() {
            document.getElementById('fileuploaderprog').innerText = "0%"
            document.getElementById('fileuploaderprog').style.width = "0%";
            location.href="../cal.php?f=" + urlParams.get("f") 
            
        }, 3000);
    }, 500);
}
