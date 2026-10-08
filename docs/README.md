# Documentation index

**architecture/** is the shape of the library, **adr/** is why it has that shape, **phases/** is the
order it gets built in, **reference/** holds facts that change. Agent rules live in
[CLAUDE.md](../CLAUDE.md) and [.claude/rules/](../.claude/rules/).

## architecture/

| #  | Document                                                              | Covers                                         |
| -- | --------------------------------------------------------------------- | ---------------------------------------------- |
| 00 | [Executive summary](architecture/00-executive-summary.md)             | The whole thing in one page                    |
| 01 | [Principles and constraints](architecture/01-principles-and-constraints.md) | What every decision serves               |
| 02 | [System architecture](architecture/02-system-architecture.md)         | Layers, layout, patterns, target API           |
| 03 | [Multi-version coexistence](architecture/03-multi-version-coexistence.md) | Two plugins, two versions, one site        |
| 04 | [Localization](architecture/04-localization.md)                       | Strings, JS, RTL, CI gates                     |
| 05 | [Quality, testing, CI](architecture/05-quality-testing-ci.md)         | Test layers, static analysis, workflows        |
| 06 | [Security](architecture/06-security.md)                               | Threat model, 0.x findings, checklist          |
| 07 | [Migration from 0.x](architecture/07-migration-from-0x.md)            | Fate of every 0.x class                        |
| 08 | [Out of scope](architecture/08-out-of-scope.md)                       | What 1.0 deliberately doesn't do               |

## adr/

[Index and process →](adr/README.md)

## phases/

[Plan, timeline, dependency graph →](phases/README.md) · live progress in [STATUS.md](../STATUS.md)

## reference/

[Open decisions](reference/open-decisions.md) · [Tech stack](reference/tech-stack.md) · [Work log](reference/work-log.md)
