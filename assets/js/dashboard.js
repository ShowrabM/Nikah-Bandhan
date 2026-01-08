(function () {
  document.addEventListener("DOMContentLoaded", function () {
    var toggleBtn = document.getElementById("odSidebarToggle");
    var sidebar = document.getElementById("odSidebar");
    var avatarInput = document.getElementById("odAvatarInput");

    if (toggleBtn && sidebar) {
      toggleBtn.addEventListener("click", function () {
        sidebar.classList.toggle("active");
      });
    }

    if (avatarInput) {
      avatarInput.addEventListener("change", function () {
        var file = avatarInput.files && avatarInput.files[0];
        if (!file) return;
        var max = 2 * 1024 * 1024;
        if (file.size > max) {
          alert("Max file size is 2MB.");
          avatarInput.value = "";
          return;
        }
        uploadAvatar(file);
      });
    }
  });

  async function uploadAvatar(file) {
    if (typeof OVW_MAT_DASH === "undefined") return;
    var fd = new FormData();
    fd.append("photo", file);

    var res = await fetch(OVW_MAT_DASH.rest + "/upload", {
      method: "POST",
      headers: { "X-WP-Nonce": OVW_MAT_DASH.nonce },
      body: fd
    });
    var text = await res.text();
    var data = null;
    try {
      data = JSON.parse(text);
    } catch (e) {
      data = { message: "Upload failed." };
    }
    if (!res.ok || data.code) {
      alert(data.message || "Upload failed.");
      return;
    }

    var url = data.url || "";
    if (!url) return;

    var saveRes = await fetch(OVW_MAT_DASH.rest + "/profile-photo", {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
        "X-WP-Nonce": OVW_MAT_DASH.nonce
      },
      body: JSON.stringify({ url: url })
    });
    var saveText = await saveRes.text();
    var saveData = null;
    try {
      saveData = JSON.parse(saveText);
    } catch (e) {
      saveData = { message: "Save failed." };
    }
    if (!saveRes.ok || saveData.code) {
      alert(saveData.message || "Save failed.");
      return;
    }

    var avatar = document.querySelector(".od-avatar");
    if (avatar) {
      avatar.innerHTML = '<img src="' + url + '" alt="Profile">';
    }
  }
})();
