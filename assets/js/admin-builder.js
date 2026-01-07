(function () {
  const root = document.getElementById("ovwBuilder");
  if (!root) return;

  const titleInput = document.getElementById("ovw_form_title");

  let schema = {};
  try {
    schema = JSON.parse(root.dataset.schema || "{}");
  } catch (e) {
    schema = {};
  }
  if (titleInput && !schema.title) schema.title = titleInput.value || "Bio Form";
  if (!schema.steps) schema.steps = [];
  if ((!schema.steps || !schema.steps.length) && Array.isArray(schema.sections)) {
    schema = {
      title: schema.title || "Bio Form",
      steps: [{
        id: uid("step"),
        label: schema.title || "Step 1",
        rows: schema.sections.map(sec => ({
          id: uid("row"),
          columns: [{
            id: uid("col"),
            width: 12,
            fields: sec.fields || []
          }]
        }))
      }]
    };
  }

  const stepsEl = document.getElementById("ovwSteps");
  const canvasEl = document.getElementById("ovwCanvas");
  const settingsEl = document.getElementById("ovwFieldSettings");
  const hidden = document.getElementById("ovw_schema_json");

  const addStepBtn = document.getElementById("ovw_add_step");
  const addRowBtn = document.getElementById("ovw_add_row");
  const addRow2Btn = document.getElementById("ovw_add_row_2");

  let activeStepId = null;
  let selectedFieldId = null;
  let inlinePick = null;

  const FIELD_TYPES = [
    { type: "text", label: "Text" },
    { type: "textarea", label: "Text Area" },
    { type: "email", label: "Email" },
    { type: "phone", label: "Phone" },
    { type: "date", label: "Date" },
    { type: "address", label: "Address" },
    { type: "country", label: "Country List" },
    { type: "photo", label: "Profile Image" },
    { type: "select", label: "Dropdown" },
    { type: "radio", label: "Radio" },
    { type: "checkbox", label: "Checkbox" },
    { type: "multichoice", label: "Multiple Choice" }
  ];

  function uid(prefix) {
    return prefix + "_" + Date.now().toString(36) + Math.floor(Math.random() * 1000).toString(36);
  }

  function slugify(s) {
    return (s || "")
      .toString()
      .toLowerCase()
      .replace(/[^a-z0-9]+/g, "_")
      .replace(/^_+|_+$/g, "") || "field";
  }

  function defaultStep() {
    return { id: uid("step"), label: "Step", rows: [] };
  }

  function defaultRow(cols) {
    if (cols === 2) {
      return {
        id: uid("row"),
        columns: [
          { id: uid("col"), width: 6, fields: [] },
          { id: uid("col"), width: 6, fields: [] }
        ]
      };
    }
    return { id: uid("row"), columns: [{ id: uid("col"), width: 12, fields: [] }] };
  }

  function defaultField(type) {
    return {
      id: uid("field"),
      key: uid(type),
      type,
      label: "New Field",
      admin_label: "",
      required: false,
      placeholder: "",
      label_placement: "default",
      options: ["Option 1", "Option 2"],
      conditional: null,
      auto_key: true
    };
  }

  function getActiveStep() {
    if (!schema.steps.length) {
      schema.steps.push(defaultStep());
    }
    if (!activeStepId) activeStepId = schema.steps[0].id;
    return schema.steps.find(s => s.id === activeStepId) || schema.steps[0];
  }

  function findFieldById(id) {
    for (const step of schema.steps) {
      for (const row of step.rows || []) {
        for (const col of row.columns || []) {
          for (const f of col.fields || []) {
            if (f.id === id) return f;
          }
        }
      }
    }
    return null;
  }

  function syncHidden() {
    if (titleInput) schema.title = titleInput.value || schema.title || "Bio Form";
    hidden.value = JSON.stringify(schema);
  }

  function renderSteps() {
    const step = getActiveStep();
    stepsEl.innerHTML = schema.steps.map(s => {
      const active = s.id === step.id ? "active" : "";
      return `<button type="button" class="ovw-step-tab ${active}" data-id="${s.id}">${escapeHtml(s.label)}</button>`;
    }).join("");
    stepsEl.querySelectorAll(".ovw-step-tab").forEach(btn => {
      btn.addEventListener("click", () => {
        activeStepId = btn.dataset.id;
        selectedFieldId = null;
        render();
      });
    });
  }

  function renderCanvas() {
    const step = getActiveStep();
    const rows = step.rows || [];
    canvasEl.innerHTML = rows.map(row => {
      const colsHtml = (row.columns || []).map(col => {
        const fields = col.fields || [];
        return `
          <div class="ovw-col" data-col-id="${col.id}" style="grid-column: span ${col.width || 12};">
            <div class="ovw-col-drop">Drop fields here</div>
            <div class="ovw-col-fields">
              ${(col.fields || []).map(f => `
                <div class="ovw-field-wrap" data-id="${f.id}">
                  <div class="ovw-field-item ${f.id === selectedFieldId ? "selected" : ""}" draggable="true" data-id="${f.id}">
                    <span class="ovw-field-type">${escapeHtml(f.type)}</span>
                    <span class="ovw-field-label">${escapeHtml(f.label)}</span>
                    <div class="ovw-field-tools" data-id="${f.id}">
                      <button type="button" class="ovw-tool" data-action="up">Up</button>
                      <button type="button" class="ovw-tool" data-action="down">Down</button>
                      <button type="button" class="ovw-tool" data-action="dup">Copy</button>
                      <button type="button" class="ovw-tool" data-action="del">Del</button>
                    </div>
                  </div>
                  <button type="button" class="ovw-add-after" data-col-id="${col.id}" data-after-id="${f.id}">+ Add field</button>
                </div>
              `).join("")}
              <button type="button" class="ovw-add-after" data-col-id="${col.id}" data-after-id="">+ Add field</button>
            </div>
          </div>
        `;
      }).join("");
      return `
        <div class="ovw-row" data-row-id="${row.id}">
          <div class="ovw-row-head">
            <span>Row</span>
            <button type="button" class="ovw-row-remove" data-row-id="${row.id}">Remove Row</button>
          </div>
          <div class="ovw-row-cols">${colsHtml}</div>
        </div>
      `;
    }).join("");

    bindFieldEvents();
    bindDropTargets();
    bindInlineAdd();
  }

  function getAllFields(excludeId) {
    const list = [];
    for (const step of schema.steps) {
      for (const row of step.rows || []) {
        for (const col of row.columns || []) {
          for (const f of col.fields || []) {
            if (excludeId && f.id === excludeId) continue;
            list.push({ id: f.id, key: f.key, label: f.label || f.key });
          }
        }
      }
    }
    return list;
  }

  function renderSettings() {
    const field = selectedFieldId ? findFieldById(selectedFieldId) : null;
    if (!field) {
      const step = getActiveStep();
      settingsEl.className = "ovw-settings";
      settingsEl.innerHTML = `
        <div class="ovw-setting-group">
          <label>Step Label</label>
          <input type="text" id="ovw_step_label" value="${escapeHtml(step.label)}">
        </div>
        <div class="ovw-setting-group">
          <button type="button" class="button" id="ovw_remove_step">Remove Step</button>
        </div>
      `;
      document.getElementById("ovw_step_label")?.addEventListener("input", (e) => {
        step.label = e.target.value;
        renderSteps();
        syncHidden();
      });
      document.getElementById("ovw_remove_step")?.addEventListener("click", () => {
        if (schema.steps.length <= 1) return;
        schema.steps = schema.steps.filter(s => s.id !== step.id);
        activeStepId = schema.steps[0]?.id || null;
        render();
      });
      return;
    }

    settingsEl.className = "ovw-settings";
    const showOptions = ["select", "radio", "checkbox", "multichoice"].includes(field.type);
    const showPlaceholder = ["text", "textarea", "email", "phone", "date", "select", "country"].includes(field.type);

    const allFields = getAllFields(field.id);
    const cond = field.conditional || null;
    const condEnabled = !!cond;
    const condField = cond?.field || (allFields[0]?.key || "");
    const condOp = cond?.operator || "equals";
    const condVal = cond?.value || "";

    const sysVal = field.system || "";
    const autoKey = field.auto_key !== false;
    settingsEl.innerHTML = `
      <div class="ovw-setting-group">
        <label>Element Label</label>
        <input type="text" id="ovw_set_label" value="${escapeHtml(field.label)}">
      </div>
      <div class="ovw-setting-group">
        <label>Field Key</label>
        <input type="text" id="ovw_set_key" value="${escapeHtml(field.key)}" ${autoKey ? "disabled" : ""}>
        <div class="ovw-help">
          <label><input type="checkbox" id="ovw_set_autokey" ${autoKey ? "checked" : ""}> Auto-generate key from label</label>
        </div>
      </div>
      <div class="ovw-setting-group">
        <label>Label Placement</label>
        <select id="ovw_set_label_place">
          ${optionEl("default", "Default", field.label_placement)}
          ${optionEl("top", "Top", field.label_placement)}
          ${optionEl("right", "Right", field.label_placement)}
          ${optionEl("bottom", "Bottom", field.label_placement)}
          ${optionEl("left", "Left", field.label_placement)}
          ${optionEl("hide", "Hide Label", field.label_placement)}
        </select>
      </div>
      <div class="ovw-setting-group">
        <label>Admin Field Label</label>
        <input type="text" id="ovw_set_admin_label" value="${escapeHtml(field.admin_label || "")}">
      </div>
      <div class="ovw-setting-group">
        <label>System Field (for search/index)</label>
        <select id="ovw_set_system">
          <option value="">-- None --</option>
          ${optionEl("biodata_type", "Biodata Type", sysVal)}
          ${optionEl("marital_status", "Marital Status", sysVal)}
          ${optionEl("present_address", "Present Address", sysVal)}
          ${optionEl("height", "Height", sysVal)}
          ${optionEl("complexion", "Complexion", sysVal)}
          ${optionEl("occupation", "Occupation", sysVal)}
          ${optionEl("dob", "Date of Birth", sysVal)}
          ${optionEl("photo_url", "Profile Image URL", sysVal)}
        </select>
      </div>
      ${showPlaceholder ? `
      <div class="ovw-setting-group">
        <label>Placeholder</label>
        <input type="text" id="ovw_set_placeholder" value="${escapeHtml(field.placeholder || "")}">
      </div>` : ""}
      <div class="ovw-setting-group">
        <label>Required</label>
        <input type="checkbox" id="ovw_set_required" ${field.required ? "checked" : ""}>
      </div>
      ${showOptions ? renderOptions(field) : ""}
      <div class="ovw-setting-group">
        <label>Conditional Logic</label>
        <label><input type="checkbox" id="ovw_cond_enable" ${condEnabled ? "checked" : ""}> Enable</label>
        <div id="ovw_cond_fields" style="${condEnabled ? "" : "display:none;"}">
          <select id="ovw_cond_field">
            ${allFields.map(f => optionEl(f.key, f.label + " (" + f.key + ")", condField)).join("")}
          </select>
          <select id="ovw_cond_operator">
            ${optionEl("equals", "Equals", condOp)}
            ${optionEl("not_equals", "Not Equals", condOp)}
            ${optionEl("contains", "Contains", condOp)}
            ${optionEl("empty", "Is Empty", condOp)}
            ${optionEl("not_empty", "Is Not Empty", condOp)}
          </select>
          <input type="text" id="ovw_cond_value" value="${escapeHtml(condVal)}" placeholder="Value" style="${(condOp === "empty" || condOp === "not_empty") ? "display:none;" : ""}">
        </div>
      </div>
    `;

    bindSettings(field);
  }

  function renderOptions(field) {
    const opts = (field.options || []).map((o, i) => {
      return `
        <div class="ovw-option-row" data-idx="${i}">
          <input type="text" value="${escapeHtml(o)}">
          <button type="button" class="ovw-opt-up">Up</button>
          <button type="button" class="ovw-opt-down">Down</button>
          <button type="button" class="ovw-opt-remove">Del</button>
        </div>
      `;
    }).join("");
    return `
      <div class="ovw-setting-group">
        <label>Options</label>
        <div class="ovw-options">${opts}</div>
        <div class="ovw-option-actions">
          <button type="button" class="button" id="ovw_add_option">Add Option</button>
          <button type="button" class="button" id="ovw_bulk_options">Bulk Edit</button>
        </div>
      </div>
    `;
  }

  function bindSettings(field) {
    const label = document.getElementById("ovw_set_label");
    const keyInput = document.getElementById("ovw_set_key");
    const autoKey = document.getElementById("ovw_set_autokey");
    const labelPlace = document.getElementById("ovw_set_label_place");
    const adminLabel = document.getElementById("ovw_set_admin_label");
    const systemField = document.getElementById("ovw_set_system");
    const placeholder = document.getElementById("ovw_set_placeholder");
    const required = document.getElementById("ovw_set_required");
    const addOpt = document.getElementById("ovw_add_option");
    const bulkOpt = document.getElementById("ovw_bulk_options");
    const condEnable = document.getElementById("ovw_cond_enable");
    const condFields = document.getElementById("ovw_cond_fields");
    const condField = document.getElementById("ovw_cond_field");
    const condOp = document.getElementById("ovw_cond_operator");
    const condVal = document.getElementById("ovw_cond_value");

    label?.addEventListener("input", () => {
      field.label = label.value;
      if (field.auto_key !== false) {
        field.key = slugify(label.value);
        if (keyInput) keyInput.value = field.key;
      }
      const el = canvasEl.querySelector(`.ovw-field-item[data-id="${field.id}"] .ovw-field-label`);
      if (el) el.textContent = field.label || field.key;
      syncHidden();
    });
    keyInput?.addEventListener("input", () => {
      field.key = keyInput.value;
      field.auto_key = false;
      if (autoKey) autoKey.checked = false;
      syncHidden();
    });
    autoKey?.addEventListener("change", () => {
      field.auto_key = autoKey.checked;
      if (keyInput) keyInput.disabled = autoKey.checked;
      if (autoKey.checked) {
        field.key = slugify(field.label || field.key);
        if (keyInput) keyInput.value = field.key;
      }
      syncHidden();
    });
    labelPlace?.addEventListener("change", () => { field.label_placement = labelPlace.value; syncHidden(); });
    adminLabel?.addEventListener("input", () => { field.admin_label = adminLabel.value; syncHidden(); });
    systemField?.addEventListener("change", () => { field.system = systemField.value; syncHidden(); });
    placeholder?.addEventListener("input", () => { field.placeholder = placeholder.value; syncHidden(); });
    required?.addEventListener("change", () => { field.required = required.checked; syncHidden(); });

    addOpt?.addEventListener("click", () => {
      field.options = field.options || [];
      field.options.push("Option");
      renderSettings();
      syncHidden();
    });

    bulkOpt?.addEventListener("click", () => {
      const current = (field.options || []).join("\n");
      const input = window.prompt("Enter one option per line:", current);
      if (input === null) return;
      field.options = input.split("\n").map(s => s.trim()).filter(Boolean);
      renderSettings();
      syncHidden();
    });

    function updateConditional() {
      if (!condEnable?.checked) {
        field.conditional = null;
        syncHidden();
        return;
      }
      field.conditional = {
        field: condField?.value || "",
        operator: condOp?.value || "equals",
        value: condVal?.value || ""
      };
      syncHidden();
    }

    condEnable?.addEventListener("change", () => {
      if (condFields) condFields.style.display = condEnable.checked ? "" : "none";
      updateConditional();
    });
    condField?.addEventListener("change", updateConditional);
    condOp?.addEventListener("change", () => {
      if (condVal) {
        const needsValue = condOp.value !== "empty" && condOp.value !== "not_empty";
        condVal.style.display = needsValue ? "" : "none";
      }
      updateConditional();
    });
    condVal?.addEventListener("input", updateConditional);

    settingsEl.querySelectorAll(".ovw-option-row").forEach(row => {
      const idx = parseInt(row.dataset.idx, 10);
      const input = row.querySelector("input");
      input?.addEventListener("input", () => {
        field.options[idx] = input.value;
        syncHidden();
      });
      row.querySelector(".ovw-opt-remove")?.addEventListener("click", () => {
        field.options.splice(idx, 1);
        renderSettings();
        syncHidden();
      });
      row.querySelector(".ovw-opt-up")?.addEventListener("click", () => {
        if (idx <= 0) return;
        const tmp = field.options[idx - 1];
        field.options[idx - 1] = field.options[idx];
        field.options[idx] = tmp;
        renderSettings();
        syncHidden();
      });
      row.querySelector(".ovw-opt-down")?.addEventListener("click", () => {
        if (idx >= field.options.length - 1) return;
        const tmp = field.options[idx + 1];
        field.options[idx + 1] = field.options[idx];
        field.options[idx] = tmp;
        renderSettings();
        syncHidden();
      });
    });
  }

  function bindFieldEvents() {
    canvasEl.querySelectorAll(".ovw-field-item").forEach(el => {
      el.addEventListener("click", () => {
        selectedFieldId = el.dataset.id;
        renderSettings();
        renderCanvas();
      });
      el.addEventListener("dragstart", (e) => {
        e.dataTransfer.setData("text/plain", el.dataset.id);
        e.dataTransfer.effectAllowed = "move";
      });
    });

    canvasEl.querySelectorAll(".ovw-field-tools .ovw-tool").forEach(btn => {
      btn.addEventListener("click", (e) => {
        e.stopPropagation();
        const fieldId = btn.closest(".ovw-field-tools")?.dataset.id;
        if (!fieldId) return;
        const action = btn.dataset.action;
        if (action === "del") {
          removeFieldById(fieldId);
          selectedFieldId = null;
        }
        if (action === "up") moveField(fieldId, -1);
        if (action === "down") moveField(fieldId, 1);
        if (action === "dup") duplicateField(fieldId);
        render();
      });
    });

    canvasEl.querySelectorAll(".ovw-row-remove").forEach(btn => {
      btn.addEventListener("click", () => {
        const step = getActiveStep();
        step.rows = (step.rows || []).filter(r => r.id !== btn.dataset.rowId);
        render();
      });
    });
  }

  function bindDropTargets() {
    canvasEl.querySelectorAll(".ovw-col").forEach(colEl => {
      colEl.addEventListener("dragover", (e) => {
        e.preventDefault();
        colEl.classList.add("drag-over");
      });
      colEl.addEventListener("dragleave", () => {
        colEl.classList.remove("drag-over");
      });
      colEl.addEventListener("drop", (e) => {
        e.preventDefault();
        colEl.classList.remove("drag-over");
        const data = e.dataTransfer.getData("text/plain");
        if (!data) return;
        if (data.startsWith("new:")) {
          const type = data.replace("new:", "");
          addFieldToColumn(type, colEl.dataset.colId);
        } else {
          moveFieldToColumn(data, colEl.dataset.colId);
        }
        render();
      });
    });
  }

  function moveFieldToColumn(fieldId, colId) {
    let field = null;
    for (const step of schema.steps) {
      for (const row of step.rows || []) {
        for (const col of row.columns || []) {
          const idx = (col.fields || []).findIndex(f => f.id === fieldId);
          if (idx >= 0) {
            field = col.fields.splice(idx, 1)[0];
          }
        }
      }
    }
    if (!field) return;
    for (const step of schema.steps) {
      for (const row of step.rows || []) {
        for (const col of row.columns || []) {
          if (col.id === colId) {
            col.fields.push(field);
            return;
          }
        }
      }
    }
  }

  function moveField(fieldId, dir) {
    for (const step of schema.steps) {
      for (const row of step.rows || []) {
        for (const col of row.columns || []) {
          const idx = (col.fields || []).findIndex(f => f.id === fieldId);
          if (idx >= 0) {
            const next = idx + dir;
            if (next < 0 || next >= col.fields.length) return;
            const tmp = col.fields[next];
            col.fields[next] = col.fields[idx];
            col.fields[idx] = tmp;
            return;
          }
        }
      }
    }
  }

  function duplicateField(fieldId) {
    for (const step of schema.steps) {
      for (const row of step.rows || []) {
        for (const col of row.columns || []) {
          const idx = (col.fields || []).findIndex(f => f.id === fieldId);
          if (idx >= 0) {
            const orig = col.fields[idx];
            const copy = JSON.parse(JSON.stringify(orig));
            copy.id = uid("field");
            copy.key = slugify(orig.key || orig.label) + "_" + uid("k").slice(-4);
            copy.label = (orig.label || "Field") + " (Copy)";
            copy.auto_key = false;
            col.fields.splice(idx + 1, 0, copy);
            selectedFieldId = copy.id;
            return;
          }
        }
      }
    }
  }

  function removeFieldById(fieldId) {
    for (const step of schema.steps) {
      for (const row of step.rows || []) {
        for (const col of row.columns || []) {
          const idx = (col.fields || []).findIndex(f => f.id === fieldId);
          if (idx >= 0) {
            col.fields.splice(idx, 1);
            return;
          }
        }
      }
    }
  }

  function addFieldToColumn(type, colId) {
    const step = getActiveStep();
    if (!step.rows.length) step.rows.push(defaultRow(1));
    let target = null;
    for (const row of step.rows) {
      for (const col of row.columns || []) {
        if (col.id === colId) target = col;
      }
    }
    if (!target) target = step.rows[0].columns[0];
    const field = defaultField(type);
    target.fields.push(field);
    selectedFieldId = field.id;
  }

  function insertFieldAfter(afterId, colId, type) {
    for (const step of schema.steps) {
      for (const row of step.rows || []) {
        for (const col of row.columns || []) {
          if (colId && col.id !== colId) continue;
          const idx = (col.fields || []).findIndex(f => f.id === afterId);
          if (idx >= 0) {
            const field = defaultField(type);
            col.fields.splice(idx + 1, 0, field);
            selectedFieldId = field.id;
            return;
          }
        }
      }
    }
  }

  function bindInlineAdd() {
    canvasEl.querySelectorAll(".ovw-add-after").forEach(btn => {
      btn.addEventListener("click", (e) => {
        e.preventDefault();
        const colId = btn.dataset.colId;
        showInlineMenu(btn, (type) => {
          if (btn.dataset.afterId) {
            insertFieldAfter(btn.dataset.afterId, colId, type);
          } else {
            addFieldToColumn(type, colId);
          }
          render();
        });
      });
    });
  }

  function showInlineMenu(anchor, onPick) {
    let menu = document.getElementById("ovw_inline_menu");
    if (!menu) {
      menu = document.createElement("div");
      menu.id = "ovw_inline_menu";
      menu.className = "ovw-inline-menu";
      menu.innerHTML = FIELD_TYPES.map(f => `<button type="button" data-type="${f.type}">${f.label}</button>`).join("");
      document.body.appendChild(menu);
      menu.addEventListener("click", (e) => {
        const t = e.target;
        if (t.tagName !== "BUTTON") return;
        const type = t.dataset.type;
        menu.style.display = "none";
        if (type && inlinePick) inlinePick(type);
      });
      document.addEventListener("click", (e) => {
        if (!menu.contains(e.target) && e.target !== anchor) {
          menu.style.display = "none";
        }
      });
    }
    inlinePick = onPick;
    const rect = anchor.getBoundingClientRect();
    menu.style.display = "grid";
    menu.style.top = (rect.bottom + window.scrollY + 6) + "px";
    menu.style.left = (rect.left + window.scrollX) + "px";
  }
  function addFieldToActiveColumn(type) {
    addFieldToColumn(type, null);
    render();
  }

  function render() {
    renderSteps();
    renderCanvas();
    renderSettings();
    syncHidden();
  }

  addStepBtn?.addEventListener("click", () => {
    const step = defaultStep();
    schema.steps.push(step);
    activeStepId = step.id;
    render();
  });

  addRowBtn?.addEventListener("click", () => {
    const step = getActiveStep();
    step.rows.push(defaultRow(1));
    render();
  });

  addRow2Btn?.addEventListener("click", () => {
    const step = getActiveStep();
    step.rows.push(defaultRow(2));
    render();
  });

  document.querySelectorAll(".ovw-palette-item").forEach(btn => {
    btn.setAttribute("draggable", "true");
    btn.addEventListener("click", () => addFieldToActiveColumn(btn.dataset.type));
    btn.addEventListener("dragstart", (e) => {
      e.dataTransfer.setData("text/plain", "new:" + btn.dataset.type);
      e.dataTransfer.effectAllowed = "copy";
    });
  });

  canvasEl.addEventListener("drop", (e) => {
    const data = e.dataTransfer.getData("text/plain");
    if (!data || !data.startsWith("new:")) return;
    const type = data.replace("new:", "");
    addFieldToActiveColumn(type);
  });

  render();

  titleInput?.addEventListener("input", () => {
    syncHidden();
  });

  function optionEl(value, label, current) {
    return `<option value="${value}" ${current === value ? "selected" : ""}>${label}</option>`;
  }

  function escapeHtml(s) {
    return (s || "").toString()
      .replaceAll("&", "&amp;")
      .replaceAll("<", "&lt;")
      .replaceAll(">", "&gt;")
      .replaceAll("\"", "&quot;");
  }
})();

