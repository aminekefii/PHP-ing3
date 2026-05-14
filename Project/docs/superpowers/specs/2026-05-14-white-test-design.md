# PCAP White Test — Design Spec

**Date:** 2026-05-14
**Status:** Approved (brainstorm phase). Pending implementation plan.
**Scope:** A timed, 5-question, multiple-choice practice exam ("white test") that students unlock after completing 100 % of the PCAP course videos. PCAP-only for this demo; other certs keep the white-test row locked.

---

## 1. Purpose

After watching every video in the PCAP track, students currently see a "You unlocked the white test" placeholder. This spec turns that placeholder into a real, take-able test: timed, scored, with a per-question review on submit and pass/fail branching into either a future "Final exam" step or a retake.

The white test is a knowledge check **before** the official Python Institute certification exam — not the certification exam itself.

---

## 2. Goals & non-goals

### In scope

- Three-page flow: **intro → take → result**, gated on `progress >= 100`.
- 5 PCAP multiple-choice questions, one per page, forward-only.
- 10-minute server-authoritative timer that survives page refresh.
- Server-side scoring; correct answers never sent to the client during the test.
- Pass = score ≥ 70 % (4 out of 5).
- Results page shows: total score, pass/fail badge, and a per-question review (your answer vs. correct answer, green/red).
- Pass branch: enabled **Final exam** button (placeholder href for the demo) + **Back to dashboard**.
- Fail branch: **Retake exam** (wipes session, fresh 10 min) + **Back to dashboard**.
- Resume on refresh: `current_q` and `started_at` come from `$_SESSION`, so the user lands on the same question and the timer keeps counting down from the original start.

### Out of scope

- DB persistence of attempts, scores, or attempt history. Every retake is fresh state; no leaderboard, no "previous best".
- White test for any cert other than PCAP. PCEP keeps the locked white-test row; clicking it after PCEP reaches 100 % goes nowhere meaningful for now.
- The real "Final exam" — the button is a placeholder.
- Question randomization, question pools, weighted scoring, partial credit.
- Going back to previous questions (forward-only matches the supplied screenshot, which only has a Next button).
- Showing the timer-expiry warning at < 1 minute remaining; the only timer event is auto-submit at 0:00.

---

## 3. File layout

```
Project/
├── white-test.php              (existing — intro page, gated + 3 info chips)
├── white-test-take.php         (new — quiz page, one question at a time)
├── white-test-result.php       (new — score + per-question review + branches)
└── includes/
    └── white-test-questions.php (new — PCAP question bank as a const array)
```

Routes (HTTP):

```
GET  /white-test.php?cert=<id>            → intro
POST /white-test.php?cert=<id>            → start session, redirect to take.php?q=0
GET  /white-test-take.php                 → render current question
POST /white-test-take.php                 → record answer, advance or submit
GET  /white-test-result.php               → render score + review
```

`cert=<id>` query string is only used on intro/start. Once a session is in flight, `$_SESSION['white_test']['cert_id']` is the source of truth — the user can't switch cert mid-test by changing the URL.

---

## 4. Data

### 4.1 Question bank — `includes/white-test-questions.php`

Returns an indexed array; each entry has a `question`, four `choices` keyed `A`–`D`, and a single-letter `answer`:

```php
return [
    'PCAP' => [
        [
            'question' => 'What is the correct way to print "Hello" in Python?',
            'choices'  => ['A' => 'echo("Hello")', 'B' => 'print("Hello")', 'C' => 'printf("Hello")', 'D' => 'show("Hello")'],
            'answer'   => 'B',
        ],
        // ... 4 more
    ],
];
```

Keyed by cert code so growing this to other certs later is a one-line addition.

### 4.2 Session state — `$_SESSION['white_test']`

| Key | Type | Notes |
|---|---|---|
| `cert_id` | int | The cert the attempt belongs to. |
| `cert_code` | string | Used to look up the question bank. |
| `started_at` | int | Unix timestamp set when the test starts. |
| `current_q` | int | 0..4. Persists across refresh. |
| `answers` | array | `[0 => 'A', 1 => null, ...]`. Unanswered slots stay `null`. |
| `submitted` | bool | True once submit or auto-submit fires. |

### 4.3 No DB schema changes

`user_certifications.progress` is unchanged. No `white_test_attempts` table. The white test is intentionally ephemeral for the demo.

---

## 5. Page-by-page behaviour

### 5.1 `white-test.php` (intro)

- **GET:** Already implemented. Adds one extra thing: the **Start the test** button becomes a `<form method="post">` so we can start the session server-side rather than via a GET link.
- **POST:** Validates `progress >= 100` again (defence in depth), then:
  1. Builds a fresh `$_SESSION['white_test']` with `started_at = time()`, `current_q = 0`, `answers = [null, null, null, null, null]`, `submitted = false`.
  2. Redirects 302 to `white-test-take.php`.

### 5.2 `white-test-take.php`

Header strip (matches the reference screenshot):

```
[ ☰ ]              Question 2 / 5              ⏱ 08:43   [ Next → ]
```

Body (two columns):

```
┌─────────────────────────────┬───────────────────────────┐
│ Which symbol is used for    │  ○  //                    │
│ comments in Python?         │  ○  <!-- -->              │
│                             │  ●  #                     │
│                             │  ○  **                    │
└─────────────────────────────┴───────────────────────────┘
```

- The four choices are clickable cards (whole card is the click target, not just the radio).
- Selecting a choice **does not** advance — Next does.
- **GET** rules:
  - No `$_SESSION['white_test']` at all → redirect to `certifications.php` (we don't know which cert they meant).
  - Session exists but `submitted === true` → redirect to `white-test-result.php`.
  - Session exists and timer remaining ≤ 0 → flip `submitted = true`, redirect to result.
  - Otherwise render the question at `current_q`. The button text is "Next →" for `current_q < 4` and "Submit" for `current_q === 4`.
- **POST** rules:
  - Same session/timer checks as GET. If session is missing or timer expired, redirect appropriately.
  - Validate `$_POST['answer']` is one of `A B C D`. Anything else → re-render the same question with an inline "Please select an answer" notice.
  - Write `$answers[current_q] = answer`.
  - If `current_q < 4`: `current_q++`, redirect 302 to `white-test-take.php` (PRG pattern — no resubmit on refresh).
  - If `current_q === 4`: set `submitted = true`, redirect to `white-test-result.php`.

### 5.3 `white-test-result.php`

- **GET:**
  - No session, or `submitted !== true` → redirect to `white-test.php?cert=<id>` (intro).
- Server computes score by comparing `answers` to the bank and renders:

```
┌──────────────────────────────────┐
│           ✓ Passed               │   (or ✗ Not passed, in red)
│           4 / 5  —  80 %         │
│   Passing score: 70 %            │
└──────────────────────────────────┘

1. What is the correct way to print "Hello"?
   Your answer:    B) print("Hello")           ✓
2. Which symbol is used for comments?
   Your answer:    A) //                       ✗
   Correct answer: C) #
... (3 more)

[Final exam →]   [Back to dashboard]
   (Retake exam — only on fail)
```

- Buttons:
  - **Passed:** `Final exam →` (anchor with `href="#"` and `aria-disabled="false"`; real destination is a future task), plus `Back to dashboard` → `dashboard.php`.
  - **Failed:** `Retake exam` (POST to `white-test.php?cert=<id>` to start a fresh session) plus `Back to dashboard` → `dashboard.php`.

---

## 6. Timer

### Server-side (authoritative)

```php
$elapsed   = time() - $_SESSION['white_test']['started_at'];
$remaining = 600 - $elapsed;  // 10 minutes
if ($remaining <= 0) { /* force submit */ }
```

`remaining` is rendered into the page on every GET. The client's JS countdown starts from that number, so a refresh re-syncs from the server. There is no way to gain time client-side; the server enforces the deadline on every POST.

### Client-side (display + auto-submit)

A tiny inline script:

```js
let remaining = <?= (int) $remaining ?>;
const t = document.getElementById('test-timer');
const form = document.getElementById('test-form');
setInterval(() => {
  remaining--;
  t.textContent = `${Math.floor(remaining/60)}:${String(remaining%60).padStart(2,'0')}`;
  if (remaining <= 0) form.submit();  // auto-submit; server also enforces
}, 1000);
```

If the user has JS disabled, the next POST still triggers server-side expiry and lands them on the result page.

---

## 7. Anti-cheat / integrity

- Correct answers are **not** in the HTML during the test. They are only embedded into the rendered output on `white-test-result.php`.
- The user can't reorder questions or skip ahead — `current_q` is set server-side.
- The user can't gain time by refreshing — timer reads from `started_at`.
- The user can't replay a passed test in the same session — `submitted = true` is checked on both take and result.
- All decisions (score, pass/fail, branch) are computed server-side from `$_SESSION` state at submit time.

The demo doesn't need stronger guarantees (no DB attempt log, no rate-limit on retakes, no IP/UA fingerprinting).

---

## 8. Look & feel

Reuses the existing gold-on-cream theme:

- The header strip on `white-test-take.php` uses `.course-stage`-style card chrome with a 3px `#c9a44a` top border, white background, rounded corners.
- The question pane and the choices pane sit in a Bootstrap row (`col-lg-6` each), inside a single card with a vertical divider.
- Selected choice gets the same `#f2eedb` (cream) background as `.course-video-row.is-playing`.
- Pass badge: green (`#10b981`-ish), wrong-answer mark: red (`#ef4444`-ish). Both kept restrained so they don't clash with the gold theme.
- All new CSS lives in `assets/css/templatemo-space-dynamic.css`, appended after the existing white-test rules.

---

## 9. Edge cases

| Situation | Behaviour |
|---|---|
| User opens `take.php` without ever starting | Redirect to `white-test.php?cert=<id>` if cert id is somehow known, else `certifications.php`. |
| User refreshes during the test | Same question, timer continues from server. |
| User has JS disabled when the timer hits 0 | Their next POST is rejected; server forces `submitted=true` and they land on the result page. |
| User opens `result.php` without finishing | Redirect to intro. |
| User passes and clicks "Retake exam" | They can't — that button only renders on fail. (Re-taking after a pass is intentionally not offered.) |
| User logs out mid-test | Session destroyed → next visit goes to login then intro. Their answers are gone, which is fine for the demo. |
| Two tabs open on the same test | Both share `$_SESSION`. Last write wins. Acceptable for the demo. |
| User completes course in a second tab while a test is open | Doesn't matter — they're already past the intro gate. |

---

## 10. Open questions

None as of this version. If a real "Final exam" page is built later, only the button's `href` on the pass branch needs to change.
