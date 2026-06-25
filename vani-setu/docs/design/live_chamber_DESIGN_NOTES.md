# Live Chamber Design Notes

Screen layout: current floor snapshot with slot, speaker, ASR confidence, capture lanes, recent transcript.
Components: floor tiles, assignment lane list, recent speech list, refresh action.
Data model: read model over live sitting, current slot, block speaker metadata, assignments, ASR confidence.
State machine: read-only live snapshot; no persistent module transition.
Audit actions: `live_chamber.snapshot.viewed`.
Role boundaries: visible to Track A operational roles.
