# Open decisions

Trade-offs not yet decided. Each has options, a recommendation and the reasoning. When decided, it
moves to an ADR and is struck through here.

| #  | Question | Options | Recommendation | Raised |
| -- | -------- | ------- | -------------- | ------ |
| 1  | Lowest PHP image CI can lint `bootstrap/guard.php` on | PHP 5.6 image · 7.0 image · PHPCompatibility sniff only | The oldest official Docker image still pullable, **plus** the sniff — two independent checks | 2026-10-07 |
| 2  | Should the guard auto-deactivate a plugin whose requirements fail? | Stay active but inert · auto-deactivate | **Inert.** A temporary PHP downgrade on a host shouldn't silently switch plugins off; the notice has a deactivate link | 2026-10-07 |
| 3  | Runtime hook containment default | On in production · always off · always on | **On in production, off in development** (ADR-0013); revisit after dogfooding | 2026-10-07 |
| 4  | Docs site | GitHub Pages from `docs/` · own domain | **GitHub Pages** for 1.0; a domain only once there are users to send there | 2026-10-07 |
