let currentSort = { column: null, ascending: true };
let courseData = []; // store current data for sorting
let courseModal;

// Initialize the modal once
document.addEventListener("DOMContentLoaded", () => {
  courseModal = new bootstrap.Modal(document.getElementById("courseModal"));
  loadDepartments();
  loadCourses();
});

// Open modal for ADD
function openAddCourseModal() {
  document.getElementById("courseModalLabel").textContent = "Add Course";
  document.getElementById("courseForm").reset();
  document.getElementById("course_id").value = "";
  courseModal.show();
}

// Open modal for EDIT
function editCourse(c) {
  document.getElementById("courseModalLabel").textContent = "Edit Course";
  document.getElementById("course_id").value = c.course_id;
  document.getElementById("course_code").value = c.course_code;
  document.getElementById("course_title").value = c.course_title;
  document.getElementById("units").value = c.units;
  document.getElementById("lecture_hours").value = c.lecture_hours;
  document.getElementById("lab_hours").value = c.lab_hours;
  document.getElementById("dept_id").value = c.dept_id;
  courseModal.show();
}

document.getElementById("saveCourseBtn").addEventListener("click", async () => {
  const form = document.getElementById("courseForm");
  const formData = new FormData(form);
  const id = document.getElementById("course_id").value;
  formData.append("action", id ? "edit" : "add");

  const res = await fetch("../php/course_api.php", { method: "POST", body: formData });
  const data = await res.json();

  if (data.success) {
    document.activeElement.blur();
    courseModal.hide();

    if (id) {
      alert("Course updated successfully!");
      loadCourses();
    } else {
      alert("Course added successfully!");
      addCourseToTableTop(data.course);
    }

  } else {
    alert(data.error || "Something went wrong while saving the course.");
  }
});

function addCourseToTableTop(course) {
  const tableBody = document.querySelector("#courseTable tbody");
  courseData.unshift(course);

  renderTable(courseData);

  const firstRow = tableBody.querySelector("tr");
  if (firstRow) {
    firstRow.classList.add("table-success");
    setTimeout(() => firstRow.classList.remove("table-success"), 1000);
  }
}

document.getElementById("cancelCourseBtn").addEventListener("click", () => {
  document.activeElement.blur();
  courseModal.hide();
});

function showAllCourses() {
  const searchBox = document.getElementById("searchBox");
  if (searchBox.value.trim() !== "") {
    searchBox.value = "";
  }
  loadCourses();
}

async function loadCourses() {
  const res = await fetch("../php/course_api.php?action=list");
  const data = await res.json();
  renderTable(data);
}

async function loadDepartments() {
  const res = await fetch("../php/course_api.php?action=get_departments");
  const data = await res.json();

  const selects = [document.getElementById("dept_id"), document.getElementById("edit_dept_id")];
  selects.forEach(select => {
    if (!select) return;
    select.innerHTML = `<option value="" disabled selected>Select a department</option>`;
    data.forEach(d => {
      const option = document.createElement("option");
      option.value = d.dept_id;
      option.textContent = d.dept_name;
      select.appendChild(option);
    });
  });
}

async function searchCourse() {
  const q = document.getElementById("searchBox").value;
  const res = await fetch("../php/course_api.php?action=search&q=" + encodeURIComponent(q));
  const data = await res.json();
  renderTable(data);
}

function renderTable(data) {
  courseData = data;

  const tbody = document.querySelector("#courseTable tbody");
  tbody.innerHTML = "";

  data.forEach(c => {
    const row = document.createElement("tr");
    row.innerHTML = `
      <td>${c.course_code}</td>
      <td>${c.course_title}</td>
      <td>${c.units}</td>
      <td>${c.lecture_hours}</td>
      <td>${c.lab_hours}</td>
      <td>${c.dept_name}</td>
      <td>
        <button class="btn btn-warning btn-sm" onclick='editCourse(${JSON.stringify(c)})'>Edit</button>
        <button class="btn btn-danger btn-sm" onclick='deleteCourse(${c.course_id})'>Delete</button>
      </td>
    `;
    tbody.appendChild(row);
  });
}

function sortTableBy(columnKey) {
  if (currentSort.column === columnKey) {
    currentSort.ascending = !currentSort.ascending;
  } else {
    currentSort.column = columnKey;
    currentSort.ascending = true;
  }

  courseData.sort((a, b) => {
    const valA = a[columnKey]?.toString().toLowerCase() ?? "";
    const valB = b[columnKey]?.toString().toLowerCase() ?? "";

    if (valA < valB) return currentSort.ascending ? -1 : 1;
    if (valA > valB) return currentSort.ascending ? 1 : -1;
    return 0;
  });

  renderTable(courseData);

  document.querySelectorAll("#courseTable th").forEach(th => th.classList.remove("sorted-asc", "sorted-desc"));
  const th = document.querySelector(`#courseTable th[data-key="${columnKey}"]`);
  if (th) th.classList.add(currentSort.ascending ? "sorted-asc" : "sorted-desc");
}

async function deleteCourse(id) {
  if (!confirm("Are you sure you want to delete this course?")) return;
  const formData = new FormData();
  formData.append("action", "delete");
  formData.append("course_id", id);
  const res = await fetch("../php/course_api.php", { method: "POST", body: formData });
  const data = await res.json();
  if (data.success) {
    alert("Course deleted successfully!");
    loadCourses();
  } else {
    alert("Failed to delete course.");
  }
}

function resetForm() {
  document.getElementById("courseForm").reset();
  document.getElementById("course_id").value = "";
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
  window.open("../php/course_api.php?action=export&type=" + type, "_blank");
}

loadDepartments();
loadCourses();
