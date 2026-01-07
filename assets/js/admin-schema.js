(function () {
  const app = document.getElementById("ovw-schema-app");
  if (!app) return;

  let schema = {};
  try {
    schema = JSON.parse(app.dataset.schema || "{}");
  } catch (e) {
    schema = {};
  }
  if (!schema.sections) schema.sections = [];

  const sectionsEl = document.getElementById("ovw_schema_sections");
  const titleEl = document.getElementById("ovw_schema_title");
  const addSectionBtn = document.getElementById("ovw_schema_add_section");
  const hidden = document.getElementById("ovw_schema_json");
  const form = app.closest("form");

  function slugify(s) {
    return (s || "")
      .toString()
      .toLowerCase()
      .replace(/[^a-z0-9]+/g, "_")
      .replace(/^_+|_+$/g, "") || "field";
  }

  function uniqueKey(prefix) {
    return prefix + "_" + Date.now().toString(36) + Math.floor(Math.random() * 1000).toString(36);
  }

  function newField() {
    return {
      key: uniqueKey("field"),
      label: "New Field",
      type: "text",
      required: false,
      options: [],
      placeholder: ""
    };
  }

  function newSection() {
    return {
      key: uniqueKey("section"),
      label: "New Section",
      fields: [newField()]
    };
  }

  function optionString(opts) {
    if (!Array.isArray(opts)) return "";
    return opts.join(", ");
  }

  function render() {
    sectionsEl.innerHTML = schema.sections.map((sec, i) => {
      const rows = (sec.fields || []).map((f, j) => {
        const optionsVal = optionString(f.options);
        const optionsDisabled = f.type !== "select" ? "disabled" : "";
        const reqChecked = f.required ? "checked" : "";

        return `
          <tr data-section="${i}" data-field="${j}">
            <td><input type="text" class="ovw-field-label" value="${esc(f.label)}" placeholder="Field label"></td>
            <td><input type="text" class="ovw-field-key" value="${esc(f.key)}" placeholder="field_key"></td>
            <td>
              <select class="ovw-field-type">
                <option value="text" ${f.type === "text" ? "selected" : ""}>Text</option>
                <option value="textarea" ${f.type === "textarea" ? "selected" : ""}>Textarea</option>
                <option value="select" ${f.type === "select" ? "selected" : ""}>Select</option>
                <option value="date" ${f.type === "date" ? "selected" : ""}>Date</option>
              </select>
            </td>
            <td><input type="checkbox" class="ovw-field-required" ${reqChecked}></td>
            <td><input type="text" class="ovw-field-options" value="${esc(optionsVal)}" placeholder="Option 1, Option 2" ${optionsDisabled}></td>
            <td><input type="text" class="ovw-field-placeholder" value="${esc(f.placeholder || "")}" placeholder="Placeholder"></td>
            <td><button type="button" class="button link-delete ovw-remove-field">Remove</button></td>
          </tr>
        `;
      }).join("");

      return `
        <div class="ovw-schema-section" data-index="${i}">
          <div class="ovw-schema-section-head">
            <div class="ovw-schema-field">
              <label><strong>Section Label</strong></label>
              <input type="text" class="ovw-section-label" value="${esc(sec.label)}" placeholder="Section label">
            </div>
            <div class="ovw-schema-field">
              <label><strong>Section Key</strong></label>
              <input type="text" class="ovw-section-key" value="${esc(sec.key)}" placeholder="section_key">
            </div>
            <button type="button" class="button link-delete ovw-remove-section">Remove Section</button>
          </div>

          <div class="ovw-schema-hint">Changing keys can break existing biodata. Only change if needed.</div>

          <table class="ovw-schema-table">
            <thead>
              <tr>
                <th>Label</th>
                <th>Key</th>
                <th>Type</th>
                <th>Required</th>
                <th>Options (for Select)</th>
                <th>Placeholder</th>
                <th></th>
              </tr>
            </thead>
            <tbody>${rows}</tbody>
          </table>

          <div class="ovw-schema-actions">
            <button type="button" class="button ovw-add-field">Add Field</button>
          </div>
        </div>
      `;
    }).join("");
    syncHidden();
  }

  function esc(s) {
    return (s || "").toString()
      .replaceAll("&", "&amp;")
      .replaceAll("<", "&lt;")
      .replaceAll(">", "&gt;")
      .replaceAll("\"", "&quot;");
  }

  function collectSchemaFromDom() {
    const out = {
      title: (titleEl?.value || "Matrimonial Biodata").trim(),
      sections: []
    };

    const sectionEls = sectionsEl.querySelectorAll(".ovw-schema-section");
    sectionEls.forEach(secEl => {
      const label = secEl.querySelector(".ovw-section-label")?.value || "Section";
      const key = secEl.querySelector(".ovw-section-key")?.value || slugify(label);
      const fields = [];

      secEl.querySelectorAll("tbody tr").forEach(row => {
        const fLabel = row.querySelector(".ovw-field-label")?.value || "Field";
        const fKey = row.querySelector(".ovw-field-key")?.value || slugify(fLabel);
        const fType = row.querySelector(".ovw-field-type")?.value || "text";
        const fReq = !!row.querySelector(".ovw-field-required")?.checked;
        const fOptsRaw = row.querySelector(".ovw-field-options")?.value || "";
        const fPlaceholder = row.querySelector(".ovw-field-placeholder")?.value || "";

        const field = {
          key: fKey,
          label: fLabel,
          type: fType,
          required: fReq
        };

        if (fType === "select") {
          field.options = fOptsRaw.split(",").map(s => s.trim()).filter(Boolean);
        }
        if (fPlaceholder.trim()) {
          field.placeholder = fPlaceholder.trim();
        }
        fields.push(field);
      });

      out.sections.push({
        key,
        label,
        fields
      });
    });

    return out;
  }

  addSectionBtn?.addEventListener("click", () => {
    schema.sections.push(newSection());
    render();
  });


  sectionsEl.addEventListener("click", (e) => {
    const target = e.target;
    if (!(target instanceof HTMLElement)) return;

    if (target.classList.contains("ovw-remove-section")) {
      const sec = target.closest(".ovw-schema-section");
      const idx = parseInt(sec?.dataset.index || "-1", 10);
      if (idx >= 0) {
        schema.sections.splice(idx, 1);
        render();
      }
    }

    if (target.classList.contains("ovw-add-field")) {
      const sec = target.closest(".ovw-schema-section");
      const idx = parseInt(sec?.dataset.index || "-1", 10);
      if (idx >= 0) {
        schema.sections[idx].fields = schema.sections[idx].fields || [];
        schema.sections[idx].fields.push(newField());
        render();
      }
    }

    if (target.classList.contains("ovw-remove-field")) {
      const row = target.closest("tr");
      const secIdx = parseInt(row?.dataset.section || "-1", 10);
      const fieldIdx = parseInt(row?.dataset.field || "-1", 10);
      if (secIdx >= 0 && fieldIdx >= 0) {
        schema.sections[secIdx].fields.splice(fieldIdx, 1);
        render();
      }
    }
  });

  sectionsEl.addEventListener("input", (e) => {
    const target = e.target;
    if (!(target instanceof HTMLElement)) return;

    const secEl = target.closest(".ovw-schema-section");
    const secIdx = parseInt(secEl?.dataset.index || "-1", 10);
    if (secIdx < 0) return;

    if (target.classList.contains("ovw-section-label")) {
      schema.sections[secIdx].label = target.value;
      const keyInput = secEl.querySelector(".ovw-section-key");
      if (keyInput && !keyInput.value.trim()) {
        keyInput.value = slugify(target.value);
        schema.sections[secIdx].key = keyInput.value;
      }
    }

    if (target.classList.contains("ovw-section-key")) {
      schema.sections[secIdx].key = target.value;
    }

    const row = target.closest("tr");
    if (!row) return;
    const fieldIdx = parseInt(row.dataset.field || "-1", 10);
    if (fieldIdx < 0) return;

    const field = schema.sections[secIdx].fields[fieldIdx];
    if (target.classList.contains("ovw-field-label")) {
      field.label = target.value;
      const keyInput = row.querySelector(".ovw-field-key");
      if (keyInput && !keyInput.value.trim()) {
        keyInput.value = slugify(target.value);
        field.key = keyInput.value;
      }
    }
    if (target.classList.contains("ovw-field-key")) {
      field.key = target.value;
    }
    if (target.classList.contains("ovw-field-type")) {
      field.type = target.value;
      const optInput = row.querySelector(".ovw-field-options");
      if (optInput) optInput.disabled = target.value !== "select";
    }
    if (target.classList.contains("ovw-field-required")) {
      field.required = !!target.checked;
    }
    if (target.classList.contains("ovw-field-options")) {
      field.options = target.value.split(",").map(s => s.trim()).filter(Boolean);
    }
    if (target.classList.contains("ovw-field-placeholder")) {
      field.placeholder = target.value;
    }
  });

  function syncHidden() {
    const out = collectSchemaFromDom();
    hidden.value = JSON.stringify(out);
  }

  sectionsEl.addEventListener("input", () => {
    syncHidden();
  });

  titleEl?.addEventListener("input", () => {
    syncHidden();
  });

  form?.addEventListener("submit", () => {
    syncHidden();
  });

  render();
})();
