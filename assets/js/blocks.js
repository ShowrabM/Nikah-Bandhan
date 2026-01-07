(function () {
  var el = window.wp.element.createElement;
  var registerBlockType = window.wp.blocks.registerBlockType;

  function block(name, title, icon) {
    registerBlockType("ovw-matrimonial/" + name, {
      title: title,
      category: "ovw-matrimonial",
      icon: icon || "id",
      edit: function () {
        return el(
          "div",
          { className: "ovw-mat-block-placeholder" },
          el("strong", null, title),
          el("div", { className: "ovw-mat-block-note" }, "This block renders on the front-end.")
        );
      },
      save: function () {
        return null;
      }
    });
  }

  block("dashboard", "Matrimonial Dashboard", "id");
  block("create", "Matrimonial Biodata Form", "forms");
  block("search", "Matrimonial Search", "search");
  block("view", "Matrimonial View", "visibility");
})();
