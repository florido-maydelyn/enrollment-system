let currentSort = { column: null, ascending: true };
let programData = [];
let programModal;

// Initialize modal & load data
document.addEventListener("DOMContentLoaded", () => {
  programModal = new bootstrap.Modal(document.getElementById("programModal"));
  loadDepartments();
  loadPrograms();
});

// Open modal for ADD
function openAddProgramModal() {
  document.getElementById("programModalLabel").textContent = "Add Program";
  document.getElementById("programForm").reset();
  document.getElementById("program_id").value = "";
  programModal.show();
}

// Open modal for EDIT
function editProgram(p) {
  console.log(p);
  document.getElementById("programModalLabel").textContent = "Edit Program";
  document.getElementById("program_id").value = p.program_id;
  document.getElementById("program_code").value = p.program_code;
  document.getElementById("program_name").value = p.program_name;
  document.getElementById("dept_id").value = p.dept_id;
  programModal.show();
}

// Save (Add / Edit)
document.getElementById("saveProgramBtn").addEventListener("click", async () => {
  const form = document.getElementById("programForm");
  const formData = new FormData(form);
  const id = document.getElementById("program_id").value;
  formData.append("action", id ? "edit" : "add");

  const res = await fetch("../php/program_api.php", { method: "POST", body: formData });
  const data = await res.json();

  if (data.success) {
    document.activeElement.blur();
    programModal.hide();

    if (id) {
      alert("Program updated successfully!");
      loadPrograms();
    } else {
      alert("Program added successfully!");
      addProgramToTableTop(data.program);
    }
  } else {
    alert(data.error || "Something went wrong while saving the program.");
  }
});

// Add newly created program to top of table
function addProgramToTableTop(program) {
  const tableBody = document.querySelector("#programTable tbody");
  programData.unshift(program);

  renderTable(programData);

  const firstRow = tableBody.querySelector("tr");
  if (firstRow) {
    firstRow.classList.add("table-success");
    setTimeout(() => firstRow.classList.remove("table-success"), 1000);
  }
}

// Cancel button
document.getElementById("cancelProgramBtn").addEventListener("click", () => {
  document.activeElement.blur();
  programModal.hide();
});

// Show all programs
function showAllPrograms() {
  const searchBox = document.getElementById("searchBox");
  if (searchBox.value.trim() !== "") {
    searchBox.value = "";
  }
  loadPrograms();
}

// Load all programs
async function loadPrograms() {
  const res = await fetch("../php/program_api.php?action=list");
  const data = await res.json();
  renderTable(data);
}

// Load departments for dropdown
async function loadDepartments() {
  const res = await fetch("../php/program_api.php?action=get_departments");
  const data = await res.json();

  const selects = [document.getElementById("dept_id")];
  selects.forEach(select => {
    if (!select) return;
    select.innerHTML = `<option value="" disabled selected>Select a department</option>`;
    data.forEach(d => {
      const option = document.createElement("option");
      option.value = d.dept_id;
      option.textContent = d.department;
      select.appendChild(option);
    });
  });
}

// Search programs
async function searchProgram() {
  const q = document.getElementById("searchBox").value;
  const res = await fetch("../php/program_api.php?action=search&q=" + encodeURIComponent(q));
  const data = await res.json();
  renderTable(data);
}

// Render program table
function renderTable(data) {
  programData = data;

  const tbody = document.querySelector("#programTable tbody");
  tbody.innerHTML = "";

  data.forEach(p => {
    const row = document.createElement("tr");
    row.innerHTML = `
      <td>${p.program_code}</td>
      <td>${p.program_name}</td>
      <td>${p.dept_name}</td>
      <td>
        <button class="btn btn-warning btn-sm" onclick='editProgram(${JSON.stringify(p)})'>Edit</button>
        <button class="btn btn-danger btn-sm" onclick='deleteProgram(${p.program_id})'>Delete</button>
      </td>
    `;
    tbody.appendChild(row);
  });
}

// Sort table
function sortTableBy(columnKey) {
  if (currentSort.column === columnKey) {
    currentSort.ascending = !currentSort.ascending;
  } else {
    currentSort.column = columnKey;
    currentSort.ascending = true;
  }

  programData.sort((a, b) => {
    const valA = a[columnKey]?.toString().toLowerCase() ?? "";
    const valB = b[columnKey]?.toString().toLowerCase() ?? "";

    if (valA < valB) return currentSort.ascending ? -1 : 1;
    if (valA > valB) return currentSort.ascending ? 1 : -1;
    return 0;
  });

  renderTable(programData);

  document.querySelectorAll("#programTable th").forEach(th => th.classList.remove("sorted-asc", "sorted-desc"));
  const th = document.querySelector(`#programTable th[data-key="${columnKey}"]`);
  if (th) th.classList.add(currentSort.ascending ? "sorted-asc" : "sorted-desc");
}

// Delete program (soft delete)
async function deleteProgram(id) {
  if (!confirm("Are you sure you want to delete this program?")) return;
  const formData = new FormData();
  formData.append("action", "delete");
  formData.append("program_id", id);
  const res = await fetch("../php/program_api.php", { method: "POST", body: formData });
  const data = await res.json();
  if (data.success) {
    alert("Program deleted successfully!");
    loadPrograms();
  } else {
    alert("Failed to delete program.");
  }
}

// Reset form
function resetForm() {
  document.getElementById("programForm").reset();
  document.getElementById("program_id").value = "";
}

// Toggle export menu
function toggleExportMenu() {
  const menu = document.getElementById("exportMenu");
  menu.style.display = menu.style.display === "block" ? "none" : "block";
}

// Close dropdown if clicked outside
window.addEventListener("click", e => {
  if (!e.target.matches(".btn-success") && !e.target.closest("#exportMenu")) {
    document.getElementById("exportMenu").style.display = "none";
  }
});

// Export data
function exportData(type) {
  window.open("../php/program_api.php?action=export&type=" + type, "_blank");
}

loadDepartments();
loadPrograms();