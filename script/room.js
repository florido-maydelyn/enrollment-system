let currentSort = { column: null, ascending: true };
let roomData = [];
let roomModal;

document.addEventListener("DOMContentLoaded", () => {
  roomModal = new bootstrap.Modal(document.getElementById("roomModal"));
  loadRooms();
});

// ✅ Open modal for ADD
function openAddRoomModal() {
  document.getElementById("roomModalLabel").textContent = "Add Room";
  resetForm();
  roomModal.show();
}

// ✅ Open modal for EDIT
function editRoom(r) {
  document.getElementById("roomModalLabel").textContent = "Edit Room";
  document.getElementById("room_id").value = r.room_id;
  document.getElementById("building").value = r.building;
  document.getElementById("room_code").value = r.room_code;
  document.getElementById("capacity").value = r.capacity;
  roomModal.show();
}

// ✅ Save Room
document.getElementById("saveRoomBtn").addEventListener("click", async () => {
  const form = document.getElementById("roomForm");
  const formData = new FormData(form);
  const id = document.getElementById("room_id").value;
  formData.append("action", id ? "edit" : "add");

  const res = await fetch("../php/room_api.php", { method: "POST", body: formData });
  const data = await res.json();

  if (data.success) {
    roomModal.hide();
    if (id) {
      alert("Room updated successfully!");
      loadRooms();
    } else {
      alert("Room added successfully!");
      addRoomToTableTop(data.room);
    }
  } else {
    alert(data.error || "Something went wrong while saving the room.");
  }
});

// ✅ Add newly created room to table top
function addRoomToTableTop(room) {
  roomData.unshift(room);
  renderTable(roomData);

  const firstRow = document.querySelector("#roomTable tbody tr");
  if (firstRow) {
    firstRow.classList.add("table-success");
    setTimeout(() => firstRow.classList.remove("table-success"), 1000);
  }
}

// ✅ Cancel modal
document.getElementById("cancelRoomBtn").addEventListener("click", () => {
  document.activeElement.blur();
  roomModal.hide();
});

// ✅ Load Rooms
async function loadRooms() {
  const res = await fetch("../php/room_api.php?action=list");
  const data = await res.json();
  renderTable(data);
}

// ✅ Search Room
async function searchRoom() {
  const q = document.getElementById("searchBox").value;
  const res = await fetch("../php/room_api.php?action=search&q=" + encodeURIComponent(q));
  const data = await res.json();
  renderTable(data);
}

function showAllRooms() {
  const searchBox = document.getElementById("searchBox");
  if (searchBox.value.trim() !== "") {
    searchBox.value = "";
  }
  loadRooms();
}

// ✅ Render table
function renderTable(data) {
  roomData = data;
  const tbody = document.querySelector("#roomTable tbody");
  tbody.innerHTML = "";

  data.forEach(r => {
    const row = document.createElement("tr");
    row.innerHTML = `
      <td>${r.building}</td>
      <td>${r.room_code}</td>
      <td>${r.capacity}</td>
      <td>
        <button class="btn btn-warning btn-sm" onclick='editRoom(${JSON.stringify(r)})'>Edit</button>
        <button class="btn btn-danger btn-sm" onclick='deleteRoom(${r.room_id})'>Delete</button>
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

  roomData.sort((a, b) => {
    const valA = a[columnKey]?.toString().toLowerCase() ?? "";
    const valB = b[columnKey]?.toString().toLowerCase() ?? "";
    if (valA < valB) return currentSort.ascending ? -1 : 1;
    if (valA > valB) return currentSort.ascending ? 1 : -1;
    return 0;
  });

  renderTable(roomData);

  document.querySelectorAll("#roomTable th").forEach(th => th.classList.remove("sorted-asc", "sorted-desc"));
  const th = document.querySelector(`#roomTable th[data-key="${columnKey}"]`);
  if (th) th.classList.add(currentSort.ascending ? "sorted-asc" : "sorted-desc");
}

// ✅ Delete Room
async function deleteRoom(id) {
  if (!confirm("Are you sure you want to delete this room?")) return;
  const formData = new FormData();
  formData.append("action", "delete");
  formData.append("room_id", id);
  const res = await fetch("../php/room_api.php", { method: "POST", body: formData });
  const data = await res.json();
  if (data.success) {
    alert("Room deleted successfully!");
    loadRooms();
  } else {
    alert("Failed to delete room.");
  }
}

// ✅ Reset Form
function resetForm() {
  document.getElementById("roomForm").reset();
  document.getElementById("room_id").value = "";
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
  window.open("../php/room_api.php?action=export&type=" + type, "_blank");
}