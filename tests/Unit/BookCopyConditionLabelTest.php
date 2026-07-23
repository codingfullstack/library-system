<?php

use App\Models\BookCopy;

it('has one canonical label for each book copy condition', function () {
    expect(BookCopy::conditionLabels())->toBe([
        BookCopy::CONDITION_NEW => 'Nauja',
        BookCopy::CONDITION_GOOD => 'Gera',
        BookCopy::CONDITION_WORN => 'Padėvėta',
        BookCopy::CONDITION_DAMAGED => 'Sugadinta',
    ]);

    foreach (BookCopy::conditionLabels() as $condition => $label) {
        expect(BookCopy::conditionLabelFor($condition))->toBe($label);
    }
});

it('uses the same canonical condition label on model instances', function () {
    $copy = new BookCopy([
        'condition_status' => BookCopy::CONDITION_DAMAGED,
    ]);

    expect($copy->conditionLabel())
        ->toBe(BookCopy::conditionLabelFor(BookCopy::CONDITION_DAMAGED))
        ->toBe('Sugadinta');
});
