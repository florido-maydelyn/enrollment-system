let currentSort = { column: null, ascending: true };
let instructorData = [];
let instructorModal;

// Initialize modal and data
document.addEventListener("DOMContentLoaded", () => {
  instructorModal = new bootstrap.Modal(document.getElementById("instructorModal"));
  loadDepartments();
  loadInstructors();
});

// Open modal for ADD
function openAddInstructorModal() {
  document.getElementById("instructorModalLabel").textContent = "Add Instructor";
  document.getElementById("instructorForm").reset();
  document.getElementById("instructor_id").value = "";
  instructorModal.show();
}

// Open modal for EDIT
function editInstructor(i) {
  document.getElementById("instructorModalLabel").textContent = "Edit Instructor";
  document.getElementById("instructor_id").value = i.instructor_id;
  document.getElementById("first_name").value = i.first_name;
  document.getElementById("last_name").value = i.last_name;
  document.getElementById("email").value = i.email;
  document.getElementById("dept_id").value = i.dept_id;
  instructorModal.show();
}

// Save (Add/Edit) Instructor
document.getElementById("saveInstructorBtn").addEventListener("click", async () => {
  const form = document.getElementById("instructorForm");
  const formData = new FormData(form);
  const id = document.getElementById("instructor_id").value;
  formData.append("action", id ? "edit" : "add");

  const res = await fetch("../php/instructor_api.php", { method: "POST", body: formData });
  const data = await res.json();

  if (data.success) {
    document.activeElement.blur();
    instructorModal.hide();

    if (id) {
      alert("Instructor updated successfully!");
      loadInstructors();
    } else {
      alert("Instructor added successfully!");
      addInstructorToTableTop(data.instructor);
    }
  } else {
    alert(data.error || "Something went wrong while saving the instructor.");
  }
});

// Cancel button
document.getElementById("cancelInstructorBtn").addEventListener("click", () => {
  document.activeElement.blur();
  instructorModal.hide();
});

// Add newly created instructor at top of table
function addInstructorToTableTop(instructor) {
  const tableBody = document.querySelector("#instructorTable tbody");
  instructorData.unshift(instructor);
  renderTable(instructorData);

  const firstRow = tableBody.querySelector("tr");
  if (firstRow) {
    firstRow.classList.add("table-success");
    setTimeout(() => firstRow.classList.remove("table-success"), 1000);
  }
}

// Load all instructors
async function loadInstructors() {
  const res = await fetch("../php/instructor_api.php?action=list");
  const data = await res.json();
  renderTable(data);
}

// Load departments
async function loadDepartments() {
  const res = await fetch("../php/instructor_api.php?action=get_departments");
  const data = await res.json();

  const select = document.getElementById("dept_id");
  if (!select) return;

  select.innerHTML = `<option value="" disabled selected>Select a department</option>`;
  data.forEach(d => {
    const option = document.createElement("option");
    option.value = d.dept_id;
    option.textContent = d.department;
    select.appendChild(option);
  });
}

// Search instructors
async function searchInstructor() {
  const q = document.getElementById("searchBox").value;
  const res = await fetch("../php/instructor_api.php?action=search&q=" + encodeURIComponent(q));
  const data = await res.json();
  renderTable(data);
}

// Show all instructors
function showAllInstructors() {
  const searchBox = document.getElementById("searchBox");
  if (searchBox.value.trim() !== "") {
    searchBox.value = "";
  }
  loadInstructors();
}

// Render table
function renderTable(data) {
  instructorData = data;
  const tbody = document.querySelector("#instructorTable tbody");
  tbody.innerHTML = "";

  data.forEach(i => {
    const row = document.createElement("tr");
    row.innerHTML = `
      <td>${i.first_name} ${i.last_name}</td>
      <td>${i.email}</td>
      <td>${i.department}</td>
      <td>
        <button class="btn btn-warning btn-sm" onclick='editInstructor(${JSON.stringify(i)})'>Edit</button>
        <button class="btn btn-danger btn-sm" onclick='deleteInstructor(${i.instructor_id})'>Delete</button>
      </td>
    `;
    tbody.appendChild(row);
  });
}

// Sort table by column
function sortTableBy(columnKey) {
  if (currentSort.column === columnKey) {
    currentSort.ascending = !currentSort.ascending;
  } else {
    currentSort.column = columnKey;
    currentSort.ascending = true;
  }

  instructorData.sort((a, b) => {
    const valA = a[columnKey]?.toString().toLowerCase() ?? "";
    const valB = b[columnKey]?.toString().toLowerCase() ?? "";
    if (valA < valB) return currentSort.ascending ? -1 : 1;
    if (valA > valB) return currentSort.ascending ? 1 : -1;
    return 0;
  });

  renderTable(instructorData);

  document.querySelectorAll("#instructorTable th").forEach(th => th.classList.remove("sorted-asc", "sorted-desc"));
  const th = document.querySelector(`#instructorTable th[data-key="${columnKey}"]`);
  if (th) th.classList.add(currentSort.ascending ? "sorted-asc" : "sorted-desc");
}

// Delete instructor
async function deleteInstructor(id) {
  if (!confirm("Are you sure you want to delete this instructor?")) return;

  const formData = new FormData();
  formData.append("action", "delete");
  formData.append("instructor_id", id);

  const res = await fetch("../php/instructor_api.php", { method: "POST", body: formData });
  const data = await res.json();

  if (data.success) {
    alert("Instructor deleted successfully!");
    loadInstructors();
  } else {
    alert("Failed to delete instructor.");
  }
}

// Reset form
function resetForm() {
  document.getElementById("instructorForm").reset();
  document.getElementById("instructor_id").value = "";
}

// Export menu toggle
function toggleExportMenu() {
  const menu = document.getElementById("exportMenu");
  menu.style.display = menu.style.display === "block" ? "none" : "block";
}

// Hide export menu when clicking outside
window.addEventListener("click", e => {
  if (!e.target.matches(".btn-success") && !e.target.closest("#exportMenu")) {
    document.getElementById("exportMenu").style.display = "none";
  }
});

// Export data
function exportData(type) {
  window.open("../php/instructor_api.php?action=export&type=" + type, "_blank");
}

// Load initial data
loadDepartments();
loadInstructors();