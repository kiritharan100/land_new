(function() {
  "use strict";

  var form, saveBtn, leaseIdInput, grantsNumberInput, grantsDateInput;

  function showAlert(type, title, text) {
    if (window.Swal) {
      Swal.fire({ icon: type === "success" ? "success" : "error", title: title, text: text || "" });
    } else {
      alert(title + (text ? ": " + text : ""));
    }
  }

  function onSaveGrant() {
    if (!form) return;

    var leaseId = leaseIdInput ? leaseIdInput.value : "";
    if (!leaseId) {
      showAlert("error", "Error", "Lease ID is missing.");
      return;
    }

    if (saveBtn) {
      saveBtn.disabled = true;
      saveBtn.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Saving...';
    }

    var params = new URLSearchParams(new FormData(form));

    fetch("ajax_residential_lease/update_grant_details.php", {
      method: "POST",
      headers: { "Content-Type": "application/x-www-form-urlencoded" },
      body: params.toString(),
    })
      .then(function(r) {
        return r.json().catch(function() {
          return { success: false, message: "Invalid server response" };
        });
      })
      .then(function(resp) {
        if (resp && resp.success) {
          showAlert("success", "Success", resp.message || "Grant details saved successfully.");
        } else {
          var msg = (resp && resp.message) || "Failed to save grant details.";
          showAlert("error", "Error", msg);
        }
      })
      .catch(function(err) {
        showAlert("error", "Server error", (err && err.message) || "Failed to reach server");
      })
      .finally(function() {
        if (saveBtn) {
          saveBtn.disabled = false;
          saveBtn.innerHTML = '<i class="fa fa-save"></i> Save Grant Details';
        }
      });
  }

  function init() {
    form = document.getElementById("rlGrantDetailsForm");
    if (!form) return;

    leaseIdInput = form.querySelector('input[name="rl_lease_id"]');
    grantsNumberInput = document.getElementById("rl_grant_outright_grants_number");
    grantsDateInput = document.getElementById("rl_grant_outright_grants_date");
    saveBtn = document.getElementById("rl_grant_save_btn");

    if (saveBtn) {
      saveBtn.addEventListener("click", onSaveGrant);
    }
  }

  // Auto-initialize when DOM is ready
  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", init);
  } else {
    init();
  }

  window.RLGrantDetails = {
    init: init,
    save: onSaveGrant
  };
})();



