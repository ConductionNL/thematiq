# Tasks: openproject-set-file

- [x] 1 `ShellCatalogueService::entry()`; `build()` unchanged in output. `OpenProjectSetFileWriter` adds the logo.
- [x] 2 `OpenProjectSetFileController::export()` at `/settings/tokensets/{id}/set-file`.
- [x] 3 `OpenProjectSetFile`: detect and read a set file; hooked into `TokenSetConverterService::convert()` before the Figma reader.
- [x] 4 Admin page: the format in the list, English and Dutch.
- [x] 5 Tests: every shipped set round trips; the plugin's own files upload; what is left out is named.
- [x] 6 Docs and the parity list.
