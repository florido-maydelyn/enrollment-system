let currentSort = { column: null, ascending: true };
let enrollmentData = [];
let enrollmentModal;

// --- DOM Elements ---
let studentSelect, irregularContainer, regularContainer;
let saveBlockBtn, saveIrregularBtn, loadingSpinner;
let blockTermSelect, blockSectionCode, blockCourseList;
let irregularSectionSelect, irregularDate, irregularStatus, irregularGrade;

document.addEventListener("DOMContentLoaded", () => {
  enrollmentModal = new bootstrap.Modal(document.getElementById("enrollmentModal"));
  
  // Cache all DOM elements
  studentSelect = document.getElementById("student_id");
  irregularContainer = document.getElementById("irregularFlowContainer");
  regularContainer = document.getElementById("regularFlowContainer");
  saveBlockBtn = document.getElementById("saveBlockBtn");
  saveIrregularBtn = document.getElementById("saveIrregularBtn");
  loadingSpinner = document.getElementById("loadingSpinner");
  
  blockTermSelect = document.getElementById("block_term_id");
  blockSectionCode = document.getElementById("block_section_code");
  blockCourseList = document.getElementById("block_course_list");
  
  irregularSectionSelect = document.getElementById("section_id");
  irregularDate = document.getElementById("date_enrolled");
  irregularStatus = document.getElementById("status");
  irregularGrade = document.getElementById("letter_grade");

  // Load main dropdowns
  loadStudents();
  loadSectionsForIrregular();
  loadTerms();
  
  // Load main table
  loadEnrollments();

  // --- Event Listeners ---
  studentSelect.addEventListener("change", onStudentSelect);
  blockTermSelect.addEventListener("change", getBlockDetails);
  
  saveIrregularBtn.addEventListener("click", saveIrregularEnrollment);
  saveBlockBtn.addEventListener("click", saveBlockEnrollment);
});

// --- Modal Controls ---

function openAddEnrollmentModal() {
  document.getElementById("enrollmentModalLabel").textContent = "Add Enrollment";
  document.getElementById("enrollmentForm").reset();
  document.getElementById("enrollment_id").value = "";

  // Reset modal to initial state
  studentSelect.disabled = false;
  irregularContainer.style.display = "none";
  regularContainer.style.display = "none";
  saveBlockBtn.style.display = "none";
  saveIrregularBtn.style.display = "none";
  loadingSpinner.style.display = "none";

  enrollmentModal.show();
}

// Open modal for EDIT (pre-fills irregular flow)
function editEnrollment(e) {
  document.getElementById("enrollmentModalLabel").textContent = "Edit Enrollment";
  document.getElementById("enrollmentForm").reset();

  // Set the hidden ID
  document.getElementById("enrollment_id").value = e.enrollment_id;

  // Populate and disable the student
  studentSelect.value = e.student_id;
  studentSelect.disabled = true;

  // Show IRREGULAR flow for editing
  irregularContainer.style.display = "flex"; // Use 'flex' for row g-3
  regularContainer.style.display = "none";
  saveBlockBtn.style.display = "none";
  saveIrregularBtn.style.display = "block";
  loadingSpinner.style.display = "none";

  // Populate irregular fields
  irregularSectionSelect.value = e.section_id;
  irregularDate.value = e.date_enrolled;
  irregularStatus.value = e.status;
  irregularGrade.value = e.letter_grade;

  enrollmentModal.show();
}

// --- "Brain" Logic ---

async function onStudentSelect() {
  const student_id = studentSelect.value;
  
  // Hide everything first
  irregularContainer.style.display = "none";
  regularContainer.style.display = "none";
  saveBlockBtn.style.display = "none";
  saveIrregularBtn.style.display = "none";

  if (!student_id) {
    return; // Do nothing if they selected "Select a student"
  }
  
  loadingSpinner.style.display = "block";

  // Check the student's status
  try {
    const res = await fetch(`../php/enrollment_api.php?action=getStudentStatus&id=${student_id}`);
    const data = await res.json();

    if (data.is_regular == 1) {
      // Show REGULAR flow
      regularContainer.style.display = "flex"; // Use 'flex' for row g-3
      saveBlockBtn.style.display = "block";
      // Reset term dropdown and course list
      blockTermSelect.value = "";
      blockCourseList.innerHTML = '<small class="text-muted">Select a term to see courses.</small>';
      blockSectionCode.value = "";

    } else {
      // Show IRREGULAR flow
      irregularContainer.style.display = "flex"; // Use 'flex' for row g-3
      saveIrregularBtn.style.display = "block";
      // Set defaults for irregular
      irregularDate.value = new Date().toISOString().split('T')[0];
      irregularStatus.value = "Enrolled";
      irregularGrade.value = "";
      irregularSectionSelect.value = "";
    }

  } catch (error) {
    alert("Error checking student status: " + error);
  } finally {
    loadingSpinner.style.display = "none";
  }
}

// --- Dropdown Loaders ---

async function loadStudents() {
  const res = await fetch("../php/enrollment_api.php?action=students");
  const data = await res.json();
  populateSelect(studentSelect, data, "student_id", "student_name", "Select a student");
}

async function loadSectionsForIrregular() {
  const res = await fetch("../php/enrollment_api.php?action=getSectionsForIrregular");
  const data = await res.json();
  populateSelect(irregularSectionSelect, data, "section_id", "section_name", "Select a section");
}

async function loadTerms() {
  const res = await fetch("../php/enrollment_api.php?action=getTerms");
  const data = await res.json();
  populateSelect(blockTermSelect, data, "term_id", "term_code", "Select a term");
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

// --- Data Saving ---

// For IRREGULAR students (Add/Edit)
async function saveIrregularEnrollment() {
  const form = document.getElementById("enrollmentForm");
  const formData = new FormData(form);
  const id = document.getElementById("enrollment_id").value;
  formData.append("action", id ? "edit" : "add");

  // Manually add student_id if it's disabled (during edit)
  if (studentSelect.disabled) {
    formData.set("student_id", studentSelect.value);
  }

  const res = await fetch("../php/enrollment_api.php", { method: "POST", body: formData });
  const data = await res.json();

  if (data.success) {
    enrollmentModal.hide();
    alert(id ? "Enrollment updated successfully!" : "Enrollment added successfully!");
    
    if (id) {
        loadEnrollments(); // Full reload on edit
    } else {
        addEnrollmentToTableTop(data.enrollment); // Add to top on new
    }
  } else {
    alert(data.error || "Something went wrong.");
  }
}

// For REGULAR students (Block Enroll)
async function saveBlockEnrollment() {
  const student_id_val = studentSelect.value;
  const term_id_val = blockTermSelect.value;
  const section_code_val = blockSectionCode.value;

  if (!student_id_val || !term_id_val || !section_code_val || section_code_val === "N/A") {
    alert("Please select a student and term, and ensure a valid block is found.");
    return;
  }

  const studentName = studentSelect.options[studentSelect.selectedIndex].text;
  if (!confirm(`This will enroll ${studentName} in all courses for block ${section_code_val}.\n\nAre you sure?`)) {
    return;
  }

  const formData = new FormData();
  formData.append("action", "enrollBlock");
  formData.append("student_id", student_id_val);
  formData.append("term_id", term_id_val);
  formData.append("section_code", section_code_val);

  const res = await fetch("../php/enrollment_api.php", { method: "POST", body: formData });
  const data = await res.json();

  if (data.success) {
    alert(`Successfully enrolled student in ${data.count} sections.`);
    enrollmentModal.hide();
    loadEnrollments(); // Refresh main table
  } else {
    alert(`Error: ${data.error}`);
  }
}

// --- Helper Functions for Block Enroll ---

// ★★★ REPLACE THIS ENTIRE FUNCTION ★★★
async function getBlockDetails() {
  const student_id = studentSelect.value;
  const term_id = blockTermSelect.value;

  if (!student_id || !term_id) {
    blockCourseList.innerHTML = '<small class="text-muted">Select a term to see courses.</small>';
    blockSectionCode.value = "";
    return;
  }

  blockCourseList.innerHTML = "<em>Loading...</em>";
  
  try {
    const res = await fetch(`../php/enrollment_api.php?action=getBlockDetails&student_id=${student_id}&term_id=${term_id}`);
    const data = await res.json();

    if (data && data.courses && data.courses.length > 0) {
      blockSectionCode.value = data.section_code; // Set the block code
      
      // ★★★ THIS HTML FORMATTING IS NEW ★★★
      let html = "";
      data.courses.forEach(course => {
        html += `
          <div style="margin-bottom: 0.75rem;">
            <strong>${course.course_code} - ${course.course_title}</strong>
            <small class="d-block" style="line-height: 1.4;">
              ${course.instructor_name} | ${course.day_pattern} (${course.start_time_f} - ${course.end_time_f}) | Room: ${course.room_code}
            </small>
          </div>
        `;
      });
      blockCourseList.innerHTML = html;
      
    } else {
      blockSectionCode.value = data.section_code || "N/A";
      blockCourseList.innerHTML = '<strong class="text-danger">No sections found for this student/term combination.</strong>';
    }
  } catch (error) {
    alert("Error getting block details: " + error);
    blockCourseList.innerHTML = '<strong class="text-danger">Error loading details.</strong>';
  }
}


// --- Main Table Functions (Load, Render, Sort, etc.) ---

async function loadEnrollments() {
  const res = await fetch("../php/enrollment_api.php?action=list");
  const data = await res.json();
  renderTable(data);
}

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

async function searchEnrollment() {
  const q = document.getElementById("searchBox").value;
  const res = await fetch("../php/enrollment_api.php?action=search&q=" + encodeURIComponent(q));
  const data = await res.json();
  renderTable(data);
}

function renderTable(data) {
  enrollmentData = data;
  const tbody = document.querySelector("#enrollmentTable tbody");
  tbody.innerHTML = "";
  data.forEach(e => {
    const row = document.createElement("tr");
    row.innerHTML = `
      <td>${e.student_name}</td>
      <td>${e.section_code}</td>
      <td>${e.date_enrolled}</td>
      <td>${e.status}</td>
      <td>${e.letter_grade || ''}</td>
      <td>
        <button class="btn btn-warning btn-sm" onclick='editEnrollment(${JSON.stringify(e)})'>Edit</button>
        <button class="btn btn-danger btn-sm" onclick='deleteEnrollment(${e.enrollment_id})'>Delete</button>
      </td>
    `;
    tbody.appendChild(row);
  });
}

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

// --- Utility Functions ---

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