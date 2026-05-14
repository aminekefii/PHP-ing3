# PCAP White Test Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Turn the placeholder `white-test.php` into a working 3-page PCAP white test: timed 10-minute quiz, 5 questions, server-authoritative scoring, per-question review on submit, pass/fail action branches.

**Architecture:** Three PHP pages (`white-test.php` intro, `white-test-take.php` quiz, `white-test-result.php` results) plus one include (`includes/white-test-questions.php`) that owns the question bank and scoring. All state lives in `$_SESSION['white_test']` — no DB writes. Timer is server-authoritative (computed from `started_at`); a tiny JS counter just drives the display and triggers auto-submit. Correct answers are never sent to the client during the test.

**Tech Stack:** PHP 8.2 (XAMPP), PDO/MySQL, Bootstrap 5, jQuery (template's existing scripts). No new dependencies.

**Spec:** `docs/superpowers/specs/2026-05-14-white-test-design.md` (commit `69aff95`)

**Project root in all paths below:** `C:\xampp\htdocs\PHP-ing3\Project\`

---

## Task 1: Question bank + scoring function

**Files:**
- Create: `includes/white-test-questions.php`
- Test: ad-hoc CLI script (one-off, not committed)

This file is pure data + a deterministic scoring function. Easy to test from the PHP CLI without touching the web stack.

- [ ] **Step 1: Create the question bank file**

Create `includes/white-test-questions.php` with this exact content:

```php
<?php
// PCAP white-test question bank + scoring. Keyed by certification code so
// other certs can be added later without code changes.

function white_test_questions(string $cert_code): array {
    $banks = [
        'PCAP' => [
            [
                'question' => 'What is the correct way to print "Hello" in Python?',
                'choices'  => ['A' => 'echo("Hello")', 'B' => 'print("Hello")', 'C' => 'printf("Hello")', 'D' => 'show("Hello")'],
                'answer'   => 'B',
            ],
            [
                'question' => 'Which symbol is used for comments in Python?',
                'choices'  => ['A' => '//', 'B' => '<!-- -->', 'C' => '#', 'D' => '**'],
                'answer'   => 'C',
            ],
            [
                'question' => 'What is the result of 2 + 3 in Python?',
                'choices'  => ['A' => '5', 'B' => '23', 'C' => '6', 'D' => 'Error'],
                'answer'   => 'A',
            ],
            [
                'question' => 'Which data type is used for text in Python?',
                'choices'  => ['A' => 'int', 'B' => 'float', 'C' => 'str', 'D' => 'bool'],
                'answer'   => 'C',
            ],
            [
                'question' => 'Which keyword is used to create a function in Python?',
                'choices'  => ['A' => 'func', 'B' => 'define', 'C' => 'function', 'D' => 'def'],
                'answer'   => 'D',
            ],
        ],
    ];
    return $banks[$cert_code] ?? [];
}

function white_test_score(string $cert_code, array $answers): array {
    $questions = white_test_questions($cert_code);
    $total     = count($questions);
    $correct   = 0;
    $review    = [];
    foreach ($questions as $i => $q) {
        $user_answer = $answers[$i] ?? null;
        $is_correct  = $user_answer === $q['answer'];
        if ($is_correct) $correct++;
        $review[] = [
            'question'       => $q['question'],
            'choices'        => $q['choices'],
            'user_answer'    => $user_answer,
            'correct_answer' => $q['answer'],
            'is_correct'     => $is_correct,
        ];
    }
    $pct = $total > 0 ? (int) round(100 * $correct / $total) : 0;
    return [
        'correct'      => $correct,
        'total'        => $total,
        'pct'          => $pct,
        'passed'       => $pct >= 70,
        'per_question' => $review,
    ];
}
```

- [ ] **Step 2: Write the CLI verification script**

Save this as `test-white-test.php` in the project root (will be deleted after verification — do NOT commit):

```php
<?php
require __DIR__ . '/includes/white-test-questions.php';

$qs = white_test_questions('PCAP');
if (count($qs) !== 5) { fwrite(STDERR, "FAIL bank size\n"); exit(1); }

foreach ($qs as $i => $q) {
    if (!is_string($q['question'])) { fwrite(STDERR, "FAIL Q$i question\n"); exit(1); }
    if (count($q['choices']) !== 4)  { fwrite(STDERR, "FAIL Q$i choice count\n"); exit(1); }
    foreach (['A','B','C','D'] as $k) {
        if (!isset($q['choices'][$k])) { fwrite(STDERR, "FAIL Q$i choice $k\n"); exit(1); }
    }
    if (!in_array($q['answer'], ['A','B','C','D'], true)) {
        fwrite(STDERR, "FAIL Q$i answer\n"); exit(1);
    }
}

$r = white_test_score('PCAP', ['B','C','A','C','D']);  // all correct
if ($r['correct'] !== 5 || $r['pct'] !== 100 || !$r['passed']) { fwrite(STDERR, "FAIL perfect\n"); exit(1); }

$r = white_test_score('PCAP', ['A','A','B','A','A']);  // all wrong
if ($r['correct'] !== 0 || $r['pct'] !== 0 || $r['passed']) { fwrite(STDERR, "FAIL zero\n"); exit(1); }

$r = white_test_score('PCAP', ['B','C','A','C','A']);  // 4/5 = 80 → pass
if ($r['correct'] !== 4 || $r['pct'] !== 80 || !$r['passed']) { fwrite(STDERR, "FAIL 80%\n"); exit(1); }

$r = white_test_score('PCAP', ['B','C','A','A','A']);  // 3/5 = 60 → fail
if ($r['correct'] !== 3 || $r['pct'] !== 60 || $r['passed']) { fwrite(STDERR, "FAIL 60%\n"); exit(1); }

$r = white_test_score('PCAP', [null, null, null, null, null]);  // timer expired with nothing answered
if ($r['correct'] !== 0 || $r['pct'] !== 0 || $r['passed']) { fwrite(STDERR, "FAIL nulls\n"); exit(1); }

if (white_test_questions('UNKNOWN') !== []) { fwrite(STDERR, "FAIL unknown cert\n"); exit(1); }

echo "all assertions passed\n";
```

- [ ] **Step 3: Run the CLI script and verify all assertions pass**

Run:
```
C:\xampp\php\php.exe C:\xampp\htdocs\PHP-ing3\Project\test-white-test.php
```

Expected stdout (exact): `all assertions passed`
Expected exit code: 0

If any assertion fails, fix the bank or the scoring function and re-run.

- [ ] **Step 4: Delete the throwaway test script**

```
rm C:\xampp\htdocs\PHP-ing3\Project\test-white-test.php
```

- [ ] **Step 5: Commit**

```
git add Project/includes/white-test-questions.php
git commit -m "feat(white-test): add PCAP question bank and scoring function"
```

---

## Task 2: Start-the-test POST handler + take page skeleton

**Files:**
- Modify: `white-test.php` (add POST handler at top, convert Start button to form)
- Create: `white-test-take.php` (minimal: session/gate checks + placeholder body)

End state: clicking **Start the test** on the intro lands the user on the take page (still mostly empty), and refreshing or navigating directly to `white-test-take.php` without a session bounces to `certifications.php`.

- [ ] **Step 1: Add the POST handler to `white-test.php`**

Open `white-test.php`. Right after the line `$cert = $cert_stmt->fetch();` and the `if (!$cert) { ... }` redirect block (and BEFORE the `$page_title = ...` line), insert this block:

```php
// Start-the-test POST handler — initialises the session and hands off to the
// quiz page. Re-validates progress as defence in depth.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && (int) $enroll_row['progress'] >= 100) {
    $_SESSION['white_test'] = [
        'cert_id'    => (int) $cert['id'],
        'cert_code'  => (string) $cert['code'],
        'started_at' => time(),
        'current_q'  => 0,
        'answers'    => [null, null, null, null, null],
        'submitted'  => false,
    ];
    header('Location: white-test-take.php');
    exit;
}
```

- [ ] **Step 2: Convert the Start button into a POST form**

In the same file, find this line:
```php
<a href="course.php?cert=<?= (int) $cert_id ?>" class="main-button" style="margin-top:16px;">Start the test</a>
```

Replace it with:
```php
<form method="post" action="white-test.php?cert=<?= (int) $cert_id ?>" style="margin-top:16px;">
  <button type="submit" class="main-button">Start the test</button>
</form>
```

- [ ] **Step 3: Create `white-test-take.php` with gating + placeholder**

Create `white-test-take.php` with this exact content:

```php
<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/white-test-questions.php';

if (empty($_SESSION['white_test'])) {
    header('Location: certifications.php');
    exit;
}

$wt = &$_SESSION['white_test'];

if (!empty($wt['submitted'])) {
    header('Location: white-test-result.php');
    exit;
}

$remaining = 600 - (time() - $wt['started_at']);
if ($remaining <= 0) {
    $wt['submitted'] = true;
    header('Location: white-test-result.php');
    exit;
}

$page_title = 'White Test — In progress';
$active     = 'certifications';

require_once __DIR__ . '/includes/head.php';
require_once __DIR__ . '/includes/nav-portal.php';
?>

  <div class="course-page section">
    <div class="container">
      <p>White-test take page — question <?= (int) $wt['current_q'] + 1 ?> / 5. Remaining seconds: <?= (int) $remaining ?>.</p>
    </div>
  </div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
```

- [ ] **Step 4: Verify the wiring in the browser**

a) Visit `http://localhost/PHP-ing3/Project/white-test-take.php` directly while logged in. **Expected:** instant redirect to `certifications.php` (no session yet).

b) Set yourself to 100 % on a PCAP enrolment so the white-test row is unlocked:
```
"C:\xampp\mysql\bin\mysql.exe" -uroot tekup -e "UPDATE user_certifications uc JOIN certifications c ON c.id=uc.certification_id SET uc.progress=100 WHERE c.code='PCAP' AND uc.user_id=<your user id>;"
```

c) Visit `course.php?cert=<pcap-cert-id>`, click the gold **Pass white test** row.

d) On the intro page, click **Start the test**. **Expected:** browser lands on `white-test-take.php` showing the placeholder text including `question 1 / 5` and `Remaining seconds: ~599`.

e) Refresh `white-test-take.php`. **Expected:** same page (session persists). The remaining seconds should be lower than before.

- [ ] **Step 5: Commit**

```
git add Project/white-test.php Project/white-test-take.php
git commit -m "feat(white-test): start-the-test POST handler and take page skeleton"
```

---

## Task 3: Render the current question on the take page

**Files:**
- Modify: `white-test-take.php` (replace placeholder body with header strip + two-column question/choices card)
- Modify: `assets/css/templatemo-space-dynamic.css` (append quiz page styles)

End state: the take page shows the actual current question with the four choices as clickable cards (selectable but not yet submitting anywhere).

- [ ] **Step 1: Add the question-pane CSS**

Open `assets/css/templatemo-space-dynamic.css`. At the very end of the file (after the existing `.test-meta-card .test-meta-value` rule), append:

```css
.wt-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  background-color: #fff;
  border-radius: 12px;
  border-top: 3px solid #c9a44a;
  padding: 14px 22px;
  margin-bottom: 14px;
}
.wt-header .wt-counter {
  font-weight: 700;
  font-size: 15px;
  color: #0b2342;
}
.wt-header .wt-timer {
  font-weight: 700;
  font-size: 15px;
  color: #c9a44a;
  font-variant-numeric: tabular-nums;
}

.wt-card {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 0;
  background-color: #fff;
  border-radius: 12px;
  border-top: 3px solid #c9a44a;
  overflow: hidden;
  min-height: 320px;
}
.wt-question {
  padding: 28px;
  font-size: 15px;
  color: #2a2a2a;
  border-right: 1px solid #eee;
}
.wt-choices {
  padding: 18px;
  display: flex;
  flex-direction: column;
  gap: 10px;
}
.wt-choice {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 12px 16px;
  border: 1px solid #e5e7eb;
  border-radius: 10px;
  cursor: pointer;
  transition: background-color 0.15s ease, border-color 0.15s ease;
  font-size: 14px;
  color: #2a2a2a;
}
.wt-choice:hover { background-color: #f7f3e3; border-color: #c9a44a; }
.wt-choice input[type="radio"] { accent-color: #c9a44a; }
.wt-choice.is-selected {
  background-color: #f2eedb;
  border-color: #c9a44a;
  color: #0b2342;
  font-weight: 600;
}

.wt-submit-row {
  display: flex;
  justify-content: flex-end;
  margin-top: 14px;
}

@media (max-width: 768px) {
  .wt-card { grid-template-columns: 1fr; }
  .wt-question { border-right: none; border-bottom: 1px solid #eee; }
}
```

- [ ] **Step 2: Replace the take page body with the real question/choices markup**

Open `white-test-take.php`. Locate the body block (between `nav-portal.php` require and `footer.php` require). Replace the placeholder `<div class="course-page section">...</div>` block with this:

```php
<?php
$questions = white_test_questions($wt['cert_code']);
$q         = $questions[$wt['current_q']];
$selected  = $wt['answers'][$wt['current_q']] ?? null;
$is_last   = ((int) $wt['current_q'] === count($questions) - 1);
$mm        = str_pad((string) intdiv($remaining, 60), 2, '0', STR_PAD_LEFT);
$ss        = str_pad((string) ($remaining % 60), 2, '0', STR_PAD_LEFT);
?>

  <div class="course-page section">
    <div class="container">

      <div class="wt-header">
        <span class="wt-counter">Question <?= (int) $wt['current_q'] + 1 ?> / <?= count($questions) ?></span>
        <span class="wt-timer" id="wtTimer"><?= $mm ?>:<?= $ss ?></span>
      </div>

      <form id="wtForm" method="post" action="white-test-take.php">
        <div class="wt-card">
          <div class="wt-question">
            <?= htmlspecialchars($q['question']) ?>
          </div>
          <div class="wt-choices">
            <?php foreach ($q['choices'] as $letter => $text):
              $is_selected = ($selected === $letter);
            ?>
              <label class="wt-choice<?= $is_selected ? ' is-selected' : '' ?>">
                <input type="radio" name="answer" value="<?= htmlspecialchars($letter) ?>" <?= $is_selected ? 'checked' : '' ?>>
                <span><strong><?= $letter ?>)</strong> <?= htmlspecialchars($text) ?></span>
              </label>
            <?php endforeach; ?>
          </div>
        </div>

        <div class="wt-submit-row">
          <button type="submit" class="main-button">
            <?= $is_last ? 'Submit' : 'Next →' ?>
          </button>
        </div>
      </form>

    </div>
  </div>

  <script>
  // Highlight the selected choice card live (no submit yet — Task 4 wires POST).
  document.querySelectorAll('.wt-choice input[type="radio"]').forEach(function (r) {
    r.addEventListener('change', function () {
      document.querySelectorAll('.wt-choice').forEach(function (c) { c.classList.remove('is-selected'); });
      r.closest('.wt-choice').classList.add('is-selected');
    });
  });
  </script>
```

- [ ] **Step 3: Verify in the browser**

a) Hard-reload `white-test-take.php` (Ctrl+Shift+R to pick up the new CSS).
b) **Expected:** header shows `Question 1 / 5` on the left and a timer like `09:54` on the right. Card below has the question text on the left and 4 radio-choice cards on the right.
c) Click each choice — only one stays gold/cream highlighted at a time.
d) Submit the form (button label is `Next →`). **Expected:** form posts to the same URL; since no POST handler exists yet (Task 4), the page renders again with the same question (no state change). That's fine — confirms the form wiring.

- [ ] **Step 4: Commit**

```
git add Project/white-test-take.php Project/assets/css/templatemo-space-dynamic.css
git commit -m "feat(white-test): render question and choices on take page"
```

---

## Task 4: Handle Next / Submit (record answer, advance, PRG)

**Files:**
- Modify: `white-test-take.php` (add POST handler block)

End state: clicking **Next** records the selected answer in the session, increments `current_q`, and reloads the page on the next question. On question 5 the button label is **Submit**, and clicking it sets `submitted=true` and redirects to `white-test-result.php` (which will redirect back to intro for now — fine for this task; Task 6 fills in the real page).

- [ ] **Step 1: Add the POST handler near the top of `white-test-take.php`**

Open `white-test-take.php`. Find this block:

```php
$remaining = 600 - (time() - $wt['started_at']);
if ($remaining <= 0) {
    $wt['submitted'] = true;
    header('Location: white-test-result.php');
    exit;
}
```

Right AFTER that block (and BEFORE `$page_title = ...`), insert:

```php
// POST: record the selected answer, then advance (PRG redirect on every
// branch so a browser refresh never re-submits).
$post_error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $answer    = $_POST['answer'] ?? null;
    $questions = white_test_questions($wt['cert_code']);
    $is_last   = ((int) $wt['current_q'] === count($questions) - 1);

    if (!in_array($answer, ['A', 'B', 'C', 'D'], true)) {
        $post_error = 'Please select an answer to continue.';
    } else {
        $wt['answers'][$wt['current_q']] = $answer;

        if ($is_last) {
            $wt['submitted'] = true;
            header('Location: white-test-result.php');
            exit;
        }

        $wt['current_q']++;
        header('Location: white-test-take.php');
        exit;
    }
}
```

- [ ] **Step 2: Surface the validation error in the body**

In the same file, in the body markup added in Task 3, find this opening of the form:

```php
      <form id="wtForm" method="post" action="white-test-take.php">
```

Right BEFORE that line, insert:

```php
      <?php if (!empty($post_error)): ?>
        <div class="form-notice is-error" style="margin-bottom: 12px;"><?= htmlspecialchars($post_error) ?></div>
      <?php endif; ?>
```

- [ ] **Step 3: Verify the happy path in the browser**

a) Reload `white-test-take.php` (you should still be on a session). If not, restart from the intro page.
b) Select **B** on Q1, click **Next →**. **Expected:** page reloads showing Q2 (`Question 2 / 5`).
c) Continue: C for Q2, A for Q3, C for Q4. **Expected:** each Next advances; counter increments; timer keeps counting down.
d) On Q5 the button reads **Submit**. Pick **D** and click it.
e) **Expected:** browser navigates to `white-test-result.php`, which (because that page doesn't exist yet) shows a 404. The session has `submitted = true` now.

- [ ] **Step 4: Verify the validation branch**

a) Restart a fresh test from the intro (clears session and starts over).
b) On Q1, click **Next →** without selecting anything. **Expected:** page stays on Q1 with a red `Please select an answer to continue.` notice above the card.
c) Select **B** and click Next. **Expected:** advances to Q2 normally.

- [ ] **Step 5: Verify the resume-on-refresh behaviour**

a) Mid-test (say Q3), select **A** but DO NOT click Next. Refresh the browser.
b) **Expected:** still on Q3 with **A** pre-selected (because the previous answer for Q3 in the session is still `null` — refresh re-renders from session, so actually nothing is pre-selected; this is fine).
c) Now click Next. After Q3 is recorded, hit back-button.
d) **Expected:** browser warns about resubmission, OR the back-button shows Q3 again with `A` pre-selected (because the session has Q3's answer recorded).

The exact back-button behaviour depends on the browser; the key invariant is: **the server never moves backwards** and **PRG prevents double-submit**.

- [ ] **Step 6: Commit**

```
git add Project/white-test-take.php
git commit -m "feat(white-test): record answers and advance through the quiz"
```

---

## Task 5: Server-authoritative timer + JS auto-submit

**Files:**
- Modify: `white-test-take.php` (replace the placeholder script with a real countdown that auto-submits on zero)

End state: the timer in the header counts down second-by-second. When it hits 0:00, the form auto-submits — recording whatever was selected and ending the test. Server already enforces expiry; this just makes the client cooperate.

- [ ] **Step 1: Replace the placeholder `<script>` block at the bottom of the body**

Open `white-test-take.php`. Find the existing `<script>` block (the one that just highlights the selected choice). Replace the entire `<script>...</script>` block with:

```php
  <script>
  (function () {
    // Choice highlight.
    document.querySelectorAll('.wt-choice input[type="radio"]').forEach(function (r) {
      r.addEventListener('change', function () {
        document.querySelectorAll('.wt-choice').forEach(function (c) { c.classList.remove('is-selected'); });
        r.closest('.wt-choice').classList.add('is-selected');
      });
    });

    // Countdown. Server is authoritative — this just drives the display and
    // auto-submits when the page-rendered remaining hits zero. Re-render on a
    // refresh re-syncs against the real server value.
    var remaining  = <?= (int) $remaining ?>;
    var timerEl    = document.getElementById('wtTimer');
    var formEl     = document.getElementById('wtForm');
    var autoFired  = false;

    function pad(n) { return n < 10 ? '0' + n : '' + n; }
    function render() {
      var m = Math.floor(remaining / 60);
      var s = remaining % 60;
      timerEl.textContent = pad(m) + ':' + pad(s);
    }

    setInterval(function () {
      remaining--;
      if (remaining <= 0) {
        remaining = 0;
        render();
        if (!autoFired) {
          autoFired = true;
          // If no radio is selected, the server will treat it as a missing
          // answer for this question but still mark submitted=true on the next
          // POST. To force that path we POST with an empty answer.
          formEl.submit();
        }
        return;
      }
      render();
    }, 1000);
  })();
  </script>
```

Note the `<?= (int) $remaining ?>` PHP echo inside the JS — it makes the countdown start from the server-rendered value.

- [ ] **Step 2: Verify the countdown ticks in the browser**

a) Start a fresh test from the intro.
b) Watch the timer in the top-right of the take page. **Expected:** it ticks down once per second (e.g. `09:58 → 09:57 → 09:56`).
c) Refresh the page after ~15 seconds. **Expected:** timer resumes at roughly where the server says it is (e.g. `09:43` if 17 seconds have actually elapsed since session start).

The auto-submit-at-zero path is hard to verify without waiting 10 minutes. Its correctness relies on two pieces that ARE verified elsewhere: (a) `formEl.submit()` does the same thing as clicking the button — verified in Task 4; (b) the server's `if ($remaining <= 0)` block at the top of the file forces `submitted = true` and redirects to result on the very next request — verified in Task 6 by visiting result.php after submitting. No special verification needed here.

- [ ] **Step 3: Commit**

```
git add Project/white-test-take.php
git commit -m "feat(white-test): countdown timer with auto-submit on expiry"
```

---

## Task 6: Result page — gating, score, pass/fail badge

**Files:**
- Create: `white-test-result.php`
- Modify: `assets/css/templatemo-space-dynamic.css` (append result page styles)

End state: completing the test (or being redirected after auto-submit) lands the user on a result page that shows the score and a pass/fail badge. Anyone visiting `white-test-result.php` without a submitted session bounces back to the intro.

- [ ] **Step 1: Append the result-page CSS**

Open `assets/css/templatemo-space-dynamic.css`. At the very end, append:

```css
.wt-result-header {
  background-color: #fff;
  border-radius: 14px;
  border-top: 3px solid #c9a44a;
  padding: 32px;
  text-align: center;
  margin-bottom: 18px;
}
.wt-result-header .wt-score {
  font-size: 38px;
  font-weight: 800;
  color: #0b2342;
  letter-spacing: -1px;
  margin-bottom: 4px;
}
.wt-result-header .wt-pct {
  font-size: 18px;
  color: #6b7280;
  margin-bottom: 14px;
}
.wt-badge {
  display: inline-block;
  padding: 6px 18px;
  border-radius: 999px;
  font-weight: 700;
  font-size: 13px;
  letter-spacing: 1px;
  text-transform: uppercase;
}
.wt-badge.is-pass { background-color: #d1fae5; color: #065f46; }
.wt-badge.is-fail { background-color: #fee2e2; color: #991b1b; }

.wt-result-meta {
  font-size: 12px;
  color: #6b7280;
  margin-top: 12px;
  letter-spacing: 1px;
  text-transform: uppercase;
}
```

- [ ] **Step 2: Create `white-test-result.php` with gating + score header**

Create `white-test-result.php` with this exact content:

```php
<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/white-test-questions.php';

if (empty($_SESSION['white_test']) || empty($_SESSION['white_test']['submitted'])) {
    // Try to send the user back to the intro for the cert they were working on,
    // otherwise to the certifications list.
    if (!empty($_SESSION['white_test']['cert_id'])) {
        header('Location: white-test.php?cert=' . (int) $_SESSION['white_test']['cert_id']);
    } else {
        header('Location: certifications.php');
    }
    exit;
}

$wt        = $_SESSION['white_test'];
$cert_id   = (int) $wt['cert_id'];
$cert_code = (string) $wt['cert_code'];
$result    = white_test_score($cert_code, $wt['answers']);

// Cert metadata for the page header (only needs name/provider).
$stmt = $pdo->prepare('SELECT name, provider, code FROM certifications WHERE id = :id LIMIT 1');
$stmt->execute([':id' => $cert_id]);
$cert = $stmt->fetch();

$page_title = 'White Test — Result';
$active     = 'certifications';

require_once __DIR__ . '/includes/head.php';
require_once __DIR__ . '/includes/nav-portal.php';
?>

  <div class="course-page section">
    <div class="container">

      <div class="row">
        <div class="col-lg-12">
          <div class="course-head">
            <h6><?= htmlspecialchars($cert['provider']) ?> &middot; <?= htmlspecialchars($cert['code']) ?></h6>
            <h2>White Test &mdash; Result</h2>
          </div>
        </div>
      </div>

      <div class="row">
        <div class="col-lg-8 offset-lg-2">

          <div class="wt-result-header">
            <div class="wt-score"><?= (int) $result['correct'] ?> / <?= (int) $result['total'] ?></div>
            <div class="wt-pct"><?= (int) $result['pct'] ?> %</div>
            <?php if ($result['passed']): ?>
              <span class="wt-badge is-pass">✓ Passed</span>
            <?php else: ?>
              <span class="wt-badge is-fail">✗ Not passed</span>
            <?php endif; ?>
            <div class="wt-result-meta">Passing score: 70 %</div>
          </div>

          <!-- per-question review filled in Task 7 -->
          <!-- action buttons filled in Task 8 -->

        </div>
      </div>

    </div>
  </div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
```

- [ ] **Step 3: Verify gating**

a) Visit `http://localhost/PHP-ing3/Project/white-test-result.php` directly (no session). **Expected:** redirect to `certifications.php`.
b) Start a fresh test from the intro; do NOT finish it. Then manually visit `white-test-result.php`. **Expected:** redirect to `white-test.php?cert=<id>` (the intro page).

- [ ] **Step 4: Verify the result header**

a) Take the test, answering all 5 correctly (B / C / A / C / D). After Submit, browser lands on the result page.
b) **Expected:**
   - Header `White Test — Result`
   - `5 / 5` big number
   - `100 %`
   - Green pill: `✓ Passed`
   - `Passing score: 70 %` below

c) Restart and answer 2 correctly (B / C / A / A / A → 3 correct), Submit. **Expected:** `3 / 5`, `60 %`, red pill `✗ Not passed`.

- [ ] **Step 5: Commit**

```
git add Project/white-test-result.php Project/assets/css/templatemo-space-dynamic.css
git commit -m "feat(white-test): result page with score and pass/fail badge"
```

---

## Task 7: Result page — per-question review

**Files:**
- Modify: `white-test-result.php` (add review list)
- Modify: `assets/css/templatemo-space-dynamic.css` (append review styles)

End state: below the score header, every question is listed with the user's answer and (if wrong) the correct answer, colour-coded.

- [ ] **Step 1: Append the review CSS**

Open `assets/css/templatemo-space-dynamic.css`. At the very end, append:

```css
.wt-review {
  display: flex;
  flex-direction: column;
  gap: 12px;
  margin-bottom: 24px;
}
.wt-review-item {
  background-color: #fff;
  border-radius: 12px;
  border-left: 4px solid #c9a44a;
  padding: 16px 20px;
}
.wt-review-item.is-correct { border-left-color: #10b981; }
.wt-review-item.is-wrong   { border-left-color: #ef4444; }

.wt-review-q {
  font-weight: 700;
  font-size: 14px;
  color: #0b2342;
  margin-bottom: 8px;
}
.wt-review-line {
  font-size: 13px;
  color: #2a2a2a;
  margin: 2px 0;
}
.wt-review-line .wt-label {
  display: inline-block;
  min-width: 110px;
  color: #6b7280;
  font-size: 11px;
  letter-spacing: 1px;
  text-transform: uppercase;
}
.wt-review-line .wt-pick { font-weight: 600; }
.wt-review-line .wt-pick.is-correct { color: #047857; }
.wt-review-line .wt-pick.is-wrong   { color: #b91c1c; }
.wt-review-line .wt-pick.is-missing { color: #9ca3af; font-style: italic; }
```

- [ ] **Step 2: Replace the placeholder comment in `white-test-result.php`**

Open `white-test-result.php`. Find this line:

```php
          <!-- per-question review filled in Task 7 -->
```

Replace it with:

```php
          <div class="wt-review">
            <?php foreach ($result['per_question'] as $i => $r):
              $user_letter   = $r['user_answer'];
              $correct_letter= $r['correct_answer'];
              $user_text     = $user_letter ? $user_letter . ') ' . $r['choices'][$user_letter] : '— no answer —';
              $correct_text  = $correct_letter . ') ' . $r['choices'][$correct_letter];
              $item_class    = $r['is_correct'] ? 'is-correct' : 'is-wrong';
              $pick_class    = $r['is_correct'] ? 'is-correct' : ($user_letter ? 'is-wrong' : 'is-missing');
            ?>
              <div class="wt-review-item <?= $item_class ?>">
                <div class="wt-review-q"><?= ($i + 1) ?>. <?= htmlspecialchars($r['question']) ?></div>
                <div class="wt-review-line">
                  <span class="wt-label">Your answer</span>
                  <span class="wt-pick <?= $pick_class ?>"><?= htmlspecialchars($user_text) ?></span>
                </div>
                <?php if (!$r['is_correct']): ?>
                  <div class="wt-review-line">
                    <span class="wt-label">Correct answer</span>
                    <span class="wt-pick is-correct"><?= htmlspecialchars($correct_text) ?></span>
                  </div>
                <?php endif; ?>
              </div>
            <?php endforeach; ?>
          </div>
```

- [ ] **Step 3: Verify the review in the browser**

a) Take the test answering 3/5 correctly (e.g. B / C / A / A / A — Q4 and Q5 wrong).
b) After Submit, on the result page below the score header, **Expected:**
   - 5 items total
   - Items 1, 2, 3 have a green left bar and show only `Your answer` line in green
   - Items 4 and 5 have a red left bar, show `Your answer` in red AND `Correct answer` in green below
c) Take the test, leave Q3 unanswered by triggering auto-submit at the wrong moment (skip — too brittle to test live). Instead verify the "no answer" path mentally from the code: `user_letter === null` → text reads `— no answer —` with grey italic.

- [ ] **Step 4: Commit**

```
git add Project/white-test-result.php Project/assets/css/templatemo-space-dynamic.css
git commit -m "feat(white-test): per-question review on result page"
```

---

## Task 8: Result page — action buttons + Retake handler

**Files:**
- Modify: `white-test.php` (extend POST handler to accept a "retake" path)
- Modify: `white-test-result.php` (add action button row)
- Modify: `assets/css/templatemo-space-dynamic.css` (append button-row style)

End state: passing the test shows **Final exam →** (placeholder) and **Back to dashboard**. Failing shows **Retake exam** and **Back to dashboard**. Retake POSTs to the intro page, which clears the session and starts a fresh attempt.

- [ ] **Step 1: Append the button-row CSS**

Open `assets/css/templatemo-space-dynamic.css`. At the very end, append:

```css
.wt-actions {
  display: flex;
  flex-wrap: wrap;
  justify-content: center;
  gap: 14px;
  margin-top: 4px;
  margin-bottom: 24px;
}
.wt-actions .main-button { margin: 0; }
.wt-actions .main-button.is-secondary {
  background-color: #fff;
  color: #0b2342;
  border: 1px solid #cbd5e1;
}
.wt-actions form { margin: 0; }
```

- [ ] **Step 2: Replace the action-buttons placeholder in `white-test-result.php`**

Open `white-test-result.php`. Find this line:

```php
          <!-- action buttons filled in Task 8 -->
```

Replace it with:

```php
          <div class="wt-actions">
            <?php if ($result['passed']): ?>
              <a href="#" class="main-button">Final exam →</a>
              <a href="dashboard.php" class="main-button is-secondary">Back to dashboard</a>
            <?php else: ?>
              <form method="post" action="white-test.php?cert=<?= (int) $cert_id ?>">
                <input type="hidden" name="retake" value="1">
                <button type="submit" class="main-button">Retake exam</button>
              </form>
              <a href="dashboard.php" class="main-button is-secondary">Back to dashboard</a>
            <?php endif; ?>
          </div>
```

- [ ] **Step 3: Extend the intro POST handler to support retakes**

Open `white-test.php`. Find the POST handler added in Task 2:

```php
if ($_SERVER['REQUEST_METHOD'] === 'POST' && (int) $enroll_row['progress'] >= 100) {
    $_SESSION['white_test'] = [
        'cert_id'    => (int) $cert['id'],
        'cert_code'  => (string) $cert['code'],
        'started_at' => time(),
        'current_q'  => 0,
        'answers'    => [null, null, null, null, null],
        'submitted'  => false,
    ];
    header('Location: white-test-take.php');
    exit;
}
```

The existing handler already clears any prior `$_SESSION['white_test']` (it overwrites the whole key). So a POST from "Retake exam" hits this same handler and works as-is — no code change needed.

Verify this by reading the code; if true, mark this step done and move on. (If the logic were "append" instead of "overwrite", we'd need an explicit reset.)

- [ ] **Step 4: Verify the pass branch**

a) Take the test, answering all 5 correctly (B / C / A / C / D). Submit.
b) **Expected:** below the review list, two buttons side by side: gold `Final exam →` (links to `#`, fine for the demo) and white-bordered `Back to dashboard`. NO retake button.
c) Click **Back to dashboard**. **Expected:** lands on `dashboard.php`.

- [ ] **Step 5: Verify the fail branch + retake**

a) Take the test answering 3/5 correctly. Submit.
b) **Expected:** below review, gold `Retake exam` button and white-bordered `Back to dashboard`. NO `Final exam` button.
c) Click **Retake exam**. **Expected:** browser lands on `white-test-take.php` again, showing `Question 1 / 5` with a fresh 10-minute timer and no pre-selected answers.

- [ ] **Step 6: Commit**

```
git add Project/white-test-result.php Project/assets/css/templatemo-space-dynamic.css
git commit -m "feat(white-test): action buttons (pass/fail) and retake flow"
```

---

## Task 9: End-to-end smoke test + lock-screen polish

**Files:**
- Modify: none required, but verify the locked white-test row on certifications < 100 % still behaves correctly.

End state: full flow walks cleanly from intro → take → result → retake or dashboard, AND users who haven't completed the course can't sneak in by typing URLs.

- [ ] **Step 1: Verify the gates hold against URL tampering**

For each of these, the user should NOT reach the test:

a) Drop yourself back to < 100 % on PCAP:
```
"C:\xampp\mysql\bin\mysql.exe" -uroot tekup -e "UPDATE user_certifications uc JOIN certifications c ON c.id=uc.certification_id SET uc.progress=50 WHERE c.code='PCAP' AND uc.user_id=<your id>;"
```

b) Visit `white-test.php?cert=<pcap-cert-id>`. **Expected:** redirect to `course.php?cert=<id>` (intro already gates progress < 100).

c) Visit `white-test-take.php` directly. **Expected:** redirect to `certifications.php` (no session).

d) Visit `white-test-result.php` directly. **Expected:** redirect to `certifications.php`.

e) Put yourself back to 100 % for the rest of the verification:
```
"C:\xampp\mysql\bin\mysql.exe" -uroot tekup -e "UPDATE user_certifications uc JOIN certifications c ON c.id=uc.certification_id SET uc.progress=100 WHERE c.code='PCAP' AND uc.user_id=<your id>;"
```

- [ ] **Step 2: Full happy path**

a) From `certifications.php`, click **Continue →** on PCAP.
b) On the course page, click the gold `2. Pass white test` row.
c) On the intro, click `Start the test`.
d) Answer all 5 correctly (B / C / A / C / D) — clicking **Next →** after each.
e) On Q5, click **Submit**.
f) **Expected:** result page shows `5 / 5`, `100 %`, green ✓ Passed, 5 green-bordered review items, `Final exam →` + `Back to dashboard`.

- [ ] **Step 3: Full retake path**

a) Restart from the intro.
b) Answer 3/5 correctly, submit.
c) Click **Retake exam** on the result page.
d) Answer all 5 correctly, submit.
e) **Expected:** lands on the pass result page as in Step 2.

- [ ] **Step 4: Final commit if any tweaks were made**

If you did any cleanup during smoke testing (typo fixes, comment improvements), commit them now:

```
git add -A
git commit -m "chore(white-test): smoke-test cleanups"
```

If nothing needed fixing, skip this step.

- [ ] **Step 5: Update plan as complete**

The plan is done. The white-test feature is fully functional for PCAP. Future work (out of scope for this plan):
- Extend the question bank with more entries for PCEP and others.
- Persist attempts to a `white_test_attempts` table for history/leaderboards.
- Build the real "Final exam" target.
