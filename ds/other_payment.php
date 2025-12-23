<?php
include 'header.php';
checkPermission(25);

$categories = [];
$res = mysqli_query($con, "SELECT cat_id, payment_name, category FROM payment_category WHERE starus=1 ORDER BY payment_name");
if ($res) {
    while ($row = mysqli_fetch_assoc($res)) {
        $categories[] = $row;
    }
    mysqli_free_result($res);
}

$today = date('Y-m-d');
$monthStart = date('Y-m-01');
$currentLocationId = isset($location_id) ? (int)$location_id : 0;
?>
<style>
table th,
table td {
    font-size: 15px;
}
</style>
<div class="content-wrapper">
    <div class="container-fluid">
        <div class="row">
            <div class="col-sm-12 p-0">
                <div class="main-header">
                    <h4><i class="fa fa-coins"></i> Other Payments</h4>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <div>
                    <div class="form-inline">
                        <label class="mr-2">From</label>
                        <input type="date" id="filter_from" class="form-control form-control-sm mr-2"
                            value="<?= $monthStart ?>">
                        <label class="mr-2">To</label>
                        <input type="date" id="filter_to" class="form-control form-control-sm mr-2"
                            value="<?= $today ?>">
                        <button class="btn btn-primary btn-sm" id="btnApplyFilter"><i class="fa fa-filter"></i>
                            Apply</button>

                    </div>
                </div>
                <div>
                    <?php if (hasPermission(25)): ?>
                    <button class="btn btn-primary btn-sm" id="btnAddPayment"><i class="fa fa-plus"></i> Add
                        Payment</button>
                    <span class="ml-3 font-weight-bold" id="totalPaymentLabel"></span>
                    <?php endif; ?>
                </div>
            </div>
            <div class="card-block">
                <div class="table-responsive">
                    <table class="table table-bordered table-striped" id="example">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Serial No</th>
                                <th>Date</th>
                                <th>Receipt No</th>
                                <th>Category</th>
                                <th class="text-right">Amount</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal -->
<div class="modal fade" id="paymentModal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="paymentModalTitle">Add Payment</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form id="paymentForm">
                <div class="modal-body">
                    <input type="hidden" name="id" id="pay_id">
                    <div class="form-group row">
                        <label for="pay_cat_id" class="col-sm-4 col-form-label">Payment Category</label>
                        <div class="col-sm-8">
                            <select id="pay_cat_id" name="pay_cat_id" class="form-control" required>
                                <option value="">Select</option>
                                <?php foreach ($categories as $cat): ?>
                                <option value="<?= (int)$cat['cat_id']; ?>">
                                    <?= htmlspecialchars($cat['payment_name']); ?>
                                    (<?= htmlspecialchars($cat['category']); ?>)
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="form-group row">
                        <label for="payment_date" class="col-sm-4 col-form-label">Payment Date</label>
                        <div class="col-sm-8">
                            <input type="date" class="form-control" id="payment_date" name="payment_date"
                                value="<?= $today ?>" required>
                        </div>
                    </div>
                    <div class="form-group row">
                        <label for="receipt_number" class="col-sm-4 col-form-label">Receipt Number</label>
                        <div class="col-sm-8">
                            <input type="text" class="form-control" id="receipt_number" name="receipt_number"
                                maxlength="50">
                        </div>
                    </div>
                    <div class="form-group row">
                        <label for="amount" class="col-sm-4 col-form-label">Amount</label>
                        <div class="col-sm-8">
                            <input type="number" step="0.01" min="0" class="form-control" id="amount" name="amount"
                                required>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-primary">Save</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
$(function() {
    var LOCATION_ID = <?= $currentLocationId ?>;
    var lastData = [];
    var tableInstance = null;

    function fetchData(doExport) {
        var from = $('#filter_from').val();
        var to = $('#filter_to').val();
        return $.getJSON('payment_ajax/payment_records.php', {
            action: 'list',
            start_date: from,
            end_date: to,
            location_id: LOCATION_ID
        }, function(resp) {
            if (!resp || !resp.success) {
                Swal.fire('Error', (resp && resp.message) || 'Failed to load data', 'error');
                return;
            }
            if (doExport) {
                exportCsv(resp.data || []);
                return;
            }
            lastData = resp.data || [];
            renderTable(lastData);
        });
    }

    function renderTable(rows) {
        // total amount display
        var totalAmt = rows.reduce(function(sum, r) {
            if (String(r.status) === '1' || r.status === 1) {
                return sum + (parseFloat(r.amount || 0) || 0);
            }
            return sum;
        }, 0);
        $('#totalPaymentLabel').text('Total Payment: ' + totalAmt.toLocaleString('en-US', {
            minimumFractionDigits: 2
        }));

        // DataTable with pagination and rendering
        $.fn.dataTable.ext.errMode = 'none';
        if ($.fn.DataTable) {
            if (tableInstance) {
                tableInstance.clear().destroy();
            }
            tableInstance = $('#example').DataTable({

                data: rows,
                pageLength: 25,
                columns: [{
                        data: 'id',
                        defaultContent: ''
                    },
                    {
                        data: 'location_serial',
                        defaultContent: ''
                    },
                    {
                        data: 'payment_date',
                        defaultContent: ''
                    },
                    {
                        data: 'receipt_number',
                        defaultContent: ''
                    },
                    {
                        data: 'payment_name',
                        defaultContent: ''
                    },
                    {
                        data: 'amount',
                        render: function(d) {
                            return Number(d || 0).toLocaleString('en-US', {
                                minimumFractionDigits: 2
                            });
                        },
                        className: 'text-right'
                    },
                    {
                        data: 'status',
                        render: function(d) {
                            return (String(d) === '1' || d === 1) ? 'Active' : 'Inactive';
                        }
                    },
                    {
                        data: null,
                        orderable: false,
                        searchable: false,
                        render: function(row) {
                            var actions = '';
                            <?php if (hasPermission(26)): ?>
                            actions +=
                                '<button class="btn btn-sm btn-outline-primary mr-1 btn-edit" data-id="' +
                                row.id + '"><i class="fa fa-edit"></i></button>';
                            <?php endif; ?>
                            <?php if (hasPermission(27)): ?>
                            actions +=
                                '<button class="btn btn-sm btn-outline-danger btn-del" data-id="' +
                                row.id + '"><i class="fa fa-trash"></i></button>';
                            <?php endif; ?>
                            return actions;
                        }
                    }
                ],
                createdRow: function(row, data) {
                    if (String(data.status) !== '1' && data.status !== 1) {
                        $(row).addClass('inactive-row');
                    }
                }
            });
        }
    }

    function exportCsv(rows) {
        // export only active payments
        rows = (rows || []).filter(function(r) {
            return String(r.status) === '1' || r.status === 1;
        });
        if (!rows.length) {
            Swal.fire('Info', 'No rows to export', 'info');
            return;
        }
        var header = ['#', 'Serial No', 'Date', 'Receipt Number', 'Category', 'Amount', 'Status'];
        var lines = [header.join(',')];
        rows.forEach(function(r) {
            lines.push([
                '"' + (r.id || '') + '"',
                '"' + (r.location_serial || '') + '"',
                '"' + (r.payment_date || '') + '"',
                '"' + (r.receipt_number || '') + '"',
                '"' + (r.payment_name || '') + '"',
                '"' + Number(r.amount || 0).toFixed(2) + '"',
                '"' + ((r.status == 1) ? 'Active' : 'Inactive') + '"'
            ].join(','));
        });
        var blob = new Blob([lines.join('\n')], {
            type: 'text/csv;charset=utf-8;'
        });
        var url = URL.createObjectURL(blob);
        var a = document.createElement('a');
        a.href = url;
        a.download = 'other_payments.csv';
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        URL.revokeObjectURL(url);
    }



    $('#btnApplyFilter').on('click', function() {
        fetchData(false);
    });
    $('#btnExport').on('click', function() {
        fetchData(true);
    });

    $('#btnAddPayment').on('click', function() {
        $('#pay_id').val('');
        $('#paymentModalTitle').text('Add Payment');
        $('#paymentForm')[0].reset();
        $('#payment_date').val('<?= $today ?>');
        $('#paymentModal').modal('show');
    });

    $('#example').on('click', '.btn-edit', function() {
        if (!tableInstance) return;
        var rowData = tableInstance.row($(this).closest('tr')).data();
        if (!rowData) return;
        $('#pay_id').val(rowData.id);
        $('#pay_cat_id').val(rowData.pay_cat_id);
        $('#receipt_number').val(rowData.receipt_number || '');
        $('#amount').val(rowData.amount);
        $('#payment_date').val(rowData.payment_date || '<?= $today ?>');
        $('#paymentModalTitle').text('Edit Payment');
        $('#paymentModal').modal('show');
    });

    $('#example').on('click', '.btn-del', function() {
        var id = $(this).data('id');
        Swal.fire({
            icon: 'warning',
            title: 'Delete payment?',
            showCancelButton: true
        }).then(function(res) {
            if (!res.isConfirmed) return;
            $.post('payment_ajax/payment_records.php', {
                action: 'delete',
                id: id
            }, function(resp) {
                if (resp && resp.success) {
                    fetchData(false);
                    Swal.fire('Deleted', resp.message || 'Removed', 'success');
                } else {
                    Swal.fire('Error', (resp && resp.message) || 'Delete failed',
                        'error');
                }
            }, 'json');
        });
    });

    $('#paymentForm').on('submit', function(e) {
        e.preventDefault();
        var id = $('#pay_id').val();
        var data = $(this).serializeArray();
        data.push({
            name: 'action',
            value: id ? 'update' : 'create'
        });
        data.push({
            name: 'location_id',
            value: LOCATION_ID
        });
        $.post('payment_ajax/payment_records.php', data, function(resp) {
            if (resp && resp.success) {
                $('#paymentModal').modal('hide');
                // Reload page to ensure fresh totals/table
                window.location.reload();
            } else {
                Swal.fire('Error', (resp && resp.message) || 'Save failed', 'error');
            }
        }, 'json');
    });

    fetchData(false);
    $(window).on('load', function() {
        fetchData(false);
    });
});
</script>

<style>
.inactive-row {
    background-color: #F29583 !important;
}
</style>

<?php include 'footer.php'; ?>
