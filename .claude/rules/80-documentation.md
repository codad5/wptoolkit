# Rule 80 — Documentation

- **ADR** for any decision a future reader could reasonably question — before or with the code.
  Template: [docs/adr/0000-template.md](../../docs/adr/0000-template.md). Never rewrite an accepted
  ADR; supersede it.
- **Phase doc**: tick the task when it lands; add tasks that were missed; record deferrals under
  "Deliberately not in this phase".
- **STATUS.md**: update the step table when a task lands.
- **Architecture docs** change in the same PR as the structure they describe.
- **Migration map** ([07](../../docs/architecture/07-migration-from-0x.md)): update the row when a
  0.x class is ported or deleted.
- **Open questions** go to [open-decisions.md](../../docs/reference/open-decisions.md) with options
  and a recommendation — not silently decided.
- **Work log** ([work-log.md](../../docs/reference/work-log.md)): one dated line per session —
  what landed, what was learned, what's next.
- Public API changes → guide + CHANGELOG (via the commit) + migration note.
