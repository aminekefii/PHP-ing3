# Course Content (Video Sections) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add tracked, video-based course content (sections → videos with watched-tracking) to enrolled certifications, starting with PCEP, using local-disk storage outside the project repo.

**Architecture:** Three new MySQL tables (`course_sections`, `course_videos`, `video_progress`) plus a `media_path` column on `certifications`. Videos live at `C:/xampp/htdocs/PHP-ing3/<media_path>/<section_folder>/<video_filename>`. PHP serves them through an auth-gated `stream.php` (with `Range:` support) and `subtitle.php` (SRT→VTT). A `course.php` player page tracks progress; `progress.php` records watched videos and recomputes `user_certifications.progress`. A one-off `import-course.php` CLI script slugifies the messy source folder and seeds the DB.

**Tech Stack:** PHP 8 (XAMPP), MySQL via PDO, HTML5 `<video>`, vanilla JavaScript, Bootstrap 5 (already in `vendor/`). No automated test framework — verification is by manual smoke tests, per spec §13.

**Spec:** `docs/superpowers/specs/2026-05-14-course-content-design.md`

**Working directory for all commands:** `C:\xampp\htdocs\PHP-ing3\Project` (referred to below as the project root).

---

## File Map

**New files (under project root):**

- `includes/config.php` — defines `MEDIA_ROOT`. Required by every entry point that includes `db.php`.
- `import-course.php` — one-off CLI. Walks the messy source folder, slugifies names, inserts `course_sections` + `course_videos`, sets `certifications.media_path`.
- `stream.php` — auth-gated video byte server with `Range:` support. Takes `?video_id=N`.
- `subtitle.php` — auth-gated SRT→VTT converter. Takes `?video_id=N`.
- `progress.php` — POST endpoint. Body: `video_id`. Inserts into `video_progress`, recomputes `user_certifications.progress`, returns JSON.
- `course.php` — the player page. Takes `?cert=N`. Two-column layout: section accordion (left) + HTML5 video player (right).

**Existing files modified:**

- `includes/db.php` — add `require_once 'config.php';` at the top.
- `database/tekup.sql` — `ALTER TABLE certifications` + three new `CREATE TABLE` blocks + seed `UPDATE` that pins `media_path = 'pcep'` for the PCEP row.
- `certifications.php` — change the in-progress card's `Continue →` `href` from `#` to `course.php?cert=<id>`.

**Untouched:** `index.php`, `login.php`, `logout.php`, `dashboard.php`, `profile.php`, `catalog.php`, `contact.php`, and all `assets/`.

---

## Task 1: Add `MEDIA_ROOT` constant in `includes/config.php`

**Files:**
- Create: `includes/config.php`

- [ ] **Step 1: Create the config file**

Create `includes/config.php` with this exact content:

```php
<?php
// Media root for course content (videos, subtitles).
// Lives outside the project so multi-GB media never enters the repo.
// Used by stream.php, subtitle.php, course.php and import-course.php.
if (!defined('MEDIA_ROOT')) {
    define('MEDIA_ROOT', 'C:/xampp/htdocs/PHP-ing3');
}
```

- [ ] **Step 2: Smoke-test the constant**

From the project root, run:

```bash
php -r "require 'includes/config.php'; echo MEDIA_ROOT . PHP_EOL;"
```

Expected output:

```
C:/xampp/htdocs/PHP-ing3
```

- [ ] **Step 3: Commit**

```bash
git add includes/config.php
git commit -m "feat(config): add MEDIA_ROOT constant for course media"
```

---

## Task 2: Wire `config.php` into `includes/db.php`

**Files:**
- Modify: `includes/db.php` (top of file)

- [ ] **Step 1: Add the require_once line**

Open `includes/db.php`. The current first line is `<?php`. Right after it, insert one line so the top of the file looks exactly like:

```php
<?php
require_once __DIR__ . '/config.php';

// MySQL connection for XAMPP (default credentials).
```

(Leave the rest of the file unchanged.)

- [ ] **Step 2: Smoke-test that every page still loads**

Open `http://localhost/PHP-ing3/Project/index.php` in a browser. Expected: page renders normally with no PHP error banners.

Also `http://localhost/PHP-ing3/Project/login.php` — same expectation.

- [ ] **Step 3: Commit**

```bash
git add includes/db.php
git commit -m "feat(config): require config.php from db.php so MEDIA_ROOT is global"
```

---

## Task 3: Database migration — add `media_path` column and three new tables

**Files:**
- Modify: `database/tekup.sql` (append new statements before the closing of the file)

- [ ] **Step 1: Append the schema changes**

Open `database/tekup.sql`. Append the following block at the end of the file (after the last existing `INSERT` statement for `certifications`):

```sql

-- ---------------------------------------------------------------
-- Course content: sections, videos, and per-user progress.
-- Added 2026-05-14.
-- ---------------------------------------------------------------

ALTER TABLE `certifications`
  ADD COLUMN IF NOT EXISTS `media_path` VARCHAR(255) NULL AFTER `description`;

DROP TABLE IF EXISTS `video_progress`;
DROP TABLE IF EXISTS `course_videos`;
DROP TABLE IF EXISTS `course_sections`;

CREATE TABLE `course_sections` (
  `id`               INT AUTO_INCREMENT PRIMARY KEY,
  `certification_id` INT NOT NULL,
  `position`         SMALLINT NOT NULL,
  `title`            VARCHAR(255) NOT NULL,
  `folder`           VARCHAR(255) NOT NULL,
  UNIQUE KEY `uq_cert_pos` (`certification_id`, `position`),
  CONSTRAINT `fk_section_cert`
    FOREIGN KEY (`certification_id`) REFERENCES `certifications`(`id`)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `course_videos` (
  `id`                INT AUTO_INCREMENT PRIMARY KEY,
  `section_id`        INT NOT NULL,
  `position`          SMALLINT NOT NULL,
  `title`             VARCHAR(255) NOT NULL,
  `filename`          VARCHAR(255) NOT NULL,
  `subtitle_filename` VARCHAR(255) NULL,
  `duration_seconds`  INT NULL,
  UNIQUE KEY `uq_section_pos` (`section_id`, `position`),
  CONSTRAINT `fk_video_section`
    FOREIGN KEY (`section_id`) REFERENCES `course_sections`(`id`)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `video_progress` (
  `user_id`    INT NOT NULL,
  `video_id`   INT NOT NULL,
  `watched_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`user_id`, `video_id`),
  CONSTRAINT `fk_vp_user`  FOREIGN KEY (`user_id`)  REFERENCES `users`(`id`)         ON DELETE CASCADE,
  CONSTRAINT `fk_vp_video` FOREIGN KEY (`video_id`) REFERENCES `course_videos`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

Note: `ADD COLUMN IF NOT EXISTS` requires MySQL 8.0.29+. XAMPP ships a recent enough MariaDB/MySQL. If the import errors with "Syntax error near IF NOT EXISTS", replace that one line with:

```sql
ALTER TABLE `certifications` ADD COLUMN `media_path` VARCHAR(255) NULL AFTER `description`;
```

(This will error on a second import but you only run import once per schema reset.)

- [ ] **Step 2: Re-import the SQL file**

In phpMyAdmin (`http://localhost/phpmyadmin`):

1. Click the **Import** tab.
2. Choose file → `C:\xampp\htdocs\PHP-ing3\Project\database\tekup.sql`.
3. Click **Go**.

Expected: import succeeds; no red error banner.

- [ ] **Step 3: Verify the new objects exist**

In phpMyAdmin, click the `tekup` database. Expected to see in the table list:

- `users`, `profiles`, `certifications`, `user_certifications` (pre-existing)
- `course_sections` (new, empty)
- `course_videos` (new, empty)
- `video_progress` (new, empty)

Click on `certifications` → **Structure** tab. Expected: there is now a `media_path VARCHAR(255) NULL` column placed right after `description`.

- [ ] **Step 4: Commit**

```bash
git add database/tekup.sql
git commit -m "feat(db): add course_sections, course_videos, video_progress tables and media_path column"
```

---

## Task 4: Importer skeleton — CLI args + cert lookup

**Files:**
- Create: `import-course.php`

- [ ] **Step 1: Create the importer scaffold**

Create `import-course.php` with this exact content:

```php
<?php
/**
 * One-off CLI importer for course content.
 *
 * Usage:
 *   php import-course.php "<source folder>" <CERT_CODE>
 *
 * Example:
 *   php import-course.php "C:/xampp/htdocs/PHP-ing3/PCEP - Pass Certified Entry-Level Python Programmer/~Get Your Files Here !" PCEP
 *
 * The script is idempotent: re-running on already-imported content is a no-op.
 */

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "This script must be run from the command line.\n");
    exit(1);
}

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';

if ($argc < 3) {
    fwrite(STDERR, "Usage: php import-course.php \"<source folder>\" <CERT_CODE>\n");
    exit(1);
}

$source_folder = rtrim(str_replace('\\', '/', $argv[1]), '/');
$cert_code     = strtoupper($argv[2]);

if (!is_dir($source_folder)) {
    fwrite(STDERR, "Source folder not found: {$source_folder}\n");
    exit(1);
}

$stmt = $pdo->prepare('SELECT id, code, media_path FROM certifications WHERE code = :code LIMIT 1');
$stmt->execute([':code' => $cert_code]);
$cert = $stmt->fetch();

if (!$cert) {
    fwrite(STDERR, "Certification with code '{$cert_code}' not found in the database.\n");
    exit(1);
}

$cert_id    = (int) $cert['id'];
$media_path = $cert['media_path'] ?: strtolower($cert_code);

echo "Importing into certification '{$cert_code}' (id {$cert_id}), media_path='{$media_path}'\n";
echo "Source: {$source_folder}\n";
echo "Target: " . MEDIA_ROOT . "/{$media_path}\n";
```

- [ ] **Step 2: Smoke-test arg parsing**

Run with bad args first:

```bash
php import-course.php
```

Expected:

```
Usage: php import-course.php "<source folder>" <CERT_CODE>
```

Then run with a non-existent code:

```bash
php import-course.php "C:/xampp/htdocs/PHP-ing3" XYZ
```

Expected:

```
Certification with code 'XYZ' not found in the database.
```

Then run with a real path and code (it should print the three "Importing/Source/Target" lines and exit cleanly):

```bash
php import-course.php "C:/xampp/htdocs/PHP-ing3/PCEP - Pass Certified Entry-Level Python Programmer/~Get Your Files Here !" PCEP
```

Expected output (the script doesn't do anything yet — just prints):

```
Importing into certification 'PCEP' (id 26), media_path='pcep'
Source: C:/xampp/htdocs/PHP-ing3/PCEP - Pass Certified Entry-Level Python Programmer/~Get Your Files Here !
Target: C:/xampp/htdocs/PHP-ing3/pcep
```

(Cert id may differ; that's fine.)

- [ ] **Step 3: Commit**

```bash
git add import-course.php
git commit -m "feat(importer): add CLI scaffold for course content importer"
```

---

## Task 5: Importer — add `slugify()` helper

**Files:**
- Modify: `import-course.php` (append a helper function)

- [ ] **Step 1: Add the slugify helper**

In `import-course.php`, append this function at the bottom of the file (after the last `echo` line):

```php

/**
 * Turn an arbitrary title string into a URL/filesystem-safe slug.
 * "Control Flow – loops and conditional blocks" -> "control-flow-loops-and-conditional-blocks"
 * Strips diacritics where iconv is available, lower-cases, collapses runs
 * of non-alphanumeric characters to single dashes.
 */
function slugify(string $s): string {
    if (function_exists('iconv')) {
        $ascii = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $s);
        if ($ascii !== false) {
            $s = $ascii;
        }
    }
    $s = strtolower($s);
    $s = preg_replace('/[^a-z0-9]+/', '-', $s);
    return trim($s ?? '', '-');
}

// Smoke-test for slugify when SLUGIFY_TEST env var is set.
if (getenv('SLUGIFY_TEST') === '1') {
    $cases = [
        'Introduction'                                => 'introduction',
        '1. Roadmap of the Course'                    => '1-roadmap-of-the-course',
        'Control Flow – loops and conditional blocks' => 'control-flow-loops-and-conditional-blocks',
        'Data Types, Evaluations, and Basic IO Operations' => 'data-types-evaluations-and-basic-io-operations',
    ];
    foreach ($cases as $in => $expected) {
        $got = slugify($in);
        $ok  = $got === $expected ? 'OK  ' : 'FAIL';
        echo "{$ok} slugify(\"{$in}\") = \"{$got}\" (expected \"{$expected}\")\n";
    }
    exit($got === $expected ? 0 : 1);
}
```

- [ ] **Step 2: Smoke-test the slug rules**

From the project root:

```bash
SLUGIFY_TEST=1 php import-course.php "x" "x"
```

(On Windows PowerShell:)

```powershell
$env:SLUGIFY_TEST="1"; php import-course.php "x" "x"; Remove-Item Env:SLUGIFY_TEST
```

Expected output (the cert lookup happens after the slugify-test branch only if `SLUGIFY_TEST` is unset, so we need to put the test branch earlier):

Wait — re-read your file. The slugify test is at the *bottom*, which runs after the cert lookup that exits on a bad code. Move the test block to **right after the `require_once` lines** instead. Replace what you just appended; instead, edit `import-course.php` so the slugify function + its test are inserted between the two `require_once` lines and the `if ($argc < 3)` block:

```php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';

function slugify(string $s): string {
    if (function_exists('iconv')) {
        $ascii = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $s);
        if ($ascii !== false) {
            $s = $ascii;
        }
    }
    $s = strtolower($s);
    $s = preg_replace('/[^a-z0-9]+/', '-', $s);
    return trim($s ?? '', '-');
}

if (getenv('SLUGIFY_TEST') === '1') {
    $cases = [
        'Introduction'                                => 'introduction',
        '1. Roadmap of the Course'                    => '1-roadmap-of-the-course',
        'Control Flow – loops and conditional blocks' => 'control-flow-loops-and-conditional-blocks',
        'Data Types, Evaluations, and Basic IO Operations' => 'data-types-evaluations-and-basic-io-operations',
    ];
    $all_ok = true;
    foreach ($cases as $in => $expected) {
        $got = slugify($in);
        $ok  = $got === $expected;
        $all_ok = $all_ok && $ok;
        echo ($ok ? 'OK  ' : 'FAIL') . " slugify(\"{$in}\") = \"{$got}\" (expected \"{$expected}\")\n";
    }
    exit($all_ok ? 0 : 1);
}

if ($argc < 3) {
    ...
```

Re-run:

```bash
SLUGIFY_TEST=1 php import-course.php "x" "x"
```

Expected output (every line starts with `OK`):

```
OK  slugify("Introduction") = "introduction" (expected "introduction")
OK  slugify("1. Roadmap of the Course") = "1-roadmap-of-the-course" (expected "1-roadmap-of-the-course")
OK  slugify("Control Flow – loops and conditional blocks") = "control-flow-loops-and-conditional-blocks" (expected "control-flow-loops-and-conditional-blocks")
OK  slugify("Data Types, Evaluations, and Basic IO Operations") = "data-types-evaluations-and-basic-io-operations" (expected "data-types-evaluations-and-basic-io-operations")
```

Exit code `0` on success.

- [ ] **Step 3: Commit**

```bash
git add import-course.php
git commit -m "feat(importer): add slugify helper with built-in test mode"
```

---

## Task 6: Importer — walk + rename + insert sections

**Files:**
- Modify: `import-course.php` (append the section-processing loop)

- [ ] **Step 1: Add the section walking logic**

Append the following block at the end of `import-course.php` (after the existing `echo "Target: …\n";` line):

```php

// Ensure the destination root exists: MEDIA_ROOT/<media_path>/
$dest_root = MEDIA_ROOT . '/' . $media_path;
if (!is_dir($dest_root)) {
    if (!mkdir($dest_root, 0777, true)) {
        fwrite(STDERR, "Could not create destination folder: {$dest_root}\n");
        exit(1);
    }
    echo "Created destination folder: {$dest_root}\n";
}

// Walk top-level subfolders of the source, parse "N. Title", slugify, rename in place
// to <pos>-<slug>, move to dest_root, insert into course_sections.
$entries = scandir($source_folder);
if ($entries === false) {
    fwrite(STDERR, "Could not list source folder: {$source_folder}\n");
    exit(1);
}
natsort($entries);

$section_insert = $pdo->prepare(
    "INSERT IGNORE INTO course_sections (certification_id, position, title, folder)
     VALUES (:cert_id, :pos, :title, :folder)"
);

$section_id_lookup = $pdo->prepare(
    "SELECT id FROM course_sections
     WHERE certification_id = :cert_id AND position = :pos LIMIT 1"
);

foreach ($entries as $name) {
    if ($name === '.' || $name === '..') continue;
    $full = $source_folder . '/' . $name;
    if (!is_dir($full)) continue;

    if (!preg_match('/^(\d+)\.\s*(.+)$/u', $name, $m)) {
        echo "  skip (no leading number): {$name}\n";
        continue;
    }
    $position    = (int) $m[1];
    $title       = trim($m[2]);
    $section_dir = sprintf('%02d-%s', $position, slugify($title));
    $section_dest = $dest_root . '/' . $section_dir;

    if (!is_dir($section_dest)) {
        if (!@rename($full, $section_dest)) {
            fwrite(STDERR, "  ERROR renaming '{$full}' -> '{$section_dest}'\n");
            continue;
        }
        echo "  moved: {$name}  ->  {$media_path}/{$section_dir}\n";
    } else {
        echo "  already in place: {$media_path}/{$section_dir}\n";
    }

    $section_insert->execute([
        ':cert_id' => $cert_id,
        ':pos'     => $position,
        ':title'   => $title,
        ':folder'  => $section_dir,
    ]);
}
```

- [ ] **Step 2: Smoke-test against the real PCEP folder**

Run:

```bash
php import-course.php "C:/xampp/htdocs/PHP-ing3/PCEP - Pass Certified Entry-Level Python Programmer/~Get Your Files Here !" PCEP
```

Expected output (cert id will differ):

```
Importing into certification 'PCEP' (id 26), media_path='pcep'
Source: C:/xampp/htdocs/PHP-ing3/PCEP - Pass Certified Entry-Level Python Programmer/~Get Your Files Here !
Target: C:/xampp/htdocs/PHP-ing3/pcep
Created destination folder: C:/xampp/htdocs/PHP-ing3/pcep
  moved: 1. Introduction  ->  pcep/01-introduction
  moved: 2. Data Types, Evaluations, and Basic IO Operations  ->  pcep/02-data-types-evaluations-and-basic-io-operations
  moved: 3. Control Flow – loops and conditional blocks  ->  pcep/03-control-flow-loops-and-conditional-blocks
  moved: 4. Data Collections – Lists, Tuples, and Dictionaries  ->  pcep/04-data-collections-lists-tuples-and-dictionaries
  moved: 5. Functions  ->  pcep/05-functions
```

Verify on disk: `C:/xampp/htdocs/PHP-ing3/pcep/` now contains five folders with slug names.

Verify in phpMyAdmin: open `course_sections` → expect five rows for the PCEP cert id with positions 1–5 and matching titles + folders.

- [ ] **Step 3: Verify idempotency**

Re-run the exact same command. Expected output now reports "already in place" for each section and inserts no duplicate rows (because of `INSERT IGNORE` + the `UNIQUE` index):

```
  already in place: pcep/01-introduction
  already in place: pcep/02-data-types-evaluations-and-basic-io-operations
  ...
```

Confirm in phpMyAdmin that `course_sections` still has exactly five rows.

- [ ] **Step 4: Commit**

```bash
git add import-course.php
git commit -m "feat(importer): walk + slugify + move sections, idempotent inserts"
```

---

## Task 7: Importer — walk + rename + insert videos (and their .srt)

**Files:**
- Modify: `import-course.php` (extend the section loop to handle videos)

- [ ] **Step 1: Add the video walking logic inside the section loop**

In `import-course.php`, find the line `$section_insert->execute([...]);` at the bottom of the section loop. Right after that `execute` call (still inside the `foreach ($entries as $name)` loop), append:

```php
    // Look up the section id we just inserted (or that already existed).
    $section_id_lookup->execute([':cert_id' => $cert_id, ':pos' => $position]);
    $section_row = $section_id_lookup->fetch();
    if (!$section_row) {
        fwrite(STDERR, "  ERROR could not find section id after insert\n");
        continue;
    }
    $section_id = (int) $section_row['id'];

    // Walk video files inside this section folder.
    $video_insert = $pdo->prepare(
        "INSERT IGNORE INTO course_videos
            (section_id, position, title, filename, subtitle_filename, duration_seconds)
         VALUES (:section_id, :pos, :title, :filename, :subtitle, :duration)"
    );

    $video_files = glob($section_dest . '/*.mp4');
    natsort($video_files);

    foreach ($video_files as $video_path) {
        $orig = basename($video_path);
        if (!preg_match('/^(\d+)\.\s*(.+)\.mp4$/u', $orig, $vm)) {
            echo "    skip (no leading number): {$orig}\n";
            continue;
        }
        $vpos       = (int) $vm[1];
        $vtitle     = trim($vm[2]);
        $vslug      = slugify($vtitle);
        $vfile_new  = sprintf('%02d-%s.mp4', $vpos, $vslug);
        $sfile_new  = sprintf('%02d-%s.srt', $vpos, $vslug);

        $video_new_path = $section_dest . '/' . $vfile_new;
        if ($video_path !== $video_new_path) {
            if (!@rename($video_path, $video_new_path)) {
                fwrite(STDERR, "    ERROR renaming '{$video_path}' -> '{$video_new_path}'\n");
                continue;
            }
        }

        // Sibling .srt with the original numbering "<N>. <Title>.srt".
        $orig_srt = $section_dest . '/' . preg_replace('/\.mp4$/', '.srt', $orig);
        $subtitle_to_store = null;
        if (file_exists($section_dest . '/' . $sfile_new)) {
            $subtitle_to_store = $sfile_new;
        } elseif (file_exists($orig_srt)) {
            @rename($orig_srt, $section_dest . '/' . $sfile_new);
            $subtitle_to_store = $sfile_new;
        }

        $video_insert->execute([
            ':section_id' => $section_id,
            ':pos'        => $vpos,
            ':title'      => $vtitle,
            ':filename'   => $vfile_new,
            ':subtitle'   => $subtitle_to_store,
            ':duration'   => null, // backfilled later if/when ffprobe is wired in
        ]);

        echo "    video: {$vfile_new}" . ($subtitle_to_store ? " (+ srt)" : "") . "\n";
    }
```

- [ ] **Step 2: Re-run the importer**

```bash
php import-course.php "C:/xampp/htdocs/PHP-ing3/PCEP - Pass Certified Entry-Level Python Programmer/~Get Your Files Here !" PCEP
```

Note that on a re-run the source folder is now empty (sections were moved into `MEDIA_ROOT/pcep` last task), so the script will skip section processing. We need to point it at `pcep/` directly this time, since that's where the un-slugified video files still are. Re-run with the new path:

```bash
php import-course.php "C:/xampp/htdocs/PHP-ing3/pcep" PCEP
```

Wait — the source folder convention now needs to handle this. **Update the importer to accept either form**: if the source folder is already inside `MEDIA_ROOT/<media_path>/`, treat each subfolder as an already-moved section folder (recognize them by the `\d+-...` pattern) and skip the rename step but still process videos inside.

Replace the `if (!preg_match('/^(\d+)\.\s*(.+)$/u', $name, $m))` check with the following block that handles **both** original and already-slugged folder names:

```php
    if (preg_match('/^(\d+)\.\s*(.+)$/u', $name, $m)) {
        // Original "N. Title" form — needs renaming.
        $position    = (int) $m[1];
        $title       = trim($m[2]);
        $section_dir = sprintf('%02d-%s', $position, slugify($title));
    } elseif (preg_match('/^(\d+)-(.+)$/', $name, $m)) {
        // Already-slugged "NN-slug" form — no rename, but we still want the row.
        $position    = (int) $m[1];
        $section_dir = $name;
        // Reverse-engineer a title by replacing dashes with spaces and title-casing.
        // Only used if the row doesn't exist yet.
        $title       = ucwords(str_replace('-', ' ', $m[2]));
    } else {
        echo "  skip (no leading number): {$name}\n";
        continue;
    }
```

Now re-run with the slugged path:

```bash
php import-course.php "C:/xampp/htdocs/PHP-ing3/pcep" PCEP
```

Expected output (one section line per folder, multiple `video:` lines per section):

```
  already in place: pcep/01-introduction
    video: 01-roadmap-of-the-course.mp4 (+ srt)
    video: 02-introduction.mp4 (+ srt)
    video: 03-how-to-setup-python.mp4 (+ srt)
    ...
  already in place: pcep/02-data-types-evaluations-and-basic-io-operations
    video: 01-...
    ...
```

- [ ] **Step 3: Verify rows in phpMyAdmin**

`course_videos` should now have ~80 rows (varies). Spot-check a few:

```sql
SELECT cs.position, cs.title, cv.position, cv.title, cv.filename, cv.subtitle_filename
FROM course_videos cv
JOIN course_sections cs ON cs.id = cv.section_id
WHERE cs.certification_id = (SELECT id FROM certifications WHERE code='PCEP')
ORDER BY cs.position, cv.position
LIMIT 10;
```

Expected: ordered rows showing `01 Roadmap of the Course → 01-roadmap-of-the-course.mp4 + 01-roadmap-of-the-course.srt`.

- [ ] **Step 4: Verify idempotency**

Re-run:

```bash
php import-course.php "C:/xampp/htdocs/PHP-ing3/pcep" PCEP
```

Expected: same output, no errors, no new rows in `course_videos`.

- [ ] **Step 5: Commit**

```bash
git add import-course.php
git commit -m "feat(importer): walk + slugify + insert videos + matching srt"
```

---

## Task 8: Importer — set `media_path` on certifications

**Files:**
- Modify: `import-course.php` (one statement at the very end)

- [ ] **Step 1: Add the media_path update**

At the very end of `import-course.php`, append:

```php

// Persist media_path on the certifications row so other pages can find the folder.
$mp_update = $pdo->prepare('UPDATE certifications SET media_path = :mp WHERE id = :id');
$mp_update->execute([':mp' => $media_path, ':id' => $cert_id]);

echo "Done. media_path='{$media_path}' set on certification {$cert_code}.\n";
```

- [ ] **Step 2: Re-run the importer**

```bash
php import-course.php "C:/xampp/htdocs/PHP-ing3/pcep" PCEP
```

Expected final line:

```
Done. media_path='pcep' set on certification PCEP.
```

- [ ] **Step 3: Verify the column in phpMyAdmin**

```sql
SELECT id, code, media_path FROM certifications WHERE code = 'PCEP';
```

Expected: one row with `media_path = 'pcep'`.

- [ ] **Step 4: Commit**

```bash
git add import-course.php
git commit -m "feat(importer): set certifications.media_path on completion"
```

---

## Task 9: `stream.php` — auth + DB lookup + path confinement

**Files:**
- Create: `stream.php`

- [ ] **Step 1: Create the stream endpoint without Range support yet**

Create `stream.php` with this exact content:

```php
<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';

$user_id  = (int) ($_SESSION['user']['id'] ?? 0);
$video_id = isset($_GET['video_id']) ? (int) $_GET['video_id'] : 0;

if ($video_id <= 0) {
    http_response_code(400);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'bad_request']);
    exit;
}

$stmt = $pdo->prepare(
    "SELECT cv.filename,
            cs.folder       AS section_folder,
            c.media_path    AS cert_media_path,
            c.id            AS cert_id
     FROM course_videos cv
     JOIN course_sections cs ON cs.id = cv.section_id
     JOIN certifications  c  ON c.id  = cs.certification_id
     WHERE cv.id = :vid LIMIT 1"
);
$stmt->execute([':vid' => $video_id]);
$row = $stmt->fetch();

if (!$row) {
    http_response_code(404);
    exit;
}

// Enrolment check: the signed-in user must have a user_certifications row for this cert.
$enroll = $pdo->prepare(
    'SELECT 1 FROM user_certifications WHERE user_id = :uid AND certification_id = :cid LIMIT 1'
);
$enroll->execute([':uid' => $user_id, ':cid' => (int) $row['cert_id']]);
if (!$enroll->fetchColumn()) {
    http_response_code(403);
    exit;
}

// Resolve disk path and confine it under MEDIA_ROOT.
$relative = $row['cert_media_path'] . '/' . $row['section_folder'] . '/' . $row['filename'];
$path     = MEDIA_ROOT . '/' . $relative;
$real     = realpath($path);
$root     = realpath(MEDIA_ROOT);

if ($real === false || $root === false || strncmp($real, $root, strlen($root)) !== 0) {
    http_response_code(403);
    exit;
}

if (!is_file($real)) {
    http_response_code(404);
    exit;
}

// For now (Task 9) just emit the file straight; Range support comes in Task 10.
header('Content-Type: video/mp4');
header('Content-Length: ' . filesize($real));
header('Accept-Ranges: bytes');
readfile($real);
exit;
```

- [ ] **Step 2: Smoke-test in the browser**

Find a real `video_id` for an enrolled user:

```sql
-- in phpMyAdmin, while signed in as user1 enrolled in PCEP
SELECT cv.id, cv.title FROM course_videos cv
JOIN course_sections cs ON cs.id = cv.section_id
JOIN certifications c ON c.id = cs.certification_id
WHERE c.code = 'PCEP' ORDER BY cs.position, cv.position LIMIT 1;
```

If `user1` is not yet enrolled in PCEP, enroll them via the catalog page first.

Then in the browser, visit (substituting the real id):

```
http://localhost/PHP-ing3/Project/stream.php?video_id=1
```

Expected: a video starts playing in the browser's native viewer (Chrome renders raw `video/mp4` inline).

- [ ] **Step 3: Smoke-test the auth and authorization guards**

While **signed out**, visit `stream.php?video_id=1` → expected: redirect to `login.php`.

Sign back in as `user2@tek-up.de` (not enrolled in PCEP) and visit `stream.php?video_id=1` → expected: HTTP 403 (browser shows "This page isn't working" or a blank 403).

Pass `?video_id=999999` → expected: HTTP 404.

Pass `?video_id=abc` → expected: HTTP 400 with `{"error":"bad_request"}`.

- [ ] **Step 4: Commit**

```bash
git add stream.php
git commit -m "feat(stream): auth-gated video file endpoint with path confinement"
```

---

## Task 10: `stream.php` — add `Range:` support

**Files:**
- Modify: `stream.php` (replace the last 4 lines)

- [ ] **Step 1: Replace the trailing readfile block with Range handling**

In `stream.php`, find this block at the bottom of the file:

```php
header('Content-Type: video/mp4');
header('Content-Length: ' . filesize($real));
header('Accept-Ranges: bytes');
readfile($real);
exit;
```

Replace it with:

```php
$size  = filesize($real);
$start = 0;
$end   = $size - 1;
$mime  = 'video/mp4';

header('Accept-Ranges: bytes');
header('Content-Type: ' . $mime);

if (isset($_SERVER['HTTP_RANGE'])
    && preg_match('/bytes=(\d*)-(\d*)/', $_SERVER['HTTP_RANGE'], $m)) {
    if ($m[1] !== '') $start = (int) $m[1];
    if ($m[2] !== '') $end   = (int) $m[2];

    if ($start > $end || $start >= $size) {
        http_response_code(416);
        header("Content-Range: bytes */{$size}");
        exit;
    }
    if ($end >= $size) $end = $size - 1;

    http_response_code(206);
    header("Content-Range: bytes {$start}-{$end}/{$size}");
}

$length = $end - $start + 1;
header("Content-Length: {$length}");

$fp = fopen($real, 'rb');
if ($fp === false) {
    http_response_code(500);
    exit;
}
fseek($fp, $start);

$chunk_size = 8 * 1024; // 8 KB
$remaining  = $length;
while ($remaining > 0 && !feof($fp)) {
    $read = $remaining < $chunk_size ? $remaining : $chunk_size;
    echo fread($fp, $read);
    flush();
    $remaining -= $read;
}
fclose($fp);
exit;
```

- [ ] **Step 2: Smoke-test Range support with curl**

From a separate terminal:

```bash
curl -i -H "Range: bytes=0-1023" --cookie "PHPSESSID=<your session cookie>" "http://localhost/PHP-ing3/Project/stream.php?video_id=1" -o NUL
```

(Easier: open the video URL in Chrome, open DevTools → Network, click the video request, look for `Content-Range: bytes 0-X/<size>` and `206 Partial Content` in the response headers.)

Expected: response is `206 Partial Content` with `Content-Range: bytes 0-1023/<file size>` and the body is exactly 1024 bytes.

- [ ] **Step 3: Smoke-test seeking in the browser**

In an `<video>` test page (open the URL directly works), click somewhere in the timeline scrubber to seek. Expected: playback resumes at the new position with no full-file reload. (Without `Range:` support, Chrome would either refuse to seek or reload the whole file.)

- [ ] **Step 4: Commit**

```bash
git add stream.php
git commit -m "feat(stream): add Range: header parsing for video seek support"
```

---

## Task 11: `subtitle.php` — auth-gated SRT → WebVTT

**Files:**
- Create: `subtitle.php`

- [ ] **Step 1: Create the subtitle endpoint**

Create `subtitle.php` with this exact content:

```php
<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';

$user_id  = (int) ($_SESSION['user']['id'] ?? 0);
$video_id = isset($_GET['video_id']) ? (int) $_GET['video_id'] : 0;

if ($video_id <= 0) {
    http_response_code(400);
    exit;
}

$stmt = $pdo->prepare(
    "SELECT cv.subtitle_filename,
            cs.folder       AS section_folder,
            c.media_path    AS cert_media_path,
            c.id            AS cert_id
     FROM course_videos cv
     JOIN course_sections cs ON cs.id = cv.section_id
     JOIN certifications  c  ON c.id  = cs.certification_id
     WHERE cv.id = :vid LIMIT 1"
);
$stmt->execute([':vid' => $video_id]);
$row = $stmt->fetch();

if (!$row || !$row['subtitle_filename']) {
    http_response_code(404);
    exit;
}

$enroll = $pdo->prepare(
    'SELECT 1 FROM user_certifications WHERE user_id = :uid AND certification_id = :cid LIMIT 1'
);
$enroll->execute([':uid' => $user_id, ':cid' => (int) $row['cert_id']]);
if (!$enroll->fetchColumn()) {
    http_response_code(403);
    exit;
}

$relative = $row['cert_media_path'] . '/' . $row['section_folder'] . '/' . $row['subtitle_filename'];
$path     = MEDIA_ROOT . '/' . $relative;
$real     = realpath($path);
$root     = realpath(MEDIA_ROOT);

if ($real === false || $root === false || strncmp($real, $root, strlen($root)) !== 0) {
    http_response_code(403);
    exit;
}

if (!is_file($real)) {
    http_response_code(404);
    exit;
}

$srt = @file_get_contents($real);
if ($srt === false) {
    http_response_code(500);
    exit;
}

// SRT -> WebVTT: prepend "WEBVTT\n\n" and swap ',' to '.' in timestamps.
$vtt = "WEBVTT\n\n" . preg_replace(
    '/(\d{2}:\d{2}:\d{2}),(\d{3})/',
    '$1.$2',
    $srt
);

header('Content-Type: text/vtt; charset=utf-8');
header('Content-Length: ' . strlen($vtt));
echo $vtt;
```

- [ ] **Step 2: Smoke-test in the browser**

Find a `video_id` whose row has a non-NULL `subtitle_filename`:

```sql
SELECT id, title FROM course_videos WHERE subtitle_filename IS NOT NULL LIMIT 1;
```

Visit `http://localhost/PHP-ing3/Project/subtitle.php?video_id=<id>` while signed in.

Expected: a plain-text response starting with `WEBVTT` and showing the cue lines with timestamps using `.` (not `,`).

- [ ] **Step 3: Smoke-test the not-enrolled case**

Sign in as `user2`, visit the same URL → expected: HTTP 403.

- [ ] **Step 4: Commit**

```bash
git add subtitle.php
git commit -m "feat(subtitle): auth-gated SRT to WebVTT endpoint"
```

---

## Task 12: `progress.php` — record watched + recompute percentage

**Files:**
- Create: `progress.php`

- [ ] **Step 1: Create the progress endpoint**

Create `progress.php` with this exact content:

```php
<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'method_not_allowed']);
    exit;
}

$user_id  = (int) ($_SESSION['user']['id'] ?? 0);
$video_id = isset($_POST['video_id']) ? (int) $_POST['video_id'] : 0;

if ($video_id <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'bad_request']);
    exit;
}

// Lookup the video's parent section + certification.
$lookup = $pdo->prepare(
    "SELECT cs.id           AS section_id,
            c.id            AS cert_id
     FROM course_videos cv
     JOIN course_sections cs ON cs.id = cv.section_id
     JOIN certifications  c  ON c.id  = cs.certification_id
     WHERE cv.id = :vid LIMIT 1"
);
$lookup->execute([':vid' => $video_id]);
$ref = $lookup->fetch();

if (!$ref) {
    http_response_code(404);
    echo json_encode(['error' => 'video_not_found']);
    exit;
}

$cert_id    = (int) $ref['cert_id'];
$section_id = (int) $ref['section_id'];

// Enrolment check.
$enroll = $pdo->prepare(
    'SELECT 1 FROM user_certifications WHERE user_id = :uid AND certification_id = :cid LIMIT 1'
);
$enroll->execute([':uid' => $user_id, ':cid' => $cert_id]);
if (!$enroll->fetchColumn()) {
    http_response_code(403);
    echo json_encode(['error' => 'not_enrolled']);
    exit;
}

// Record the watched event (idempotent — keeps the original timestamp on duplicate).
$ins = $pdo->prepare(
    "INSERT INTO video_progress (user_id, video_id)
     VALUES (:uid, :vid)
     ON DUPLICATE KEY UPDATE watched_at = watched_at"
);
$ins->execute([':uid' => $user_id, ':vid' => $video_id]);

// Recompute the cert-level percentage and push it onto user_certifications.
$rollup = $pdo->prepare(
    "UPDATE user_certifications uc
     JOIN (
         SELECT cs.certification_id,
                ROUND(100 * SUM(vp.video_id IS NOT NULL) / NULLIF(COUNT(cv.id), 0)) AS pct
         FROM course_sections cs
         JOIN course_videos   cv ON cv.section_id = cs.id
         LEFT JOIN video_progress vp ON vp.video_id = cv.id AND vp.user_id = :uid
         WHERE cs.certification_id = :cid
     ) calc ON calc.certification_id = uc.certification_id
     SET uc.progress = COALESCE(calc.pct, 0)
     WHERE uc.user_id = :uid AND uc.certification_id = :cid"
);
$rollup->execute([':uid' => $user_id, ':cid' => $cert_id]);

// Per-section counts for the response (used to refresh the left rail).
$sec = $pdo->prepare(
    "SELECT COUNT(cv.id)                          AS total,
            SUM(vp.video_id IS NOT NULL)          AS watched
     FROM course_videos cv
     LEFT JOIN video_progress vp ON vp.video_id = cv.id AND vp.user_id = :uid
     WHERE cv.section_id = :sid"
);
$sec->execute([':uid' => $user_id, ':sid' => $section_id]);
$sec_row = $sec->fetch() ?: ['total' => 0, 'watched' => 0];

// Re-read the new percentage.
$pct = (int) $pdo->query(
    "SELECT progress FROM user_certifications
     WHERE user_id = {$user_id} AND certification_id = {$cert_id}"
)->fetchColumn();

echo json_encode([
    'ok'       => true,
    'progress' => $pct,
    'section'  => [
        'id'      => $section_id,
        'total'   => (int) $sec_row['total'],
        'watched' => (int) $sec_row['watched'],
    ],
]);
```

- [ ] **Step 2: Smoke-test with curl**

While signed in (cookie taken from your browser DevTools → Application → Cookies → `PHPSESSID`):

```bash
curl -X POST \
     --cookie "PHPSESSID=<your session cookie>" \
     --data "video_id=1" \
     "http://localhost/PHP-ing3/Project/progress.php"
```

Expected JSON response (numbers will vary):

```json
{"ok":true,"progress":1,"section":{"id":1,"total":15,"watched":1}}
```

Re-run the same command. Expected: same JSON; no errors; no duplicate row in `video_progress`.

- [ ] **Step 3: Smoke-test in phpMyAdmin**

After the POST:

```sql
SELECT progress FROM user_certifications WHERE user_id = 1 AND certification_id = (SELECT id FROM certifications WHERE code='PCEP');
```

Expected: the value matches the JSON `progress` field.

```sql
SELECT * FROM video_progress WHERE user_id = 1 AND video_id = 1;
```

Expected: one row with a recent `watched_at`.

- [ ] **Step 4: Commit**

```bash
git add progress.php
git commit -m "feat(progress): record watched video and recompute user_certifications.progress"
```

---

## Task 13: `course.php` — server-side query + page shell

**Files:**
- Create: `course.php`

- [ ] **Step 1: Create the course page with auth, query, and an empty shell**

Create `course.php` with this exact content (player UI comes in the next tasks):

```php
<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';

$user_id = (int) ($_SESSION['user']['id'] ?? 0);
$cert_id = isset($_GET['cert']) ? (int) $_GET['cert'] : 0;

if ($cert_id <= 0) {
    header('Location: certifications.php');
    exit;
}

// Must be enrolled.
$enroll = $pdo->prepare(
    'SELECT progress FROM user_certifications WHERE user_id = :uid AND certification_id = :cid LIMIT 1'
);
$enroll->execute([':uid' => $user_id, ':cid' => $cert_id]);
$enroll_row = $enroll->fetch();

if (!$enroll_row) {
    header('Location: catalog.php');
    exit;
}

// Cert metadata.
$cert_stmt = $pdo->prepare('SELECT id, code, name, provider, media_path FROM certifications WHERE id = :id LIMIT 1');
$cert_stmt->execute([':id' => $cert_id]);
$cert = $cert_stmt->fetch();

if (!$cert) {
    header('Location: certifications.php');
    exit;
}

$has_content = !empty($cert['media_path']);

// Sections with per-section watched counts.
$sections = [];
if ($has_content) {
    $sec_stmt = $pdo->prepare(
        "SELECT cs.id, cs.title, cs.position,
                COUNT(cv.id)                        AS total,
                SUM(vp.video_id IS NOT NULL)        AS watched
         FROM course_sections cs
         LEFT JOIN course_videos   cv ON cv.section_id = cs.id
         LEFT JOIN video_progress  vp ON vp.video_id = cv.id AND vp.user_id = :uid
         WHERE cs.certification_id = :cid
         GROUP BY cs.id
         ORDER BY cs.position"
    );
    $sec_stmt->execute([':uid' => $user_id, ':cid' => $cert_id]);
    $sections = $sec_stmt->fetchAll();
}

// Videos grouped by section.
$videos_by_section = [];
if ($has_content) {
    $vid_stmt = $pdo->prepare(
        "SELECT cv.id, cv.section_id, cv.position, cv.title, cv.duration_seconds,
                vp.video_id IS NOT NULL AS watched
         FROM course_videos cv
         JOIN course_sections cs ON cs.id = cv.section_id
         LEFT JOIN video_progress vp ON vp.video_id = cv.id AND vp.user_id = :uid
         WHERE cs.certification_id = :cid
         ORDER BY cs.position, cv.position"
    );
    $vid_stmt->execute([':uid' => $user_id, ':cid' => $cert_id]);
    foreach ($vid_stmt->fetchAll() as $v) {
        $videos_by_section[(int) $v['section_id']][] = $v;
    }
}

// Decide which video to load initially: first unwatched, falling back to the last video.
$initial_video = null;
foreach ($videos_by_section as $vs) {
    foreach ($vs as $v) {
        if (!$v['watched']) { $initial_video = $v; break 2; }
    }
}
if ($initial_video === null) {
    foreach ($videos_by_section as $vs) {
        foreach ($vs as $v) { $initial_video = $v; }
    }
}

$page_title = htmlspecialchars($cert['name']) . ' — TEK-UP Certified Students';
$active     = 'certifications';

require_once __DIR__ . '/includes/head.php';
require_once __DIR__ . '/includes/nav-portal.php';
?>

  <div class="course-page section">
    <div class="container">

      <div class="row">
        <div class="col-lg-12">
          <a href="certifications.php" class="back-link"><i class="fa fa-arrow-left"></i> Back to my certifications</a>
          <div class="course-head">
            <h6><?= htmlspecialchars($cert['provider']) ?> &middot; <?= htmlspecialchars($cert['code']) ?></h6>
            <h2><?= htmlspecialchars($cert['name']) ?></h2>
            <div class="course-progress">
              <div class="bar"><span id="courseBar" style="width: <?= (int) $enroll_row['progress'] ?>%;"></span></div>
              <span class="progress-label"><span id="courseProgressLabel"><?= (int) $enroll_row['progress'] ?></span>% complete</span>
            </div>
          </div>
        </div>
      </div>

      <?php if (!$has_content): ?>
        <div class="row">
          <div class="col-lg-12">
            <div class="course-empty">
              <h3>Content not yet uploaded</h3>
              <p>The course material for this certification will be available soon. Check back later.</p>
              <a href="certifications.php" class="main-button">Back</a>
            </div>
          </div>
        </div>
      <?php else: ?>
        <p>Player UI lands in the next tasks. <?= count($sections) ?> sections loaded.</p>
      <?php endif; ?>

    </div>
  </div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
```

- [ ] **Step 2: Smoke-test in the browser**

Visit `http://localhost/PHP-ing3/Project/course.php?cert=<PCEP id>` while signed in as user1 (enrolled).

Expected: page renders with provider + cert name, a gold progress bar at the user's current percentage, and the line "Player UI lands in the next tasks. 5 sections loaded."

- [ ] **Step 3: Smoke-test guards**

While signed out → expected: redirect to `login.php`.

Sign in as user2 (not enrolled in PCEP) → expected: redirect to `catalog.php`.

Pass `?cert=999999` → expected: redirect to `certifications.php`.

Pass no `cert` query → expected: redirect to `certifications.php`.

- [ ] **Step 4: Commit**

```bash
git add course.php
git commit -m "feat(course): page shell with auth, enrolment, and metadata query"
```

---

## Task 14: `course.php` — left rail (section accordion + video list)

**Files:**
- Modify: `course.php` (replace the `<p>Player UI lands in the next tasks. …</p>` line)

- [ ] **Step 1: Replace the placeholder with the two-column layout (left rail only for now)**

Find this line in `course.php`:

```php
        <p>Player UI lands in the next tasks. <?= count($sections) ?> sections loaded.</p>
```

Replace it with:

```php
        <div class="row course-layout">
          <div class="col-lg-4 course-rail">
            <?php foreach ($sections as $sec):
                $sec_id      = (int) $sec['id'];
                $sec_watched = (int) $sec['watched'];
                $sec_total   = (int) $sec['total'];
            ?>
              <details class="course-section" <?= ($initial_video && (int) $initial_video['section_id'] === $sec_id) ? 'open' : '' ?>>
                <summary>
                  <span class="sec-title"><?= htmlspecialchars($sec['position'] . '. ' . $sec['title']) ?></span>
                  <span class="sec-count" data-section-id="<?= $sec_id ?>"><?= $sec_watched ?>/<?= $sec_total ?></span>
                </summary>
                <ul class="course-video-list">
                  <?php foreach ($videos_by_section[$sec_id] ?? [] as $v):
                      $vid     = (int) $v['id'];
                      $watched = (int) $v['watched'] === 1;
                      $is_init = $initial_video && (int) $initial_video['id'] === $vid;
                  ?>
                    <li class="course-video-row<?= $is_init ? ' is-playing' : '' ?>"
                        data-video-id="<?= $vid ?>"
                        data-video-title="<?= htmlspecialchars($v['title']) ?>"
                        data-section-id="<?= $sec_id ?>">
                      <span class="status-mark"><?= $watched ? '✓' : ($is_init ? '▶' : '') ?></span>
                      <span class="video-title"><?= htmlspecialchars($v['title']) ?></span>
                    </li>
                  <?php endforeach; ?>
                </ul>
              </details>
            <?php endforeach; ?>
          </div>

          <div class="col-lg-8 course-stage">
            <p>Right pane lands in the next task.</p>
          </div>
        </div>
```

- [ ] **Step 2: Add minimal CSS for the left rail**

Append to `assets/css/templatemo-space-dynamic.css`:

```css

/* ---------------------------------------------
   Course player page
   --------------------------------------------- */

.course-page {
  background-color: #f2eedb;
  padding: 180px 0px 120px 0px;
  min-height: 100vh;
}

.course-page .back-link {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  font-size: 13px;
  font-weight: 600;
  color: #0b2342;
  letter-spacing: 0.5px;
  text-transform: uppercase;
  margin-bottom: 14px;
}

.course-page .back-link:hover { color: #c9a44a; }

.course-head h6 {
  text-transform: uppercase;
  letter-spacing: 3px;
  font-size: 13px;
  font-weight: 600;
  color: #c9a44a;
  margin-bottom: 12px;
}

.course-head h2 {
  font-size: 32px;
  font-weight: 700;
  color: #0b2342;
  margin-bottom: 18px;
}

.course-progress {
  display: flex;
  align-items: center;
  gap: 14px;
  max-width: 520px;
  margin-bottom: 36px;
}

.course-progress .bar {
  flex: 1;
  height: 8px;
  background-color: rgba(11, 35, 66, 0.1);
  border-radius: 4px;
  overflow: hidden;
}

.course-progress .bar span {
  display: block;
  height: 100%;
  background-color: #c9a44a;
  border-radius: 4px;
  transition: width 0.4s ease;
}

.course-progress .progress-label {
  font-size: 13px;
  font-weight: 600;
  color: #0b2342;
}

.course-empty {
  background-color: #fff;
  border-radius: 16px;
  padding: 50px;
  text-align: center;
  border-top: 3px solid #c9a44a;
}

.course-rail {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.course-section {
  background-color: #fff;
  border-radius: 12px;
  border-top: 3px solid #c9a44a;
  padding: 0;
  overflow: hidden;
}

.course-section > summary {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 14px 18px;
  font-weight: 700;
  font-size: 14px;
  color: #0b2342;
  cursor: pointer;
  list-style: none;
}

.course-section > summary::-webkit-details-marker { display: none; }

.course-section .sec-count {
  font-size: 11px;
  letter-spacing: 1px;
  color: #6b7280;
  background-color: #f2eedb;
  padding: 3px 8px;
  border-radius: 999px;
}

.course-video-list {
  list-style: none;
  margin: 0;
  padding: 0 0 8px 0;
}

.course-video-row {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 8px 18px;
  font-size: 13px;
  color: #2a2a2a;
  cursor: pointer;
  transition: background-color 0.15s ease;
}

.course-video-row:hover { background-color: #f7f3e3; }

.course-video-row.is-playing {
  background-color: #f2eedb;
  font-weight: 600;
  color: #0b2342;
}

.course-video-row .status-mark {
  width: 18px;
  text-align: center;
  font-weight: 700;
  color: #c9a44a;
}

.course-stage {
  background-color: #fff;
  border-radius: 16px;
  padding: 24px;
  border-top: 3px solid #c9a44a;
  min-height: 320px;
}

@media (max-width: 992px) {
  .course-page { padding: 140px 0 80px 0; }
  .course-rail { margin-bottom: 24px; }
}
```

- [ ] **Step 2: Smoke-test the rail**

Reload `course.php?cert=<PCEP id>`. Expected:

- A column on the left shows 5 collapsible section cards.
- The section containing the initial video is open by default; others are collapsed.
- Each collapsed section shows `N/M` watched count.
- Inside the open section, the initial video is highlighted with a `▶` mark.
- Clicking other section summaries expands/collapses them (native `<details>` behavior).

- [ ] **Step 3: Commit**

```bash
git add course.php assets/css/templatemo-space-dynamic.css
git commit -m "feat(course): left rail with section accordion and video list"
```

---

## Task 15: `course.php` — right pane HTML5 player

**Files:**
- Modify: `course.php` (replace the right-pane placeholder)

- [ ] **Step 1: Replace the right-pane placeholder with the player**

Find this block in `course.php`:

```php
          <div class="col-lg-8 course-stage">
            <p>Right pane lands in the next task.</p>
          </div>
```

Replace it with:

```php
          <div class="col-lg-8 course-stage">
            <?php if ($initial_video): ?>
              <video id="coursePlayer"
                     controls
                     preload="metadata"
                     data-video-id="<?= (int) $initial_video['id'] ?>"
                     crossorigin="anonymous">
                <source src="stream.php?video_id=<?= (int) $initial_video['id'] ?>" type="video/mp4">
                <track id="courseSubtitle"
                       kind="subtitles"
                       src="subtitle.php?video_id=<?= (int) $initial_video['id'] ?>"
                       srclang="en"
                       label="English"
                       default>
                Your browser does not support HTML5 video.
              </video>
              <div class="stage-meta">
                <span class="now-playing-label">Now playing</span>
                <h4 id="nowPlayingTitle"><?= htmlspecialchars($initial_video['title']) ?></h4>
              </div>
            <?php else: ?>
              <p>No videos in this course yet.</p>
            <?php endif; ?>
          </div>
```

- [ ] **Step 2: Add CSS for the player + meta**

Append to `assets/css/templatemo-space-dynamic.css`:

```css

.course-stage video {
  width: 100%;
  height: auto;
  max-height: 520px;
  border-radius: 12px;
  background: #000;
}

.course-stage .stage-meta {
  margin-top: 14px;
}

.now-playing-label {
  display: inline-block;
  font-size: 11px;
  letter-spacing: 2px;
  text-transform: uppercase;
  color: #c9a44a;
  margin-bottom: 4px;
}

#nowPlayingTitle {
  font-size: 20px;
  font-weight: 700;
  color: #0b2342;
  margin: 0;
}
```

- [ ] **Step 3: Smoke-test playback**

Reload `course.php?cert=<PCEP id>`. Expected:

- A `<video>` player loads on the right, showing the first unwatched video's poster frame.
- Click ▶ — playback starts.
- Seek by clicking on the scrubber — playback jumps without reloading the whole file (because `stream.php` supports `Range:` from Task 10).
- Click CC — English subtitles toggle on, showing cues from `subtitle.php`.

- [ ] **Step 4: Commit**

```bash
git add course.php assets/css/templatemo-space-dynamic.css
git commit -m "feat(course): right-pane HTML5 video player with subtitles"
```

---

## Task 16: `course.php` — JS: auto-mark watched at 90% + live UI update

**Files:**
- Modify: `course.php` (append a `<script>` block before the footer include)

- [ ] **Step 1: Add the auto-mark + UI-update JS**

In `course.php`, find this line near the bottom:

```php
<?php require_once __DIR__ . '/includes/footer.php'; ?>
```

Insert this block immediately **before** it:

```php
<?php if ($has_content && $initial_video): ?>
<script>
(function () {
    const video       = document.getElementById('coursePlayer');
    const titleEl     = document.getElementById('nowPlayingTitle');
    const barEl       = document.getElementById('courseBar');
    const labelEl     = document.getElementById('courseProgressLabel');
    const subtitleTr  = document.getElementById('courseSubtitle');

    if (!video) return;

    // Per-load flag so we only POST progress.php once per video, even if the user
    // re-seeks past the 90% mark.
    let markedForId = null;

    function currentRow() {
        const id = video.dataset.videoId;
        return document.querySelector('.course-video-row[data-video-id="' + id + '"]');
    }

    function markWatched() {
        const id = video.dataset.videoId;
        if (markedForId === id) return;
        markedForId = id;

        const fd = new FormData();
        fd.append('video_id', id);

        fetch('progress.php', { method: 'POST', body: fd, credentials: 'same-origin' })
            .then(r => r.json())
            .then(json => {
                if (!json.ok) return;
                // Bar.
                barEl.style.width = json.progress + '%';
                labelEl.textContent = json.progress;
                // Row + section count.
                const row = currentRow();
                if (row) {
                    row.querySelector('.status-mark').textContent = '✓';
                }
                const secCount = document.querySelector('.sec-count[data-section-id="' + json.section.id + '"]');
                if (secCount) {
                    secCount.textContent = json.section.watched + '/' + json.section.total;
                }
            })
            .catch(() => { markedForId = null; });
    }

    video.addEventListener('timeupdate', function () {
        if (!video.duration || isNaN(video.duration)) return;
        if (video.currentTime / video.duration >= 0.9) {
            markWatched();
        }
    });
})();
</script>
<?php endif; ?>
```

- [ ] **Step 2: Smoke-test the mark-at-90% behavior**

Open `course.php?cert=<PCEP id>` while signed in as user1. Take note of the percentage shown at the top.

Play the first video. Either watch it to ~90% or click in the scrubber to seek past 90% and let it play for a moment. Expected:

1. The percentage in the page header increases (e.g., 0 → 1% if there are ~80 videos).
2. The bar visually grows.
3. The current row in the left rail flips from `▶` to `✓`.
4. The section counter ("1/15" → "2/15") updates.

Verify in phpMyAdmin:

```sql
SELECT * FROM video_progress WHERE user_id = 1;
SELECT progress FROM user_certifications WHERE user_id = 1 AND certification_id = (SELECT id FROM certifications WHERE code='PCEP');
```

Expected: one new row in `video_progress`; the `progress` value matches what the UI shows.

- [ ] **Step 3: Smoke-test idempotency**

While still on the same video, drag the scrubber back to the start and forward past 90% again. Expected: no second POST (check DevTools → Network — only one `progress.php` call total per video load).

- [ ] **Step 4: Commit**

```bash
git add course.php
git commit -m "feat(course): JS auto-mark watched at 90% and live UI sync"
```

---

## Task 17: `course.php` — JS: click-to-load + auto-advance

**Files:**
- Modify: `course.php` (extend the existing `<script>` block)

- [ ] **Step 1: Extend the script with click + ended handlers**

In `course.php`, find the closing `})();` line of the existing script. Just **before** that line, insert:

```javascript

    // Click-to-load: change the player source without a full reload.
    function loadVideo(row) {
        const id    = row.dataset.videoId;
        const title = row.dataset.videoTitle;
        const src   = 'stream.php?video_id=' + encodeURIComponent(id);
        const sub   = 'subtitle.php?video_id=' + encodeURIComponent(id);

        // Reset state.
        markedForId = null;

        // Swap sources.
        video.pause();
        const srcEl = video.querySelector('source');
        if (srcEl) srcEl.src = src;
        if (subtitleTr) subtitleTr.src = sub;
        video.dataset.videoId = id;
        video.load();

        // Update right-pane title.
        if (titleEl) titleEl.textContent = title;

        // Update left-rail playing indicator.
        document.querySelectorAll('.course-video-row.is-playing').forEach(function (el) {
            el.classList.remove('is-playing');
            const mark = el.querySelector('.status-mark');
            if (mark && mark.textContent === '▶') mark.textContent = '';
        });
        row.classList.add('is-playing');
        const mark = row.querySelector('.status-mark');
        if (mark && mark.textContent === '') mark.textContent = '▶';

        // Auto-open the parent <details> if it's collapsed.
        const details = row.closest('details');
        if (details && !details.open) details.open = true;

        video.play().catch(function () { /* user gesture not granted; that's fine */ });
    }

    document.querySelectorAll('.course-video-row').forEach(function (row) {
        row.addEventListener('click', function () { loadVideo(row); });
    });

    // Auto-advance: when one video ends, jump to the next one in the rail.
    video.addEventListener('ended', function () {
        const rows = Array.from(document.querySelectorAll('.course-video-row'));
        const currentId = video.dataset.videoId;
        const idx = rows.findIndex(function (r) { return r.dataset.videoId === currentId; });
        if (idx >= 0 && idx + 1 < rows.length) {
            loadVideo(rows[idx + 1]);
        }
    });
```

- [ ] **Step 2: Smoke-test click-to-load**

Reload `course.php?cert=<PCEP id>`. Click any video row in the left rail (whether watched or not). Expected:

- The player swaps to the new video without a full page reload.
- The right-pane title updates.
- The `▶` indicator moves to the clicked row.
- Subtitles for the new video appear when CC is on.
- Seeking in the new video still works.

- [ ] **Step 3: Smoke-test auto-advance**

Play a video to its end (or seek to within a few seconds of the end and let it run out). Expected: when the video ends, the next row in the rail is auto-loaded and starts playing.

If the *last* row finishes, expected: playback just stops (nothing breaks).

- [ ] **Step 4: Commit**

```bash
git add course.php
git commit -m "feat(course): click-to-load and auto-advance between videos"
```

---

## Task 18: Wire `certifications.php` Continue → `course.php?cert=<id>`

**Files:**
- Modify: `certifications.php`

- [ ] **Step 1: Replace the Continue link**

Open `certifications.php`. Find this line:

```php
                  <a href="#" class="cert-action">Continue &rarr;</a>
```

(There is exactly one occurrence — inside the `<?php else: ?>` branch of the cert-meta render, which handles in-progress cards.)

Replace it with:

```php
                  <a href="course.php?cert=<?= (int) $c['id'] ?>" class="cert-action">Continue &rarr;</a>
```

- [ ] **Step 2: Smoke-test the round trip**

1. Sign in as `user1`, open `certifications.php`. Confirm at least one in-progress PCEP card is visible.
2. Click **Continue →** on that card.
3. Expected: lands on `course.php?cert=<id>` with the player loaded.
4. Click "Back to my certifications" → returns to `certifications.php`.
5. The progress bar on the card now reflects whatever percentage was earned.

- [ ] **Step 3: Commit**

```bash
git add certifications.php
git commit -m "feat(certifications): wire Continue button to course.php"
```

---

## Task 19: End-to-end smoke tests (full spec §13 checklist)

This task has no code changes — only verification + a final commit of nothing if everything passes. If any test fails, **stop and open a new task** to fix it.

- [ ] **Step 1: Happy path with `user1`**

1. Sign in as `user1@tek-up.de` / `123456789`.
2. Open `catalog.php`, enroll in PCEP if not already enrolled (use the password modal).
3. Open `certifications.php`, click Continue → on PCEP.
4. Watch the first video to ≥ 90% of its runtime.
5. Confirm: top progress bar advanced, current row shows `✓`, section counter advanced.
6. Click the second video in the rail.
7. Confirm: player swaps, title updates, scrubber still works.
8. Let the second video play to the end.
9. Confirm: third video auto-loads and starts.

- [ ] **Step 2: Per-user isolation with `user2`**

1. Sign out, sign in as `user2@tek-up.de` / `123456789`.
2. Open `catalog.php`, enroll in PCEP.
3. Open `certifications.php` → Continue → on PCEP.
4. Confirm: progress bar shows **0%**, every video row is unmarked (no `✓` from user1's session).

- [ ] **Step 3: Cross-user authorization on `stream.php`**

1. While still signed in as `user2`, grab a video id from PCEP (any will do — `user2` is now enrolled). It should work.
2. Sign out, sign in as a fresh new user via direct DB insert (or use `user2` and remove their enrolment row, then re-test):

```sql
DELETE FROM user_certifications WHERE user_id = 2 AND certification_id = (SELECT id FROM certifications WHERE code='PCEP');
```

3. Reload the open `course.php` tab. Expected: redirect to `catalog.php` (not enrolled).
4. Open the network tab of the player and try the previous `stream.php?video_id=<id>` URL. Expected: HTTP 403.
5. Restore `user2` access:

```sql
INSERT INTO user_certifications (user_id, certification_id, status, progress)
  VALUES (2, (SELECT id FROM certifications WHERE code='PCEP'), 'in_progress', 0);
```

- [ ] **Step 4: Path-traversal sanity**

1. In phpMyAdmin, temporarily corrupt one video row to point outside MEDIA_ROOT:

```sql
UPDATE course_videos SET filename = '../../../sensitive.txt' WHERE id = (SELECT id FROM (SELECT id FROM course_videos LIMIT 1) tmp);
```

2. Hit the corresponding `stream.php?video_id=<id>` in the browser.
3. Expected: HTTP 403 (the `realpath()` + `strncmp` guard rejects).
4. Restore the row:

```sql
-- Get the slug back from disk and put it back, OR re-run the importer to recreate it cleanly:
DELETE FROM course_videos WHERE filename = '../../../sensitive.txt';
-- Then re-run: php import-course.php "C:/xampp/htdocs/PHP-ing3/pcep" PCEP
```

- [ ] **Step 5: Missing file sanity**

1. Rename one .mp4 on disk (e.g., add `.bak` to its name).
2. Reload the course page. Expected: the player tile that points at that file shows the broken-video placeholder; clicking it loads an empty player.

   (Optional polish, not in scope: marking the row with ⚠ requires a separate left-rail change — note it but don't fix in this task.)

3. Restore the filename.

- [ ] **Step 6: Importer idempotency**

```bash
php import-course.php "C:/xampp/htdocs/PHP-ing3/pcep" PCEP
```

Expected: every section reports "already in place", no new rows inserted, exit cleanly.

- [ ] **Step 7: Final commit (no changes if everything passes)**

If you fixed anything during these tests, commit the fixes. Otherwise no commit is needed for this task.

```bash
git status
# Expected: clean working tree.
```

---

## Self-review checklist (run after writing this plan, not during execution)

- [x] Every spec section has at least one task implementing it (config in T1–T2, schema in T3, importer in T4–T8, stream in T9–T10, subtitle in T11, progress in T12, course page in T13–T17, integration in T18, error handling exercised in T19).
- [x] No placeholders or "TBD" markers in any task.
- [x] Method signatures match across tasks (`progress.php` returns `{ok, progress, section: {id, total, watched}}` consistently in Task 12 and Task 16).
- [x] Type consistency: `course_videos.filename` is the basename only; full path always assembled via `MEDIA_ROOT . '/' . media_path . '/' . folder . '/' . filename` in both `stream.php` and `subtitle.php`.
- [x] All commits are individually meaningful; no "WIP" commits.
- [x] PCAP import is **not** in scope of this plan — only PCEP. The same importer works for PCAP, but actually running it is left for whenever the user drops a PCAP folder into `PHP-ing3/`.

---

## Out of scope reminders

(From spec §2 — do **not** add these in this iteration.)

- Final exam / quiz flow. Reaching 100% does not flip status to `earned`.
- Resume position. We track which videos are watched, not where in a video you stopped.
- Admin/instructor UI for managing content.
- Cloud storage backends (Firebase, R2, S3). Local files only.
- Analytics, view counts, leaderboards.
