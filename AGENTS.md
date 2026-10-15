# Agent instructions

Read `README.md` first: it explains the scoped vendor directory, the tests and the static analysis.

## Code conventions

- Give every public method a docblock that says what it does and when to use it. Add `@param` and `@return` only when the signature does not show the type.
  - A constructor that only takes dependencies needs none.
  - A method that only fulfils an interface without logic of its own, such as the placeholders in `src/Pdk/Placeholder/`, needs none.
- Mark follow-up work with `@TODO INT-<number>: <what to do>`, with the key of a real ticket.
