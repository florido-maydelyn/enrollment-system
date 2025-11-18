let currentSort = { column: null, ascending: true };
let departmentData = [];
let deptModal;

document.addEventListener("DOMContentLoaded", () => {
  deptModal = new bootstrap.Modal(document.getElementById("deptModal"));
  loadDepartments();
});

// Open modal for ADD
function openAddDepartmentModal() {
  document.getElementById("deptModalLabel").textContent = "Add Department";
  resetForm();
  deptModal.show();
}

// Open modal for EDIT
function editDepartment(d) {
  document.getElementById("deptModalLabel").textContent = "Edit Department";
  document.getElementById("dept_id").value = d.dept_id;
  document.getElementById("dept_code").value = d.dept_code;
  document.getElementById("dept_name").value = d.dept_name;
  deptModal.show();
}

// Save Department
document.getElementById("saveDeptBtn").addEventListener("click", async () => {
  const form = document.getElementById("deptForm");
  const formData = new FormData(form);
  const id = document.getElementById("dept_id").value;
  formData.append("action", id ? "edit" : "add");

  const res = await fetch("../php/department_api.php", { method: "POST", body: formData });
  const data = await res.json();

  if (data.success) {
    deptModal.hide();
    if (id) {
      alert("Department updated successfully!");
      loadDepartments();
    } else {
      alert("Department added successfully!");
      addDepartmentToTableTop(data.department);
    }
  } else {
    alert(data.error || "Something went wrong while saving the department.");
  }
});

// Add newly created department to table top
function addDepartmentToTableTop(dept) {
  departmentData.unshift(dept);
  renderTable(departmentData);

  const firstRow = document.querySelector("#deptTable tbody tr");
  if (firstRow) {
    firstRow.classList.add("table-success");
    setTimeout(() => firstRow.classList.remove("table-success"), 1000);
  }
}

// Cancel modal
document.getElementById("cancelDeptBtn").addEventListener("click", () => {
  document.activeElement.blur();
  deptModal.hide();
});

// Load Departments
async function loadDepartments() {
  const res = await fetch("../php/department_api.php?action=list");
  const data = await res.json();
  renderTable(data);
}

// Search
async function searchDepartment() {
  const q = document.getElementById("searchBox").value;
  const res = await fetch("../php/department_api.php?action=search&q=" + encodeURIComponent(q));
  const data = await res.json();
  renderTable(data);
}

function showAllDepartments() {
  const searchBox = document.getElementById("searchBox");
  if (searchBox.value.trim() !== "") {
    searchBox.value = "";
  }
  loadDepartments();
}

// Render table
function renderTable(data) {
  departmentData = data;
  const tbody = document.querySelector("#deptTable tbody");
  tbody.innerHTML = "";

  data.forEach(d => {
    const row = document.createElement("tr");
    row.innerHTML = `
      <td>${d.dept_code}</td>
      <td>${d.dept_name}</td>
      <td>
        <button class="btn btn-warning btn-sm" onclick='editDepartment(${JSON.stringify(d)})'>Edit</button>
        <button class="btn btn-danger btn-sm" onclick='deleteDepartment(${d.dept_id})'>Delete</button>
      </td>
    `;
    tbody.appendChild(row);
  });
}

// Sorting
function sortTableBy(columnKey) {
  if (currentSort.column === columnKey) {
    currentSort.ascending = !currentSort.ascending;
  } else {
    currentSort.column = columnKey;
    currentSort.ascending = true;
  }

  departmentData.sort((a, b) => {
    const valA = a[columnKey]?.toString().toLowerCase() ?? "";
    const valB = b[columnKey]?.toString().toLowerCase() ?? "";
    if (valA < valB) return currentSort.ascending ? -1 : 1;
    if (valA > valB) return currentSort.ascending ? 1 : -1;
    return 0;
  });

  renderTable(departmentData);

  document.querySelectorAll("#deptTable th").forEach(th => th.classList.remove("sorted-asc", "sorted-desc"));
  const th = document.querySelector(`#deptTable th[data-key="${columnKey}"]`);
  if (th) th.classList.add(currentSort.ascending ? "sorted-asc" : "sorted-desc");
}

// Delete Department
async function deleteDepartment(id) {
  if (!confirm("Are you sure you want to delete this department?")) return;
  const formData = new FormData();
  formData.append("action", "delete");
  formData.append("dept_id", id);
  const res = await fetch("../php/department_api.php", { method: "POST", body: formData });
  const data = await res.json();
  if (data.success) {
    alert("Department deleted successfully!");
    loadDepartments();
  } else {
    alert("Failed to delete department.");
  }
}

// Reset form
function resetForm() {
  document.getElementById("deptForm").reset();
  document.getElementById("dept_id").value = "";
}

// Export menu
function toggleExportMenu() {
  const menu = document.getElementById("exportMenu");
  menu.style.display = menu.style.display === "block" ? "none" : "block";
}

// Close dropdown on outside click
window.addEventListener("click", e => {
  if (!e.target.matches(".btn-success") && !e.target.closest("#exportMenu")) {
    document.getElementById("exportMenu").style.display = "none";
  }
});

// Export Data
function exportData(type) {
  window.open("../php/department_api.php?action=export&type=" + type, "_blank");
}