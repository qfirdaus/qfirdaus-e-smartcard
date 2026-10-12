# Modal Tone Standard

Purpose: lock the modal tone convention currently used in `e-base` so new pages and modules follow the same visual language.

## Tone Mapping

- `New / Add`
  - tone: `Green`
  - reference: `public/pages/senarai-pengguna.php`
  - gradient: `#28a745 -> #20c997`

- `Edit`
  - parent modal tone: `Blue`
  - reference: `public/pages/senarai-pengguna.php`
  - gradient: `#667eea -> #764ba2`

- `Edit Child Modal`
  - tone: `Pink`
  - reference: `public/pages/senarai-pengguna.php`
  - gradient: `#f093fb -> #f5576c`

- `View`
  - tone: `Yellow`
  - current reference: `public/pages/template-generator.php`
  - recommended gradient family: `#f6c23e -> #facc15`

## Implementation Notes

- follow the modal shell pattern already used in `public/pages/senarai-pengguna.php`
- keep header padding, title weight, close button treatment, and footer button radius consistent with the existing system
- use page-level modal CSS only when no shared modal standard exists yet

## Reminder For Future Work

When adding a new modal in `e-base`, check the intent first:

- create flow: use `green`
- edit flow: use `blue`
- child edit / secondary edit flow: use `pink`
- view / read-only detail flow: use `yellow`

If a new modal does not fit one of these, document the exception before introducing a new tone.
