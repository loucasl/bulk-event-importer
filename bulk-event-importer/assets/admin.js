document.addEventListener("DOMContentLoaded", function () {

  function escapeHtml(str) {
    return String(str || "")
      .replace(/&/g, "&amp;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;")
      .replace(/"/g, "&quot;")
      .replace(/'/g, "&#039;");
  }

  function genKey(prefix) {
    return (prefix || "grp") + "_" + Math.random().toString(36).slice(2, 8) + Date.now().toString(36).slice(-4);
  }

  /* ===============================
     UNIVERSAL CHIP INPUT SYSTEM (event-delegated so it also works
     on rows added dynamically by the taxonomy builder)
  ================================= */

  function updateChipHidden(wrapper) {
    const chipsContainer = wrapper.querySelector(".chips");
    const hidden = wrapper.querySelector(".chip-hidden");
    if (!chipsContainer || !hidden) return;
    const keywords = Array.from(chipsContainer.querySelectorAll(".keyword-chip")).map(function (chip) {
      return chip.firstChild.textContent.trim();
    });
    hidden.value = keywords.join(",");
  }

  function addChip(wrapper, keyword) {
    if (!keyword) return;
    keyword = keyword.trim();
    if (!keyword) return;

    const hidden = wrapper.querySelector(".chip-hidden");
    const chipsContainer = wrapper.querySelector(".chips");
    if (!hidden || !chipsContainer) return;

    const currentVal = hidden.value ? hidden.value.split(",").map(function (k) { return k.trim().toLowerCase(); }) : [];
    if (currentVal.includes(keyword.toLowerCase())) return;

    const chip = document.createElement("span");
    chip.className = "keyword-chip";
    chip.innerHTML = escapeHtml(keyword) + '<button type="button" class="remove-chip">&times;</button>';
    chipsContainer.appendChild(chip);

    updateChipHidden(wrapper);
  }

  document.addEventListener("keydown", function (e) {
    if (!e.target.classList || !e.target.classList.contains("chip-input")) return;
    if (e.key === "Enter" || e.key === ",") {
      e.preventDefault();
      const wrapper = e.target.closest(".chip-wrapper");
      if (wrapper) {
        addChip(wrapper, e.target.value);
        e.target.value = "";
      }
    }
  });

  document.addEventListener("click", function (e) {
    if (e.target.classList && e.target.classList.contains("remove-chip")) {
      const wrapper = e.target.closest(".chip-wrapper");
      e.target.closest(".keyword-chip").remove();
      if (wrapper) updateChipHidden(wrapper);
    }
  });

  /* ===============================
     DYNAMIC TAXONOMY / CATEGORY BUILDER
  ================================= */

  const taxList = document.getElementById("bei-taxonomy-list");
  const addTaxBtn = document.getElementById("bei-add-taxonomy");
  const taxJsonInput = document.getElementById("bei-taxonomies-json");
  const taxBlockTpl = document.getElementById("bei-taxonomy-block-template");
  const groupRowTpl = document.getElementById("bei-group-row-template");

  function optionName() {
    return (window.bulkEventImporter && bulkEventImporter.optionName) || "bulk_event_importer_settings";
  }

  function makeGroupRow(key) {
    const groupKey = key || genKey("grp");
    const frag = groupRowTpl.content.cloneNode(true);
    const row = frag.querySelector(".bei-group-row");
    row.dataset.groupKey = groupKey;
    const hidden = row.querySelector(".chip-hidden");
    hidden.name = optionName() + "[group_kw__" + groupKey + "]";
    return row;
  }

  if (addTaxBtn && taxList && taxBlockTpl) {
    addTaxBtn.addEventListener("click", function () {
      const frag = taxBlockTpl.content.cloneNode(true);
      const block = frag.querySelector(".bei-taxonomy-block");
      block.dataset.taxIndex = String(taxList.children.length);
      taxList.appendChild(block);
    });
  }

  if (taxList) {
    taxList.addEventListener("click", function (e) {
      if (e.target.classList.contains("bei-remove-taxonomy")) {
        const block = e.target.closest(".bei-taxonomy-block");
        if (block) block.remove();
        return;
      }
      if (e.target.classList.contains("bei-add-group")) {
        const block = e.target.closest(".bei-taxonomy-block");
        const tbody = block ? block.querySelector(".bei-group-rows") : null;
        if (tbody) {
          tbody.appendChild(makeGroupRow());
        }
        return;
      }
      if (e.target.classList.contains("bei-remove-group")) {
        const row = e.target.closest(".bei-group-row");
        if (row) row.remove();
        return;
      }
    });
  }

  function serializeTaxonomies() {
    const result = [];
    if (!taxList) return result;

    taxList.querySelectorAll(".bei-taxonomy-block").forEach(function (block) {
      const slug = (block.querySelector(".bei-tax-slug") || {}).value || "";
      const label = (block.querySelector(".bei-tax-label") || {}).value || "";
      const defaultTerm = (block.querySelector(".bei-tax-default") || {}).value || "";
      if (!slug.trim() || !label.trim()) return;

      const groups = [];
      block.querySelectorAll(".bei-group-row").forEach(function (row) {
        const key = row.dataset.groupKey;
        const term = (row.querySelector(".bei-group-term") || {}).value || "";
        if (!key || !term.trim()) return;
        groups.push({ key: key, term: term.trim() });
      });

      result.push({
        slug: slug.trim(),
        label: label.trim(),
        default_term: defaultTerm.trim(),
        groups: groups,
      });
    });

    return result;
  }

  const settingsForm = document.querySelector(".bulk-event-importer-settings form");
  if (settingsForm && taxJsonInput) {
    settingsForm.addEventListener("submit", function () {
      taxJsonInput.value = JSON.stringify(serializeTaxonomies());
    });
  }

  /* ===============================
     FIELD MAP: date mode toggle
  ================================= */

  const dateModeSelect = document.getElementById("bei-date-mode");
  function applyDateModeVisibility() {
    if (!dateModeSelect) return;
    const isJe = dateModeSelect.value === "je_advanced_date";
    document.querySelectorAll(".bei-fm-split").forEach(function (row) {
      row.style.display = isJe ? "none" : "";
    });
    document.querySelectorAll(".bei-fm-je").forEach(function (row) {
      row.style.display = isJe ? "" : "none";
    });
  }
  if (dateModeSelect) {
    dateModeSelect.addEventListener("change", applyDateModeVisibility);
    applyDateModeVisibility();
  }

  /* ===============================
     STATIC EXTRA META REPEATER
  ================================= */

  const extraRows = document.getElementById("bei-extra-meta-rows");
  const addExtraBtn = document.getElementById("bei-add-extra-meta");

  if (addExtraBtn && extraRows) {
    addExtraBtn.addEventListener("click", function () {
      const p = document.createElement("p");
      p.className = "bei-extra-row";
      p.innerHTML =
        '<input type="text" name="' + optionName() + '[fm_extra_keys][]" placeholder="meta key">' +
        '<input type="text" name="' + optionName() + '[fm_extra_values][]" placeholder="value">' +
        '<button type="button" class="button-link bei-remove-extra">&times;</button>';
      extraRows.appendChild(p);
    });
  }

  if (extraRows) {
    extraRows.addEventListener("click", function (e) {
      if (e.target.classList.contains("bei-remove-extra")) {
        e.target.closest(".bei-extra-row").remove();
      }
    });
  }

  /* ===============================
     AJAX IMPORT + PROGRESS BAR
  ================================= */

  let importRunning = false;
  let cancelRequested = false;
  let currentJobId = "";
  let statusDivRef = null;
  const perFeed = {};

  const runBtn = document.getElementById("run-import-ajax");
  const cancelBtn = document.getElementById("cancel-import-ajax");

  if (runBtn) {
    runBtn.addEventListener("click", function (e) {
      e.preventDefault();
      runImport();
    });
  }

  if (cancelBtn) {
    cancelBtn.addEventListener("click", function (e) {
      e.preventDefault();
      requestCancel();
    });
  }

  if (window.location.hash === "#import-progress") {
    runImport();
  }

  function renderTotals(totals) {
    if (!totals) return "";
    return (
      "<hr>" +
      "<strong>Totals so far</strong><br>" +
      "Created: " + Number(totals.created || 0) + "<br>" +
      "Updated: " + Number(totals.updated || 0) + "<br>" +
      "Skipped: " + Number(totals.skipped || 0) + "<br>" +
      "Deleted: " + Number(totals.deleted || 0)
    );
  }

  function renderFeedsTable() {
    const keys = Object.keys(perFeed)
      .map(function (k) { return Number(k); })
      .filter(function (n) { return Number.isFinite(n); })
      .sort(function (a, b) { return a - b; });

    if (!keys.length) return "";

    let html = '<table class="widefat striped" style="margin-top:12px; width:100%; text-align:left;">' +
      "<thead><tr><th>Feed</th><th>Progress</th><th>Created</th><th>Updated</th><th>Skipped</th></tr></thead><tbody>";

    keys.forEach(function (k) {
      const feed = perFeed[k] || {};
      const source = escapeHtml(feed.source || "Unknown");
      const url = escapeHtml(feed.url || "");
      const errors = Array.isArray(feed.errors) ? feed.errors.filter(Boolean) : [];
      const errCount = errors.length;
      const debugObj = feed.debug && typeof feed.debug === "object" ? feed.debug : null;

      const skipReasons = feed.skip_reasons && typeof feed.skip_reasons === "object" ? feed.skip_reasons : {};
      const blockedCounts = feed.blocked_keyword_counts && typeof feed.blocked_keyword_counts === "object" ? feed.blocked_keyword_counts : {};

      const reasonLabel = {
        blocked_keyword_match: "Blocked keywords",
        allowlist_no_match: "No allowlist match",
        missing_start_date: "Missing start date",
        invalid_start_date: "Invalid start date",
        date_out_of_import_window: "Out of import window",
        event_exception: "Unexpected error",
      };

      function getTopBlockedKeywords(max) {
        const entries = Object.entries(blockedCounts).filter(e => e[0] && Number(e[1]) > 0);
        entries.sort((a, b) => Number(b[1]) - Number(a[1]));
        return entries.slice(0, max).map(([kw, count]) => escapeHtml(kw) + " (" + Number(count) + ")");
      }

      function getTopReasons(max) {
        const entries = Object.entries(skipReasons).filter(e => e[1] && Number(e[1]) > 0);
        entries.sort((a, b) => Number(b[1]) - Number(a[1]));
        return entries.slice(0, max).map(([reason, count]) => escapeHtml(reasonLabel[reason] || reason) + ": " + Number(count));
      }

      let skipSummaryHtml = "";
      if (Object.keys(skipReasons).length) {
        const topReasons = getTopReasons(3);
        const blockedTotal = Number(skipReasons.blocked_keyword_match || 0);
        if (blockedTotal > 0 && Object.keys(blockedCounts).length) {
          const blockedList = getTopBlockedKeywords(9999);
          skipSummaryHtml =
            '<div class="bei-skip-callout"><div class="bei-skip-title">' +
            blockedTotal + " events skipped due to " + Object.keys(blockedCounts).length + " blocked keywords:</div>" +
            '<div class="bei-skip-keywords">' + (blockedList.length ? blockedList.join(", ") : "\u2014") + "</div></div>";
        } else if (topReasons.length) {
          skipSummaryHtml =
            '<div class="bei-skip-callout"><div class="bei-skip-title">Events skipped (breakdown):</div>' +
            '<div class="bei-skip-keywords">' + topReasons.join(", ") + "</div></div>";
        }
      }

      const errHtml = errCount
        ? '<details style="margin-top:4px;"><summary>Errors (' + errCount + ')</summary><ul style="margin:6px 0 0 18px;">' +
          errors.slice(-6).map(function (e) { return "<li>" + escapeHtml(e) + "</li>"; }).join("") +
          "</ul></details>"
        : "";

      let debugHtml = "";
      if (errCount && debugObj) {
        const fetchDbg = debugObj.fetch && typeof debugObj.fetch === "object" ? debugObj.fetch : null;
        const parseErr = debugObj.parse_error ? String(debugObj.parse_error) : "";

        let summaryLine = "";
        if (fetchDbg) {
          const attempts = Number(fetchDbg.attempts_total || 0);
          const ms = Number(fetchDbg.timing_ms || 0);
          const last = String(fetchDbg.last_error || "");
          const code = Number(fetchDbg.http_code || 0);
          summaryLine =
            "Fetch: " + (attempts ? attempts + " attempt" + (attempts === 1 ? "" : "s") : "\u2014") +
            (ms ? ", " + ms + "ms" : "") + (code ? ", HTTP " + code : "") + (last ? ", last: " + escapeHtml(last) : "");
        } else if (parseErr) {
          summaryLine = "Parse: " + escapeHtml(parseErr);
        }

        if (summaryLine) {
          debugHtml = '<div style="margin-top:6px;font-size:12px;opacity:0.95;"><strong>Debug:</strong> ' + summaryLine + "</div>";
        }

        const detailsPayload = {};
        if (fetchDbg) detailsPayload.fetch = fetchDbg;
        if (parseErr) detailsPayload.parse_error = parseErr;
        if (Object.keys(detailsPayload).length) {
          debugHtml += '<details style="margin-top:4px;"><summary>Debug details</summary><pre style="white-space:pre-wrap;margin:6px 0 0;">' +
            escapeHtml(JSON.stringify(detailsPayload, null, 2)) + "</pre></details>";
        }
      }

      html +=
        "<tr><td>" +
          "<strong>Feed " + (k + 1) + ": " + source + "</strong><br>" +
          '<span style="font-size:12px;opacity:0.9;word-break:break-all;">' + url + "</span>" +
          skipSummaryHtml + debugHtml + errHtml +
        "</td>" +
        "<td>" + Number(feed.done || 0) + " / " + Number(feed.total || 0) + "</td>" +
        "<td>" + Number(feed.created || 0) + "</td>" +
        "<td>" + Number(feed.updated || 0) + "</td>" +
        "<td>" + Number(feed.skipped || 0) + "</td></tr>";
    });

    html += "</tbody></table>";
    return html;
  }

  function setRunButtonState(running) {
    if (!runBtn) return;
    runBtn.disabled = running;
    runBtn.innerText = running ? "Importing..." : "Run Import Now";
  }

  function setCancelButtonState(running) {
    if (!cancelBtn) return;
    cancelBtn.style.display = running ? "" : "none";
    cancelBtn.disabled = !running;
    cancelBtn.innerText = "Cancel Import";
  }

  function requestCancel() {
    if (!importRunning || cancelRequested) return;
    cancelRequested = true;

    if (cancelBtn) {
      cancelBtn.disabled = true;
      cancelBtn.innerText = "Cancelling...";
    }
    if (statusDivRef) {
      statusDivRef.insertAdjacentHTML("beforeend", "<br><strong>Import cancelling...</strong>");
    }
    if (!currentJobId) return;

    const cancelParams = new URLSearchParams();
    cancelParams.append("action", "bulk_event_ajax_import_cancel");
    cancelParams.append("_ajax_nonce", bulkEventImporter.nonce);
    cancelParams.append("job_id", currentJobId);

    fetch(ajaxurl, {
      method: "POST",
      credentials: "same-origin",
      headers: { "Content-Type": "application/x-www-form-urlencoded; charset=UTF-8" },
      body: cancelParams.toString(),
    }).then(function (r) { return r.json(); }).catch(function () {});
  }

  function runImport() {
    if (importRunning) return;
    importRunning = true;
    cancelRequested = false;
    currentJobId = "";

    const progressContainer = document.getElementById("import-progress");
    const statusDiv = document.getElementById("import-status");
    const progressBar = document.getElementById("progress-bar");
    statusDivRef = statusDiv;

    setRunButtonState(true);
    setCancelButtonState(true);

    progressContainer.style.display = "block";
    statusDiv.innerHTML = "Starting import...";
    progressBar.style.width = "0%";

    Object.keys(perFeed).forEach(function (k) { delete perFeed[k]; });

    const startParams = new URLSearchParams();
    startParams.append("action", "bulk_event_ajax_import_start");
    startParams.append("_ajax_nonce", bulkEventImporter.nonce);

    fetch(ajaxurl, {
      method: "POST",
      credentials: "same-origin",
      headers: { "Content-Type": "application/x-www-form-urlencoded; charset=UTF-8" },
      body: startParams.toString(),
    })
      .then(function (r) { return r.json(); })
      .then(function (start) {
        if (!start.success) {
          throw new Error((start.data && start.data.message) || "Start failed");
        }

        const jobId = start.data.job_id;
        currentJobId = jobId;
        const feedCount = Number(start.data.feed_count || 0);

        statusDiv.innerHTML =
          "<strong>Import started</strong><br>Feeds: " + feedCount +
          '<br><br><div id="bei-feed-list"></div><div id="bei-summary"></div>';

        const list = document.getElementById("bei-feed-list");
        const summary = document.getElementById("bei-summary");

        function mergePerFeed(perFeedData) {
          if (!perFeedData) return;
          if (Array.isArray(perFeedData)) {
            perFeedData.forEach(function (f, idx) { if (f) perFeed[idx] = f; });
            return;
          }
          if (typeof perFeedData === "object") {
            Object.entries(perFeedData).forEach(function (entry) {
              const idx = Number(entry[0]);
              const feed = entry[1];
              if (Number.isFinite(idx) && feed) perFeed[idx] = feed;
            });
          }
        }

        function step() {
          if (cancelRequested) {
            statusDiv.insertAdjacentHTML("beforeend", "<br><strong>Import cancelled.</strong>");
            importRunning = false;
            setRunButtonState(false);
            setCancelButtonState(false);
            return;
          }

          const stepParams = new URLSearchParams();
          stepParams.append("action", "bulk_event_ajax_import_step");
          stepParams.append("_ajax_nonce", bulkEventImporter.nonce);
          stepParams.append("job_id", jobId);

          fetch(ajaxurl, {
            method: "POST",
            credentials: "same-origin",
            headers: { "Content-Type": "application/x-www-form-urlencoded; charset=UTF-8" },
            body: stepParams.toString(),
          })
            .then(function (r) { return r.json(); })
            .then(function (res) {
              if (!res.success) {
                throw new Error((res.data && res.data.message) || "Step failed");
              }

              const data = res.data || {};

              if (typeof data.feed_i !== "undefined" && data.feed) {
                perFeed[data.feed_i] = data.feed;
              }

              if (data.done) {
                progressBar.style.width = "100%";
                mergePerFeed(data.per_feed);

                list.innerHTML = renderFeedsTable();
                summary.innerHTML =
                  "<hr><strong>" + (data.cancelled ? "Import cancelled" : "Import complete!") + "</strong><br><br>" +
                  "Feeds processed: " + Object.keys(perFeed).length + "<br>" +
                  "Events created: " + Number((data.totals && data.totals.created) || 0) + "<br>" +
                  "Events updated: " + Number((data.totals && data.totals.updated) || 0) + "<br>" +
                  "Events skipped: " + Number((data.totals && data.totals.skipped) || 0) + "<br>" +
                  "Blocked/filtered events removed: " + Number((data.totals && data.totals.deleted) || 0) + "<br>" +
                  "Old events moved to trash: " + Number((data.totals && data.totals.old_trashed) || 0);

                importRunning = false;
                setRunButtonState(false);
                setCancelButtonState(false);
                return;
              }

              const currentFeed = data.feed || {};
              const i = Number(data.feed_i || 0);
              const total = Number(currentFeed.total || 0);
              const done = Number(currentFeed.done || 0);
              const safeFeedCount = feedCount > 0 ? feedCount : 1;

              const within = total > 0 ? Math.min(1, Math.max(0, done / total)) : 1;
              const pct = Math.max(0, Math.min(100, ((i + within) / safeFeedCount) * 100));
              progressBar.style.width = pct.toFixed(2) + "%";

              list.innerHTML = renderFeedsTable();
              summary.innerHTML = renderTotals(data.totals);

              setTimeout(step, 100);
            })
            .catch(function (err) {
              statusDiv.insertAdjacentHTML("beforeend", "<br><strong style=\"color:red\">Import stopped</strong>: " + escapeHtml(err.message));
              importRunning = false;
              setRunButtonState(false);
              setCancelButtonState(false);
            });
        }

        step();
      })
      .catch(function (err) {
        statusDiv.innerHTML = "<strong style=\"color:red\">Import failed</strong><br>" + escapeHtml(err.message);
        importRunning = false;
        setRunButtonState(false);
        setCancelButtonState(false);
      });
  }
});
