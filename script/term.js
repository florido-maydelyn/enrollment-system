let currentSort = { column: null, ascending: true };
let termData = [];
let termModal;

document.addEventListener("DOMContentLoaded", () => {
  termModal = new bootstrap.Modal(document.getElementById("termModal"));
  loadTerms();
});

// ✅ Open modal for ADD
function openAddTermModal() {
  document.getElementById("termModalLabel").textContent = "Add Term";
  resetForm();
  termModal.show();
}

// ✅ Open modal for EDIT
function editTerm(t) {
  document.getElementById("termModalLabel").textContent = "Edit Term";
  document.getElementById("term_id").value = t.term_id;
  document.getElementById("term_code").value = t.term_code;
  document.getElementById("start_date").value = t.start_date;
  document.getElementById("end_date").value = t.end_date;
  termModal.show();
}

// ✅ Save Term
document.getElementById("saveTermBtn").addEventListener("click", async () => {
  const form = document.getElementById("termForm");
  const formData = new FormData(form);
  const id = document.getElementById("term_id").value;
  formData.append("action", id ? "edit" : "add");

  const res = await fetch("../php/term_api.php", { method: "POST", body: formData });
  const data = await res.json();

  if (data.success) {
    termModal.hide();
    if (id) {
      alert("Term updated successfully!");
      loadTerms();
    } else {
      alert("Term added successfully!");
      addTermToTableTop(data.term);
    }
  } else {
    alert(data.error || "Something went wrong while saving the term.");
  }
});

// ✅ Add newly created term to table top
function addTermToTableTop(term) {
  termData.unshift(term);
  renderTable(termData);

  const firstRow = document.querySelector("#termTable tbody tr");
  if (firstRow) {
    firstRow.classList.add("table-success");
    setTimeout(() => firstRow.classList.remove("table-success"), 1000);
  }
}

// ✅ Cancel modal
document.getElementById("cancelTermBtn").addEventListener("click", () => {
  document.activeElement.blur();
  termModal.hide();
});

// ✅ Load Terms
async function loadTerms() {
  const res = await fetch("../php/term_api.php?action=list");
  const data = await res.json();
  renderTable(data);
}

// ✅ Search Term
async function searchTerm() {
  const q = document.getElementById("searchBox").value;
  const res = await fetch("../php/term_api.php?action=search&q=" + encodeURIComponent(q));
  const data = await res.json();
  renderTable(data);
}

function showAllTerms() {
  const searchBox = document.getElementById("searchBox");
  if (searchBox.value.trim() !== "") {
    searchBox.value = "";
  }
  loadTerms();
}

// ✅ Render table
function renderTable(data) {
  termData = data;
  const tbody = document.querySelector("#termTable tbody");
  tbody.innerHTML = "";

  data.forEach(t => {
    const row = document.createElement("tr");
    row.innerHTML = `
      <td>${t.term_code}</td>
      <td>${t.start_date}</td>
      <td>${t.end_date}</td>
      <td>
        <button class="btn btn-warning btn-sm" onclick='editTerm(${JSON.stringify(t)})'>Edit</button>
        <button class="btn btn-danger btn-sm" onclick='deleteTerm(${t.term_id})'>Delete</button>
      </td>
    `;
    tbody.appendChild(row);
  });
}

// ✅ Sorting
function sortTableBy(columnKey) {
  if (currentSort.column === columnKey) {
    currentSort.ascending = !currentSort.ascending;
  } else {
    currentSort.column = columnKey;
    currentSort.ascending = true;
  }

  termData.sort((a, b) => {
    const valA = a[columnKey]?.toString().toLowerCase() ?? "";
    const valB = b[columnKey]?.toString().toLowerCase() ?? "";
    if (valA < valB) return currentSort.ascending ? -1 : 1;
    if (valA > valB) return currentSort.ascending ? 1 : -1;
    return 0;
  });

  renderTable(termData);

  document.querySelectorAll("#termTable th").forEach(th => th.classList.remove("sorted-asc", "sorted-desc"));
  const th = document.querySelector(`#termTable th[data-key="${columnKey}"]`);
  if (th) th.classList.add(currentSort.ascending ? "sorted-asc" : "sorted-desc");
}

// ✅ Delete Term
async function deleteTerm(id) {
  if (!confirm("Are you sure you want to delete this term?")) return;
  const formData = new FormData();
  formData.append("action", "delete");
  formData.append("term_id", id);
  const res = await fetch("../php/term_api.php", { method: "POST", body: formData });
  const data = await res.json();
  if (data.success) {
    alert("Term deleted successfully!");
    loadTerms();
  } else {
    alert("Failed to delete term.");
  }
}

// ✅ Reset Form
function resetForm() {
  document.getElementById("termForm").reset();
  document.getElementById("term_id").value = "";
}

// ✅ Export menu
function toggleExportMenu() {
  const menu = document.getElementById("exportMenu");
  menu.style.display = menu.style.display === "block" ? "none" : "block";
}

// ✅ Close dropdown on outside click
window.addEventListener("click", e => {
  if (!e.target.matches(".btn-success") && !e.target.closest("#exportMenu")) {
    document.getElementById("exportMenu").style.display = "none";
  }
});

// ✅ Export Data
function exportData(type) {
  window.open("../php/term_api.php?action=export&type=" + type, "_blank");
}