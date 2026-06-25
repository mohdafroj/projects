# Synopsis Hosted Model Contract

The Synopsis module uses the in-house E&T hosted model. It must not call Sarvam.

## Configuration

Laravel reads the endpoint and model metadata from:

- `SYNOPSIS_MODEL_URL`, for example `http://ml-gateway:8000/v1/synopsis`
- `SYNOPSIS_MODEL_ALLOWED_HOSTS`, comma-separated internal hosts, defaulting to
  `ml-gateway`
- `SYNOPSIS_MODEL`, default `vani-setu-synopsis`
- `SYNOPSIS_MODEL_TOKEN`, optional bearer token for the internal model service
- `SYNOPSIS_MODEL_TIMEOUT`
- `SYNOPSIS_MODEL_RETRIES`
- `SYNOPSIS_MODEL_RETRY_SLEEP_MS`

If `SYNOPSIS_MODEL_URL` is empty or the hosted model returns an unusable response,
the module falls back to the governed local template and records the fallback
reason in the audit payload.

`SYNOPSIS_MODEL_URL` must be an `http` or `https` URL with a host, and must not
point to Sarvam. The host must be listed in `SYNOPSIS_MODEL_ALLOWED_HOSTS`, so
arbitrary external hosts cannot receive proceedings text. If the endpoint is
malformed, Laravel does not issue an HTTP request and records
`invalid_hosted_endpoint`. If the configured host ends with `sarvam.ai`, Laravel
records `forbidden_sarvam_endpoint`. If the host is valid but not allowlisted,
Laravel records `non_inhouse_hosted_endpoint`.

## Request

`POST {SYNOPSIS_MODEL_URL}`

Headers:

- `Authorization: Bearer {SYNOPSIS_MODEL_TOKEN}` when configured
- `X-Vani-Setu-Module: synopsis`
- `X-Vani-Setu-Chunk: {chunk_code}`
- `X-Vani-Setu-Model: {SYNOPSIS_MODEL}`
- `X-Vani-Setu-Source-Sha256: {source.sha256}`
- `X-Vani-Setu-Request-Id: {request_id}`

JSON body:

```json
{
  "request_id": "550e8400-e29b-41d4-a716-446655440000",
  "model": "vani-setu-synopsis",
  "task": "parliamentary_synopsis",
  "title": "Synopsis - Sitting 1 - Chunk A",
  "source": {
    "label": "Writer pasted proceedings text",
    "session_no": 2026,
    "sitting_no": 1,
    "chunk_code": "A",
    "duration_ms": 3600000,
    "text": "Optional pasted proceedings text",
    "sha256": "f90f2aca5c640289d0a29417bcb63a37f87d53bb50d4795474b79253d36c616e",
    "items": [
      {
        "speaker_name": "Shri R. Patil",
        "constituency": "Karnataka",
        "summary_text": "Raised delays in National Highway 75 expansion.",
        "source_excerpt": "Raised delays in National Highway 75 expansion..."
      }
    ]
  },
  "template": {
    "required_sections": [
      "Source Notes",
      "Synopsis",
      "Attribution Notes",
      "Editorial Status"
    ],
    "style": "formal_parliamentary_record",
    "editorial_status": "First draft by AI; writer review and final commit required before publication."
  }
}
```

`source.sha256` is always populated. For pasted-text generation Laravel trims
leading and trailing whitespace, requires at least 40 meaningful characters, and
uses the SHA-256 of that trimmed text. For accepted Chief-consolidation
generation it is a deterministic fingerprint of the ordered source block ids,
versions, speaker links, and block text hashes; in that mode `source.text` is
`null`.

## Response

The response must include a complete governed draft:

```json
{
  "title": "Same-day Synopsis - Pasted Text",
  "body": "Same-day Synopsis - Pasted Text\n\nSource Notes\n...\n\nSynopsis\n...\n\nAttribution Notes\n...\n\nEditorial Status: First draft by AI; writer review and final commit required before publication.",
  "request_id": "550e8400-e29b-41d4-a716-446655440000",
  "source_sha256": "f90f2aca5c640289d0a29417bcb63a37f87d53bb50d4795474b79253d36c616e",
  "attributions": [
    {
      "speaker_name": "Shri R. Patil",
      "constituency": "Karnataka",
      "summary_text": "Raised delays in National Highway 75 expansion."
    }
  ]
}
```

`body` is accepted only if it contains all required section headings in this
order. Section names embedded inside prose do not satisfy the template contract:

- `Source Notes`
- `Synopsis`
- `Attribution Notes`
- `Editorial Status`

The `Editorial Status` heading must be the final line and must exactly read:
`Editorial Status: First draft by AI; writer review and final commit required
before publication.`

The `Source Notes` section must contain a line that exactly matches the
requested source label, either `Source: Writer pasted proceedings text` or
`Source: Approved Chief consolidation`.

`title` and `body` must be present as strings. `title` must be nonblank and at
most 255 characters. The first nonblank line of `body` must exactly match
`title`. `body` must be at most 50,000 characters, matching the writer draft-save
limits. Do not return `synopsis` as an alias for `body`. Missing, typed,
mismatched, or oversize hosted responses are discarded and replaced with the
local template fallback.

`attributions` must be a non-empty JSON array/list of at most 100 object rows
with nonblank string `speaker_name` and `summary_text`. Do not return an object
keyed by speaker, member id, or row id. `constituency` may be a string or null.
`speaker_name` and `constituency` must each be at most 255 characters, and
`summary_text` must be at most 2,000 characters. Empty, malformed,
object-shaped, typed as arrays/objects, excessive, or overlong attribution rows
are not accepted. A mixed list with one valid row and one malformed row rejects
the whole hosted draft. Every structured attribution row must also appear in the
`Synopsis` and `Attribution Notes` sections using the same speaker label
(`speaker_name` or `speaker_name (constituency)` when a constituency is
present), and every `Synopsis` or `Attribution Notes` bullet label must have a
matching structured row. Speaker labels mentioned in prose do not satisfy this
contract; the labels must appear as bullet prefixes. Each bullet must include a
colon followed by nonblank visible text after the label.

`source_sha256` must be a string that echoes the request `source.sha256`.
Laravel also accepts the nested alias `source.sha256` in the response. If both
forms are returned, they must match exactly after trimming. If the source hash is
missing, malformed, internally inconsistent, or does not match, Laravel discards
the hosted draft and writes the local template fallback instead.

The response must include `request_id` as a nonblank string that matches the
request `request_id`. Laravel also accepts `meta.request_id` as an alias. If both
forms are returned, they must match exactly after trimming. A missing,
malformed, internally inconsistent, or mismatched response request id is treated
as stale or cross-request response and is discarded.

If any required section, source hash, or structured attribution is missing,
Laravel discards the hosted draft and writes the local template fallback instead.

## Workspace API Payload

`GET /api/synopsis/queue`, `GET /api/synopsis/chunks/{consolidation}`, and
generation responses expose the latest generation metadata as `latest_generation`
on the chunk summary and document payload. This mirrors the audit payload enough
for the authenticated workspace to show whether the draft came from the hosted
model or from the governed fallback:

```json
{
  "latest_generation": {
    "provider": "hosted_model",
    "model": "vani-setu-synopsis",
    "endpoint_configured": true,
    "http_status": 200,
    "fallback_reason": null,
    "fallback_detail": null,
    "request_id": "550e8400-e29b-41d4-a716-446655440000",
    "source_sha256": "f90f2aca5c640289d0a29417bcb63a37f87d53bb50d4795474b79253d36c616e"
  }
}
```

If a writer switches a draft to scratch authoring with
`POST /api/synopsis/chunks/{consolidation}/author`, `latest_generation` becomes
`null`. Submit and finalise audit payloads then do not carry the discarded AI
generation source hash or model metadata.

`GET /api/synopsis/chunks/{consolidation}/history` returns edit rows with
`audit_log` context, plus `audit_events` for document-level audit actions such
as PDF export. Both surfaces include a compact `audit_evidence` object. The full
audit payload is not returned. Evidence fields can include:

- `source_sha256`
- `generation_provider`
- `generation_model`
- `generation_fallback_reason`
- `generation_fallback_detail`
- `generation_http_status`
- `generation_request_id`
- `pdf_sha256`
- `pdf_bytes`

Draft save calls to `PUT /api/synopsis/chunks/{consolidation}/draft` must include
the current `version`. Missing versions fail validation, and stale versions
return `409` with `current_version`, `current_title`, `current_body`, and
`current_attributions` so the workspace can resolve against the full saved draft
state without an extra detail reload. Source-fingerprint conflicts still require
a chunk reload because the accepted source blocks changed. The saved `body` is
trimmed before validation and persistence, and a whitespace-only body is
rejected. Saved `attributions` must use the same JSON array/list row shape as
hosted responses; keyed attribution objects, missing `summary_text`, and more
than 100 rows are rejected with validation errors. Draft-save `title`,
`speaker_name`, `constituency`, and `summary_text` fields are trimmed before
validation and persistence; blank constituency values are stored as `null`,
while blank speaker or summary values are rejected. The authenticated workspace
trims these fields before sending draft-save and pasted-text generation requests
so visibly empty rows are not submitted as whitespace.

Once a document is `submitted`, draft mutation endpoints (`generate`,
`generate-from-text`, `author`, and `draft`) are read-only. The workspace should
only allow `finalise` from that state. `submit` and `finalise` also require the
source Chief consolidation to remain in an accepted Synopsis-ready state.

For drafts generated from approved Chief consolidation blocks,
`generate`, `submit`, and `finalise` compare the current block fingerprint with
the generation `source_sha256`. If the blocks changed during or after
generation, the API returns `409` with `source_sha256` and
`generated_source_sha256`, and the writer must regenerate before submitting or
finalising. Pasted-text generation keeps the hash of the pasted source text and
is not compared to current block contents.

The authenticated workspace shows a disabled Draft Notes panel when no Synopsis
comment API is registered. This is intentionally local-only and non-persistent;
do not infer final comment authority or audit persistence from that panel.

Final PDF exports return `X-Vani-Setu-Pdf-Sha256`, matching the SHA-256 of the
served PDF bytes.

## Audit Evidence

Generation actions write `synopsis.draft.generated` or
`synopsis.draft.generated_from_text`.

The audit payload includes:

- `source_sha256` for pasted-text and accepted-block generation
- `generation.provider`: `hosted_model` or `fallback`
- `generation.model`
- `generation.endpoint_configured`
- `generation.http_status`
- `generation.fallback_reason`
- `generation.fallback_detail`
- `generation.request_id` when an HTTP request was attempted

Submit and finalise audit payloads carry forward the latest draft generation
evidence as `source_sha256` and `generation` so a finalised Synopsis remains
tied to its source material and generation provider.

PDF export audit payloads include:

- `pdf_sha256`
- `pdf_bytes`

Known fallback reasons:

- `endpoint_not_configured`
- `invalid_hosted_endpoint`
- `non_inhouse_hosted_endpoint`
- `forbidden_sarvam_endpoint`
- `hosted_model_http_error`
- `hosted_model_invalid_json`
- `hosted_model_exception`
- `hosted_model_invalid_title`
- `hosted_model_missing_title`
- `hosted_model_missing_body`
- `hosted_model_empty_body`
- `hosted_model_invalid_body`
- `hosted_model_title_too_long`
- `hosted_model_body_too_long`
- `hosted_model_title_body_mismatch`
- `hosted_model_missing_section`
- `hosted_model_invalid_section_order`
- `hosted_model_source_notes_mismatch`
- `hosted_model_invalid_editorial_status`
- `hosted_model_invalid_source_hash`
- `hosted_model_source_mismatch`
- `hosted_model_invalid_request_id`
- `hosted_model_missing_request_id`
- `hosted_model_request_mismatch`
- `hosted_model_missing_attributions`
- `hosted_model_invalid_attributions_shape`
- `hosted_model_invalid_attribution_row`
- `hosted_model_attribution_notes_mismatch`
- `hosted_model_synopsis_notes_mismatch`
- `hosted_model_too_many_attributions`
- `hosted_model_attribution_too_long`
