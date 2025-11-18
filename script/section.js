let currentSort = { column: null, ascending: true };
let sectionData = [];
let sectionModal;

// Initialize modal & load data
document.addEventListener("DOMContentLoaded", () => {
  sectionModal = new bootstrap.Modal(document.getElementById("sectionModal"));
  loadDropdowns();
  loadSections();
});

// -------------------- DROPDOWNS --------------------
async function loadDropdowns() {
  await Promise.all([
    loadCourses(),
    loadTerms(),
    loadInstructors(),
    loadRooms()
  ]);
}

async function loadCourses() {
  const res = await fetch("../php/section_api.php?action=courses");
  const data = await res.json();
  populateSelect(document.getElementById("course_id"), data, "course_id", "name", "Select a course");
}

async function loadTerms() {
  const res = await fetch("../php/section_api.php?action=terms");
  const data = await res.json();
  populateSelect(document.getElementById("term_id"), data, "term_id", "name", "Select a term");
}

async function loadInstructors() {
  const res = await fetch("../php/section_api.php?action=instructors");
  const data = await res.json();
  populateSelect(document.getElementById("instructor_id"), data, "instructor_id", "name", "Select an instructor");
}

async function loadRooms() {
  const res = await fetch("../php/section_api.php?action=rooms");
  const data = await res.json();
  populateSelect(document.getElementById("room_id"), data, "room_id", "name", "Select a room");
}

function populateSelect(select, data, valueField, textField, defaultText) {
  if (!select) return;
  select.innerHTML = `<option value="" disabled selected>${defaultText}</option>`;
  data.forEach(d => {
    const option = document.createElement("option");
    option.value = d[valueField];
    option.textContent = d[textField];
    select.appendChild(option);
  });
}

// -------------------- MODAL (ADD / EDIT) --------------------
function openAddSectionModal() {
  document.getElementById("sectionModalLabel").textContent = "Add Section";
  document.getElementById("sectionForm").reset();
  document.getElementById("section_id").value = "";
  sectionModal.show();
}

function editSection(s) {
  document.getElementById("sectionModalLabel").textContent = "Edit Section";
  document.getElementById("section_id").value = s.section_id;
  document.getElementById("section_code").value = s.section_code;
  document.getElementById("course_id").value = s.course_id;
  document.getElementById("term_id").value = s.term_id;
  document.getElementById("instructor_id").value = s.instructor_id;
  document.getElementById("day_pattern").value = s.day_pattern;
  document.getElementById("start_time").value = s.start_time;
  document.getElementById("end_time").value = s.end_time;
  document.getElementById("room_id").value = s.room_id;
  document.getElementById("max_capacity").value = s.max_capacity;
  sectionModal.show();
}

// -------------------- SAVE --------------------
document.getElementById("saveSectionBtn").addEventListener("click", async () => {
  const form = document.getElementById("sectionForm");
  const formData = new FormData(form);
  const id = document.getElementById("section_id").value;
  formData.append("action", id ? "edit" : "add");

  const res = await fetch("../php/section_api.php", { method: "POST", body: formData });
  const data = await res.json();

  if (data.success) {
    document.activeElement.blur();
    sectionModal.hide();

    if (id) {
      alert("Section updated successfully!");
      loadSections();
    } else {
      alert("Section added successfully!");
      addSectionToTableTop(data.section);
    }
  } else {
    alert(data.error || "Something went wrong while saving the section.");
  }
});

// Add newly created section to top of table
function addSectionToTableTop(section) {
  const tableBody = document.querySelector("#sectionTable tbody");
  sectionData.unshift(section);
  renderTable(sectionData);

  const firstRow = tableBody.querySelector("tr");
  if (firstRow) {
    firstRow.classList.add("table-success");
    setTimeout(() => firstRow.classList.remove("table-success"), 1000);
  }
}

// -------------------- CANCEL MODAL --------------------
document.getElementById("cancelSectionBtn").addEventListener("click", () => {
  document.activeElement.blur();
  sectionModal.hide();
});

// -------------------- LOAD / SEARCH / SHOW ALL --------------------
async function loadSections() {
  const res = await fetch("../php/section_api.php?action=list");
  const data = await res.json();
  renderTable(data);
}

async function searchSection() {
  const q = document.getElementById("searchBox").value;
  const res = await fetch("../php/section_api.php?action=search&q=" + encodeURIComponent(q));
  const data = await res.json();
  renderTable(data);
}

function showAllSections() {
  const searchBox = document.getElementById("searchBox");
  if (searchBox.value.trim() !== "") {
    searchBox.value = "";
  }
  loadSections();
}

// -------------------- RENDER TABLE --------------------
function renderTable(data) {
  sectionData = data;
  const tbody = document.querySelector("#sectionTable tbody");
  tbody.innerHTML = "";

  data.forEach(s => {
    const row = document.createElement("tr");
    row.innerHTML = `
      <td>${s.section_code}</td>
      <td>${s.course_name}</td>
      <td>${s.term_name}</td>
      <td>${s.instructor_name}</td>
      <td>${s.day_pattern}</td>
      <td>${s.start_time}</td>
      <td>${s.end_time}</td>
      <td>${s.room_code}</td>
      <td>${s.max_capacity}</td>
      <td>
        <button class="btn btn-warning btn-sm" onclick='editSection(${JSON.stringify(s)})'>Edit</button>
        <button class="btn btn-danger btn-sm" onclick='deleteSection(${s.section_id})'>Delete</button>
      </td>
    `;
    tbody.appendChild(row);
  });
}

// -------------------- SORT --------------------
function sortTableBy(columnKey) {
  if (currentSort.column === columnKey) {
    currentSort.ascending = !currentSort.ascending;
  } else {
    currentSort.column = columnKey;
    currentSort.ascending = true;
  }

  sectionData.sort((a, b) => {
    const valA = a[columnKey]?.toString().toLowerCase() ?? "";
    const valB = b[columnKey]?.toString().toLowerCase() ?? "";
    if (valA < valB) return currentSort.ascending ? -1 : 1;
    if (valA > valB) return currentSort.ascending ? 1 : -1;
    return 0;
  });

  renderTable(sectionData);

  document.querySelectorAll("#sectionTable th").forEach(th => th.classList.remove("sorted-asc", "sorted-desc"));
  const th = document.querySelector(`#sectionTable th[data-key="${columnKey}"]`);
  if (th) th.classList.add(currentSort.ascending ? "sorted-asc" : "sorted-desc");
}

// -------------------- DELETE --------------------
async function deleteSection(id) {
  if (!confirm("Are you sure you want to delete this section?")) return;
  const formData = new FormData();
  formData.append("action", "delete");
  formData.append("section_id", id);
  const res = await fetch("../php/section_api.php", { method: "POST", body: formData });
  const data = await res.json();

  if (data.success) {
    alert("Section deleted successfully!");
    loadSections();
  } else {
    alert("Failed to delete section.");
  }
}

// -------------------- UTILITIES --------------------
function resetForm() {
  document.getElementById("sectionForm").reset();
  document.getElementById("section_id").value = "";
}

// -------------------- EXPORT --------------------
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
  window.open("../php/section_api.php?action=export&type=" + type, "_blank");
}

// -------------------- INITIAL LOAD --------------------
loadDropdowns();
loadSections();