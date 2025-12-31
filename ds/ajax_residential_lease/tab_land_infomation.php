<?php
require_once dirname(__DIR__, 2) . '/auth.php';
?>
<form id="rl_land_form" class="mb-3">
    <input type="hidden" id="rl_ben_id" name="ben_id" value="<?php echo isset($ben_id) ? (int)$ben_id : 0; ?>">
    <input type="hidden" id="rl_land_id" name="land_id" value="">
    <div class="row">
        <div class="col-md-3">
            <div class="form-group">
                <label for="rl_ds_id">DS Division</label>
                <select id="rl_ds_id" name="ds_id" class="form-control" required>
                    <?php 
            $dsdivs = mysqli_query($con, "SELECT c_id, client_name FROM client_registration WHERE c_id='$location_id' ");
            while($ds = mysqli_fetch_assoc($dsdivs)) {
              echo '<option value="'.htmlspecialchars($ds['c_id']).'">'.htmlspecialchars($ds['client_name']).'</option>';
            }
          ?>
                </select>
            </div>
        </div>
        <div class="col-md-3">
            <div class="form-group">
                <label for="rl_gn_id">GN Division</label>
                <select id="rl_gn_id" name="gn_id" class="form-control" required>
                    <option value="">Select GN Division</option>
                    <?php 
            $gns = mysqli_query($con, "SELECT gn_id, gn_name, gn_no FROM gn_division Where c_id='$location_id' ORDER BY gn_name");
            while($gn = mysqli_fetch_assoc($gns)) {
              echo '<option value="'.htmlspecialchars($gn['gn_id']).'">'.htmlspecialchars($gn['gn_name']).' ('.htmlspecialchars($gn['gn_no']).')</option>';
            }
          ?>
                </select>
            </div>
        </div>
        <div class="col-md-3">
            <div class="form-group">
                <label for="rl_land_address">Land Address</label>
                <input type="text" id="rl_land_address" name="land_address" class="form-control"
                    placeholder="Enter address" required>
            </div>
        </div>
        <div class="col-md-3">
            <div class="form-group">
                <label for="rl_developed_status">Development Status</label>
                <select id="rl_developed_status" name="developed_status" class="form-control">
                    <option value="Not Developed">Not Developed</option>
                    <option value="Partially Developed">Partially Developed</option>
                    <option value="Developed">Developed</option>
                </select>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-md-2">
            <div class="form-group">
                <label for="rl_sketch_plan_no">Sketch Plan No</label>
                <input type="text" id="rl_sketch_plan_no" name="sketch_plan_no" class="form-control">
            </div>
        </div>
        <div class="col-md-2">
            <div class="form-group">
                <label for="rl_plc_plan_no">PLC Plan No</label>
                <input type="text" id="rl_plc_plan_no" name="plc_plan_no" class="form-control">
            </div>
        </div>
        <div class="col-md-2">
            <div class="form-group">
                <label for="rl_survey_plan_no">Survey Plan No</label>
                <input type="text" id="rl_survey_plan_no" name="survey_plan_no" class="form-control">
            </div>
        </div>
        <div class="col-md-2">
            <div class="form-group">
                <label for="rl_extent">Extent</label>
                <input type="number" step="any" id="rl_extent" name="extent" class="form-control"
                    placeholder="Enter extent">
            </div>
        </div>
        <div class="col-md-2">
            <div class="form-group">
                <label for="rl_extent_unit">Unit</label>
                <select class="form-control" id="rl_extent_unit" name="extent_unit">
                    <option value="hectares">Hectares</option>
                    <option value="sqft">Square feet</option>
                    <option value="sqyd">Square yards</option>
                    <option value="perch">Perch</option>
                    <option value="rood">Rood</option>
                    <option value="acre">Acre</option>
                    <option value="cent">Cent</option>
                    <option value="ground">Ground</option>
                    <option value="sqm">Square meters</option>
                </select>
            </div>
        </div>
        <div class="col-md-2">
            <div class="form-group">
                <label for="rl_extent_ha">Hectares</label>
                <input type="text" id="rl_extent_ha" name="extent_ha" class="form-control" placeholder="Ha" readonly>
            </div>
        </div>
        <input type="hidden" id="rl_landBoundary" name="landBoundary" value="">
    </div>

    <div class="row">
        <div class="col-md-6">
            <label class="d-block">Boundary (Lat / Lng)</label>
            <div id="rl_boundary_list"></div>
            <button type="button" class="btn btn-light btn-sm mt-2" id="rl_add_line_btn">
                <i class="fa fa-plus"></i> Add Line
            </button>
        </div>
        <div class="col-md-6">
            <label class="d-block">Map Preview</label>
            <div id="rl_map" style="width:100%;height:320px;border:1px solid #ddd;"></div>
            <small class="text-muted">Click on the map to append a coordinate pair. Use the list to edit values. Polygon
                preview updates automatically.</small>
        </div>
    </div>

    <div class="mt-3 d-flex justify-content-end">
        <button type="button" class="btn btn-secondary mr-2" id="rl_land_edit_btn">Edit</button>
        <button type="submit" class="btn btn-success" id="rl_land_save_btn"><i class="fa fa-save"></i> Save Land
            Information</button>
    </div>
</form>

<script>
(function() {
    var map, polygonLayer;
    var boundaryList = document.getElementById('rl_boundary_list');
    var form = document.getElementById('rl_land_form');

    function addBoundaryRow(lat, lng) {
        var row = document.createElement('div');
        row.className = 'form-row mb-2';
        row.innerHTML = '<div class="col-md-6"><input type="text" class="form-control rl-lat" placeholder="Latitude" value="' +
            (lat || '') + '"></div>' +
            '<div class="col-md-6"><input type="text" class="form-control rl-lng" placeholder="Longitude" value="' + (lng ||
                '') + '"></div>';
        boundaryList.appendChild(row);
    }

    function syncBoundaryJson() {
        var coords = [];
        boundaryList.querySelectorAll('.form-row').forEach(function(row) {
            var lat = row.querySelector('.rl-lat').value.trim();
            var lng = row.querySelector('.rl-lng').value.trim();
            if (lat !== '' && lng !== '' && !isNaN(parseFloat(lat)) && !isNaN(parseFloat(lng))) {
                coords.push([parseFloat(lat), parseFloat(lng)]);
            }
        });
        document.getElementById('rl_landBoundary').value = coords.length ? JSON.stringify(coords) : '';
        drawPolygon(coords);
    }

    function drawPolygon(coords) {
        if (!map) return;
        if (polygonLayer) {
            polygonLayer.remove();
            polygonLayer = null;
        }
        if (coords && coords.length >= 3) {
            polygonLayer = L.polygon(coords, {
                color: '#d9534f',
                fillColor: '#f9d6d5',
                weight: 2,
                fillOpacity: 0.35
            }).addTo(map);
            map.fitBounds(polygonLayer.getBounds());
        }
    }

    function initMap() {
        if (!window.L) return;
        map = L.map('rl_map').setView([8.5711, 81.2335], 10);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 18
        }).addTo(map);

        map.on('click', function(e) {
            addBoundaryRow(e.latlng.lat.toFixed(6), e.latlng.lng.toFixed(6));
            syncBoundaryJson();
        });
    }

    function loadLand() {
        var benId = document.getElementById('rl_ben_id').value || '';
        if (!benId) return;
        $('#rl_land_id, #rl_ben_id').prop('disabled', false);
        $.getJSON('ajax_residential_lease/load_rl_land.php', {
            ben_id: benId
        }, function(resp) {
            if (resp && resp.success && resp.data) {
                var d = resp.data;
                $('#rl_land_id').val(d.land_id || '');
                $('#rl_ds_id').val(d.ds_id || '').trigger('change');
                $('#rl_gn_id').val(d.gn_id || '');
                $('#rl_land_address').val(d.land_address || '');
                $('#rl_developed_status').val(d.developed_status || 'Not Developed');
                $('#rl_sketch_plan_no').val(d.sketch_plan_no || '');
                $('#rl_plc_plan_no').val(d.plc_plan_no || '');
                $('#rl_survey_plan_no').val(d.survey_plan_no || '');
                $('#rl_extent').val(d.extent || '');
                $('#rl_extent_unit').val(d.extent_unit || 'hectares');
                $('#rl_extent_ha').val(d.extent_ha || '');
                boundaryList.innerHTML = '';
                if (d.landBoundary) {
                    try {
                        var arr = JSON.parse(d.landBoundary) || [];
                        arr.forEach(function(pt) {
                            addBoundaryRow(pt[0], pt[1]);
                        });
                        drawPolygon(arr);
                        $('#rl_landBoundary').val(d.landBoundary);
                    } catch (e) {
                        boundaryList.innerHTML = '';
                    }
                }
            }
        });
    }

    function calcHectares() {
        var extent = parseFloat(document.getElementById('rl_extent').value || '0');
        var unit = (document.getElementById('rl_extent_unit').value || 'hectares').toLowerCase();
        var factors = {
            'hectares': 1,
            'sqft': 0.0000092903,
            'sqyd': 0.0000836127,
            'perch': 0.0252929,
            'rood': 0.1011714,
            'acre': 0.4046856,
            'cent': 0.00404686,
            'ground': 0.0023237,
            'sqm': 0.0001
        };
        var ha = extent * (factors[unit] || 1);
        document.getElementById('rl_extent_ha').value = extent ? ha.toFixed(6) : '';
    }

    document.getElementById('rl_extent').addEventListener('input', calcHectares);
    document.getElementById('rl_extent_unit').addEventListener('change', calcHectares);

    document.getElementById('rl_add_line_btn').addEventListener('click', function() {
        addBoundaryRow('', '');
    });

    boundaryList.addEventListener('input', function(e) {
        if (e.target.classList.contains('rl-lat') || e.target.classList.contains('rl-lng')) {
            syncBoundaryJson();
        }
    });

    document.getElementById('rl_land_form').addEventListener('submit', function(e) {
        e.preventDefault();
        syncBoundaryJson();
        var fd = $(this).serialize();
        $('#rl_land_save_btn').prop('disabled', true).text('Saving...');
        $.post('ajax_residential_lease/save_rl_land.php', fd, function(resp) {
            if (resp && resp.success) {
                Swal.fire('Success', resp.message || 'Saved', 'success');
                if (resp.land_id) $('#rl_land_id').val(resp.land_id);
            } else {
                Swal.fire('Error', (resp && resp.message) || 'Failed to save', 'error');
            }
        }, 'json').always(function() {
            $('#rl_land_save_btn').prop('disabled', false).text('Save Land Information');
        });
    });

    document.getElementById('rl_land_edit_btn').addEventListener('click', function() {
        $('#rl_land_form input, #rl_land_form select').prop('disabled', false).prop('readonly', false);
    });

    // init
    addBoundaryRow('', '');
    calcHectares();
    setTimeout(function() {
        if (typeof initMap === 'function') {} // placeholder
        if (window.L) {
            initMap();
        }
    }, 200);
    // Delay load to allow map/init
    setTimeout(loadLand, 300);
    // Enhance GN select with select2 if available
    if (window.jQuery && $('#rl_gn_id').length) {
        $('#rl_gn_id').select2({
            width: '100%',
            dropdownParent: $('#rl_land_form').closest('.modal, body')
        });
    }
})();
</script>
