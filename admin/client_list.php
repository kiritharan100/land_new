<?php include 'header.php'; 
checkPermission(2);
?>

<?php 
 
$user_license = $_SESSION['customer'];
  if(isset($_POST['create_new_client'])){
 
   
   $client_id1 = $_POST['client_id'];
   $client_name =$_POST['client_name'];
   $client_type = $_POST['client_type'];
   $client_email = $_POST['client_email'];
   $client_phone = $_POST['client_phone'];
   // New fields per requirement
   $bank_and_branch = $_POST['bank_and_branch'] ?? '';
   $account_number = $_POST['account_number'] ?? '';
   $account_name = $_POST['account_name'] ?? '';
   $regNumber = $_POST['regNumber'];
   $md5_client = md5(time());
  
   $bank_and_branch_safe = mysqli_real_escape_string($con, $bank_and_branch);
   $account_number_safe = mysqli_real_escape_string($con, $account_number);
   $account_name_safe = mysqli_real_escape_string($con, $account_name);
   $update="INSERT INTO `client_registration`(`md5_client`, `user_license`, `client_id`, `client_name`, `client_type`, `client_email`, `client_phone`, `regNumber`, `bank_and_branch`, `account_number`, `account_name`) 
    VALUES ('$md5_client','$user_license','$client_id1','$client_name','$client_type','$client_email','$client_phone','$regNumber','$bank_and_branch_safe','$account_number_safe','$account_name_safe')";
   mysqli_query($con, $update);
  
echo ' <script>  
notify("success","Great Job :","New Client '.$client_name.' Has been added");
</script> ';
}
  
if(isset($_POST['edit_client'])){
   $client_id1 = $_POST['client_id'];
   $client_name =$_POST['client_name'];
   $client_type = $_POST['client_type'];
   $client_email = $_POST['client_email'];
   $client_phone = $_POST['client_phone'];
   $bank_and_branch = $_POST['bank_and_branch'] ?? '';
   $account_number = $_POST['account_number'] ?? '';
   $account_name = $_POST['account_name'] ?? '';
   $regNumber = $_POST['regNumber'];
   $md5_client = $_POST['md5_client'];
   $bank_and_branch_safe = mysqli_real_escape_string($con, $bank_and_branch);
   $account_number_safe = mysqli_real_escape_string($con, $account_number);
   $account_name_safe = mysqli_real_escape_string($con, $account_name);
   // Region field removed from edit form; leave existing DB value unchanged
   $update="UPDATE  client_registration SET  client_id='$client_id1',client_name='$client_name',client_type='$client_type',client_email='$client_email',client_phone='$client_phone',regNumber='$regNumber',bank_and_branch='$bank_and_branch_safe',account_number='$account_number_safe',account_name='$account_name_safe' WHERE md5_client ='$md5_client'";
   mysqli_query($con, $update);
  
echo ' <script>  
notify("success","Great Job :","Client '.$client_name.' Has been edited");
</script> ';

}
  ?>

 <style>
.switch {
  position: relative;
  display: inline-block;
  width: 46px;
  height: 24px;
}
.switch input {display:none;}
.slider {
  position: absolute;
  cursor: pointer;
  background-color: #ccc;
  border-radius: 24px;
  top: 0; left: 0; right: 0; bottom: 0;
  transition: .4s;
}
.slider:before {
  position: absolute;
  content: "";
  height: 18px;
  width: 18px;
  left: 3px;
  bottom: 3px;
  background: white;
  transition: .4s;
  border-radius: 50%;
}
input:checked + .slider {
  background-color: #4CAF50;
}
input:checked + .slider:before {
  transform: translateX(22px);
}
.slider.round { border-radius: 30px; }
.slider.round:before { border-radius: 50%; }
</style>




<div class="content-wrapper">
   <!-- Container-fluid starts -->
   <div class="container-fluid">
      <!-- Header Starts -->
      <div class="row">
         <div class="col-sm-12 p-0">
            <div class="main-header">
               <h4>Manage DS Division </h4>
               <ol class="breadcrumb breadcrumb-title breadcrumb-arrow">
                  <li class="breadcrumb-item">
                     <a href="index.php">
                        <i class="icofont icofont-home"></i>
                     </a>
                  </li>
                  <li class="breadcrumb-item"><a href="#">Admin  </a>
                  </li>

               </ol>
            </div>
         </div>
      </div>
      <!-- Header end -->

      <div class="row">
         <div class="col-sm-12">

            <div class="card">
               <div class="card-header" align='right'>
                  <a href="#!" class="btn btn-primary waves-effect" data-type="info" data-toggle="modal" data-target="#AddTrailModal"> <i class="fa fa-plus" aria-hidden="true"></i> Add new DS Division</a>
                  <!-- <button type='button' id="exportButton" filename='<?php echo "LIST_".$client_name; ?>.xlsx' class="btn btn-primary"><i class="ti-cloud-down"></i> Export</button> -->
                  <br>
 <!--* Common Production   supplied by-->
<!--                   
                  <div class="card-block button-list notifications">

                     <a href="#!" class="btn btn-inverse waves-effect" data-type="inverse">Inverse</a>


                     <a href="#!" class="btn btn-info waves-effect" data-type="info">Info</a>


                     <a href="#!" class="btn btn-success waves-effect" data-type="success">Success</a>


                     <a href="#!" class="btn btn-warning waves-effect" data-type="warning">Warning</a>


                     <a href="#!" class="btn btn-danger waves-effect" data-type="danger">Danger</a>

                  </div>  -->



                 

               </div>
               <div class="card-block">
                  <div class="row">
                     <div class="col-sm-12 table-responsive">


                        <table id="example1" class="table table-bordered " style="width:100%">
                           <thead>
                              <tr>
                                 <th>#</th>
                                 <th>DS Division</th>
                                 <th>center Type</th>
                                 <th>District</th>
                                 <th>Email</th>
                                 <th>Phone Number</th>
                                 <th>Bank and Branch</th>
                                 <th>Account Number</th>
                                <th>Pay SMS</th>
                                 <th>Reminder SMS</th>
                                 <th>Status</th>
                                 <th>Action</th> 
                              </tr>
                           </thead>
                           <tbody>

                           <?php
$count=1;
  $sel_query="SELECT * from client_registration
  Order BY regNumber, client_name ASC;";
$result = mysqli_query($con,$sel_query);
while($row = mysqli_fetch_assoc($result)) { 
?>
                              <tr <?php  if($row['user_license']==0) {echo "style='background-color:#fe9d9b'";} ?>>
                                 <td><?php echo $count;  $count ++ ; ?></td>
                                 <td><?php echo $row['client_name']; ?></td>
                                 <td><?php echo $row['client_type']; ?></td>
                                 <td><?php echo $row['regNumber']; ?></td>
                                 <td><?php echo $row['client_email']; ?></td>
                                 <td><?php echo $row['client_phone']; ?></td>
                                 <td><?php echo $row['bank_and_branch']; ?></td>
                                 <td><?php echo $row['account_number']; ?></td>
                                  <td>
                                    <label class="switch">
                                       <input type="checkbox" class="smsToggle" 
                                                field="payment_sms"
                                                row_id="<?php echo $row['md5_client']; ?>"
                                                <?php echo ($row['payment_sms'] == 1 ? 'checked' : ''); ?>>
                                       <span class="slider round"></span>
                                    </label>
                                 </td>

                                 <td>
                                    <label class="switch">
                                       <input type="checkbox" class="smsToggle"
                                                field="remindes_sms"
                                                row_id="<?php echo $row['md5_client']; ?>"
                                                <?php echo ($row['remindes_sms'] == 1 ? 'checked' : ''); ?>>
                                       <span class="slider round"></span>
                                    </label>
                                 </td>
                                 <td><?php  if($row['user_license']==1) {echo "Active";} else { echo "Inactive";}?></td>
                                 <td>  <select class="dropdown_change"  class="form-select"   row_id="<?php echo $row["md5_client"]; ?>"  > 
                                            <option>Select</option>
                                            <option>Active</option> 
                                            <option>Inactive</option>
                                            <option>Edit</option>
                                           </select></td>
                                 
                              </tr>
                              
                              <?php } ?>
                           </tbody>
                        </table>
                        
                        <script>
                            $(".dropdown_change").change(function() {
    var value = $(this).find(":selected").val(); 
    var row_id = $(this).attr("row_id");
    
    if (value === 'Edit') {
        $('#EditClient').modal('show');
        
        $.ajax({
            type: "GET", 
            url: 'ajax/get_edit_client.php', 
            data: {md5_client: row_id}, 
            success: function(response) {
                response = JSON.parse(response); 
                $("#edit-form [name='client_name']").val(response.client_name);
                $("#edit-form [name='client_id']").val(response.client_id);
                $("#edit-form [name='client_type']").val(response.client_type);
                $("#edit-form [name='client_email']").val(response.client_email);
                $("#edit-form [name='client_phone']").val(response.client_phone);
                $("#edit-form [name='bank_and_branch']").val(response.bank_and_branch);
                $("#edit-form [name='account_number']").val(response.account_number);
                $("#edit-form [name='account_name']").val(response.account_name);
                $("#edit-form [name='regNumber']").val(response.regNumber);
                $("#edit-form [name='md5_client']").val(response.md5_client);
            }
        });
        $(".dropdown_change").val('Select');
    } 
    
    else if (value === 'Active' || value === 'Inactive') {
        // Confirm with SweetAlert2
        Swal.fire({
            title: 'Are you sure?',
            text: `You want to mark this client as ${value}?`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Yes, change status!',
            cancelButtonText: 'No, cancel'
        }).then((result) => {
            if (result.isConfirmed) {
                // AJAX request to update status
                $.ajax({
                    type: "POST",
                    url: "ajax/update_client_status.php",
                    data: {md5_client: row_id, status: value},
                    success: function(response) {
                        if (response === 'success') {
                            Swal.fire({
                                title: 'Status Updated!',
                                text: `Client is now ${value}`,
                                icon: 'success'
                            });
                            
                            setTimeout(function() {
                                location.reload(); // Refresh the page
                            }, 2000);
                            
                        } else {
                            Swal.fire({
                                title: 'Error!',
                                text: 'Something went wrong while updating the status.',
                                icon: 'error'
                            });
                        }
                    }
                });
            } else {
                // Reset dropdown to "Select" if user cancels
                $(".dropdown_change").val('Select');
            }
        });
    }
});

                        </script>

                       <!-- <script>-->
                       <!-- $(".dropdown_change").change(function() { -->
                       <!--    var value = $(this).find(":selected").val(); -->
                       <!--    var row_id = $(this).attr("row_id");-->
                       <!--    if(value == 'Edit'){-->
                       <!--       $('#EditClient').modal('show');-->
                       <!--       $.ajax({-->
                       <!--             type: "GET", -->
                       <!--             url: 'ajax/get_edit_client.php', -->
                       <!--             data: {md5_client:row_id}, -->
                       <!--             beforeSend: function () { -->
                       <!--             },-->
                       <!--             success: function (response) {-->
                       <!--                   response = JSON.parse(response); -->
                       <!--                   $("#edit-form [name=\"client_name\"]").val(response.client_name);-->
                       <!--                   $("#edit-form [name=\"client_id\"]").val(response.client_id);-->
                       <!--                   $("#edit-form [name=\"client_type\"]").val(response.client_type);-->
                       <!--                   $("#edit-form [name=\"client_email\"]").val(response.client_email);-->
                       <!--                   $("#edit-form [name=\"client_phone\"]").val(response.client_phone);-->
                       <!--                   $("#edit-form [name=\"contact_name\"]").val(response.contact_name);-->
                       <!--                   $("#edit-form [name=\"contact_email\"]").val(response.contact_email);-->
                       <!--                   $("#edit-form [name=\"contact_phone\"]").val(response.contact_phone);-->
                       <!--                   $("#edit-form [name=\"regNumber\"]").val(response.regNumber);-->
                       <!--                       $("#edit-form [name=\"region\"]").val(response.region);-->
                       <!--                   $("#edit-form [name=\"md5_client\"]").val(response.md5_client);-->
                       <!--             }-->

                                   
                       <!--          });-->
                       <!--          $(".dropdown_change").val('Select');-->
                       <!--    }-->

                       <!--  });                           -->
                       <!--</script> -->


                     </div>
                  </div>
               </div>
            </div>



         </div>
      </div>
      <!-- Row end -->
      <!-- Tables end -->
   </div>

   <!-- Container-fluid ends -->
</div>


<?php include 'footer.php'; ?>


<script>
$(".smsToggle").change(function() {

    var md5_client = $(this).attr("row_id");
    var field = $(this).attr("field");       // payment_sms or remindes_sms
    var status = $(this).is(":checked") ? 1 : 0;

    $.ajax({
        url: "ajax/update_sms_settings.php",
        type: "POST",
        data: {
            md5_client: md5_client,
            field: field,
            status: status
        },
        success: function(response){
            if(response === "success"){
                notify('success', 'SMS status updated successfully');
            }else{
                notify('danger', 'SMS update failed');
            }
        }
    });

});
</script>



<script>
   $(document).ready(function() {
      $('#example1').DataTable(
         {
            "pageLength": 200,
            "order": [[0, "asc"]],
            "columnDefs": [
               { "orderable": false, "targets": [8, 9] } // Disable sorting on the last two columns
            ]
         }

      );
   });

   function lookin2(row_value){
 var c_id = row_value.getAttribute('c_id');
 
 var value = row_value.value;

 $.ajax({
type: "POST", 
url: 'ajax/estimate.php', 
data: {
	select_dmu:c_id
	,c_id:c_id
   ,value:value
  

 
}, 
beforeSend: function () { 
},
success: function (response) {
   notify('success', 'Updated successfully');
} 
}); 
}



</script>

<div class="modal fade" id="AddTrailModal" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel" aria-hidden="true">
   <div class="modal-dialog" role="document">
      <div class="modal-content">
         <div class="modal-header">
         <i class="fa fa-refresh" aria-hidden="true"></i>
            <button type="button" class="close" data-dismiss="modal" aria-label="Close">
               <span aria-hidden="true">&times;</span>
            </button>
            <h5 class="modal-title" id="exampleModalLabel">Add New  </h5>
         </div>
         <div class="modal-body" style="padding-left: 20px;">
         <form action="" method="POST">

            
               <div  class="form-group row"><label  class="col-xl-4 col-lg-4 col-md-12 pt-2 label-form">Center ID *</label>
                  <div  class="col-xl-8 col-lg-8 col-md-12 col-12 col-sm-12" style="position: relative;top: 6px;"><input  class="form-control SearchBar noSpl " required="" type="text" placeholder="Type in your Internal Reference ID" name='client_id'></div>
               </div>
               <div  class="form-group row"><label  class="col-xl-4 col-lg-4  col-md-12 pt-3 label-form">

                     <span  class="ng-star-inserted">DS Division Name *</span>
                  </label>
                  <div  class="col-xl-8 col-lg-8 col-md-12 col-12 col-sm-12" style="position: relative;top: 6px;">
                     <div  class="input-group"><input  class="form-control SearchBar noSpl " maxlength="50" required="" type="text" placeholder="Type Legal Registration name" oninput="this.value = this.value.toUpperCase()" name='client_name'><span  class="input-group-btn" > </div>
                
                  </div>
               </div>
               <div  class="form-group row"><label  class="col-xl-4 col-lg-4 col-md-12 label-form">Center Type *</label>
                  <div  class="col-xl-8 col-lg-8 col-md-12 selectdiv" >
                     <select name='client_type' class="form-control" style="margin:5px;" required name='client_type'>
                        <option value=''> Select </option>
                        <option>DS</option>
                        <option>HO</option>
                      
                

                     </select>
                  </div>
               </div>
               <div  class="form-group row"><label  class="col-xl-4 col-lg-4 col-md-12 label-form">District *</label>
                  <div  class="col-xl-8 col-lg-8 col-md-12 selectdiv" >
                     <select   class="form-control" style="margin:5px;" required name='regNumber'>
                        <option value=''> Select </option>
                        <option>Trincomalee</option>
                        <option>Batticaloa</option>
                        <option>Ampara</option>
                    
                

                     </select>
                  </div>
               </div>
               
               
               
            
             
               <div  class="form-group row"><label  class="col-xl-4 col-lg-4 col-md-12 label-form pt-2"> Email </label>
                  <div  class="col-xl-8 col-lg-8 col-md-12"><input  class="form-control ng-untouched ng-pristine ng-valid"  pattern="^[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,15}$" type="email" placeholder="Type in client email"  name='client_email'>
                     
                  </div>
               </div>
               <div  class="form-group row"><label  class="col-xl-4 col-lg-4 col-md-12 pt-2 label-form"> Phone Number </label>
                  <div  class="col-xl-8 col-lg-8 col-md-12"><input  class="form-control ng-untouched ng-pristine ng-valid" maxlength="13" minlength="10"   type="text" placeholder="Type in client phone number" name='client_phone'></div>
               </div>
               
               <div  class="form-group row"><label  class="col-xl-4 col-lg-4 col-md-12 pt-2 label-form">Bank and Branch *</label>
                  <div  class="col-xl-8 col-lg-8 col-md-12"><input  class="form-control" placeholder="Type bank and branch"   type="text" name='bank_and_branch'  ></div>
               </div>
               <div  class="form-group row"><label  class="col-xl-4 col-lg-4 col-md-12 pt-2 label-form">Account Number *</label>
                  <div  class="col-xl-8 col-lg-8 col-md-12"><input  class="form-control"   type="text" placeholder="Type account number" name='account_number'  ></div>
               </div>
               <div  class="form-group row"><label  class="col-xl-4 col-lg-4 col-md-12 pt-2 label-form">Account Name</label>
                  <div  class="col-xl-8 col-lg-8 col-md-12"><input  class="form-control"   type="text" placeholder="Type account name" name='account_name'></div>
               </div>
       
      
               
                
            

 
            
         </div>
         <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
            <button type="submit" name='create_new_client' class="btn btn-primary"><i class="fa fa-save"></i> Create</button>
         </div>
         </form>
      </div>
   </div>
</div>


<div class="modal fade" id="EditClient" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel" aria-hidden="true">
   <div class="modal-dialog" role="document">
      <div class="modal-content">
         <div class="modal-header">
            <button type="button" class="close" data-dismiss="modal" aria-label="Close">
               <span aria-hidden="true">&times;</span>
            </button>
            <h5 class="modal-title" id="exampleModalLabel">Edit Client</h5>
         </div>
         <div class="modal-body" style="padding-left: 20px;">
         <form action="" method="POST" id="edit-form">

            
               <div  class="form-group row"><label  class="col-xl-4 col-lg-4 col-md-12 pt-2 label-form">Center  ID *</label>
                  <div  class="col-xl-8 col-lg-8 col-md-12 col-12 col-sm-12" style="position: relative;top: 6px;">
                  <input  class="form-control SearchBar noSpl " required="" type="text" placeholder="Type in your Internal Reference ID"   name='client_id'>
                  <input type="hidden" name='md5_client' value=''>
               
               </div>
               </div>
               <div  class="form-group row"><label  class="col-xl-4 col-lg-4  col-md-12 pt-3 label-form">

                     <span  class="ng-star-inserted">DS Division Name *</span>
                  </label>
                  <div  class="col-xl-8 col-lg-8 col-md-12 col-12 col-sm-12" style="position: relative;top: 6px;">
                     <div  class="input-group"><input  class="form-control SearchBar noSpl " maxlength="50" required="" type="text" placeholder="Type Legal Registration name" oninput="this.value = this.value.toUpperCase()" name='client_name' id='edit-client_name'><span  class="input-group-btn" > </div>
                
                  </div>
               </div>
               <div  class="form-group row"><label  class="col-xl-4 col-lg-4 col-md-12 label-form">Center  Type *</label>
                  <div  class="col-xl-8 col-lg-8 col-md-12 selectdiv" >
                     <select name='client_type' class="form-control" style="margin:5px;" required name='client_type'>
                        <option value=''> Select </option>
                      <option>DS</option>
                      <option>Ho</option>
                

                     </select>
                  </div>
               </div>
               
               
               <div  class="form-group row"><label  class="col-xl-4 col-lg-4 col-md-12 label-form">District *</label>
                  <div  class="col-xl-8 col-lg-8 col-md-12 selectdiv" >
                     <select name='regNumber' class="form-control" style="margin:5px;" required   >
                        <option value=''> Select </option>
                        <option>Trincomalee</option>
                        <option>Batticaloa</option>
                        <option>Ampara</option>
                

                     </select>
                  </div>
               </div>
               
 
              
               
               
             
               <div  class="form-group row"><label  class="col-xl-4 col-lg-4 col-md-12 label-form pt-2"> Email </label>
                  <div  class="col-xl-8 col-lg-8 col-md-12"><input  class="form-control ng-untouched ng-pristine ng-valid"  pattern="^[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,15}$" type="email" placeholder="Type in client email"  name='client_email'>
                     
                  </div>
               </div>
               <div  class="form-group row"><label  class="col-xl-4 col-lg-4 col-md-12 pt-2 label-form"> Phone Number </label>
                  <div  class="col-xl-8 col-lg-8 col-md-12"><input  class="form-control ng-untouched ng-pristine ng-valid" maxlength="13" minlength="10"   type="text" placeholder="Type in client phone number" name='client_phone'></div>
               </div>
               
               <div  class="form-group row"><label  class="col-xl-4 col-lg-4 col-md-12 pt-2 label-form">Bank and Branch *</label>
                  <div  class="col-xl-8 col-lg-8 col-md-12"><input  class="form-control" placeholder="Type bank and branch"   type="text" name='bank_and_branch' ></div>
               </div>
               <div  class="form-group row"><label  class="col-xl-4 col-lg-4 col-md-12 pt-2 label-form">Account Number *</label>
                  <div  class="col-xl-8 col-lg-8 col-md-12"><input  class="form-control"   type="text" placeholder="Type account number" name='account_number' ></div>
               </div>
               <div  class="form-group row"><label  class="col-xl-4 col-lg-4 col-md-12 pt-2 label-form">Account Name</label>
                  <div  class="col-xl-8 col-lg-8 col-md-12"><input  class="form-control"   type="text" placeholder="Type account name" name='account_name'></div>
               </div>
         </div>
         <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
            <button type="submit" name='edit_client' class="btn btn-primary"><i class="fa fa-save"></i> Save</button>
         </div>
         </form>
      </div>
   </div>
</div>
