(function(){
  const modal = document.getElementById("ovw-mat-modal");
  if(!modal) return;

  const body = document.getElementById("ovw-mat-modal-body");
  const closeBtn = document.getElementById("ovw-mat-close");

  const API = (typeof OVW_MAT_ADMIN !== "undefined" && OVW_MAT_ADMIN.rest) ? OVW_MAT_ADMIN.rest : (window.location.origin + "/wp-json/ovw-matrimonial/v1");
  const NONCE = (typeof OVW_MAT_ADMIN !== "undefined" && OVW_MAT_ADMIN.nonce) ? OVW_MAT_ADMIN.nonce : "";
  const AJAX = (typeof OVW_MAT_ADMIN !== "undefined" && OVW_MAT_ADMIN.ajax) ? OVW_MAT_ADMIN.ajax : "";

  function esc(s){
    return (s||"").toString().replaceAll("&","&amp;").replaceAll("<","&lt;").replaceAll(">","&gt;");
  }

  function render(schema, payload){
    if (schema.steps) {
      return (schema.steps||[]).map(step=>{
        const fields = [];
        (step.rows||[]).forEach(row=>{
          (row.columns||[]).forEach(col=>{
            (col.fields||[]).forEach(f=>fields.push(f));
          });
        });
        const rows = fields.map(f=>{
          const v = payload[f.key];
          const val = (v===undefined||v===null||v==="") ? "N/A" : (Array.isArray(v)?v.join(", "):v);
          return `<tr><td class="k">${esc(f.label||f.key)}</td><td class="v">${esc(val)}</td></tr>`;
        }).join("");
        return `<div class="ovw-section-card"><div class="ovw-section-title">${esc(step.label||"Step")}</div><table class="ovw-table">${rows}</table></div>`;
      }).join("");
    }

    return (schema.sections||[]).map(sec=>{
      const rows = (sec.fields||[]).map(f=>{
        const v = payload[f.key];
        const val = (v===undefined||v===null||v==="") ? "N/A" : (Array.isArray(v)?v.join(", "):v);
        return `<tr><td class="k">${esc(f.label)}</td><td class="v">${esc(val)}</td></tr>`;
      }).join("");
      return `<div class="ovw-section-card"><div class="ovw-section-title">${esc(sec.label)}</div><table class="ovw-table">${rows}</table></div>`;
    }).join("");
  }

  async function fetchJSON(url, opts) {
    const res = await fetch(url, opts || {});
    const text = await res.text();
    try {
      const data = JSON.parse(text);
      return data;
    } catch (e) {
      return { code: "bad_json", message: text };
    }
  }

  document.querySelectorAll(".ovw-mat-open").forEach(btn=>{
    btn.addEventListener("click", async (e)=>{
      e.preventDefault();
      const id = btn.dataset.id;

      modal.style.display = "block";
      body.innerHTML = "Loading...";

      let data = null;

      if (AJAX) {
        data = await fetchJSON(AJAX + "?action=ovw_mat_admin_entry&id=" + encodeURIComponent(id));
        if (data && data.success) data = data.data;
      }

      if (!data || data.code) {
        data = await fetchJSON(API + "/admin-entry?id=" + encodeURIComponent(id), {
          headers: {
            "X-WP-Nonce": NONCE
          }
        });
        if (data && data.data) data = data.data;
      }

      if (data && data.code) { body.innerHTML = "Not found"; return; }
      if (!data) { body.innerHTML = "Not found"; return; }
      body.innerHTML = render(data.schema || {}, data.payload || {});
    });
  });

  closeBtn?.addEventListener("click", ()=> modal.style.display = "none");
})();
