# Formatting Design Notes

Screen layout: formatting job list, source preview, rule validation, CRC output panel.
Components: job summary, line preview, validation status, CRC compiler, dispatch controls.
Data model: formatting jobs, formatting lines, formatting transitions.
State machine: draft -> validated -> crc_compiled -> dispatched.
Audit actions: `formatting.job.created`, `formatting.job.validated`, `formatting.crc.compiled`, `formatting.job.dispatched`.
Role boundaries: director/admin formatting surface; DVOT-Yogesh and parliamentary layout checks enforced server-side.
