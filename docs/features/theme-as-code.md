---
sidebar_position: 17
---

# Keep the house style in Git

Keep your house style in a Git repository and review changes through pull requests, like the rest of your infrastructure. Thematiq reads the house style from a branding package on disk and applies it whenever the package changes. Your deployment tool puts the package there: Argo CD, Helm or Ansible. Thematiq never connects to Git itself.

## The branding package

A branding package is a directory, or a ZIP of one:

```
branding/
  bundle.json          the configuration bundle, same format as the download on the settings page
  fonts/<id>.woff2     one file per font in the bundle
  tokens/<id>.json     optional: a design token document (DTCG), converted on apply
  REVISION             optional: the Git commit the directory came from
```

A ZIP may hold the tree at its root or in one top-level folder, the way a Git host's download ZIP does.

`tokens/custom-gemeente.json` replaces the custom token set `custom-gemeente`. Designers can keep their design tokens in Git and skip the generated CSS.

## Start from what you have

Write a package from the running server, fonts included:

```bash
occ nldesign:config:export --package /srv/branding
```

Commit that directory to a new repository. Add a `REVISION` file in your pipeline, for example with `git rev-parse --short HEAD > REVISION`, so the audit log shows which commit was applied.

## Move a house style by hand

Import a package on another server. The fonts come along, so nobody re-uploads them:

```bash
occ nldesign:config:import /srv/branding
occ nldesign:config:import /tmp/branding.zip --dry-run
```

A package that fails any check changes nothing. A bare `bundle.json` still works as before: fonts in a bare bundle are for information only.

## Apply it from config.php

Name the package in `config.php`:

```php
'thematiq.config_source' => '/srv/branding',
```

Thematiq applies the package when its content changes:

- after every upgrade,
- from a background job every five minutes,
- when you run `occ thematiq:config:apply`. The command exits non-zero when the package fails, so a pipeline can stop on it. Add `--force` to apply an unchanged package again.

An unchanged package is not applied again. A package that fails changes nothing: users keep the running house style, and Settings > Administration > Theming lists the errors. Each successful apply writes a `config_imported` entry to the theming audit log, with user `system` and the revision.

## Lock the settings page

By default the package is a baseline: an administrator can still make small changes on the settings page. The configuration bundle block then shows that the running configuration differs from the package, and that the next apply replaces those changes.

To refuse every change on the settings page, turn on the lock:

```php
'thematiq.config_source_lock' => true,
```

The settings page disables its controls, and every configuration change answers HTTP 423 with the path of the package.

## Example: Helm with a ConfigMap

Small packages without fonts fit in a ConfigMap. Mount it where `thematiq.config_source` points:

```yaml
# values.yaml
nextcloud:
  configs:
    thematiq.config.php: |-
      <?php
      $CONFIG = array (
        'thematiq.config_source' => '/srv/branding',
      );
  extraVolumes:
    - name: branding
      configMap:
        name: nextcloud-branding
  extraVolumeMounts:
    - name: branding
      mountPath: /srv/branding
      readOnly: true
```

```bash
kubectl create configmap nextcloud-branding --from-file=bundle.json --from-file=REVISION
```

A ConfigMap holds at most 1 MB. A package with fonts belongs on a volume, see the next example.

## Example: Argo CD with a Git source

Let Argo CD keep a volume in sync with your branding repository, for example with a `git-sync` sidecar next to Nextcloud:

```yaml
# a sidecar in the Nextcloud pod
- name: branding-sync
  image: registry.k8s.io/git-sync/git-sync:v4.2.4
  args:
    - --repo=https://git.example.org/gemeente/branding.git
    - --ref=main
    - --root=/srv/git
    - --link=branding
    - --period=60s
  volumeMounts:
    - name: branding
      mountPath: /srv/git
```

```php
'thematiq.config_source' => '/srv/git/branding',
```

A merged pull request reaches the volume within a minute, and the next background job applies it within five minutes after that. Write the commit to `REVISION` in your repository's pipeline, or point git-sync's `--exechook-command` at a script that does it.
