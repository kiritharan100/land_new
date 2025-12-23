<?php include 'header.php';
checkPermission(1);
?>



<?php
 
 

if (isset($_POST['update_user'])) {
    $userId    = $_POST['editUserId'];
    $name      = $_POST['editName'];
    $email     = $_POST['editEmail'];
    $mobile_no = $_POST['editmobile_no'];
    $nic       = $_POST['editnic'];
    $role_id   = isset($_POST['editUserGroup']) ? $_POST['editUserGroup'] : '';

    // Update query including mobile_no and role_id
    $sql = "UPDATE user_license SET i_name = ?, username = ?, mobile_no = ?, nic = ?, role_id = ? WHERE usr_id = ?";
    $stmt = $con->prepare($sql);
    $stmt->bind_param("ssssis", $name, $email, $mobile_no, $nic, $role_id, $userId);

    if ($stmt->execute()) {
        UserLog("User", "Updated Profile", "User $name updated by admin");
        echo "<script>
            notify('success', 'Success', 'User updated successfully');
            setTimeout(function() {}, 1000);
        </script>";
    } else {
        echo "<script>
            notify('danger', 'Error', 'Failed to update user: " . addslashes($con->error) . "');
        </script>";
    }
    $stmt->close();
}


// if (isset($_POST['update_user'])) {
//     $userId = $_POST['editUserId'];
//     $name = $_POST['editName'];
//     $email = $_POST['editEmail'];
//     $email = $_POST['editEmail'];
//     $mobile_no = $_POST['editmobile_no'];
//     // Retrieve other fields as needed

//     $sql = "UPDATE user_license SET i_name = ?, username = ? WHERE usr_id = ?";
//     $stmt = $con->prepare($sql);
//     $stmt->bind_param("ssi", $name, $email, $userId);  // Adjust the types accordingly
//     if ($stmt->execute()) {
//         // Success, you can redirect or show a success message
//         echo "User updated successfully";
//     } else {
//         // Error handling
//         echo "Error updating user: " . $con->error;
//     }
// }





if (isset($_POST['submit_user_location'])) {
  $usr_id = $_POST['usr_id'];


  if (isset($_POST['name'])) {
      $update="DELETE FROM `user_location` WHERE usr_id='$usr_id'";
    mysqli_query($con, $update);
   // Iterate over each checkbox in the 'name' array
   foreach ($_POST['name'] as $id => $value) {


   $update="INSERT INTO `user_location`( `usr_id`, `location_id`) VALUES ('$usr_id','$id')";
   mysqli_query($con, $update);
   
 
   }
   echo "<script> notify('success', 'Selected Location added'); </script>";


} else {
   $update="DELETE FROM `user_location` WHERE usr_id='$usr_id'";
   mysqli_query($con, $update);
   echo "<script> notify('success', 'No checkboxes are selected ANd  Removed all the location '); </script>";
    
}


   
   // $update="UPDATE drugs_master set m_code='$m_code', m_name = '$m_name' , m_cat = '$m_cat' ,m_tamil = '$m_tamil' , m_sinhala='$m_sinhala' , m_unit ='$m_unit' where  m_id = '$m_id'";
   // mysqli_query($con, $update);
//  echo "<script> notify('success', 'Drugs  $m_name Edeted successfully'); </script>";
}



 if (isset($_POST['add_user'])) {

    // Get system email, company name etc.
    $queryx = "SELECT system_email, company_name, domain FROM letter_head LIMIT 1";
    $resultx = mysqli_query($con, $queryx);
    $rowx = mysqli_fetch_assoc($resultx);

    $system_email = $rowx['system_email'];
    $company_name = $rowx['company_name'];
    $domain = $rowx['domain'];

    // Inputs
    $name      = $_POST['name'];
    $mobile_no = $_POST['email'];  // Mobile number entered here
    $email     = $_POST['email'];  // same input name
    $nic = $_POST['nic'];
    $username  = $mobile_no;
    $role_id   = $_POST['userGroup'] ?? '';
    $token     = rand(10000, 99999);   // 5-digit token

    // Insert user
    $sql = "INSERT INTO user_license (i_name, username, token, mobile_no, role_id,nic)
            VALUES (?, ?, ?, ?, ?, ?)";
    $stmt = $con->prepare($sql);
    $stmt->bind_param("ssssis", $name, $username, $token, $mobile_no, $role_id,$nic);

    if ($stmt->execute()) {

        // NEW SMS MODULE
        require_once __DIR__ . '/../sms_helper.php';
        $sms = new SMS_Helper();

        $message = "You are invited to set up your IRMIS account.  Token: $token , Use New User button on the login page";
        $sms_type = "User Invitation";

        // SMS send & log
        $sms_result = $sms->sendSMS(
            $lease_id = 0,      // When not related to lease use 0
            $mobile_no,
            $message,
            $sms_type
        );

        //update user log
        UserLog(1, "New User Created", "User $name created by admin");

        if ($sms_result['success']) {
            echo "<script> notify('success','New user created and SMS invitation sent.'); </script>";
        } else {
            echo "<script> notify('warning','User created but SMS failed: " . addslashes($sms_result['comment']) . "'); </script>";
        }

    } else {
        echo "<script> notify('danger','Error creating user'); </script>";
    }

    echo '<script> window.location.href = "manage_user.php"; </script>';
}




?>



<div class="content-wrapper">
    <!-- Container-fluid starts -->
    <div class="container-fluid">
        <!-- Header Starts -->
        <div class="row">
            <div class="col-sm-12 p-0">
                <div class="main-header">
                    <h4>User List</h4>
                    <ol class="breadcrumb breadcrumb-title breadcrumb-arrow">
                        <li class="breadcrumb-item">
                            <a href="index.php">
                                <i class="icofont icofont-home"></i>
                            </a>
                        </li>
                        <li class="breadcrumb-item"><a href="#">Admin </a>
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
                        <!--<a href="#!" class="btn btn-primary waves-effect" data-type="info" data-toggle="modal" data-target="#userModalNew"> <i class="fa fa-plus" aria-hidden="true"></i> Add new  user</a>-->
                        <button class="btn btn-primary" id="addUserButton">Add New User</button>
                        <a href='group_activity_map.php'><button class="btn btn-primary">User Privileges</button></a>
                    </div>
                    <hr>
                    <div class="card-block">
                        <div class="row">
                            <div class="col-sm-12 table-responsive">


                                <table id="example" class="table table-bordered " style="width:100%">
                                    <thead>
                                        <tr>
                                            <th>#</th>
                                            <th>Name</th>
                                            <th>username</th>
                                            <th>NIC No</th>
                                            <th>User Privileges</th>
                                            <th>Last Log</th>
                                            <th>Centers</th>
                                            <th>Status</th>
                                            <th>Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>

                                        <?php
                                    $count=1;
                                      $sel_query="SELECT user_license.*,count(user_location.id) as allocated FROM `user_license`
                                      LEFT JOIN user_location ON user_location.usr_id = user_license.usr_id  WHERE  user_license.usr_id > 0
                                      GROUP BY user_license.usr_id ORDER BY user_license.usr_id DESC";
                                    $result = mysqli_query($con,$sel_query);
                                    while($row = mysqli_fetch_assoc($result)) { 
                                    ?>


                                        <tr data-user-id="<?php echo $row['usr_id'] ?>"
                                            <?php if($row['account_status']==2){echo "style='background-color:#85C1E9;'"; } elseif($row['account_status']==1){  } else {echo "style='background-color:#F49151;'";} ?>>
                                            <td><?php echo $row['usr_id'] ?></td>
                                            <td><?php echo $row['i_name'] ?></td>
                                            <td><?php echo $row['username'] ?></td>
                                            <td align='center' ><?= $row['nic'] ?>
                                            <td align='center'>
                                                <?php 
                        if (!empty($row['role_id'])) {
                            $priv_query = "SELECT group_name FROM manage_user_group WHERE group_id = '" . $row['role_id'] . "'";
                            $priv_result = mysqli_query($con, $priv_query);
                            if ($priv_row = mysqli_fetch_assoc($priv_result)) {
                                echo htmlspecialchars($priv_row['group_name']);
                            } else {
                                echo '<span class="text-muted">No group</span>';
                            }
                        } else {
                            echo '<span class="text-muted">No group</span>';
                        }
                        ?>
                                            </td>
                                            <td><?php echo $row['last_log_in'] ?></td>
                                            <td> <label class="label bg-success" p_id='<?php echo $row['p_id'] ?>'
                                                    id='<?php echo $row['usr_id'] ?>' data-toggle="modal"
                                                    data-target="#edit_material"
                                                    onclick="return Lode_Information(this)"><?php echo $row['allocated']; ?>
                                                    Branch Allocated</label>
                                            </td>
                                            <td align='center'>
                                                <?php if($row['account_status']==1){ echo "Active";} elseif($row['account_status']==2){ echo "Pending"; } else {echo "Inactive";} ?>
                                            </td>
                                            <td>
                                                <div class="dropdown">
                                                    <button class="btn btn-secondary dropdown-toggle" type="button"
                                                        id="actionMenuButton" data-toggle="dropdown"
                                                        aria-haspopup="true" aria-expanded="false">
                                                        Action
                                                    </button>
                                                    <div class="dropdown-menu" aria-labelledby="actionMenuButton">
                                                        <a class="dropdown-item edit-button" href="#"
                                                            data-user-id="<?php echo $row['usr_id'] ?>"> <i
                                                                class="fa fa-user" aria-hidden="true"></i> Edit User</a>
                                                        <a class="dropdown-item active-button" href="#"
                                                            data-user-id="<?php echo $row['usr_id'] ?>"> <i
                                                                class="fa fa-user" aria-hidden="true"></i> Active</a>
                                                        <a class="dropdown-item inactive-button" href="#"
                                                            data-user-id="<?php echo $row['usr_id'] ?>"><i
                                                                class="fa fa-ban" aria-hidden="true"></i> Deactive</a>
                                                        <a class="dropdown-item invite-button" href="#"
                                                            data-user-id="<?php echo $row['usr_id'] ?>"><i
                                                                class="fa fa-envelope" aria-hidden="true"></i>
                                                            Invitation</a>
                                                    </div>
                                                </div>
                                            </td>
                                        </tr>
                                        <?php } ?>
                                    </tbody>
                                </table>
                                * Check on the green button to allocate DS Office to the user
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



<div class="modal fade" id="userEditModal" tabindex="-1" role="dialog" aria-labelledby="userEditModalLabel"
    aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <form method='POST' action='' id="editUserForm">
                <div class="modal-header">
                    <h5 class="modal-title" id="userEditModalLabel">Edit User</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="editUserId" id="editUserId">
                    <div class="form-group">
                        <label for="editUsername">Name</label>
                        <input type="text" class="form-control" id="editUsername" name="editName" required>
                    </div>
                    <div class="form-group">
                        <label for="editEmail">Mobile Number (Ten digits) Eg : 0771234567 </label>
                        <input type="number" class="form-control" id="editEmail" name="editEmail" required>
                    </div>

                     <div class="form-group">
                        <label for="editnic">NIC No</label>
                        <input type="text" class="form-control" id="editnic" name="editnic"
                            placeholder='123456789V' required>
                    </div>

                    <div class="form-group">
                        <label for="editUserGroup">User Group</label>
                        <select class="form-control" id="editUserGroup" name="editUserGroup" required>
                            <option value="">Select Group</option>
                            <?php 
                            $group_query = "SELECT group_id, group_name FROM manage_user_group WHERE status=1";
                            $group_result = mysqli_query($con, $group_query);
                            while($group = mysqli_fetch_assoc($group_result)) {
                                echo '<option value="'. $group['group_id'] .'">'. htmlspecialchars($group['group_name']) .'</option>';
                            }
                            ?>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                    <button type="submit" name='update_user' class="btn btn-primary">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>




<?php include 'footer.php'; ?>

<div class="modal" id="edit_material" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <!--<div class="modal-header">-->
            <div class="modal-body">
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"> <span
                        aria-hidden="true">&times;</span> </button>
                <h4 class="modal-title">Assign centers to user</h4>
                <hr>
                <form method='POST' action=''>
                    <div align='right'>
                        Check Asign Button to record <input type="submit" class="btn btn-success"
                            name='submit_user_location' value='Assign'>
                        <div id="more_info_div">
                            <h2>Faild to get Data.....</h2>
                        </div>
                        <div align='right'>
                            <input type="submit" class="btn btn-success" name='submit_user_location' value='Assign'>
                        </div>
                </form>
            </div>
        </div>
    </div>
</div>


<script>
function Lode_Information(el) {
    // document.getElementById("acc_button").disabled = true;
    // document.getElementById("checkbox").checked = false;

    var row_id1 = el.id;


    $("#more_info_div").load("ajax/material_request_model.php?id=" + row_id1 +
        "&language=<?php echo $client_language;?>&location=<?php echo $location_id;?>",
        function(responseTxt, statusTxt, xhr) {
            if (statusTxt == "success")
                if (statusTxt == "error")
                    alert("Error: " + xhr.status + ": " + xhr.statusText);
        });

}
</script>











<div class="modal fade" id="userModal" tabindex="-1" role="dialog" aria-labelledby="userModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <form id="userForm">
                <div class="modal-header">
                    <h5 class="modal-title" id="userModalLabel">Add/Edit User</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <input type="text" name="userId" id="userId">
                    <div class="form-group">
                        <label for="username">Username</label>
                        <input type="text" class="form-control" id="username" name="username" required>
                    </div>
                    <div class="form-group">
                        <label for="email">Email</label>
                        <input type="email" class="form-control" id="email" name="email" required>
                    </div>

                    <div class="form-group">
                        <label for="mobile_no">Mobile No</label>
                        <input type="Text" class="form-control" id="mobile_no" name="mobile_no"
                            placeholder='+9477123456' required>
                    </div>
                    Add other form fields as needed
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-primary">Save changes</button>
                </div>
            </form>
        </div>
    </div>
</div>
</div>

<div class="modal fade" id="userModalNew" tabindex="-1" role="dialog" aria-labelledby="userModalLabel"
    aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <form method='POST' action='' class='processing_form'>
                <div class="modal-header">
                    <h5 class="modal-title" id="userModalLabel">Add User</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="userId" id="userId">
                    <div class="form-group">
                        <label for="username">Name </label>
                        <input type="text" class="form-control" id="username" name="name" required>
                    </div>
                    <div class="form-group">
                        <label for="email">Mobile Number (Ten digits) Eg : 0771234567 </label>
                        <input type="number" class="form-control" id="email" name="email" required>
                    </div>
                    <div class="form-group">
                        <label for="nic">NIC Number </label>
                        <input type="text" class="form-control" id="nic" name="nic" required>
                    </div>

                    <div class="form-group">
                        <label for="userGroup">User Group</label>
                        <select class="form-control" id="userGroup" name="userGroup" required>
                            <option value="">Select Group</option>
                            <?php 
                                $group_query = "SELECT group_id, group_name FROM manage_user_group WHERE status=1";
                                $group_result = mysqli_query($con, $group_query);
                                while($group = mysqli_fetch_assoc($group_result)) {
                                    echo '<option value="'. $group['group_id'] .'">'. htmlspecialchars($group['group_name']) .'</option>';
                                }
                                ?>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                    <button type="submit" name='add_user' class="btn btn-primary processing_button">Creare new User and
                        send invitation</button>
                </div>
            </form>
        </div>
    </div>
</div>
</div>



<!--<script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>-->
<!--<script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>-->
<!--<script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script> -->



<script>
// JavaScript code for handling actions

// Add/Edit User Modal
document.getElementById('userForm').addEventListener('submit', function(e) {
    e.preventDefault();

    let formData = new FormData(this);

    fetch('save_user.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('User saved successfully');
                location.reload(); // Reload the page to show the updated user list
            } else {
                alert('Failed to save user');
            }
        });
});



$(document).on('click', '.edit-button', function(e) {
    e.preventDefault();

    let userId = $(this).data('user-id');
    $('#editUserId').val(userId); // Set the hidden input with the userId

    $.ajax({
        url: 'get_user.php', // Replace with your PHP file to fetch user data
        type: 'GET',
        data: {
            userId: userId
        },
        success: function(data) {
            let user = JSON.parse(data);
            $('#editUsername').val(user.i_name);
            $('#editEmail').val(user.username);
            $('#editmobile_no').val(user.mobile_no);
            $('#editnic').val(user.nic);
            // Populate other fields if needed
            if (user.role_id) {
                $('#editUserGroup').val(user.role_id);
            } else {
                $('#editUserGroup').val('');
            }
            $('#userEditModal').modal('show'); // Show the edit modal
        },
        error: function(xhr, status, error) {
            console.error("Failed to fetch user data:", error);
        }
    });
});


document.getElementById('addUserButton').addEventListener('click', function() {
    document.getElementById('userForm').reset();
    document.getElementById('userId').value = '';
    $('#userModalNew').modal('show');
});

// Handle checkbox changes
document.querySelectorAll('input[type=checkbox]').forEach(checkbox => {
    checkbox.addEventListener('change', function() {
        let userId = this.closest('tr').dataset.userId;
        let module = this.name;
        let isChecked = this.checked ? 1 : 0;

        fetch('update_permissions.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    userId: userId,
                    module: module,
                    isChecked: isChecked
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    notify('success', 'Permission updated successfully');

                } else {
                    notify('danger', 'Error');
                }
            });
    });
});

// Inactive User
document.querySelectorAll('.inactive-button').forEach(button => {
    button.addEventListener('click', function() {
        let userId = this.dataset.userId;
        if (confirm('Are you sure you want to deactivate this user?')) {
            fetch('deactivate_user.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        userId: userId
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('User deactivated successfully');
                        location.reload(); // Reload the page to show the updated user list
                    } else {
                        alert('Failed to deactivate user');
                    }
                });
        }
    });
});
document.querySelectorAll('.invite-button').forEach(button => {
    button.addEventListener('click', function() {
        let userId = this.dataset.userId;
        if (confirm('Are you sure you want to send an invitation to log-in to the system ?')) {
            fetch('invite_user.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        userId: userId
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert(' Sent successfully');
                        location.reload(); // Reload the page to show the updated user list
                    } else {
                        alert('faild to send');
                    }
                });
        }
    });
});

document.querySelectorAll('.active-button').forEach(button => {
    button.addEventListener('click', function() {
        let userId = this.dataset.userId;
        if (confirm('Are you sure you want to Activate this user?')) {
            fetch('activate_user.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        userId: userId
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('User activated successfully');
                        location.reload(); // Reload the page to show the updated user list
                    } else {
                        alert('Failed to activate user');
                    }
                });
        }
    });
});
</script>
</body>

</html>