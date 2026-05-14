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
        $title       = ucwords(str_replace('-', ' ', $m[2]));
    } else {
        echo "  skip (no leading number): {$name}\n";
        continue;
    }

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
            ':duration'   => null,
        ]);

        echo "    video: {$vfile_new}" . ($subtitle_to_store ? " (+ srt)" : "") . "\n";
    }
}

// Persist media_path on the certifications row so other pages can find the folder.
$mp_update = $pdo->prepare('UPDATE certifications SET media_path = :mp WHERE id = :id');
$mp_update->execute([':mp' => $media_path, ':id' => $cert_id]);

echo "Done. media_path='{$media_path}' set on certification {$cert_code}.\n";
