(function () {
  if (typeof OVW_MAT === "undefined") return;

  const API = OVW_MAT.rest;
  const API_FALLBACK = window.location.origin + "/wp-json/ovw-matrimonial/v1";
  const NONCE = OVW_MAT.nonce;

  function esc(s) {
    return (s || "").toString()
      .replaceAll("&","&amp;")
      .replaceAll("<","&lt;")
      .replaceAll(">","&gt;")
      .replaceAll('"',"&quot;")
      .replaceAll("'","&#039;");
  }

  async function fetchJSON(url, opts = {}) {
    const res = await fetch(url, {
      ...opts,
      headers: {
        "Content-Type": "application/json",
        "X-WP-Nonce": NONCE,
        ...(opts.headers || {})
      }
    });

    const text = await res.text();
    try {
      const data = JSON.parse(text);
      if (!res.ok) {
        return { __error: true, status: res.status, data };
      }
      return data;
    } catch (e) {
      return { __error: true, status: res.status, text };
    }
  }

  // =========================================================
  // A) CREATE BIODATA UI (left menu + right fields)
  // =========================================================
  (function initCreate() {
    const app = document.getElementById("ovwBioApp");
    if (!app) return;

    const nav = document.getElementById("ovwBioNav");
    const fieldsWrap = document.getElementById("ovwBioFields");
    const title = document.getElementById("ovwBioSectionTitle");
    const msg = document.getElementById("ovwBioMsg");
    const form = document.getElementById("ovwBioForm");
    const formId = app.dataset.formId || "";
    if (form) {
      form.querySelectorAll("button[type='submit']").forEach((btn) => {
        if (btn.id !== "ovw_step_submit") btn.remove();
      });
    }

    const state = {};
    let schema = null;
    let activeStepId = null;
    let fieldMap = {};
    let currentStatus = "";

    const COUNTRIES = ["Afghanistan","Albania","Algeria","Andorra","Angola","Argentina","Armenia","Australia","Austria","Azerbaijan","Bahamas","Bahrain","Bangladesh","Barbados","Belarus","Belgium","Belize","Benin","Bhutan","Bolivia","Bosnia and Herzegovina","Botswana","Brazil","Brunei","Bulgaria","Burkina Faso","Burundi","Cambodia","Cameroon","Canada","Cape Verde","Central African Republic","Chad","Chile","China","Colombia","Comoros","Congo","Costa Rica","Croatia","Cuba","Cyprus","Czech Republic","Denmark","Djibouti","Dominica","Dominican Republic","Ecuador","Egypt","El Salvador","Equatorial Guinea","Eritrea","Estonia","Ethiopia","Fiji","Finland","France","Gabon","Gambia","Georgia","Germany","Ghana","Greece","Grenada","Guatemala","Guinea","Guyana","Haiti","Honduras","Hungary","Iceland","India","Indonesia","Iran","Iraq","Ireland","Italy","Jamaica","Japan","Jordan","Kazakhstan","Kenya","Kuwait","Kyrgyzstan","Laos","Latvia","Lebanon","Lesotho","Liberia","Libya","Liechtenstein","Lithuania","Luxembourg","Madagascar","Malawi","Malaysia","Maldives","Mali","Malta","Mexico","Moldova","Monaco","Mongolia","Montenegro","Morocco","Mozambique","Myanmar","Namibia","Nepal","Netherlands","New Zealand","Nicaragua","Niger","Nigeria","North Korea","North Macedonia","Norway","Oman","Pakistan","Panama","Paraguay","Peru","Philippines","Poland","Portugal","Qatar","Romania","Russia","Rwanda","Saudi Arabia","Senegal","Serbia","Seychelles","Sierra Leone","Singapore","Slovakia","Slovenia","Somalia","South Africa","South Korea","Spain","Sri Lanka","Sudan","Suriname","Sweden","Switzerland","Syria","Taiwan","Tajikistan","Tanzania","Thailand","Togo","Trinidad and Tobago","Tunisia","Turkey","Turkmenistan","Uganda","Ukraine","United Arab Emirates","United Kingdom","United States","Uruguay","Uzbekistan","Venezuela","Vietnam","Yemen","Zambia","Zimbabwe"];

    function setMessage(text, type) {
      msg.style.display = "block";
      msg.className = "ovw-message " + (type || "info");
      msg.textContent = text;
    }

    function normalizeSchema(s) {
      if (s && Array.isArray(s.steps)) return s;
      if (s && Array.isArray(s.sections)) {
        return {
          title: s.title || "Bio Form",
          steps: [{
            id: "step_1",
            label: s.title || "Step 1",
            rows: (s.sections || []).map(sec => ({
              id: "row_" + (sec.key || Math.random().toString(36).slice(2)),
              label: sec.label || "",
              columns: [{ id: "col_" + (sec.key || Math.random().toString(36).slice(2)), width: 12, fields: sec.fields || [] }]
            }))
          }]
        };
      }
      return { title: "Bio Form", steps: [] };
    }

    function buildFieldMap() {
      fieldMap = {};
      (schema.steps || []).forEach(step => {
        (step.rows || []).forEach(row => {
          (row.columns || []).forEach(col => {
            (col.fields || []).forEach(f => {
              if (f.key) fieldMap[f.key] = f;
            });
          });
        });
      });
    }

    function getActiveStep() {
      if (!schema.steps || !schema.steps.length) return null;
      if (!activeStepId) activeStepId = schema.steps[0].id;
      return schema.steps.find(s => s.id === activeStepId) || schema.steps[0];
    }

    function renderNav() {
      if (!nav) return;
      nav.innerHTML = (schema.steps || []).map(step => {
        const active = step.id === activeStepId ? "active" : "";
        return `<button type="button" class="ovw-nav-item ${active}" data-id="${esc(step.id)}">${esc(step.label || "Step")}</button>`;
      }).join("");
      nav.querySelectorAll(".ovw-nav-item").forEach(btn => {
        btn.addEventListener("click", () => {
          activeStepId = btn.dataset.id;
          renderNav();
          renderStep();
        });
      });
    }

    function labelHtml(field) {
      const required = !!field.required;
      const reqStar = required ? '<span class="req">*</span>' : '';
      if (field.label_placement === "hide") return "";
      return `<label class="ovw-label">${esc(field.label || "")} ${reqStar}</label>`;
    }

    function fieldWrapper(field, inner) {
      const placement = field.label_placement || "default";
      const cls = "ovw-field ovw-label-" + placement;
      const errId = "err_" + field.key;
      return `
        <div class="${cls}" data-key="${esc(field.key)}">
          ${labelHtml(field)}
          <div class="ovw-control">${inner}</div>
          <div class="ovw-error" id="${esc(errId)}"></div>
        </div>
      `;
    }

    function renderField(field) {
      const key = field.key;
      const val = state[key];
      const placeholder = esc(field.placeholder || "");

      if (field.type === "photo") {
        const preview = val ? `<div class="ovw-photo-preview"><img src="${esc(val)}" alt="Profile"></div>` : "";
        return fieldWrapper(field, `
          <input class="ovw-input" type="file" name="${esc(key)}" data-key="${esc(key)}" accept="image/*">
          <div class="ovw-photo-hint">Max size: 2MB (JPG/PNG)</div>
          ${preview}
        `);
      }

      if (field.type === "select" || field.type === "country") {
        const options = (field.type === "country" ? COUNTRIES : (field.options || [])).map(o =>
          `<option value="${esc(o)}" ${(o === val) ? "selected" : ""}>${esc(o)}</option>`
        ).join("");
        return fieldWrapper(field, `
          <select class="ovw-input" name="${esc(key)}" data-key="${esc(key)}">
            <option value="">-- Select --</option>
            ${options}
          </select>
        `);
      }

      if (field.type === "radio") {
        const options = (field.options || []).map(o => {
          const checked = o === val ? "checked" : "";
          return `<label class="ovw-option"><input type="radio" name="${esc(key)}" data-key="${esc(key)}" value="${esc(o)}" ${checked}> ${esc(o)}</label>`;
        }).join("");
        return fieldWrapper(field, `<div class="ovw-options-group">${options}</div>`);
      }

      if (field.type === "checkbox" || field.type === "multichoice") {
        const selected = Array.isArray(val) ? val : [];
        const options = (field.options || []).map(o => {
          const checked = selected.includes(o) ? "checked" : "";
          return `<label class="ovw-option"><input type="checkbox" data-key="${esc(key)}" data-multi="1" value="${esc(o)}" ${checked}> ${esc(o)}</label>`;
        }).join("");
        return fieldWrapper(field, `<div class="ovw-options-group">${options}</div>`);
      }

      if (field.type === "textarea" || field.type === "address") {
        return fieldWrapper(field, `
          <textarea class="ovw-input ovw-textarea" name="${esc(key)}" data-key="${esc(key)}" placeholder="${placeholder}">${esc(val || "")}</textarea>
        `);
      }

      const inputType = field.type === "date" ? "date" : (field.type === "email" ? "email" : (field.type === "phone" ? "tel" : "text"));
      return fieldWrapper(field, `
        <input class="ovw-input" type="${esc(inputType)}" name="${esc(key)}" data-key="${esc(key)}" value="${esc(val || "")}" placeholder="${placeholder}">
      `);
    }

    function renderRow(row) {
      const cols = (row.columns || []).map(col => {
        const fieldsHtml = (col.fields || []).map(renderField).join("");
        const span = col.width || 12;
        return `<div class="ovw-col" style="grid-column: span ${span};">${fieldsHtml}</div>`;
      }).join("");
      const label = row.label ? `<div class="ovw-row-label">${esc(row.label)}</div>` : "";
      return `${label}<div class="ovw-row">${cols}</div>`;
    }

    function isFieldVisible(field) {
      if (!field || !field.conditional) return true;
      const cond = field.conditional;
      const otherVal = state[cond.field];
      const hasVal = Array.isArray(otherVal) ? otherVal.length > 0 : (otherVal !== undefined && otherVal !== null && String(otherVal).trim() !== "");
      const valStr = Array.isArray(otherVal) ? otherVal.join(",") : String(otherVal || "");

      if (cond.operator === "empty") return !hasVal;
      if (cond.operator === "not_empty") return hasVal;
      if (cond.operator === "contains") return Array.isArray(otherVal) ? otherVal.includes(cond.value) : valStr.includes(cond.value || "");
      if (cond.operator === "not_equals") return Array.isArray(otherVal) ? !otherVal.includes(cond.value) : valStr !== (cond.value || "");
      return Array.isArray(otherVal) ? otherVal.includes(cond.value) : valStr === (cond.value || "");
    }

    function applyConditional() {
      fieldsWrap.querySelectorAll(".ovw-field[data-key]").forEach(el => {
        const key = el.dataset.key;
        const field = fieldMap[key];
        if (!field) return;
        const visible = isFieldVisible(field);
        el.classList.toggle("ovw-hidden", !visible);
        if (!visible) {
          const err = document.getElementById("err_" + key);
          if (err) err.textContent = "";
        }
      });
    }

    function hasValue(val) {
      if (Array.isArray(val)) return val.length > 0;
      if (val === undefined || val === null) return false;
      return String(val).trim() !== "";
    }

    function validateStep(step, showErrors) {
      let ok = true;
      (step.rows || []).forEach(row => {
        (row.columns || []).forEach(col => {
          (col.fields || []).forEach(f => {
            if (!f.required) return;
            if (!isFieldVisible(f)) return;
            const val = state[f.key];
            if (!hasValue(val)) {
              ok = false;
              if (showErrors) {
                const err = document.getElementById("err_" + f.key);
                if (err) err.textContent = "This field is required";
              }
            }
          });
        });
      });
      return ok;
    }

    function validateAll() {
      let firstBad = null;
      (schema.steps || []).forEach(step => {
        const ok = validateStep(step, false);
        if (!ok && !firstBad) firstBad = step.id;
      });
      if (firstBad) {
        activeStepId = firstBad;
        renderNav();
        renderStep();
        validateStep(getActiveStep(), true);
        return false;
      }
      return true;
    }

    function renderStep() {
      const step = getActiveStep();
      if (!step) return;
      title.textContent = step.label || "Step";
      const rowsHtml = (step.rows || []).map(renderRow).join("");

      const stepIndex = (schema.steps || []).findIndex(s => s.id === step.id);
      const totalSteps = (schema.steps || []).length;
      const showPrev = stepIndex > 0;
      const showNext = stepIndex < totalSteps - 1;

      fieldsWrap.innerHTML = `
        ${rowsHtml || "<div class='ovw-muted'>No fields in this step.</div>"}
        <div class="ovw-step-actions">
          <button type="button" class="ovw-step-btn" id="ovw_step_save">Save & Continue Later</button>
          ${showPrev ? '<button type="button" class="ovw-step-btn" id="ovw_step_prev">Back</button>' : ''}
          ${showNext ? '<button type="button" class="ovw-step-btn" id="ovw_step_next">Next</button>' : ''}
          ${!showNext ? '<button type="submit" class="ovw-submit" id="ovw_step_submit">Submit Biodata</button>' : ''}
        </div>
      `;

      const saveBtn = document.getElementById("ovw_step_save");
      const prevBtn = document.getElementById("ovw_step_prev");
      const nextBtn = document.getElementById("ovw_step_next");
      saveBtn?.addEventListener("click", () => saveDraft());
      prevBtn?.addEventListener("click", () => {
        activeStepId = schema.steps[stepIndex - 1]?.id;
        renderNav();
        renderStep();
      });
      nextBtn?.addEventListener("click", () => {
        if (!validateStep(step, true)) return;
        activeStepId = schema.steps[stepIndex + 1]?.id;
        renderNav();
        renderStep();
      });

      applyConditional();
    }

    function handleInput(e) {
      const t = e.target;
      const key = t?.dataset?.key;
      if (!key) return;

      if (t.type === "file") {
        const file = t.files && t.files[0];
        if (!file) return;
        const max = 2 * 1024 * 1024;
        if (file.size > max) {
          const err = document.getElementById("err_" + key);
          if (err) err.textContent = "Max file size is 2MB.";
          t.value = "";
          return;
        }
        uploadPhoto(file, key);
        return;
      }

      if (t.type === "checkbox") {
        const isMulti = t.dataset.multi === "1";
        if (isMulti) {
          const arr = Array.isArray(state[key]) ? state[key] : [];
          if (t.checked && !arr.includes(t.value)) arr.push(t.value);
          if (!t.checked) state[key] = arr.filter(v => v !== t.value);
          else state[key] = arr;
        } else {
          state[key] = t.checked ? "Yes" : "";
        }
      } else if (t.type === "radio") {
        state[key] = t.value;
      } else {
        state[key] = t.value;
      }

      const err = document.getElementById("err_" + key);
      if (err) err.textContent = "";
      applyConditional();
    }

    fieldsWrap.addEventListener("input", handleInput);
    fieldsWrap.addEventListener("change", handleInput);

    form.addEventListener("submit", async (e) => {
      e.preventDefault();
      msg.style.display = "none";

      if (!validateAll()) {
        setMessage("Please fill all required fields.", "error");
        return;
      }

      const res = await savePayload(false);

      if (res && res.ok) {
        setMessage(res.message || "Your biodata is pending to approve.", "success");
        form.reset();
        Object.keys(state).forEach(k => delete state[k]);
        renderStep();
      } else {
        setMessage(res.message || "Submit failed. Please try again.", "error");
      }
    });

    fetchJSON(API + "/schema?form_id=" + encodeURIComponent(formId)).then(async (s) => {
      if (s && s.__error && API_FALLBACK !== API) {
        s = await fetchJSON(API_FALLBACK + "/schema?form_id=" + encodeURIComponent(formId));
      }
      if (!s || s.__error) {
        title.textContent = "Form";
        return;
      }
      schema = normalizeSchema(s);
      buildFieldMap();
      if (!schema.steps || !schema.steps.length) {
        title.textContent = "Form";
        return;
      }
      activeStepId = schema.steps[0]?.id || null;
      renderNav();
      renderStep();

      const profile = await fetchJSON(API + "/my-biodata?form_id=" + encodeURIComponent(formId), { method: "GET" });
      if (profile && profile.entry && profile.entry.payload) {
        Object.assign(state, profile.entry.payload);
        currentStatus = profile.entry.status || "";
        renderStep();
      }
    });

    async function savePayload(isDraft) {
      return fetchJSON(API + "/submit?form_id=" + encodeURIComponent(formId) + (isDraft ? "&draft=1" : ""), {
        method: "POST",
        body: JSON.stringify(state)
      });
    }

    async function saveDraft() {
      msg.style.display = "none";
      const res = await savePayload(true);
      if (res && res.ok) {
        setMessage(res.message || "Your biodata has been saved as draft.", "success");
      } else {
        setMessage(res.message || "Save failed. Please try again.", "error");
      }
    }

    async function uploadPhoto(file, key) {
      const fd = new FormData();
      fd.append("photo", file);

      const res = await fetch(API + "/upload", {
        method: "POST",
        headers: { "X-WP-Nonce": NONCE },
        body: fd
      });

      const text = await res.text();
      let data = null;
      try {
        data = JSON.parse(text);
      } catch (e) {
        data = { message: "Upload failed." };
      }

      if (!res.ok || data.code) {
        const err = document.getElementById("err_" + key);
        if (err) err.textContent = data.message || "Upload failed.";
        return;
      }

      state[key] = data.url || "";
      renderStep();
    }
  })();

  // =========================================================
  // B) SEARCH UI (top bar 3 dropdowns + button) - dynamic
  // =========================================================
  (function initSearch() {
    const wrap = document.getElementById("ovwTopSearch");
    if (!wrap) return;
    const formId = wrap.dataset.formId || "";

    const els = {
      type: document.getElementById("ovw_filter_type"),
      marital: document.getElementById("ovw_filter_marital"),
      address: document.getElementById("ovw_filter_address"),
      btn: document.getElementById("ovw_search_btn"),
      out: document.getElementById("ovw_search_results"),
      count: document.getElementById("ovw_search_count"),
      more: document.getElementById("ovw_load_more"),
    };

    let page = 1;
    const perPage = 9;

    function fillSelect(select, items) {
      const first = select.querySelector("option");
      select.innerHTML = "";
      if (first) select.appendChild(first);
      (items || []).forEach(v => {
        const opt = document.createElement("option");
        opt.value = v;
        opt.textContent = v;
        select.appendChild(opt);
      });
    }

    function card(it) {
      // NOTE: set your view page URL here if needed
      // Example: const href = "/biodata-view/?id=" + it.id;
      const href = "?id=" + encodeURIComponent(it.id);

      return `
        <div class="ovw-card">
          <div class="ovw-card-no">${esc(it.biodata_no)}</div>
          <div class="ovw-muted">Age - ${esc(it.age ?? "N/A")}</div>
          <div class="ovw-muted">Height - ${esc(it.height ?? "N/A")}</div>
          <div class="ovw-muted">${it.occupation ? ("Occupation - " + esc(it.occupation)) : ("Complexion - " + esc(it.complexion ?? "N/A"))}</div>
          <a class="ovw-card-btn" href="${href}">Biodata -></a>
        </div>
      `;
    }

    async function loadOptions() {
      const data = await fetchJSON(API + "/options?form_id=" + encodeURIComponent(formId), { method: "GET" });
      if (data && data.message) {
        els.count.textContent = data.message;
        return;
      }
      fillSelect(els.type, data.biodata_types);
      fillSelect(els.marital, data.marital_statuses);
      fillSelect(els.address, data.present_addresses);
    }

    async function search(reset) {
      if (reset) {
        page = 1;
        els.out.innerHTML = "";
        els.more.style.display = "none";
      }

      const params = new URLSearchParams({
        biodata_type: els.type.value || "",
        marital_status: els.marital.value || "",
        present_address: els.address.value || "",
        page: String(page),
        per_page: String(perPage),
      });

      els.out.insertAdjacentHTML("beforeend", `<div class="ovw-muted" id="ovw_loading">Loading...</div>`);
      if (formId) params.set("form_id", formId);
      const data = await fetchJSON(API + "/search?" + params.toString(), { method: "GET" });

      const loading = document.getElementById("ovw_loading");
      if (loading) loading.remove();

      if (data.message) {
        els.out.innerHTML = `<div class="ovw-muted">${esc(data.message)}</div>`;
        els.count.textContent = "";
        return;
      }

      els.count.textContent = data.total ? `${data.total} biodatas found` : "";

      const items = data.items || [];
      if (!items.length && reset) {
        els.out.innerHTML = `<div class="ovw-muted">No biodata found.</div>`;
        return;
      }

      items.forEach(it => els.out.insertAdjacentHTML("beforeend", card(it)));

      const shown = page * perPage;
      if (data.total && shown < data.total) els.more.style.display = "";
      else els.more.style.display = "none";
    }

    els.btn.addEventListener("click", () => search(true));
    els.more.addEventListener("click", () => {
      page++;
      search(false);
    });

    loadOptions().then(() => search(true));
  })();

  // =========================================================
  // C) VIEW PAGE (single biodata details)
  // =========================================================
  (function initView() {
    const view = document.getElementById("ovwView");
    if (!view) return;

    const id = view.dataset.id;
    const noEl = document.getElementById("ovwViewNo");
    const right = document.getElementById("ovwViewRight");

    function renderSection(section, payload) {
      const rows = (section.fields || []).map(f => {
        const v = payload[f.key];
        const val = (v === undefined || v === null || v === "") ? "N/A" : (Array.isArray(v) ? v.join(", ") : v);
        return `<tr><td class="k">${esc(f.label)}</td><td class="v">${esc(val)}</td></tr>`;
      }).join("");

      return `
        <div class="ovw-section-card">
          <div class="ovw-section-title">${esc(section.label || "Section")}</div>
          <table class="ovw-table">${rows}</table>
        </div>
      `;
    }

    function renderStep(step, payload) {
      const fields = [];
      (step.rows || []).forEach(row => {
        (row.columns || []).forEach(col => {
          (col.fields || []).forEach(f => fields.push(f));
        });
      });
      const rows = fields.map(f => {
        const v = payload[f.key];
        const val = (v === undefined || v === null || v === "") ? "N/A" : (Array.isArray(v) ? v.join(", ") : v);
        return `<tr><td class="k">${esc(f.label || f.key)}</td><td class="v">${esc(val)}</td></tr>`;
      }).join("");
      return `
        <div class="ovw-section-card">
          <div class="ovw-section-title">${esc(step.label || "Step")}</div>
          <table class="ovw-table">${rows}</table>
        </div>
      `;
    }

    fetchJSON(API + "/view?id=" + encodeURIComponent(id), { method: "GET" }).then(async data => {
      if (data && data.code) {
        right.innerHTML = "<p>" + esc(data.message || "Biodata not found.") + "</p>";
        return;
      }
      noEl.textContent = data.biodata_no || "Biodata";
      const schema = data.schema || {};
      const payload = data.payload || {};
      if (schema.steps) {
        right.innerHTML = (schema.steps || []).map(step => renderStep(step, payload)).join("");
      } else {
        right.innerHTML = (schema.sections || []).map(sec => renderSection(sec, payload)).join("");
      }
    });
  })();

})();
