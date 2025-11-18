let currentSort = { column: null, ascending: true };
let prereqData = [];
let prereqModal;

// -------------------- INITIALIZE --------------------
document.addEventListener("DOMContentLoaded", () => {
  prereqModal = new bootstrap.Modal(document.getElementById("prerequisiteModal"));
  loadCourses();
  loadPrerequisites();
});

// -------------------- DROPDOWNS --------------------
async function loadCourses() {
  const res = await fetch("../php/course_prerequisite_api.php?action=courses");
  const data = await res.json();

  populateSelect(document.getElementById("course_id"), data, "course_id", "name", "Select a course");
  populateSelect(document.getElementById("prereq_course_id"), data, "course_id", "name", "Select a prerequisite course");
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
// -------------------- MODAL (ADD / EDIT) --------------------
function openAddPrereqModal() {
  document.getElementById("prerequisiteModalLabel").textContent = "Add Prerequisite";
  document.getElementById("prereqForm").reset();
  
  // ✅ CLEAR the original keys
  document.getElementById("orig_course_id").value = "";
  document.getElementById("orig_prereq_course_id").value = "";
  
  prereqModal.show();
}

function editPrereq(p) {
  document.getElementById("prerequisiteModalLabel").textContent = "Edit Prerequisite";
  
  // Set the dropdown values
  document.getElementById("course_id").value = p.course_id;
  document.getElementById("prereq_course_id").value = p.prereq_course_id;
  
  // ✅ SET the original keys in the hidden fields
  document.getElementById("orig_course_id").value = p.course_id;
  document.getElementById("orig_prereq_course_id").value = p.prereq_course_id;
  
  prereqModal.show();
}

// -------------------- SAVE --------------------
document.getElementById("savePrereqBtn").addEventListener("click", async () => {
  const form = document.getElementById("prereqForm");
  const formData = new FormData(form);
  
  // ✅ CHECK the new hidden field to see if it's an edit
  const orig_id = document.getElementById("orig_course_id").value;
  
  // ✅ Append 'edit' or 'add' based on the hidden field
  formData.append("action", orig_id ? "edit" : "add");

  // The 'edit' action in your PHP is already set up to receive these fields
  // (orig_course_id and orig_prereq_course_id)

  const res = await fetch("../php/course_prerequisite_api.php", { method: "POST", body: formData });
  const data = await res.json();

  if (data.success) {
    document.activeElement.blur();
    prereqModal.hide();

    if (orig_id) { // ✅ Check if it was an edit
      alert("Prerequisite updated successfully!");
      loadPrerequisites();
    } else {
      alert("Prerequisite added successfully!");
      
      // This 'restored' flag comes from your PHP's "undelete" logic
      if (data.restored) {
        loadPrerequisites(); // Just reload if it was a restored item
      } else {
        // ✅ This is your original bug fix
        addPrereqToTableTop(data.prerequisite);
      }
    }
  } else {
    alert(data.error || "Something went wrong while saving the prerequisite.");
  }
});

function addPrereqToTableTop(prereq) {
  const tableBody = document.querySelector("#prereqTable tbody");
  prereqData.unshift(prereq);
  renderTable(prereqData);

  const firstRow = tableBody.querySelector("tr");
  if (firstRow) {
    firstRow.classList.add("table-success");
    setTimeout(() => firstRow.classList.remove("table-success"), 1000);
  }
}

// -------------------- CANCEL MODAL --------------------
document.getElementById("cancelPrereqBtn").addEventListener("click", () => {
  document.activeElement.blur();
  prereqModal.hide();
});

// -------------------- LOAD / SEARCH --------------------
async function loadPrerequisites() {
  const res = await fetch("../php/course_prerequisite_api.php?action=list");
  const data = await res.json();
  renderTable(data);
}

async function searchPrereq() {
  const q = document.getElementById("searchBox").value;
  const res = await fetch("../php/course_prerequisite_api.php?action=search&q=" + encodeURIComponent(q));
  const data = await res.json();
  renderTable(data);
}

function showAllPrereqs() {
  const searchBox = document.getElementById("searchBox");
  if (searchBox.value.trim() !== "") {
    searchBox.value = "";
  }
  loadPrerequisites();
}

// -------------------- RENDER TABLE --------------------
function renderTable(data) {
  prereqData = data;
  console.log(prereqData);
  const tbody = document.querySelector("#prereqTable tbody");
  tbody.innerHTML = "";

  data.forEach(p => {
    console.log(p);
    const row = document.createElement("tr");
    row.innerHTML = `
      <td>${p.course_code}</td>
      <td>${p.course_title}</td>
      <td>${p.prereq_code}</td>
      <td>${p.prereq_title}</td>
      <td>
        <button class="btn btn-warning btn-sm" onclick='editPrereq(${JSON.stringify(p)})'>Edit</button>
        <button class="btn btn-danger btn-sm" onclick='deletePrereq(${p.course_id}, ${p.prereq_course_id})'>Delete</button>
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

  prereqData.sort((a, b) => {
    const valA = a[columnKey]?.toString().toLowerCase() ?? "";
    const valB = b[columnKey]?.toString().toLowerCase() ?? "";
    if (valA < valB) return currentSort.ascending ? -1 : 1;
    if (valA > valB) return currentSort.ascending ? 1 : -1;
    return 0;
  });

  renderTable(prereqData);

  document.querySelectorAll("#prereqTable th").forEach(th => th.classList.remove("sorted-asc", "sorted-desc"));
  const th = document.querySelector(`#prereqTable th[data-key="${columnKey}"]`);
  if (th) th.classList.add(currentSort.ascending ? "sorted-asc" : "sorted-desc");
}

// -------------------- DELETE --------------------
async function deletePrereq(course_id, prereq_id) {
  if (!confirm("Are you sure you want to delete this prerequisite?")) return;
  const formData = new FormData();
  formData.append("action", "delete");
  formData.append("course_id", course_id);
  formData.append("prereq_course_id", prereq_id);
  const res = await fetch("../php/course_prerequisite_api.php", { method: "POST", body: formData });
  const data = await res.json();

  if (data.success) {
    alert("Prerequisite deleted successfully!");
    loadPrerequisites();
  } else {
    alert("Failed to delete prerequisite.");
  }
}

// -------------------- UTILITIES --------------------
function resetForm() {
  document.getElementById("prereqForm").reset();
  document.getElementById("prereq_id").value = "";
}

// -------------------- EXPORT (Optional) --------------------
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
  window.open("../php/course_prerequisite_api.php?action=export&type=" + type, "_blank");
}
