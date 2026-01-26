    </main>
    <script>
      document.addEventListener("DOMContentLoaded", function() {
        console.log("post-body loaded");
        [].forEach.call(document.querySelectorAll('.dropimage'), function(img){
          console.log("Setting up dropimage", img);
          img.onchange = function(e){
            var inputfile = this, reader = new FileReader();
            reader.onloadend = function(){
              inputfile.style['background-image'] = 'url('+reader.result+')';
            }
            reader.readAsDataURL(e.target.files[0]);
          }
        });
      });
    </script>
  </body>
</html>