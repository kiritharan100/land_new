(function() {
  "use strict";

  var form,
    saveBtn,
    editBtn,
    leaseIdInput,
    startInput,
    endInput,
    basisSelect,
    firstSelect,
    valuationInput,
    valuvationDateInput,
    valuvationLetterInput,
    annualPctInput,
    incomeInput,
    initialRentInput,
    discountInput,
    penaltyInput;

  var initialized = false;

  var alwaysReadOnly = [
    "rl_discount_rate",
    "rl_penalty_rate",
    "rl_end_date",
  ];

  function showAlert(type, title, text) {
    if (window.Swal) {
      Swal.fire({ icon: type === "success" ? "success" : "error", title: title, text: text || "" });
    } else {
      alert(title + (text ? ": " + text : ""));
    }
  }

  function parseFloatSafe(v) {
    var n = parseFloat(v);
    return isNaN(n) ? 0 : n;
  }

  function setEndDate(force) {
    if (!startInput || !endInput || !startInput.value) return;
    var d = new Date(startInput.value);
    if (isNaN(d.getTime())) return;
    d.setFullYear(d.getFullYear() + 30);
    if (force || !endInput.value) {
      endInput.value = d.toISOString().split("T")[0];
    }
  }

  function toggleFirstLeaseRequired() {
    if (!firstSelect) return;
    var isFirst = firstSelect.value === "1";
    var basisRaw = basisSelect ? (basisSelect.value || "").trim().toLowerCase() : "";
    var isValBasis = basisRaw.indexOf("valu") === 0;
    var requireValuation = isFirst && isValBasis;
    if (valuationInput) {
      valuationInput.required = requireValuation;
      if (!requireValuation) valuationInput.removeAttribute("required");
    }
    if (valuvationDateInput) {
      valuvationDateInput.required = requireValuation;
      if (!requireValuation) valuvationDateInput.removeAttribute("required");
    }
    if (valuvationLetterInput) {
      valuvationLetterInput.required = false;
    }
    if (initialRentInput) {
      initialRentInput.readOnly = isFirst;
      initialRentInput.disabled = false;
    }
  }

  function toggleBasisRequired() {
    var basis = basisSelect ? basisSelect.value : "";
    var isValBasis = basis === "Valuvation basis" || basis === "Valuation basis";
    var isIncomeBasis = basis === "Income basis";
    if (annualPctInput) {
      annualPctInput.required = isValBasis;
      if (!isValBasis && !annualPctInput.required) {
        annualPctInput.removeAttribute("required");
      }
    }
    if (incomeInput) {
      incomeInput.required = isIncomeBasis;
      if (!isIncomeBasis && !incomeInput.required) {
        incomeInput.removeAttribute("required");
      }
    }
    // valuation fields required only when first lease AND valuation basis
    toggleFirstLeaseRequired();
  }

  function computeInitialRent() {
    var basisRaw = basisSelect ? (basisSelect.value || "").trim() : "";
    var basisLc = basisRaw.toLowerCase();
    var isValBasis = basisLc.indexOf("valu") === 0; // matches Valuvation / Valuation
    var isIncomeBasis = basisLc.indexOf("income") === 0;
    var isFirst = firstSelect ? firstSelect.value === "1" : true;
    var initial = 0;

    // If not first lease, allow manual edit and do not override user input unless empty
    if (!isFirst) {
      if (initialRentInput && initialRentInput.value === "") {
        // leave as blank; user can type or basis change will not override
      } else {
        return;
      }
    }

    if (isValBasis) {
      var val = parseFloatSafe(valuationInput ? valuationInput.value : 0);
      var pct = parseFloatSafe(annualPctInput ? annualPctInput.value : 0);
      if (val > 0 && pct > 0) {
        initial = val * (pct / 100);
      }
    } else if (isIncomeBasis) {
      var income = parseFloatSafe(incomeInput ? incomeInput.value : 0);
      if (income > 0) {
        initial = income * 0.05;
        if (initial > 1000) initial = 1000;
      }
    }

    if (initialRentInput) {
      if (initial > 0) {
        initialRentInput.value = initial.toFixed(2);
      } else if (basisRaw) {
        initialRentInput.value = "0.00";
      } else {
        initialRentInput.value = "";
      }
    }
  }

  function lockReadOnlyFields() {
    alwaysReadOnly.forEach(function(id) {
      var el = document.getElementById(id);
      if (el) {
        el.readOnly = true;
        el.disabled = false; // keep in form submission
      }
    });
  }

  function disableForm(disabled) {
    if (!form) return;
    Array.prototype.forEach.call(
      form.querySelectorAll("input, select, textarea"),
      function(el) {
        if (el.type === "hidden") return;
        if (alwaysReadOnly.indexOf(el.id) !== -1) {
          el.readOnly = true;
          el.disabled = false;
          return;
        }
        if (disabled) {
          el.setAttribute("disabled", "disabled");
        } else {
          el.removeAttribute("disabled");
          el.removeAttribute("readonly");
        }
      }
    );
    lockReadOnlyFields();
  }

  function fetchNumbersIfNeeded(force) {
    if (!document.getElementById("rl_lease_number")) return;
    var existing = leaseIdInput && leaseIdInput.value;
    var leaseField = document.getElementById("rl_lease_number");
    var fileField = document.getElementById("rl_file_number");
    if (!leaseField || !fileField) return;
    if (!force && existing) return;

    var leaseVal = (leaseField.value || "").trim();
    var fileVal = (fileField.value || "").trim();
    var applyLease =
      force ||
      leaseVal === "" ||
      leaseVal.toLowerCase() === "pending" ||
      leaseVal.toLowerCase() === "generate";
    var applyFile =
      force ||
      fileVal === "" ||
      fileVal.toLowerCase() === "pending" ||
      fileVal.toLowerCase() === "generate";

    var locField = document.querySelector('input[name="location_id"]');
    var locId = locField ? (locField.value || "").trim() : "";
    var url = "ajax_residential_lease/generate_res_lease_number.php";
    if (locId) {
      url += "?location_id=" + encodeURIComponent(locId);
    }

    fetch(url)
      .then(function(r) {
        return r.json();
      })
      .then(function(resp) {
        if (resp && resp.success) {
          if (resp.lease_number && applyLease) leaseField.value = resp.lease_number;
          if (resp.file_number && applyFile) fileField.value = resp.file_number;
        }
      })
      .catch(function() {});
  }

  function onSubmit(e) {
    if (e && typeof e.preventDefault === "function") {
      e.preventDefault();
      if (typeof e.stopPropagation === "function") {
        e.stopPropagation();
      }
    }
    if (!form) {
      // Attempt late binding in case init did not run before submit
      form = document.getElementById("rlCreateLeaseForm");
      leaseIdInput = document.getElementById("rl_lease_id");
      saveBtn = document.getElementById("rl_save_btn");
      editBtn = document.getElementById("rl_edit_btn");
      startInput = document.getElementById("rl_start_date");
      endInput = document.getElementById("rl_end_date");
      basisSelect = document.getElementById("rl_lease_calculation_basic");
      firstSelect = document.getElementById("rl_is_first_lease");
      valuationInput = document.getElementById("rl_valuation_amount");
      annualPctInput = document.getElementById("rl_annual_rent_percentage");
      incomeInput = document.getElementById("rl_ben_income");
      initialRentInput = document.getElementById("rl_initial_annual_rent");
      discountInput = document.getElementById("rl_discount_rate");
      penaltyInput = document.getElementById("rl_penalty_rate");
      valuvationDateInput = document.getElementById("rl_valuvation_date");
      valuvationLetterInput = document.getElementById("rl_valuvation_letter_date");
      if (!form) {
        showAlert("error", "Form not ready", "Reload the tab and try again.");
        return false;
      }
    }
    var isUpdate = !!(leaseIdInput && leaseIdInput.value);
    var url = isUpdate
      ? "payment_ajax/update_res_lease.php"
      : "payment_ajax/create_res_lease.php";
    if (saveBtn) {
      saveBtn.disabled = true;
      saveBtn.innerHTML = isUpdate ? "Updating..." : "Saving...";
    }

    var params = new URLSearchParams(new FormData(form));

    fetch(url, {
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
          showAlert("success", "Success", resp.message || "Saved");

          // If created, attach lease id so subsequent saves are updates
          if (!isUpdate && resp.rl_lease_id) {
            var hid = document.createElement("input");
            hid.type = "hidden";
            hid.name = "rl_lease_id";
            hid.id = "rl_lease_id";
            hid.value = String(resp.rl_lease_id);
            form.appendChild(hid);
            leaseIdInput = hid;
            var nav = document.querySelector(
              '#submenu-list a[data-target="#create_leases"]'
            );
            if (nav) nav.textContent = "Manage Leases";
            var heading = document.querySelector("#create_leases h5");
            if (heading) heading.textContent = "Manage Lease";
          }

          disableForm(true);
          if (saveBtn) saveBtn.classList.add("d-none");
          if (editBtn) editBtn.classList.remove("d-none");
        } else {
          var msg = (resp && resp.message) || "Failed to save";
          showAlert("error", "Error", msg);
        }
      })
      .catch(function(err) {
        showAlert("error", "Server error", (err && err.message) || "Failed to reach server");
      })
      .finally(function() {
        if (saveBtn) {
          saveBtn.disabled = false;
          saveBtn.innerHTML = leaseIdInput && leaseIdInput.value
            ? "Update Lease"
            : "Create Lease";
        }
      });
  }

  function enableEdit() {
    var proceed = function() {
      disableForm(false);
      lockReadOnlyFields();
      if (saveBtn) {
        saveBtn.classList.remove("d-none");
        saveBtn.innerHTML = "Update Lease";
      }
      if (editBtn) editBtn.classList.add("d-none");
      toggleFirstLeaseRequired();
      toggleBasisRequired();
      computeInitialRent();
    };

    if (window.Swal) {
      Swal.fire({
        icon: "warning",
        title: "Enable editing?",
        text: "This lease already exists. Unlock fields to update it?",
        showCancelButton: true,
        confirmButtonText: "Yes, Edit",
      }).then(function(res) {
        if (res.isConfirmed) proceed();
      });
    } else if (confirm("Enable editing for this lease?")) {
      proceed();
    }
  }

  function attachListeners() {
    if (startInput) {
      startInput.addEventListener("change", function() {
        setEndDate(true);
      });
    }
    if (basisSelect) {
      basisSelect.addEventListener("change", function() {
        toggleBasisRequired();
        computeInitialRent();
      });
      basisSelect.addEventListener("input", computeInitialRent);
    }
    if (firstSelect) {
      firstSelect.addEventListener("change", function() {
        toggleFirstLeaseRequired();
        computeInitialRent();
      });
      firstSelect.addEventListener("input", computeInitialRent);
    }
    [valuationInput, annualPctInput, incomeInput].forEach(function(el) {
      if (!el) return;
      el.addEventListener("input", computeInitialRent);
      el.addEventListener("change", computeInitialRent);
      el.addEventListener("blur", computeInitialRent);
      el.addEventListener("keyup", computeInitialRent);
    });
    if (form) {
      if (!form.getAttribute("data-rl-bound")) {
        form.addEventListener("submit", onSubmit);
        // form-wide listeners to catch any missed recalculation
        form.addEventListener("input", computeInitialRent);
        form.addEventListener("change", computeInitialRent);
        form.addEventListener("keyup", computeInitialRent);
        form.setAttribute("data-rl-bound", "1");
      }
    }
    if (editBtn) {
      editBtn.addEventListener("click", enableEdit);
    }
  }

  function init(opts) {
    if (initialized) return;
    form = document.getElementById("rlCreateLeaseForm");
    if (!form) return;
    initialized = true;

    leaseIdInput = document.getElementById("rl_lease_id");
    saveBtn = document.getElementById("rl_save_btn");
    editBtn = document.getElementById("rl_edit_btn");
    startInput = document.getElementById("rl_start_date");
    endInput = document.getElementById("rl_end_date");
    basisSelect = document.getElementById("rl_lease_calculation_basic");
    firstSelect = document.getElementById("rl_is_first_lease");
    valuationInput = document.getElementById("rl_valuation_amount");
    valuvationDateInput = document.getElementById("rl_valuvation_date");
    valuvationLetterInput = document.getElementById("rl_valuvation_letter_date");
    annualPctInput = document.getElementById("rl_annual_rent_percentage");
    incomeInput = document.getElementById("rl_ben_income");
    initialRentInput = document.getElementById("rl_initial_annual_rent");
    discountInput = document.getElementById("rl_discount_rate");
    penaltyInput = document.getElementById("rl_penalty_rate");

    lockReadOnlyFields();
    toggleFirstLeaseRequired();
    toggleBasisRequired();
    computeInitialRent();
    setEndDate(false);
    // run once more after a short delay in case values are populated asynchronously
    setTimeout(computeInitialRent, 50);
    // periodic safeguard to recompute if fields change outside listeners
    var lastSig = "";
    setInterval(function() {
      var sig = [
        basisSelect ? basisSelect.value : "",
        valuationInput ? valuationInput.value : "",
        annualPctInput ? annualPctInput.value : "",
        incomeInput ? incomeInput.value : ""
      ].join("|");
      if (sig !== lastSig) {
        lastSig = sig;
        computeInitialRent();
      }
    }, 500);

    var hasExisting = opts && opts.hasExisting;
    if (hasExisting) {
      disableForm(true);
      if (saveBtn) saveBtn.classList.add("d-none");
      if (editBtn) editBtn.classList.remove("d-none");
    } else {
      fetchNumbersIfNeeded(true);
      disableForm(false);
      if (saveBtn) saveBtn.classList.remove("d-none");
      if (editBtn) editBtn.classList.add("d-none");
    }

    attachListeners();
  }

  function submitDirect(ev) {
    return onSubmit(ev || { preventDefault: function() {}, stopPropagation: function() {} });
  }

  window.RLLeaseForm = {
    init: init,
    submitDirect: submitDirect,
    recompute: computeInitialRent,
    numbers: fetchNumbersIfNeeded,
  };
})();
