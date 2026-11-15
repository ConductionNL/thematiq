# Tasks: token-references-stay-live

## 1. Editor and storage
- [ ] 1.1 `lib/Service/TokenValueValidator.php`: accept `var(--nldesign-...)` as a value of any token type, refuse a target that `TokenRegistry` does not know and a reference that closes a cycle
- [ ] 1.2 `lib/Service/OverridesCssBuilder.php`: write a reference as `var(--...)`, unresolved
- [ ] 1.3 `js/admin.js`: let a token row take a reference, and show the resolved value beside it

## 2. Import
- [ ] 2.1 `lib/Service/DesignTokensMapper.php`: keep an alias whose target maps onto a `--nldesign-*` token as a `var(--...)` reference; resolve only aliases whose target is not mapped

## 3. Tests
- [ ] 3.1 Unit tests for 1.1, 1.2 and 2.1, one per scenario of `A token can refer to another token`

## 4. Matrix
- [ ] 4.1 When the tasks are done, archive this change and set `aut-semantic-aliases` back to `built` with its evidence
