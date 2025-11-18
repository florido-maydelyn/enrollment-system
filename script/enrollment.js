let currentSort = { column: null, ascending: true };
let enrollmentData = []; // store current data for sorting
let enrollmentModal;

// Initialize the modal once
document.addEventListener("DOMContentLoaded", () => {
  enrollmentModal = new bootstrap.Modal(document.getElementById("enrollmentModal"));
  loadDropdowns();
  loadEnrollments();
});

// Open modal for ADD
function openAddEnrollmentModal() {
  document.getElementById("enrollmentModalLabel").textContent = "Add Enrollment";
  document.getElementById("enrollmentForm").reset();
  document.getElementById("enrollment_id").value = "";
  enrollmentModal.show();
}

// Open modal for EDIT (modifies your existing function)
function editEnrollment(e) {
  document.getElementById("enrollmentModalLabel").textContent = "Edit Enrollment";
  document.getElementById("enrollment_id").value = e.enrollment_id;
  document.getElementById("student_id").value = e.student_id;
  document.getElementById("section_id").value = e.section_id;
  document.getElementById("date_enrolled").value = e.date_enrolled;
  document.getElementById("status").value = e.status;
  document.getElementById("letter_grade").value = e.letter_grade;
  enrollmentModal.show();
}

// Save button click listener (replaces your form submit listener)
document.getElementById("saveEnrollmentBtn").addEventListener("click", async () => {
  const form = document.getElementById("enrollmentForm");
  const formData = new FormData(form);
  const id = document.getElementById("enrollment_id").value;
  formData.append("action", id ? "edit" : "add");

  const res = await fetch("../php/enrollment_api.php", { method: "POST", body: formData });
  const data = await res.json();

  if (data.success) {
    document.activeElement.blur();
    enrollmentModal.hide();

    if (id) {
      alert("Enrollment updated successfully!");
      loadEnrollments(); // Full reload on edit
    } else {
      alert("Enrollment added successfully!");
      // Assumes API returns the new object as 'data.enrollment'
      addEnrollmentToTableTop(data.enrollment);
    }
  } else {
    alert(data.error || "Something went wrong while saving the enrollment.");
  }
});

// Adds new row to top of table without full reload
function addEnrollmentToTableTop(enrollment) {
  const tableBody = document.querySelector("#enrollmentTable tbody");
  enrollmentData.unshift(enrollment);

  renderTable(enrollmentData);

  const firstRow = tableBody.querySelector("tr");
  if (firstRow) {
    firstRow.classList.add("table-success");
    setTimeout(() => firstRow.classList.remove("table-success"), 1000);
  }
}

// Cancel button click listener
document.getElementById("cancelEnrollmentBtn").addEventListener("click", () => {
  document.activeElement.blur();
  enrollmentModal.hide();
});

// Show all (clears search)
function showAllEnrollments() {
  const searchBox = document.getElementById("searchBox");
  if (searchBox.value.trim() !== "") {
    searchBox.value = "";
  }
  loadEnrollments();
}

// Load all enrollments
async function loadEnrollments() {
  const res = await fetch("../php/enrollment_api.php?action=list");
  const data = await res.json();
  renderTable(data);
}

// --- Dropdown Loading Functions (from your original file) ---
async function loadDropdowns() {
  await Promise.all([
    loadStudents(),
    loadSections()
  ]);
}

async function loadStudents() {
  const res = await fetch("../php/enrollment_api.php?action=students");
  const data = await res.json();
  const select = document.getElementById("student_id");
  populateSelect(select, data, "student_id", "student_name", "Select a student");
}

async function loadSections() {
  const res = await fetch("../php/enrollment_api.php?action=sections");
  const data = await res.json();
  const select = document.getElementById("section_id");
  populateSelect(select, data, "section_id", "section_code", "Select a section");
}

function populateSelect(select, data, valueField, textField, defaultText) {
  select.innerHTML = "";
  const def = document.createElement("option");
  def.value = "";
  def.textContent = defaultText;
  select.appendChild(def);
  data.forEach(d => {
    const opt = document.createElement("option");
    opt.value = d[valueField];
    opt.textContent = d[textField];
    select.appendChild(opt);
  });
}
// --- End Dropdown Functions ---

// Search function
async function searchEnrollment() {
  const q = document.getElementById("searchBox").value;
  const res = await fetch("../php/enrollment_api.php?action=search&q=" + encodeURIComponent(q));
  const data = await res.json();
  renderTable(data);
}

// Render table data
function renderTable(data) {
  enrollmentData = data; // Store data for sorting

  const tbody = document.querySelector("#enrollmentTable tbody");
  tbody.innerHTML = "";
  data.forEach(e => {
    const row = document.createElement("tr");
    row.innerHTML = `
      <td>${e.student_name}</td>
      <td>${e.section_code}</td>
      <td>${e.date_enrolled}</td>
      <td>${e.status}</td>
      <td>${e.letter_grade}</td>
      <td>
        <button class="btn btn-warning btn-sm" onclick='editEnrollment(${JSON.stringify(e)})'>Edit</button>
        <button class="btn btn-danger btn-sm" onclick='deleteEnrollment(${e.enrollment_id})'>Delete</button>
      </td>
    `;
    tbody.appendChild(row);
  });
}

// Sort function (added from course.js)
function sortTableBy(columnKey) {
  if (currentSort.column === columnKey) {
    currentSort.ascending = !currentSort.ascending;
  } else {
    currentSort.column = columnKey;
    currentSort.ascending = true;
  }

  enrollmentData.sort((a, b) => {
    const valA = a[columnKey]?.toString().toLowerCase() ?? "";
    const valB = b[columnKey]?.toString().toLowerCase() ?? "";

    if (valA < valB) return currentSort.ascending ? -1 : 1;
    if (valA > valB) return currentSort.ascending ? 1 : -1;
    return 0;
  });

  renderTable(enrollmentData);

  document.querySelectorAll("#enrollmentTable th").forEach(th => th.classList.remove("sorted-asc", "sorted-desc"));
  const th = document.querySelector(`#enrollmentTable th[data-key="${columnKey}"]`);
  if (th) th.classList.add(currentSort.ascending ? "sorted-asc" : "sorted-desc");
}

// Delete function (aligned with course.js alerts)
async function deleteEnrollment(id) {
  if (!confirm("Are you sure you want to delete this enrollment?")) return;
  const formData = new FormData();
  formData.append("action", "delete");
  formData.append("enrollment_id", id);
  const res = await fetch("../php/enrollment_api.php", { method: "POST", body: formData });
  const data = await res.json();
  if (data.success) {
    alert("Enrollment deleted successfully!");
    loadEnrollments();
  } else {
    alert("Failed to delete enrollment.");
  }
}

// Reset form helper
function resetForm() {
  document.getElementById("enrollmentForm").reset();
  document.getElementById("enrollment_id").value = "";
}

// --- Export Functions (aligned with course.js) ---
function toggleExportMenu() {
  const menu = document.getElementById("exportMenu");
  menu.style.display = menu.style.display === "block" ? "none" : "block";
}

window.addEventListener("click", e => {
  if (!e.target.matches(".btn-success") && !e.target.closest("#exportMenu")) {
    document.getElementById("exportMenu").style.display = "none";
  }
});

function exportData(type) {
  window.open("../php/enrollment_api.php?action=export&type=" + type, "_blank");
}