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
