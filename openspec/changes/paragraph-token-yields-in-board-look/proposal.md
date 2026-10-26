# Proposal: paragraph-token-yields-in-board-look

## Why

theme.css set every `<p>` to the paragraph token with `!important` (Zuiddrecht: 15px / 22.5px). In the nextcloud-vue
board look many components draw a subtitle, note or caption as a `<p>` at the board's own size, and the forced rule beat
every one of them. Read on :8080 (dossiq Dq pages, 25 Oct), paragraphs whose own rule asks for another size but render
15px/22.5px: CnMapWidget note (14px), CnFlowRunsWidget empty line (13px), the hours card's source note (13px),
CnStatusExplainer dwell (13px), CnAttentionFlag hint (13px), CnRecordListWidget footnote and footer note (13px) and
notice text (line 1.5), CnWidgetEmptyState name (16px) and description (14px / 1.45), CnSavedViewsPanel note
(13px / 1.3), CnPageHeader description, NcAppSidebar subname (14px), dossiq's AI features hint (0.85em), and DQ-L3's
CnConversation subtitle (14px). nextcloud-vue worked around a few by setting the paragraph tokens on the component;
the rest stayed off.

## What changes

- Outside the board look nothing changes: `p:not(.cn-look-board *)` keeps the forced token, as h1-h6 already do.
- Inside the board look the token is a default at no specificity and without `!important`
  (`:where(.cn-look-board) :where(p)`): a paragraph with no size of its own still takes the NL Design paragraph token,
  and a component's own size wins. The token workarounds in nextcloud-vue keep working (the default still reads the
  tokens).
