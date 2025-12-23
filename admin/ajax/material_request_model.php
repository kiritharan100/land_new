<?php  
include("../../auth.php");
?>
<?php
require('../../db.php');
$id = $_REQUEST['id'];
$location = $_REQUEST['location'];

$client_language = $_REQUEST['language'];
?>
<html>
 <style>
    table, th, td {
  border: 1px solid;
}
    </style>

<body>

    <!--  wrapper -->
    <div id="wrapper">
<div class="row">
                <!--  page header -->
                <div class="col-lg-12">

                </div>
 
            <div class="row">
                <div class="col-lg-12">
                    <!-- Advanced Tables -->
                    <div class="panel panel-default">
                        <div class="panel-heading">


                        </div>
                        <div class="panel-body">
                            <div class="table-responsive">
                                <table width='100%'>
                                    <thead>
                                        <tr bgcolor='#9CD0F8'>
                                            <th>#</th>
                                           
                                            <th style='text-align:center;'> District</th>
                                            <th style='text-align:center;'>Divisional Secretariats</th>
                                             <th style='text-align:center;'> </th> 
  

                                        </tr>
                                    </thead>
                                    <tbody>
                                         
                                        <?php
                                      
                                        $count = 1;
                                          $sel_query = "SELECT cr.district,cr.c_id,cr.client_name,cr.client_type,ul.usr_id as picked FROM client_registration cr
                                          LEFT JOIN user_location ul on ul.location_id =cr.c_id AND ul.usr_id=$id 
                                          ORDER BY cr.district desc ,cr.client_name";
                                        $result = mysqli_query($con, $sel_query);
                                        $no_items = mysqli_num_rows($result);
                                        while ($row = mysqli_fetch_assoc($result)) { 
                                             
                                            ?>


                                            <tr>
                                                <td align="center"> <?php echo $count; ?>  </td>
                                                <td align="center"> <?php echo $row['district'] ?></td> 
                                                    <td> <?php echo $row['client_name'] ?></td> 
                                                <td align="center"><input type='checkbox' name='name[<?php echo $row['c_id']; ?>]' value='1' <?php if($row['picked'] > 0){ echo "Checked";} ?>> </td>
                                                <!-- <td align="center"><input type='checkbox' name='name ' value='1'  > </td> -->
                                                
                                               
                                            </tr>
                                            

                                        <?php $count++;} ?>
                                         


                                    </tbody>
                                </table>
                            </div>
                          
                            <input type='hidden' name='usr_id' value='<?php echo $id; ?>'>
 
                            



                        </div>
                    </div>
                    <!--End Advanced Tables -->
                </div>
            </div>
            <div class="row">
                <div class="col-lg-6">


                </div>
                <!-- end wrapper -->


</body>

</html>