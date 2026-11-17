# Agent instructions

Read `CONTRIBUTING.md` first: the setup, the checks, where code goes and the code conventions.

## Git and PR rules

- This repository uses release-please to generate changelogs, every commit must be a conventional commit.
- The repository uses squash commits, with the PR title and body becoming the squash commit that lands on the target branch.
- The commit subjects and PR titles must be concise and describe the change clearly to a merchant installing the plugin. This is the line that goes in to the changelog.
- The PR body should provide additional context and explanation for the change, if necessary. These lines do not end up in the changelog, except for the footer lines that are recognized as changelog entries (see below).
- The PR body can provide additional changelog lines in the PR (commit) footer, by following the conventional commit formats like in the example shown below.
- For this reason, NEVER put any content that should not be in a CHANGELOG in a commit or PR body footer: no Agent disclaimer, no non-standard notes.
- A Dependabot PR for the PDK or the JS-PDK starts with a `BEGIN_COMMIT_OVERRIDE` block that holds the changelog lines of the upstream releases. release-please reads that block in place of the commit message. To change those changelog lines, edit the block, not the PR title.

### PR / Commit example

```
feat: adds v4 UUID to crypto #PR title / commit subject

This adds support for v4 UUIDs to the library.

fix(utils): unicode no longer throws exception
  BREAKING-CHANGE: encode method no longer throws.

feat(utils): update encode to support unicode
```

The above commit message would add to the changelog:

- an entry for the "adds v4 UUID to crypto" feature.
- an entry for the fix "unicode no longer throws exception", along with a note that it's a breaking change.
- an entry for the feature "update encode to support unicode".
