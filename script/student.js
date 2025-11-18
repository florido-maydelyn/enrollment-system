let currentSort = { column: null, ascending: true };
let studentData = [];
let studentModal;

document.addEventListener("DOMContentLoaded", () => {
  studentModal = new bootstrap.Modal(document.getElementById("studentModal"));
  loadPrograms();
  loadStudents();
});

// Open modal for ADD
function openAddStudentModal() {
  document.getElementById("studentModalLabel").textContent = "Add Student";
  resetForm();
  studentModal.show();
}

// Open modal for EDIT
function editStudent(s) {
  document.getElementById("studentModalLabel").textContent = "Edit Student";
  document.getElementById("student_id").value = s.student_id;
  document.getElementById("student_no").value = s.student_no;
  document.getElementById("last_name").value = s.last_name;
  document.getElementById("first_name").value = s.first_name;
  document.getElementById("email").value = s.email;
  document.getElementById("gender").value = s.gender;
  document.getElementById("birthdate").value = s.birthdate;
  document.getElementById("year_level").value = s.year_level;
  document.getElementById("program_id").value = s.program_id;
  studentModal.show();
}

// Save (Add / Edit)
document.getElementById("saveStudentBtn").addEventListener("click", async () => {
  const form = document.getElementById("studentForm");
  const formData = new FormData(form);
  const id = document.getElementById("student_id").value;
  formData.append("action", id ? "edit" : "add");

  const res = await fetch("../php/student_api.php", { method: "POST", body: formData });
  const data = await res.json();

  if (data.success) {
    document.activeElement.blur();
    studentModal.hide();
    if (id) {
      alert("Student updated successfully!");
      loadStudents();
    } else {
      alert("Student added successfully!");
      addStudentToTableTop(data.student);
    }
  } else {
    alert(data.error || "Something went wrong while saving the student.");
  }
});

function addStudentToTableTop(student) {
  const tableBody = document.querySelector("#studentTable tbody");
  studentData.unshift(student);
  renderTable(studentData);

  const firstRow = tableBody.querySelector("tr");
  if (firstRow) {
    firstRow.classList.add("table-success");
    setTimeout(() => firstRow.classList.remove("table-success"), 1000);
  }
}

// Cancel button
document.getElementById("cancelStudentBtn").addEventListener("click", () => {
  document.activeElement.blur();
  studentModal.hide();
});

// Load all students
async function loadStudents() {
  const res = await fetch("../php/student_api.php?action=list");
  const data = await res.json();
  renderTable(data);
  loadPrograms(); // refresh program list
}

// Load programs for dropdown
async function loadPrograms() {
  const res = await fetch("../php/student_api.php?action=get_programs");
  const data = await res.json();

  const select = document.getElementById("program_id");
  if (!select) return;

  select.innerHTML = `<option value="" disabled selected>Select a program</option>`;
  data.forEach(p => {
    const option = document.createElement("option");
    option.value = p.program_id;
    option.textContent = p.program_name;
    select.appendChild(option);
  });
}

// Search students
async function searchStudent() {
  const q = document.getElementById("searchBox").value;
  const res = await fetch("../php/student_api.php?action=search&q=" + encodeURIComponent(q));
  const data = await res.json();
  renderTable(data);
}

// Show all programs
function showAllStudents() {
  const searchBox = document.getElementById("searchBox");
  if (searchBox.value.trim() !== "") {
    searchBox.value = "";
  }
  loadStudents();
}

// Render table
function renderTable(data) {
  studentData = data;
  const tbody = document.querySelector("#studentTable tbody");
  tbody.innerHTML = "";

  data.forEach(s => {
    const row = document.createElement("tr");
    row.innerHTML = `
      <td>${s.student_no}</td>
      <td>${s.last_name}</td>
      <td>${s.first_name}</td>
      <td>${s.email}</td>
      <td>${s.gender}</td>
      <td>${s.birthdate}</td>
      <td>${s.year_level}</td>
      <td>${s.program_name}</td>
      <td>
        <button class="btn btn-warning btn-sm" onclick='editStudent(${JSON.stringify(s)})'>Edit</button>
        <button class="btn btn-danger btn-sm" onclick='deleteStudent(${s.student_id})'>Delete</button>
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

  studentData.sort((a, b) => {
    const valA = a[columnKey]?.toString().toLowerCase() ?? "";
    const valB = b[columnKey]?.toString().toLowerCase() ?? "";

    if (valA < valB) return currentSort.ascending ? -1 : 1;
    if (valA > valB) return currentSort.ascending ? 1 : -1;
    return 0;
  });

  renderTable(studentData);

  document.querySelectorAll("#studentTable th").forEach(th => th.classList.remove("sorted-asc", "sorted-desc"));
  const th = document.querySelector(`#studentTable th[data-key="${columnKey}"]`);
  if (th) th.classList.add(currentSort.ascending ? "sorted-asc" : "sorted-desc");
}

// Delete student (soft delete)
async function deleteStudent(id) {
  if (!confirm("Are you sure you want to delete this student?")) return;
  const formData = new FormData();
  formData.append("action", "delete");
  formData.append("student_id", id);
  const res = await fetch("../php/student_api.php", { method: "POST", body: formData });
  const data = await res.json();
  if (data.success) {
    alert("Student deleted successfully!");
    loadStudents();
  } else {
    alert("Failed to delete student.");
  }
}

// Reset form
function resetForm() {
  document.getElementById("studentForm").reset();
  document.getElementById("student_id").value = "";
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
  window.open("../php/student_api.php?action=export&type=" + type, "_blank");
}
