# Stable Snapshot Before Backdate

Generated: 2026-07-20 Asia/Jakarta

Purpose: baseline before adding PB/WO backdate feature.

Includes source code, migrations, web/mobile WebView controllers/views/routes, and schema-only DB snapshot.

ERP policy: read-only integration remains unchanged; this snapshot contains no ERP data.

Schema file: `docs/snapshots/2026-07-20-stable-before-backdate-schema.sql`

Validation notes:
- Mobile Section Head PB detail HTTP 200 and verification buttons rendered.
- Mobile L1 WO approve works with delegation notes empty.
- Web BPU PB create for eng1.bpu..eng5.bpu validated with rollback.
