// glossary.jsx - deterministic speech-to-speech glossary controls

function s2sCsrfToken() {
  return document.querySelector('meta[name="csrf-token"]')?.getAttribute("content") || "";
}

const VOCABULARY_RULE_TYPES = [
  {
    value: "filler",
    label: "Filler cleanup",
    help: "Remove speech fillers such as um, uh, like, or repeated hesitation words before translation.",
    needsReplacement: false,
  },
  {
    value: "correction",
    label: "Correction",
    help: "Fix ASR mistakes before translation.",
    needsReplacement: true,
  },
  {
    value: "replacement",
    label: "Replacement",
    help: "Always replace one phrase with another.",
    needsReplacement: true,
  },
  {
    value: "phonetic",
    label: "Phonetic",
    help: "Keep a phrase but provide a speaking hint for downstream audio.",
    needsReplacement: true,
  },
  {
    value: "blocked",
    label: "Blocked",
    help: "Redact this phrase from transcript text.",
    needsReplacement: false,
  },
  {
    value: "bad_word",
    label: "Bad word",
    help: "Redact profanity or unsafe text.",
    needsReplacement: false,
  },
  {
    value: "shadow_word",
    label: "Shadow word",
    help: "Redact sensitive words that should not be spoken.",
    needsReplacement: false,
  },
];

const vocabularyTypeMeta = (value) => (
  VOCABULARY_RULE_TYPES.find(type => type.value === value) || {
    value,
    label: String(value || "Rule").replace(/_/g, " "),
    help: "Vocabulary rule.",
    needsReplacement: true,
  }
);

async function vocabularyRequest(path, options = {}) {
  const bodyIsForm = options.body instanceof FormData;
  const headers = {
    "Accept": "application/json",
    "X-Requested-With": "XMLHttpRequest",
    ...(bodyIsForm ? {} : { "Content-Type": "application/json" }),
    ...(options.headers || {}),
  };
  const token = s2sCsrfToken();
  if (token) headers["X-CSRF-TOKEN"] = token;

  const res = await fetch(path, { ...options, headers });
  const payload = (res.headers.get("content-type") || "").includes("application/json")
    ? await res.json()
    : { message: await res.text() };
  if (!res.ok) {
    const error = new Error(payload.message || `Vocabulary request failed (${res.status})`);
    error.payload = payload;
    throw error;
  }
  return payload;
}

function VocabularyDialog({ open, sourceLang, onClose }) {
  const [rows, setRows] = React.useState([]);
  const [warning, setWarning] = React.useState("");
  const [savingId, setSavingId] = React.useState(null);
  const [filter, setFilter] = React.useState("all");
  const [draft, setDraft] = React.useState({
    rule_type: "filler",
    language_code: sourceLang === "auto" ? "" : (sourceLang || ""),
    source_phrase: "",
    replacement_text: "",
    phonetic_hint: "",
    priority: 30,
    notes: "",
    is_active: true,
  });

  const loadRows = React.useCallback(async () => {
    if (!open) return;
    try {
      const payload = await vocabularyRequest("/speech-to-speech/vocabulary");
      setRows(payload.items || []);
      setWarning("");
    } catch (e) {
      setWarning(e.payload?.message || e.message);
    }
  }, [open]);

  React.useEffect(() => {
    if (!open) return;
    setDraft(prev => ({
      ...prev,
      language_code: sourceLang === "auto" ? "" : (sourceLang || prev.language_code || ""),
    }));
  }, [open, sourceLang]);

  React.useEffect(() => { loadRows(); }, [loadRows]);

  if (!open) return null;

  const visibleRows = rows.filter(row => filter === "all" || row.rule_type === filter);
  const fillerCount = rows.filter(row => row.rule_type === "filler").length;

  const normalizeRule = (rule) => ({
    rule_type: rule.rule_type || "replacement",
    language_code: String(rule.language_code || "").trim() || null,
    source_phrase: String(rule.source_phrase || "").trim(),
    replacement_text: String(rule.replacement_text || "").trim(),
    phonetic_hint: String(rule.phonetic_hint || "").trim(),
    priority: Number(rule.priority || 100),
    is_active: !!rule.is_active,
    notes: String(rule.notes || "").trim(),
  });

  const saveRule = async (rule) => {
    const next = normalizeRule(rule);
    if (!next.source_phrase) {
      setWarning("Source phrase is required.");
      await loadRows();
      return false;
    }
    const meta = vocabularyTypeMeta(next.rule_type);
    if (meta.needsReplacement && !next.replacement_text && next.rule_type !== "phonetic") {
      setWarning(`${meta.label} rules need replacement text.`);
      await loadRows();
      return false;
    }
    setSavingId(rule.id || "new");
    try {
      const method = rule.id ? "PUT" : "POST";
      const path = rule.id ? `/speech-to-speech/vocabulary/${rule.id}` : "/speech-to-speech/vocabulary";
      await vocabularyRequest(path, { method, body: JSON.stringify(next) });
      setWarning("");
      await loadRows();
      window.dispatchEvent(new CustomEvent("vocabulary_changed", { detail: next }));
      return true;
    } catch (e) {
      setWarning(e.payload?.message || e.message);
      return false;
    } finally {
      setSavingId(null);
    }
  };

  const addRule = async () => {
    const saved = await saveRule(draft);
    if (!saved) return;
    setDraft(prev => ({
      ...prev,
      source_phrase: "",
      replacement_text: "",
      phonetic_hint: "",
      notes: "",
      priority: prev.rule_type === "filler" ? 30 : 100,
    }));
  };

  const updateRow = (id, field, value) => {
    setRows(prev => prev.map(row => row.id === id ? { ...row, [field]: value } : row));
  };

  const setDraftType = (ruleType) => {
    const meta = vocabularyTypeMeta(ruleType);
    setDraft(prev => ({
      ...prev,
      rule_type: ruleType,
      replacement_text: meta.needsReplacement ? prev.replacement_text : "",
      priority: ruleType === "filler" ? 30 : (prev.priority || 100),
    }));
  };

  return (
    <div className="modal-back" onClick={onClose}>
      <div className="modal glossary-modal vocabulary-modal" onClick={e => e.stopPropagation()}>
        <div className="modal-head">
          <h3>Vocabulary Rules</h3>
          <p>Manage DB-backed cleanup rules before translation. Filler cleanup is a first-class rule type and is intended for hesitation words that should disappear from output.</p>
        </div>
        <div className="modal-body glossary-body vocabulary-body">
          {warning && <div className="glossary-warning">{warning}</div>}

          <div className="vocabulary-toolbar">
            <div className="vocabulary-summary">
              <span className="vocabulary-count">{rows.length}</span>
              total rules
              <span className="vocabulary-dot"></span>
              <span className="vocabulary-count">{fillerCount}</span>
              filler cleanup
            </div>
            <div className="select-wrap vocabulary-filter">
              <select value={filter} onChange={e => setFilter(e.target.value)}>
                <option value="all">All rule types</option>
                {VOCABULARY_RULE_TYPES.map(type => (
                  <option key={type.value} value={type.value}>{type.label}</option>
                ))}
              </select>
            </div>
          </div>

          <div className="glossary-table-wrap vocabulary-table-wrap">
            <table className="glossary-table vocabulary-table">
              <thead>
                <tr>
                  <th>Active</th>
                  <th>Type</th>
                  <th>Language</th>
                  <th>Source Phrase</th>
                  <th>Replacement / Hint</th>
                  <th>Priority</th>
                  <th>Notes</th>
                </tr>
              </thead>
              <tbody>
                {visibleRows.length === 0 && (
                  <tr><td colSpan="7" className="glossary-empty">No vocabulary rules match this filter.</td></tr>
                )}
                {visibleRows.map(row => {
                  const meta = vocabularyTypeMeta(row.rule_type);
                  return (
                    <tr key={row.id} className={!row.is_active ? "muted" : ""}>
                      <td className="vocabulary-active-cell">
                        <input
                          type="checkbox"
                          checked={!!row.is_active}
                          disabled={savingId === row.id}
                          onChange={e => {
                            const next = { ...row, is_active: e.target.checked };
                            updateRow(row.id, "is_active", next.is_active);
                            saveRule(next);
                          }}
                          title={row.is_active ? "Rule is active" : "Rule is inactive"}
                        />
                      </td>
                      <td>
                        <select
                          value={row.rule_type || "replacement"}
                          disabled={savingId === row.id}
                          title={meta.help}
                          onChange={e => {
                            const next = { ...row, rule_type: e.target.value };
                            if (!vocabularyTypeMeta(e.target.value).needsReplacement) next.replacement_text = "";
                            updateRow(row.id, "rule_type", next.rule_type);
                            saveRule(next);
                          }}
                        >
                          {VOCABULARY_RULE_TYPES.map(type => (
                            <option key={type.value} value={type.value}>{type.label}</option>
                          ))}
                        </select>
                      </td>
                      <td>
                        <input
                          value={row.language_code || ""}
                          placeholder="all"
                          disabled={savingId === row.id}
                          onChange={e => updateRow(row.id, "language_code", e.target.value)}
                          onBlur={e => saveRule({ ...row, language_code: e.currentTarget.value })}
                        />
                      </td>
                      <td>
                        <input
                          value={row.source_phrase || ""}
                          disabled={savingId === row.id}
                          onChange={e => updateRow(row.id, "source_phrase", e.target.value)}
                          onBlur={e => saveRule({ ...row, source_phrase: e.currentTarget.value })}
                        />
                      </td>
                      <td>
                        <input
                          value={row.rule_type === "phonetic" ? (row.phonetic_hint || "") : (row.replacement_text || "")}
                          placeholder={meta.needsReplacement ? "replacement text" : "not required"}
                          disabled={savingId === row.id || !meta.needsReplacement}
                          onChange={e => updateRow(row.id, row.rule_type === "phonetic" ? "phonetic_hint" : "replacement_text", e.target.value)}
                          onBlur={e => saveRule({
                            ...row,
                            [row.rule_type === "phonetic" ? "phonetic_hint" : "replacement_text"]: e.currentTarget.value,
                          })}
                        />
                      </td>
                      <td>
                        <input
                          type="number"
                          min="1"
                          max="1000"
                          value={row.priority || 100}
                          disabled={savingId === row.id}
                          onChange={e => updateRow(row.id, "priority", e.target.value)}
                          onBlur={e => saveRule({ ...row, priority: e.currentTarget.value })}
                        />
                      </td>
                      <td>
                        <input
                          value={row.notes || ""}
                          disabled={savingId === row.id}
                          onChange={e => updateRow(row.id, "notes", e.target.value)}
                          onBlur={e => saveRule({ ...row, notes: e.currentTarget.value })}
                        />
                      </td>
                    </tr>
                  );
                })}
              </tbody>
            </table>
          </div>

          <div className="vocabulary-add-card">
            <div className="vocabulary-type-strip">
              {VOCABULARY_RULE_TYPES.slice(0, 4).map(type => (
                <button
                  key={type.value}
                  type="button"
                  className={draft.rule_type === type.value ? "selected" : ""}
                  title={type.help}
                  onClick={() => setDraftType(type.value)}
                >
                  {type.label}
                </button>
              ))}
            </div>
            <div className="glossary-add-row vocabulary-add-row">
              <input value={draft.language_code || ""} onChange={e => setDraft({ ...draft, language_code: e.target.value })} placeholder="Language (blank = all)"/>
              <input value={draft.source_phrase} onChange={e => setDraft({ ...draft, source_phrase: e.target.value })} placeholder={draft.rule_type === "filler" ? "Filler phrase (e.g. um)" : "Source phrase"}/>
              <input
                value={draft.rule_type === "phonetic" ? draft.phonetic_hint : draft.replacement_text}
                disabled={!vocabularyTypeMeta(draft.rule_type).needsReplacement}
                onChange={e => setDraft({
                  ...draft,
                  [draft.rule_type === "phonetic" ? "phonetic_hint" : "replacement_text"]: e.target.value,
                })}
                placeholder={draft.rule_type === "filler" ? "Removed automatically" : "Replacement / phonetic hint"}
              />
              <input type="number" min="1" max="1000" value={draft.priority} onChange={e => setDraft({ ...draft, priority: e.target.value })} placeholder="Priority"/>
              <input value={draft.notes} onChange={e => setDraft({ ...draft, notes: e.target.value })} placeholder="Notes (optional)"/>
              <button className="btn primary" disabled={savingId === "new"} onClick={addRule}><Icon.Plus/> Add Rule</button>
            </div>
          </div>
        </div>
        <div className="modal-actions glossary-actions">
          <span className="vocabulary-footnote">Lower priority runs earlier. Blank language applies to every source language.</span>
          <button className="btn primary" onClick={onClose}>Close</button>
        </div>
      </div>
    </div>
  );
}

window.VocabularyDialog = VocabularyDialog;
