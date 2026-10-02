---
sidebar_position: 30
---

# Monitor Thematiq

Your hosting partner can watch Thematiq like any other service. Thematiq answers a health probe and publishes metrics in the Prometheus text format.

## Health probe

```bash
curl https://cloud.example.nl/apps/thematiq/api/health
```

The endpoint needs no login, so a load balancer can call it. It allows 120 anonymous calls per minute. The answer has four fields: `status`, `app`, `version` and `checks`.

The checks come from the `observability.health` block in `src/manifest.json`, run by the OpenRegister app. Without OpenRegister, the endpoint still answers with HTTP 200, `status: degraded` and `checks.openregister: unavailable`. Read that as "Thematiq is up, the detailed checks did not run".

## Metrics

```bash
curl -u admin:app-password https://cloud.example.nl/apps/thematiq/api/metrics
```

The metrics name the active token set and exact software versions. That is why the endpoint needs an administrator. Give your Prometheus scraper an app password for an admin account.

| Metric | Type | What it tells you |
|---|---|---|
| `nldesign_info` | gauge | App, PHP and Nextcloud versions, as labels |
| `nldesign_up` | gauge | 1 when the app answers |
| `nldesign_token_sets_total` | gauge | Token sets available on this server |
| `nldesign_active_token_set` | gauge | The active token set, as a label |
| `nldesign_custom_overrides_total` | gauge | Token overrides in `custom-overrides.css` |
| `nldesign_theming_syncs_total` | counter | Theming sync runs since install |
| `nldesign_audit_entries_total` | counter | Entries written to the [audit log](./audit-log.md) |

The metric names still carry the `nldesign_` prefix from before the app was renamed to Thematiq.

Next, add an alert on `nldesign_up` and a panel for `nldesign_active_token_set`.
