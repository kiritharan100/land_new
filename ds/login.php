<?php 
if(isset($_REQUEST['multiple_sign_in'])){
  header("Location: ../login.php?multiple_sign_in");
         exit;  
}else{
     header("Location: ../login.php");
         exit;  
}

?>