<?php include 'header.php'; ?>
<div class="content-wrapper">
    <!-- Container-fluid starts -->
    <div class="container-fluid">

       <!-- Header Starts -->
       <div class="row">
          <div class="col-sm-12 p-0">
             <div class="main-header">
                <h4>Table</h4>
                <ol class="breadcrumb breadcrumb-title breadcrumb-arrow">
                   <li class="breadcrumb-item">
                      <a href="index.html">
                         <i class="icofont icofont-home"></i>
                      </a>
                   </li>
                   <li class="breadcrumb-item"><a href="#">Tables</a>
                   </li>
                   <li class="breadcrumb-item"><a href="basic-table.html">Basic Table</a>
                   </li>
                </ol>
             </div>
          </div>
       </div>
       <!-- Header end -->
 
       <div class="row">
          <div class="col-sm-12">


         <!-- Add Bootstrap CSS & Icons -->
 
<style>
.toggle-icon {
    cursor: pointer;
    transition: transform 0.3s ease;
}
.toggle-icon.rotate {
    transform: rotate(180deg);
}
.details-card {
    background-color: #f8f9fa;
    padding: 15px;
}
.details-box {
    background-color: #3f51b5;
    color: #fff;
    border-radius: 8px;
    padding: 15px;
}
</style>

<div class="container mt-4">
    <h4>Advanced Table</h4>
    <div class="table-responsive">
    <table class="table table-bordered align-middle" id="advancedTable">
            <thead class="table-primary">
                <tr>
                    <th></th>
                    <th>Quote#</th>
                    <th>Product</th>
                    <th>Business type</th>
                    <th>Policy holder</th>
                    <th>Premium</th>
                    <th>Status</th>
                    <th>Updated at</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <!-- Row 1 -->
                <tr>
                    <td><input type="checkbox"></td>
                    <td>Incs234</td>
                    <td>Car insurance</td>
                    <td>Business type 1</td>
                    <td>Jesse Thomas</td>
                    <td>$1200</td>
                    <td>In progress</td>
                    <td>25/04/2020</td>
                    <td class="text-center">
                        <button type="button" class="btn btn-link p-0 toggle-icon-btn" data-bs-toggle="collapse" data-bs-target="#details1" aria-expanded="false" aria-controls="details1">
                            <i class="bi bi-chevron-down toggle-icon"></i>
                        </button>
                    </td>
                </tr>
                <tr>
                    <td colspan="9" class="p-0">
                        <div id="details1" class="collapse" data-bs-parent="#advancedTable">
                            <div class="details-card">
                                <div class="row">
                                    <div class="col-md-4">
                                        <div class="details-box">
                                            <p>Policy start date<br><strong>25/04/2020</strong></p>
                                            <p>Policy end date<br><strong>24/04/2021</strong></p>
                                            <p>Sum insured<br><strong>$26,000</strong></p>
                                            <p>Premium<br><strong>$1200</strong></p>
                                        </div>
                                    </div>
                                    <div class="col-md-8">
                                        <p><strong>Quote no:</strong> Incs234</p>
                                        <p><strong>Policy number:</strong> Incsq123456</p>
                                        <p><strong>Vehicle Reg. No:</strong> KL-65-A-7004</p>
                                        <p><strong>Agent / Broker:</strong> Abcd Enterprises</p>
                                        <p><strong>Branch:</strong> Koramangala, Bangalore</p>
                                        <p><strong>Channel:</strong> Online</p>
                                        <p><strong>Policy holder Name & ID:</strong> Phillip Harris / 1234567</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </td>
                </tr>

                <!-- Row 2 -->
                <tr>
                    <td><input type="checkbox"></td>
                    <td>Incs235</td>
                    <td>Car insurance</td>
                    <td>Business type 2</td>
                    <td>Mary Johnson</td>
                    <td>$1450</td>
                    <td>Active</td>
                    <td>01/05/2020</td>
                    <td class="text-center">
                        <button type="button" class="btn btn-link p-0 toggle-icon-btn" data-bs-toggle="collapse" data-bs-target="#details2" aria-expanded="false" aria-controls="details2">
                            <i class="bi bi-chevron-down toggle-icon"></i>
                        </button>
                    </td>
                </tr>
                <tr>
                    <td colspan="9" class="p-0">
                        <div id="details2" class="collapse" data-bs-parent="#advancedTable">
                            <div class="details-card">
                                <div class="row">
                                    <div class="col-md-4">
                                        <div class="details-box">
                                            <p>Policy start date<br><strong>01/05/2020</strong></p>
                                            <p>Policy end date<br><strong>30/04/2021</strong></p>
                                            <p>Sum insured<br><strong>$30,000</strong></p>
                                            <p>Premium<br><strong>$1450</strong></p>
                                        </div>
                                    </div>
                                    <div class="col-md-8">
                                        <p><strong>Quote no:</strong> Incs235</p>
                                        <p><strong>Policy number:</strong> Incsq987654</p>
                                        <p><strong>Vehicle Reg. No:</strong> KL-65-B-5500</p>
                                        <p><strong>Agent / Broker:</strong> XYZ Brokers</p>
                                        <p><strong>Branch:</strong> Whitefield, Bangalore</p>
                                        <p><strong>Channel:</strong> Offline</p>
                                        <p><strong>Policy holder Name & ID:</strong> Mary Johnson / 654321</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </td>
                </tr>

                <!-- Row 3 -->
                <tr>
                    <td><input type="checkbox"></td>
                    <td>Incs236</td>
                    <td>Car insurance</td>
                    <td>Business type 3</td>
                    <td>John Smith</td>
                    <td>$1100</td>
                    <td>Expired</td>
                    <td>15/05/2020</td>
                    <td class="text-center">
                        <button type="button" class="btn btn-link p-0 toggle-icon-btn" data-bs-toggle="collapse" data-bs-target="#details3" aria-expanded="false" aria-controls="details3">
                            <i class="bi bi-chevron-down toggle-icon"></i>
                        </button>
                    </td>
                </tr>
                <tr>
                    <td colspan="9" class="p-0">
                        <div id="details3" class="collapse" data-bs-parent="#advancedTable">
                            <div class="details-card">
                                <div class="row">
                                    <div class="col-md-4">
                                        <div class="details-box">
                                            <p>Policy start date<br><strong>15/05/2019</strong></p>
                                            <p>Policy end date<br><strong>14/05/2020</strong></p>
                                            <p>Sum insured<br><strong>$20,000</strong></p>
                                            <p>Premium<br><strong>$1100</strong></p>
                                        </div>
                                    </div>
                                    <div class="col-md-8">
                                        <p><strong>Quote no:</strong> Incs236</p>
                                        <p><strong>Policy number:</strong> Incsq555555</p>
                                        <p><strong>Vehicle Reg. No:</strong> KL-65-C-9900</p>
                                        <p><strong>Agent / Broker:</strong> QRS Insurance</p>
                                        <p><strong>Branch:</strong> MG Road, Bangalore</p>
                                        <p><strong>Channel:</strong> Online</p>
                                        <p><strong>Policy holder Name & ID:</strong> John Smith / 998877</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </td>
                </tr>

            </tbody>
        </table>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.toggle-icon-btn').forEach(function (btn) {
        var icon = btn.querySelector('.toggle-icon');
        var targetId = btn.getAttribute('data-bs-target');
        var target = document.querySelector(targetId);

        target.addEventListener('show.bs.collapse', function () {
            document.querySelectorAll('.toggle-icon').forEach(function (otherIcon) {
                otherIcon.classList.remove('rotate');
            });
            icon.classList.add('rotate');
        });
        target.addEventListener('hide.bs.collapse', function () {
            icon.classList.remove('rotate');
        });
    });
});
</script>

          
        
          </div>
       </div>
       <!-- Row end -->
       <!-- Tables end -->
    </div>

    <!-- Container-fluid ends -->
 </div>
 <?php include 'footer.php';?>