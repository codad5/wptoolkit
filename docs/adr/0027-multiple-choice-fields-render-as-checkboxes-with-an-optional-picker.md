# ADR-0027 — Multiple-choice fields render as checkboxes, with an optional chip picker

- **Status:** Accepted
- **Date:** 2026-10-12
- **Deciders:** codad5
- **Affects:** `Data\Field\Types\ChoiceType`, `ChoicePickerAssets`, `Field::display()`, `FieldValidator`, `resources/js/picker.js`, `resources/css/picker.css`

## Context

A `select` field with `->multiple()` rendered the browser's `<select multiple>`. A plain click selects one
item and clears the rest (Ctrl/Cmd+click adds), which most people don't know, it looks different in every
browser and is hard to use on touch screens. 0.x plugins fixed this with jQuery "Chosen".

It also had a correctness problem: with nothing selected the browser submits nothing, the meta box
treats a missing key as "not on this form", and a saved selection could never be cleared.

## Decision

1. A multiple-choice field renders as **real checkboxes**: up to six options a plain labelled list, more
   than that a **chip picker** (chips for the selection, a search box, a panel of options). `display()`
   overrides: `checkboxes`, `tokens`, or `native` for the old select.
2. The picker is **progressive enhancement over the checkboxes**. The checkboxes stay in the page and
   remain what the form submits, so keyboard and screen-reader use are the platform's own, a script
   failure leaves a usable list, and the stored value is exactly what it was.
3. **No library.** About 120 lines of plain JavaScript and 50 of CSS, no jQuery (rule 5 is about
   Composer dependencies, but shipping and scoping a third-party JS widget, and its jQuery
   requirement, is the same cost). Chosen is unmaintained; Select2 needs jQuery.
4. The script and style are **printed once in the page footer** by the first picker on the page, from the
   library's `resources/` files; two bundled copies of the library may both print them, and an element
   is enhanced by whichever runs first.
5. **Every rendering sends an empty marker** (`name[]` with an empty value) first, so "nothing chosen"
   arrives and clears the field. Validation ignores empty entries of a multiple field; the stored
   format is unchanged (ADR-0016).

## Options considered

- **Keep `<select multiple>` and add Chosen/Select2.** jQuery and a large third-party bundle per
  consumer, and it stays a hidden select behind a custom widget.
- **Only a checkbox list.** Fine for six options, unusable for the 100-coin lists real plugins have.
- **A fully custom ARIA combobox.** More code and more accessibility risk than reusing real checkboxes.

## Consequences

- Existing multiple selects change appearance on upgrade; nothing stored changes. `display('native')`
  restores the old look.
- A rule on a multiple field applies to each value, not to how many were chosen, so "at most 7" must be
  enforced by the consumer. A count rule is a candidate for a later change.
- The `media` field still refers to an admin script that does not exist; unrelated to this change.
