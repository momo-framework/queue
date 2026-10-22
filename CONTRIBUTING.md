# Contributing

## Commit messages

Conventional Commits, subject line only:

```text
<type>: <short, imperative description>
```

Types: `feat`, `fix`, `chore`, `refactor`, `docs`, `test`, `ci`, `build`, `perf`, `style`.

- Imperative mood, no trailing period: `fix: correct locale fallback`, not
  `fixed` or `fixes`.
- Subject only. Add a body only when the diff doesn't explain its own "why."
- One author per commit, no `Co-authored-by:` trailers.
- Squash noisy WIP history before merging into `main`.

## Branches & merge requests

- Short-lived feature branches off `main`.
- Every merge request into `main` runs the pipeline before it can merge.
- Documentation changes ride in the same merge request as the code change.
  A separate "update the docs" ticket is a ticket that will not be done.

## Documentation

The guides live in one repo, `anidzen/design-assets`. Read them there. Only
`LICENSE` and this file are copied into a repo, the rest are linked.

- Prose in any file follows
  [`AI-TEXT.md`](https://gitlab.com/anidzen/design-assets/-/raw/main/writing-style/AI-TEXT.md):
  a fact in every sentence, no em dash, no decorative emoji, sentence case
  headings. It outranks every other guide, `README-STYLE.md` included.
- READMEs follow
  [`README-STYLE.md`](https://gitlab.com/anidzen/design-assets/-/raw/main/README-STYLE.md)
  for structure and chrome, including its checklist.
- `.gitlab-ci.yml` follows
  [`CI-STYLE.md`](https://gitlab.com/anidzen/design-assets/-/raw/main/CI-STYLE.md).
- `README.md`, `README.ru.md` and `README.hy.md` change in the same commit.

The index of every guide, with the URL of each:
[`README.md`](https://gitlab.com/anidzen/design-assets/-/raw/main/README.md).

## This file

Same status as `LICENSE`: a design asset every Anidzen Labs repo carries its
own copy of, taken verbatim from
[`design-assets`](https://gitlab.com/anidzen/design-assets/-/raw/main/CONTRIBUTING.md).
Don't fork the rules per repo. If the rules need to change, change them in
`design-assets` first, then re-copy the file into every repo that carries it.
Every link above is absolute, so the copy works from any repo, and the rules
it points at have one owner.
