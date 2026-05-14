# Course Content (Video Sections) — Design Spec

**Date:** 2026-05-14
**Status:** Approved (brainstorm phase). Pending implementation plan.
**Scope:** Add tracked, video-based course content to certifications a student is enrolled in, starting with PCEP and PCAP.

---

## 1. Purpose

Students enrolled in a certification need a way to actually *consume* the course material: structured sections of videos with progress tracking that feeds the gold progress bar on `certifications.php`.

The PCEP and PCAP source material already exists on disk as folders of `.mp4` + `.srt` files. This design specifies how to expose that material through the existing PHP portal without:

- Bloating the project repo with multi-GB media.
- Letting non-enrolled users access videos.
- Requiring a content database to be hand-typed.

---

## 2. Goals & non-goals

### In scope

- A new "course player" page reachable from the **Continue →** action on `certifications.php` for any in-progress enrolment.
- Course content modeled as **sections → videos** in MySQL.
- HTML5 `<video>` playback with `.srt` subtitles served as WebVTT.
- Auto-marking a video as watched when the user reaches **≥ 90 %** of its runtime.
- Per-user progress (`video_progress`) with the live percentage rolled into `user_certifications.progress`.
- A one-off CLI importer (`import-course.php`) that walks a source folder, slugifies names, and seeds the DB.
- Local-only file storage; the `stream.php` indirection is kept (auth gating, path opacity), but no cloud-storage backend is added.

### Out of scope

- Final exam / quiz step. Reaching 100 % does **not** flip status from `in_progress` to `earned`.
- Instructor / admin UI. The importer is a developer-run CLI script.
- Resume position ("continue where you left off mid-video").
- Analytics, view counts, leaderboards.
- Cloud storage backends (Firebase, R2, S3, YouTube).

---

## 3. Architecture overview

Three layers with clear responsibilities:

```
[ Disk: MEDIA_ROOT ]                [ MySQL ]                    [ Browser ]
PHP-ing3/                           certifications                course.php
└── pcep/                           ├── course_sections          ┌────────────────────┐
    ├── 01-introduction/            │   └── course_videos        │ Section accordion  │
    │   ├── 01-roadmap.mp4          └── video_progress           │  ├ ▶ Video 1 ✓ done│
    │   └── 01-roadmap.srt              (user × video)           │  └ ▶ Video 2 …     │
    ├── 02-data-types/                                           └─────────┬──────────┘
    └── ...                                                                │
                                                                           ▼
                                                              stream.php?video_id=N
                                                                  ↓ checks auth + enrolment
                                                                  ↓ joins video → cert
                                                                  ↓ streams from disk (Range)
                                                                  ↑ JS timeupdate ≥ 90 %
                                                                  → POST progress.php
```

### Request flow when a student watches a video

1. Student clicks a video in the left rail on `course.php`.
2. JS swaps the `<video>` `src` to `stream.php?video_id=N`.
3. `stream.php` runs `auth.php` → DB lookup of the video → enrolment check → `realpath()` confinement check → streams the file with `Range:` support.
4. JS listens to `timeupdate`; once `currentTime / duration >= 0.9`, it POSTs to `progress.php` with body `video_id=N` exactly once per video load.
5. `progress.php` `INSERT … ON DUPLICATE KEY UPDATE`s a row in `video_progress`, then runs a single `UPDATE user_certifications SET progress = …` joining against all the cert's videos.
6. Response payload includes the new percentage; the gold bar in the page header updates without a reload.

---

## 4. Database schema

All changes are additive. Existing tables (`users`, `profiles`, `certifications`, `user_certifications`) are untouched apart from one new column on `certifications`.

### 4.1 New column on `certifications`

```sql
ALTER TABLE certifications
  ADD COLUMN media_path VARCHAR(255) NULL AFTER description;
```

A short slug-style folder name like `'pcep'` or `'pcap'` that lives directly under `MEDIA_ROOT`. `NULL` means "content not yet available" — those certs still appear in the catalog, but `course.php` shows a "Content not yet uploaded" placeholder instead of the player.

### 4.2 `course_sections`

```sql
CREATE TABLE course_sections (
  id               INT AUTO_INCREMENT PRIMARY KEY,
  certification_id INT NOT NULL,
  position         SMALLINT NOT NULL,
  title            VARCHAR(255) NOT NULL,
  folder           VARCHAR(255) NOT NULL,
  UNIQUE KEY uq_cert_pos (certification_id, position),
  CONSTRAINT fk_section_cert
    FOREIGN KEY (certification_id) REFERENCES certifications(id)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

- `position` drives display order; `(certification_id, position)` is unique.
- `title` is the human label (e.g. `"Data Types, Evaluations, and Basic IO Operations"`).
- `folder` is the on-disk slug (e.g. `"02-data-types"`).

### 4.3 `course_videos`

```sql
CREATE TABLE course_videos (
  id                INT AUTO_INCREMENT PRIMARY KEY,
  section_id        INT NOT NULL,
  position          SMALLINT NOT NULL,
  title             VARCHAR(255) NOT NULL,
  filename          VARCHAR(255) NOT NULL,
  subtitle_filename VARCHAR(255) NULL,
  duration_seconds  INT NULL,
  UNIQUE KEY uq_section_pos (section_id, position),
  CONSTRAINT fk_video_section
    FOREIGN KEY (section_id) REFERENCES course_sections(id)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

- `filename` is the slugified video file inside the section folder (e.g. `"01-roadmap-of-the-course.mp4"`).
- `subtitle_filename` is the matching `.srt` (nullable).
- `duration_seconds` is `NULL` if `ffprobe` is unavailable at import; the player tolerates it.

### 4.4 `video_progress`

```sql
CREATE TABLE video_progress (
  user_id    INT NOT NULL,
  video_id   INT NOT NULL,
  watched_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (user_id, video_id),
  CONSTRAINT fk_vp_user  FOREIGN KEY (user_id)  REFERENCES users(id)         ON DELETE CASCADE,
  CONSTRAINT fk_vp_video FOREIGN KEY (video_id) REFERENCES course_videos(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

Presence of a row *is* the fact that the video was watched. No `is_watched` column. The composite primary key makes the operation idempotent.

### 4.5 Progress rollup query

Runs inside `progress.php` after every successful `INSERT` into `video_progress`:

```sql
UPDATE user_certifications uc
JOIN (
    SELECT
        cs.certification_id,
        ROUND(100 * SUM(vp.video_id IS NOT NULL) / NULLIF(COUNT(cv.id), 0)) AS pct
    FROM course_sections cs
    JOIN course_videos cv ON cv.section_id = cs.id
    LEFT JOIN video_progress vp
           ON vp.video_id = cv.id AND vp.user_id = :uid
    WHERE cs.certification_id = :cid
) calc ON calc.certification_id = uc.certification_id
SET uc.progress = COALESCE(calc.pct, 0)
WHERE uc.user_id = :uid AND uc.certification_id = :cid;
```

`NULLIF + COALESCE` protect against the degenerate "certification has zero videos" case.

---

## 5. File layout convention

### 5.1 `MEDIA_ROOT`

Single constant defined in a new `includes/config.php`:

```php
define('MEDIA_ROOT', 'C:/xampp/htdocs/PHP-ing3');
```

`includes/db.php` adds `require_once 'config.php';` at the top so every entry point that already includes `db.php` automatically has `MEDIA_ROOT`.

### 5.2 On-disk layout after import

```
PHP-ing3/                          ← MEDIA_ROOT
├── pcep/                          ← certifications.media_path
│   ├── 01-introduction/           ← course_sections.folder
│   │   ├── 01-roadmap-of-the-course.mp4
│   │   ├── 01-roadmap-of-the-course.srt
│   │   ├── 02-introduction.mp4
│   │   └── ...
│   ├── 02-data-types/
│   └── ...
└── pcap/
    └── ...
```

- Top-level slug = `certifications.media_path`.
- Section folder = `<zero-padded position>-<slugified title>`.
- Video file = `<zero-padded position>-<slugified title>.<ext>`.

### 5.3 Path resolver

```php
function video_disk_path(array $row): string {
    return MEDIA_ROOT
         . '/' . $row['cert_media_path']
         . '/' . $row['section_folder']
         . '/' . $row['filename'];
}
```

`stream.php` then runs `realpath($path)` and rejects anything that doesn't start with `realpath(MEDIA_ROOT)` — closing the door on path traversal even if a DB row is malicious.

---

## 6. `import-course.php` — one-shot importer

Run from CLI: `php import-course.php "<source folder>" <cert_code>`.

### Behavior

1. Look up the `certifications` row by `code` (e.g. `'PCEP'`). Abort if not found.
2. If `media_path` is `NULL`, set it to `lower(code)` (so `'pcep'`).
3. Walk top-level subfolders of the source path in natural-sort order. For each:
   - Parse `"N. Title"` → position `N`, title `Title`.
   - Slugify title → `01-introduction` style.
   - **Rename** the folder in place to the slugified form (idempotent — skip if already done).
   - `INSERT IGNORE INTO course_sections` keyed on `(certification_id, position)`.
4. Walk `.mp4` files inside each section folder. For each:
   - Parse `"N. Title.mp4"` → position, title.
   - Slugify → `01-roadmap-of-the-course.mp4`.
   - Rename file in place. Also rename the matching `.srt` if present.
   - If `ffprobe` is on `PATH`, run it to capture duration (in seconds, integer).
   - `INSERT IGNORE INTO course_videos` keyed on `(section_id, position)`.
5. Ensure the cert's content lives at `MEDIA_ROOT/<media_path>/`. If the source folder is somewhere else (e.g., the raw `"PCEP - Pass …/~Get Your Files Here !"` path), the importer renames/moves it into place; if it's already at `MEDIA_ROOT/<media_path>/`, this is a no-op.

### Idempotency

- Re-running on a clean folder is a no-op (renames detect already-clean state; inserts hit `UNIQUE` and `INSERT IGNORE`).
- Adding new videos to an already-imported folder picks them up on the next run.

---

## 7. `stream.php` — byte server with `Range:` support

### Responsibilities

1. `require auth.php` and `require db.php`.
2. Read `$_GET['video_id']` → `(int)`, validate.
3. Join: `course_videos` → `course_sections` → `certifications` to compute disk path.
4. Verify the signed-in user has an `user_certifications` row for the cert (any status). Otherwise HTTP 403.
5. Compute disk path via `video_disk_path()`. `realpath()` + `str_starts_with(realpath(MEDIA_ROOT))` check. Otherwise HTTP 403.
6. If file missing → HTTP 404.
7. Emit `Content-Type: video/mp4` and stream the file.
   - If `HTTP_RANGE` is present, parse `bytes=start-end`, `fseek`, write `206 Partial Content` with the right `Content-Range` and `Content-Length`.
   - Otherwise stream the full file with `200 OK`.

### Why we don't expose the static URL through Apache

- `<auth check>` cannot run in pure Apache.
- The disk path stays opaque (`stream.php?video_id=42`).
- A future migration to signed external URLs only changes `stream.php`.

---

## 8. `subtitle.php` — SRT → WebVTT

Same auth flow as `stream.php`. Loads the `.srt` file, runs a minimal text transform (`WEBVTT` header + comma → period in timestamps), emits `text/vtt; charset=utf-8`. Consumed by `<track>` inside the `<video>` element.

---

## 9. `progress.php` — record + recompute

**Method:** POST. **Body:** `video_id`.

1. `require auth.php`.
2. Validate `video_id`.
3. Verify the user is enrolled in the parent cert (same check as `stream.php`).
4. `INSERT INTO video_progress (user_id, video_id) VALUES (?, ?) ON DUPLICATE KEY UPDATE watched_at = watched_at;` (no-op on duplicate, keeps original timestamp).
5. Run the rollup query from §4.5.
6. Return JSON `{"ok": true, "progress": <0-100>, "section": {"id": …, "watched": <n>, "total": <m>}}`.

The client uses `progress` to update the gold bar and `section` counts to update the left rail in place.

---

## 10. `course.php` — the player page

### Layout (desktop, ≥ lg)

- Top strip: cert title, provider, percentage, gold progress bar (reuses existing `.filled-bar` / `.full-bar` styles).
- Left column (`col-lg-4`): section accordion. Each section header shows `title` and `watched/total`. Each child is a video row with status mark (`✓` watched, `▶` playing, blank). Click → loads that video on the right.
- Right column (`col-lg-8`): `<video controls preload="metadata">` with `<source src="stream.php?video_id=N" type="video/mp4">` and `<track kind="subtitles" src="subtitle.php?video_id=N" srclang="en" label="English">`. Below: title and a small "Up next →" indicator.

### JS behavior (single small inline script)

- Bind `timeupdate`: if `currentTime / duration >= 0.9` and a "watched-fired" flag for the current video isn't set, set the flag, POST `progress.php`, update the bar and the left rail from the JSON response.
- Bind `ended`: if a next video exists, swap `src`, reset the flag, autoplay.
- Click handler on left-rail rows: same swap-`src` flow, no full reload.

### Active section selection

On initial load, the active section = the lowest-position section that has at least one unwatched video. If all sections are 100 % watched, default to the last section. The active video = the first unwatched video in the active section, or the last video of the cert if everything's watched.

---

## 11. Integration points

### Files added (5)

```
Project/
├── course.php
├── stream.php
├── subtitle.php
├── progress.php
├── import-course.php
└── includes/config.php
```

### Files modified (3)

- `database/tekup.sql` — new `ALTER TABLE` + 3 `CREATE TABLE` blocks + seed `UPDATE`s setting `media_path` for PCEP and PCAP.
- `certifications.php` — change the in-progress card's `Continue →` link from `href="#"` to `href="course.php?cert=<id>"`.
- `includes/db.php` — `require_once 'config.php';` at the top.

### Files explicitly **not** modified

- `dashboard.php`, `catalog.php`, `profile.php`, `contact.php`, `login.php`, `index.php`, `logout.php`. None need changes — they already use `user_certifications.progress` which now reflects real watch progress.

---

## 12. Error handling — table form

| Situation | Behavior |
|---|---|
| Not logged in | `auth.php` redirects to `login.php` |
| Logged in, not enrolled in this cert | `course.php` → flash + redirect to `catalog.php`; `stream.php` / `subtitle.php` / `progress.php` → HTTP 403 |
| `?cert=` missing or invalid on `course.php` | Redirect to `certifications.php` |
| `?video_id=` missing or invalid on stream/subtitle/progress | HTTP 400 JSON `{"error":"bad_request"}` |
| Cert exists but `media_path` is `NULL` | `course.php` renders the "Content not yet uploaded" placeholder with a back link |
| Video file missing on disk | `stream.php` → HTTP 404; left rail marks it `⚠` with title "File unavailable" |
| Path-traversal attempt via crafted DB row | `realpath()` + `str_starts_with(MEDIA_ROOT)` check in `stream.php` → HTTP 403, no file read |
| User spams `progress.php` for the same video | `INSERT … ON DUPLICATE KEY UPDATE` keeps the original timestamp, recomputes idempotently |
| Importer re-run on already-imported folder | `INSERT IGNORE` + idempotent renames → no duplicate rows |

---

## 13. Testing approach

Manual smoke tests only — no automated harness for this iteration.

1. **Happy path:** sign in as `user1@tek-up.de`, enroll in PCEP, click Continue from `certifications.php`, watch a video to 90 %, see the percentage in the page header and the section counter update without a reload. Sign out. Sign in as `user2@tek-up.de`, navigate to PCEP — confirm their progress is independent (still 0 %).
2. **Authorization:** while logged in as `user2` (not enrolled), hit `stream.php?video_id=<id-from-user1's-cert>` directly → expect HTTP 403.
3. **Path traversal:** manually craft a `course_videos.filename` value containing `../../something.txt`, hit `stream.php` → expect HTTP 403 (path confinement check).
4. **Missing file:** rename one video on disk → reload `course.php` → ⚠ marker appears on that row; clicking it → 404 in the player.
5. **Idempotent progress:** rewatch an already-watched video past 90 % → `progress.php` returns the same percentage, no duplicate row, `watched_at` unchanged.
6. **Importer idempotency:** run `import-course.php "PCEP - Pass Certified Entry-Level Python Programmer/~Get Your Files Here !" PCEP` → produces 5 sections + ~80 videos. Run it again → no changes, no errors.

---

## 14. Open questions / decisions deferred

- **Final-exam step.** When and how a cert flips to `earned` is intentionally not designed here. Likely a future spec covering quiz/exam scoring.
- **Re-watch UX.** No UI to "un-mark" a watched video. Out of scope; can add a per-row dropdown later if needed.
- **Mobile playback.** Layout assumes desktop. Two-column layout collapses to single column under `lg`, but no testing of HTML5 Range support on iOS Safari has been done.

---

## 15. Implementation order (high-level)

This becomes the input for the writing-plans skill:

1. `includes/config.php` + DB migration (`ALTER TABLE`, three `CREATE TABLE`).
2. `import-course.php` (so we have data to test against).
3. Run the importer against the PCEP folder → DB populated.
4. `stream.php` + `subtitle.php` + the path-confinement check.
5. `progress.php` + the rollup `UPDATE`.
6. `course.php` (server-side query + markup).
7. `course.php` JS (auto-mark at 90 %, click-to-load, in-place updates).
8. Wire `certifications.php` Continue → `course.php?cert=…`.
9. Import PCAP, repeat smoke tests.
