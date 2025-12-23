// $(document).ready(function() {
//     // Initialize DataTables
//     var table = $('#example').DataTable();

//     $('#exportButton').on('click', function() {
//         // Get the file name from the button's attribute
//         var fileName = $(this).attr('filename');

//         // If no file name is provided, use a default file name
//         if (!fileName) {
//             fileName = 'table.xlsx';
//         }

//         // Ensure the file name ends with .xlsx
//         if (!fileName.endsWith('.xlsx')) {
//             fileName += '.xlsx';
//         }

//         // Retrieve all data from the table
//         var data = table.rows().data().toArray();

//         // Create a temporary table element
//         var tempTable = $('<table></table>');

//         // Create header row
//         var header = '<tr>';
//         $('#example thead th').each(function() {
//             var text = $(this).text();
//             if (text.toLowerCase() !== 'action') { // Exclude "Action" column
//                 header += '<th>' + text + '</th>';
//             }
//         });
//         header += '</tr>';
//         tempTable.append(header);

//         // Create data rows
//         data.forEach(function(rowData) {

//             var row = '<tr>';
//             rowData.forEach(function(cellData, index) {
//                 var colHeader = $('#example thead th').eq(index).text().toLowerCase();

//                     if (colHeader !== 'action') { // Exclude "Action" column

//                         if (cellData.includes("C") && colHeader.includes("total")) {
//                             row += '<td>' + 0 + '</td>';
//                         } else {
//                             row += '<td>' + cellData + '</td>';
//                         }

//                 }
//             });
//             row += '</tr>';
//             tempTable.append(row);

//         });

//         // Convert the temporary table to a worksheet
//         var workbook = XLSX.utils.table_to_book(tempTable[0], { sheet: "Sheet1" });

//         // Export the workbook to Excel file with the specified file name
//         XLSX.writeFile(workbook, fileName);
//     });
// });

// ============================== test script 2

// $(document).ready(function () {
//   var table = $("#example").DataTable();

//   $("#exportButton").on("click", function () {
//     var fileName = $(this).attr("filename") || "table.xlsx";

//     if (!fileName.endsWith(".xlsx")) {
//       fileName += ".xlsx";
//     }

//     var tempTable = $("<table></table>");

//     // Create header row
//     var header = "<tr>";
//     $("#example thead th").each(function () {
//       var text = $(this).text();
//       if (text.toLowerCase() !== "action") {
//         header += "<th>" + text + "</th>";
//       }
//     });
//     header += "</tr>";
//     tempTable.append(header);

//     // Loop through table rows
//     $("#example tbody tr").each(function () {
//       // Skip cancelled rows
//       if ($(this).hasClass("cancelled-row")) {
//         return; // continue to next row
//       }

//       var row = "<tr>";
//       $(this)
//         .find("td")
//         .each(function (index) {
//           var colHeader = $("#example thead th").eq(index).text().toLowerCase();
//           if (colHeader !== "action") {
//             var cellData = $(this).text().trim();

//             if (cellData.includes("C") && colHeader.includes("total")) {
//               row += "<td>" + 0 + "</td>";
//             } else {
//               row += "<td>" + cellData + "</td>";
//             }
//           }
//         });
//       row += "</tr>";
//       tempTable.append(row);
//     });

//     var workbook = XLSX.utils.table_to_book(tempTable[0], { sheet: "Sheet1" });
//     XLSX.writeFile(workbook, fileName);
//   });
// });



$(document).ready(function () {
  var table = $("#example").DataTable();

  $("#exportButton").on("click", function () {
    var fileName = $(this).attr("filename") || "table.xlsx";
    if (!fileName.endsWith(".xlsx")) fileName += ".xlsx";

    var tempTable = $("<table></table>");

    // Build the header
    var header = "<tr>";
    $("#example thead th").each(function () {
      var text = $(this).text();
      if (text.toLowerCase() !== "action") {
        header += "<th>" + text + "</th>";
      }
    });
    header += "</tr>";
    tempTable.append(header);

    // Loop through all data rows on all pages (not just visible ones)
    table.rows({ search: "applied" }).every(function () {
      var rowNode = this.node(); // actual <tr> element

      // ❌ Skip if it's a cancelled row
      if ($(rowNode).hasClass("cancelled-row")) return;

      var data = this.data();
      var row = "<tr>";

      $("#example thead th").each(function (index) {
        var colHeader = $(this).text().toLowerCase();
        if (colHeader !== "action") {
          var cell = $("<div>").html(data[index]).text().trim(); // strip HTML

          // Optional: If column is a total and has "C", set to 0
          if (cell.includes("C") && colHeader.includes("total")) {
            row += "<td>0</td>";
          } else {
            row += "<td>" + cell + "</td>";
          }
        }
      });

      row += "</tr>";
      tempTable.append(row);
    });

    // Create workbook and export
    var workbook = XLSX.utils.table_to_book(tempTable[0], { sheet: "Sheet1" });
    XLSX.writeFile(workbook, fileName);
  });
});




// $(document).ready(function () {
//   var table = $("#example").DataTable();

//   $("#exportButton").on("click", function () {
//     var fileName = $(this).attr("filename") || "table.xlsx";
//     if (!fileName.endsWith(".xlsx")) fileName += ".xlsx";

//     var tempTable = $("<table></table>");

//     // Build the header
//     var header = "<tr>";
//     $("#example thead th").each(function () {
//       var text = $(this).text();
//       if (text.toLowerCase() !== "action") {
//         header += "<th>" + text + "</th>";
//       }
//     });
//     header += "</tr>";
//     tempTable.append(header);

//     // Loop through all data rows (not just visible ones)
//     table.rows({ search: "applied" }).every(function () {
//       var data = this.data();
//       var row = "<tr>";
//       $("#example thead th").each(function (index) {
//         var colHeader = $(this).text().toLowerCase();
//         if (colHeader !== "action") {
//           var cell = $("<div>").html(data[index]).text().trim(); // clean HTML if any
//           if (cell.includes("C") && colHeader.includes("total")) {
//             row += "<td>0</td>";
//           } else {
//             row += "<td>" + cell + "</td>";
//           }
//         }
//       });
//       row += "</tr>";
//       tempTable.append(row);
//     });

//     var workbook = XLSX.utils.table_to_book(tempTable[0], { sheet: "Sheet1" });
//     XLSX.writeFile(workbook, fileName);
//   });
// });
