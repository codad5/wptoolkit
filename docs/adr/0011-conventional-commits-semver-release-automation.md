# ADR-0011 — Conventional Commits, SemVer, automated releases

- **Status:** Accepted
- **Date:** 2026-10-07
- **Deciders:** codad5
- **Affects:** `.gitmessage`, CI, `release.yml`, CHANGELOG

## Context

0.x has 113 commits and no tag, no release and no changelog. Consumers can't pin a version, and
nobody can tell what changed between two commits without reading diffs. Coexistence (ADR-0005)
depends on version constraints meaning something.

## Decision

- Commits follow **Conventional Commits**, with scopes from the module names (`foundation`, `http`,
  `data`, `view`, `admin`, `assets`, `i18n`, `cache`, `log`, `cli`, `docs`, `ci`, `deps`, `legacy`).
- Versions follow **SemVer**. `feat!` / `BREAKING CHANGE:` → major.
- Releases are cut by tagging; `release.yml` builds the dist, writes the changelog, publishes the
  GitHub Release and notifies Packagist.

## Options considered

### Option A — Free-form commits, hand-written changelog

**Lost:** the changelog is forgotten under pressure; it already was.

### Option B — Conventional Commits + automation (chosen)

**Pros:** the changelog writes itself; the commit vocabulary *is* the architecture. **Cons:** commit
discipline, enforced by CI.

## Consequences

**We accept:** CI rejects non-conforming commit messages on PRs.

**We gain:** trustworthy versions, which ADR-0005's constraints depend on.

**This constrains:** [50-git-and-commits.md](../../.claude/rules/50-git-and-commits.md).

## Revisit when

Never for SemVer. The tooling is replaceable.
