
   </div>

   

   <script src="../assets/table_enter.js"></script>
      <!-- export to excel -->
      <script src="../assets/xlsx.full.min.js"></script>
   <script src="../assets/export.js"></script>
  
   <!-- Required Jqurey -->
  
   <script src="../assets/plugins/jquery-ui/jquery-ui.min.js"></script>
   <script src="../assets/plugins/tether/dist/js/tether.min.js"></script>

   <!-- Required Fremwork -->
   <script src="../assets/plugins/bootstrap/js/bootstrap.min.js"></script>
   <script src="//cdnjs.cloudflare.com/ajax/libs/bootstrap-select/1.6.3/js/bootstrap-select.min.js"></script>
   <!-- <script src="https://azukcdncp.azureedge.net/contents/js/bootstrap?v=4.1"></script> -->

   <!-- waves effects.js -->
   <script src="../assets/plugins/Waves/waves.min.js"></script>

   <!-- Scrollbar JS-->
   <script src="../assets/plugins/jquery-slimscroll/jquery.slimscroll.js"></script>
   <script src="../assets/plugins/jquery.nicescroll/jquery.nicescroll.min.js"></script>

   <!--classic JS-->
   <script src="../assets/plugins/classie/classie.js"></script>

 
   <!-- custom js -->
   <script type="text/javascript" src="../assets/js/main.min.js"></script>

   <script src="../assets/notification.js"></script>
   <script type="text/javascript" src="../assets/pages/elements.js"></script>
   <!-- <script src="../assets/js/menu.min.js"></script> -->

   <!-- data table -->
   <script src="../assets/jquery.dataTables.min.js"></script>
   <script src="../assets/dataTables.bootstrap5.min.js"></script>
   <script>
   
    $(document).ready(function() {
    $('#search_client').select2();
  });
</script>

 



<script>
  document.getElementById('my-form').addEventListener('submit', function(event) {
    const form = this;

    // Validate form
    if (!form.checkValidity()) {
      event.preventDefault();
      event.stopPropagation();
      form.classList.add('was-validated');
      return;
    }

    // Get the submit button by class
    const button = form.querySelector('button.processing');
    if (button) {
      button.disabled = true;
      button.innerHTML = 'Please wait <i class="fa fa-gear fa-spin" style="font-size:24px"></i>';
    }

    // Let the form submit naturally
  });
</script>

 <script>
  document.getElementById('my-form1').addEventListener('submit', function(event) {
    const form = this;

    // Validate form
    if (!form.checkValidity()) {
      event.preventDefault();
      event.stopPropagation();
      form.classList.add('was-validated');
      return;
    }

    // Get the submit button by class
    const button = form.querySelector('button.processing1');
    if (button) {
      button.disabled = true;
      button.innerHTML = 'Please wait <i class="fa fa-gear fa-spin" style="font-size:24px"></i>';
    }

    // Let the form submit naturally
  });
  
  
  
   $(document).ready(function() {
    
  });
   
</script>


<script>
  document.querySelector('.processing_form').addEventListener('submit', function(event) {
    const form = this;

    // Validate form
    if (!form.checkValidity()) {
      event.preventDefault();
      event.stopPropagation();
      form.classList.add('was-validated');
      return;
    }

    // Get the submit button by class
    const button = form.querySelector('button.processing');
    if (button) {
      button.disabled = true;
      button.innerHTML = 'Please wait <i class="fa fa-gear fa-spin" style="font-size:24px"></i>';
    }
  });


 
 
</script>





</body>

</html>


