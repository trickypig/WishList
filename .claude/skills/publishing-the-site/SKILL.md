---
name: publishing-the-site
description: Use when publishing, deploying, uploading, or verifying the Wish List website or API at wish.trickypig.com, or when a change is missing from the live site.
---

# Publishing the Site

`scripts\deploy.ps1` is the only way to publish. It replaces uploading files by hand from VS Code.

```powershell
.\scripts\deploy.ps1         # builds, previews changes, writes nothing to the server
.\scripts\deploy.ps1 -Push   # publishes
```

## Who decides

Run `-Push` only when the user has asked to publish in this conversation. A preview is always fine.

Before every `-Push`, run the preview and tell the user its warnings and change list. An earlier publish approval doesn't cover a later one.

## What the script does

| Local | Remote (under `/trickypig/wish/`) |
|---|---|
| `frontend/dist/*` | `frontend/dist/` (the site's web root) |
| git-tracked `api/`, except `api/tests/` | `api/` |

- **Never uploaded or deleted:** `.env`, `.htaccess`, `.git`, `data/`.
- **Only deletion:** stale build files in `frontend/dist/assets/`.
- **Database:** `-Push` runs `api/migrations/migrate.php` with `php8.4-cli` before the code that needs it goes up. The live database is MySQL, so migrations must work on both MySQL and SQLite.
- **MAUI:** the app isn't published by this script.

## When something looks wrong

| Symptom | Cause |
|---|---|
| New API file is missing live | It isn't tracked yet; `git add` it. The preview warns about this. |
| Unexpected file in the preview | Uncommitted edits to tracked files go live. Check the warnings. |
| File was already live before publishing | `.vscode/sftp.json` has `uploadOnSave: true`, so VS Code uploaded it on save. |
| "Migration failed" | The API code and frontend were not updated. Fix the migration and publish again. |
| Key or rsync error | WSL needs rsync and `~/.ssh/id_ed25519` at mode 600. |

To set up the key in WSL:

```bash
wsl -e bash -lc 'cp /mnt/c/Users/trick/.ssh/id_ed25519 ~/.ssh/ && chmod 600 ~/.ssh/id_ed25519'
```

## After publishing

The script checks the home page, reset page, current script file, and API. If they fail, tell the user straight away. Then ask the user to try the feature that changed. Email can only be tested on the live site.
