# ADR-0026 — Builds take a lock, write atomically and clean up after themselves

- **Status:** Accepted
- **Date:** 2026-10-12
- **Deciders:** codad5
- **Affects:** `packages/build` (`BuildLock`, `Interruption`, `Progress`, `ZipPackager`, `DirectoryPackager`, `ProcessCommandRunner`)

## Context

The packager copied the project, ran commands, and wrote the zip directly to its final name. Three
things went wrong, or could:

- Two builds of one project (two CI jobs sharing a workspace, a watcher firing during a build) ran
  `npm run build` in the same folder and wrote the same output; a reader could pick up a half-written zip.
- A build killed part-way (Ctrl+C, a CI timeout) left its temporary copy behind, and possibly a partial
  zip or a half-emptied output folder that looked like a real one. A command it had started could keep
  running.
- A slow step printed nothing, so a two-minute asset build looked hung.

## Decision

1. **One build per project at a time.** An operating-system file lock (`flock`) on a file in the system
   temp directory, named from the project's resolved path. The system releases it when the process
   ends however it ends, so there are no stale locks to clear. A second build fails at once saying
   which process holds it, or waits (`waitForLock()`, `--wait`). The lock is outside the project so it
   is never shipped or committed.
2. **Output is atomic.** A zip is written next to its target under a temporary name and renamed into
   place when complete, then its checksum; a directory is built beside its target and swapped in, with
   the previous one put back if the swap fails. A failed or stopped build leaves the previous output
   whole.
3. **Interruption is cooperative.** SIGINT/SIGTERM (pcntl) or Ctrl+C (Windows) set a flag; the build
   checks it between steps, several times a second while a command runs, and before each file it
   writes. It stops by throwing, so the same cleanup runs as for any failure: the command and its
   children are terminated (`taskkill /T` on Windows, `pkill -P` elsewhere), staging and partial output
   are removed, the lock is released. Staging copies older than six hours left by builds that were
   killed outright are swept at the start of the next build; recent ones may belong to a running build.
4. **Commands are watched, not blocked on.** The runner polls a temporary file the shell redirects the
   command's output into, so it can call back several times a second without a non-blocking pipe, which
   PHP cannot provide on Windows. The shell does the redirect, not a descriptor passed to `proc_open`: in
   a long test run the descriptor form lost the child's output after certain earlier tests, for a cause
   that was not identified.
5. **Progress is plain lines everywhere, plus a spinner on a real terminal.** Every step ends with its
   duration. The spinner is drawn in place and erased when its step ends, so the screen shows what a log
   would. It exists only when stdout is an interactive terminal and none of `CI`, `NO_COLOR`,
   `TERM=dumb`, `quiet()`, `plain()` or a caller-supplied `withOutput()` handler applies, and on Windows
   only if virtual-terminal sequences can be enabled. A command whose output is streamed shows that
   output instead.

## Options considered

- **No lock; document "don't run two builds".** Cheap, but the failure is silent corruption.
- **A lock file with a PID inside.** Needs stale-lock detection, which is platform-specific and racy;
  `flock` gets this for free.
- **Handle signals only with `pcntl`.** Leaves Windows, where most of this project's authors work, with
  nothing; Windows has its own control handler.
- **A spinner library.** A dependency for forty lines of drawing, and it would not know when to stay
  plain.

## Consequences

- A build that is killed with SIGKILL or a power cut still can't clean up; the sweep and the atomic
  writes bound the damage to a temporary directory and a temporary file next to the output.
- `toDirectory()` needs room for two copies of the output while it swaps.
- Tests exercise real processes, real locks and, on Linux, a real SIGINT.
