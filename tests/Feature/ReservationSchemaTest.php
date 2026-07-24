<?php

use App\Models\BookCopy;
use App\Models\Reservation;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

function skipUnlessMySqlLike(): void
{
    if (! in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
        test()->markTestSkipped('Reservation schema smoke checks require MySQL or MariaDB information_schema.');
    }
}

it('keeps reserved_at free from on update current timestamp', function () {
    skipUnlessMySqlLike();

    $column = DB::selectOne("
        SELECT column_default, extra
        FROM information_schema.columns
        WHERE table_schema = database()
          AND table_name = 'reservations'
          AND column_name = 'reserved_at'
    ");

    expect(strtolower((string) $column->extra))->not->toContain('on update')
        ->and(strtolower((string) $column->column_default))->toContain('current_timestamp');
});

it('has the assigned physical copy foreign key with matching type', function () {
    skipUnlessMySqlLike();

    $reservationColumn = DB::selectOne("
        SELECT column_type, is_nullable
        FROM information_schema.columns
        WHERE table_schema = database()
          AND table_name = 'reservations'
          AND column_name = 'assigned_book_copy_id'
    ");

    $copyColumn = DB::selectOne("
        SELECT column_type
        FROM information_schema.columns
        WHERE table_schema = database()
          AND table_name = 'book_copies'
          AND column_name = 'id'
    ");

    $foreignKey = DB::selectOne("
        SELECT kcu.referenced_table_name, kcu.referenced_column_name, rc.delete_rule
        FROM information_schema.referential_constraints rc
        JOIN information_schema.key_column_usage kcu
          ON kcu.constraint_schema = rc.constraint_schema
         AND kcu.constraint_name = rc.constraint_name
         AND kcu.table_name = rc.table_name
        WHERE rc.constraint_schema = database()
          AND rc.table_name = 'reservations'
          AND kcu.column_name = 'assigned_book_copy_id'
    ");

    expect($reservationColumn)->not->toBeNull()
        ->and($reservationColumn->column_type)->toBe($copyColumn->column_type)
        ->and($reservationColumn->is_nullable)->toBe('YES')
        ->and($foreignKey->referenced_table_name)->toBe('book_copies')
        ->and($foreignKey->referenced_column_name)->toBe('id')
        ->and($foreignKey->delete_rule)->toBe('RESTRICT');
});

it('has the active ready generated column and unique index', function () {
    skipUnlessMySqlLike();

    $generatedColumn = DB::selectOne("
        SELECT generation_expression, extra
        FROM information_schema.columns
        WHERE table_schema = database()
          AND table_name = 'reservations'
          AND column_name = 'active_ready_book_copy_id'
    ");

    $uniqueIndex = DB::selectOne("
        SELECT non_unique
        FROM information_schema.statistics
        WHERE table_schema = database()
          AND table_name = 'reservations'
          AND index_name = 'reservations_active_ready_book_copy_unique'
          AND column_name = 'active_ready_book_copy_id'
    ");

    expect($generatedColumn)->not->toBeNull()
        ->and(strtolower((string) $generatedColumn->generation_expression))->toContain('assigned_book_copy_id')
        ->and($uniqueIndex)->not->toBeNull()
        ->and((int) $uniqueIndex->non_unique)->toBe(0);
});

it('has and enforces the ready completeness check constraint', function () {
    skipUnlessMySqlLike();

    $constraint = DB::selectOne("
        SELECT constraint_name, check_clause
        FROM information_schema.check_constraints
        WHERE constraint_schema = database()
          AND constraint_name = 'reservations_ready_completeness_check'
    ");

    expect($constraint)->not->toBeNull()
        ->and(strtolower((string) $constraint->check_clause))->toContain('assigned_book_copy_id')
        ->and(strtolower((string) $constraint->check_clause))->toContain('pickup_branch_id')
        ->and(strtolower((string) $constraint->check_clause))->toContain('ready_at')
        ->and(strtolower((string) $constraint->check_clause))->toContain('expires_at');
});

it('keeps reservation lifecycle columns and indexes deployable', function () {
    skipUnlessMySqlLike();

    foreach (['ready_at', 'expires_at', 'fulfilled_at', 'cancelled_at'] as $columnName) {
        $column = DB::selectOne("
            SELECT is_nullable
            FROM information_schema.columns
            WHERE table_schema = database()
              AND table_name = 'reservations'
              AND column_name = ?
        ", [$columnName]);

        expect($column?->is_nullable)->toBe('YES');
    }

    $statusExpiresIndex = DB::table('information_schema.statistics')
        ->whereRaw('table_schema = database()')
        ->where('table_name', 'reservations')
        ->where('index_name', 'reservations_status_expires_index')
        ->orderBy('seq_in_index')
        ->pluck('column_name')
        ->all();

    expect($statusExpiresIndex)->toBe(['status', 'expires_at']);
});

it('rejects two active ready reservations for the same assigned copy', function () {
    skipUnlessMySqlLike();

    $copy = BookCopy::factory()->create(['status' => BookCopy::STATUS_AVAILABLE]);

    Reservation::factory()->create([
        'library_id' => $copy->library_id,
        'book_id' => $copy->book_id,
        'assigned_book_copy_id' => $copy->id,
        'pickup_branch_id' => $copy->branch_id,
        'status' => Reservation::STATUS_READY,
        'ready_at' => now(),
        'expires_at' => now()->addDay(),
    ]);

    $failed = false;

    try {
        Reservation::factory()->create([
            'library_id' => $copy->library_id,
            'book_id' => $copy->book_id,
            'assigned_book_copy_id' => $copy->id,
            'pickup_branch_id' => $copy->branch_id,
            'status' => Reservation::STATUS_READY,
            'ready_at' => now(),
            'expires_at' => now()->addDay(),
        ]);
    } catch (\Throwable $exception) {
        $failed = true;

        expect($exception->getMessage())->toContain('reservations_active_ready_book_copy_unique');
    }

    expect($failed)->toBeTrue();
});

it('rejects incomplete ready inserts and updates', function () {
    skipUnlessMySqlLike();

    $copy = BookCopy::factory()->create(['status' => BookCopy::STATUS_AVAILABLE]);

    $valid = Reservation::factory()->create([
        'library_id' => $copy->library_id,
        'book_id' => $copy->book_id,
        'assigned_book_copy_id' => $copy->id,
        'pickup_branch_id' => $copy->branch_id,
        'status' => Reservation::STATUS_READY,
        'ready_at' => now(),
        'expires_at' => now()->addDay(),
    ]);

    expect($valid->fresh()->isReady())->toBeTrue();

    $failedInsert = false;

    try {
        DB::table('reservations')->insert([
            'library_id' => $copy->library_id,
            'book_id' => $copy->book_id,
            'user_id' => User::factory()->member()->create(['library_id' => $copy->library_id])->id,
            'scope' => Reservation::SCOPE_LIBRARY,
            'branch_id' => null,
            'status' => Reservation::STATUS_READY,
            'assigned_book_copy_id' => null,
            'pickup_branch_id' => null,
            'ready_at' => null,
            'expires_at' => null,
            'reserved_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    } catch (\Throwable $exception) {
        $failedInsert = true;
        expect($exception->getMessage())->toContain('reservations_ready_completeness_check');
    }

    $waiting = Reservation::factory()->create([
        'library_id' => $copy->library_id,
        'book_id' => $copy->book_id,
        'status' => Reservation::STATUS_WAITING,
    ]);

    $failedUpdate = false;

    try {
        $waiting->update(['status' => Reservation::STATUS_READY]);
    } catch (\Throwable $exception) {
        $failedUpdate = true;
        expect($exception->getMessage())->toContain('reservations_ready_completeness_check');
    }

    expect($failedInsert)->toBeTrue()
        ->and($failedUpdate)->toBeTrue();
});

it('allows historical terminal reservations to retain lifecycle fields', function () {
    skipUnlessMySqlLike();

    $copy = BookCopy::factory()->create(['status' => BookCopy::STATUS_AVAILABLE]);

    $reservation = Reservation::factory()->create([
        'library_id' => $copy->library_id,
        'book_id' => $copy->book_id,
        'assigned_book_copy_id' => $copy->id,
        'pickup_branch_id' => $copy->branch_id,
        'status' => Reservation::STATUS_CANCELLED,
        'ready_at' => now()->subDay(),
        'expires_at' => now()->addDay(),
        'cancelled_at' => now(),
    ]);

    expect($reservation->fresh()->status)->toBe(Reservation::STATUS_CANCELLED);
});

it('has the active reservation generated column and unique user book index', function () {
    skipUnlessMySqlLike();

    $generatedColumn = DB::selectOne("
        SELECT generation_expression, extra
        FROM information_schema.columns
        WHERE table_schema = database()
          AND table_name = 'reservations'
          AND column_name = 'active_reservation_marker'
    ");

    $indexColumns = DB::table('information_schema.statistics')
        ->whereRaw('table_schema = database()')
        ->where('table_name', 'reservations')
        ->where('index_name', 'reservations_active_user_book_unique')
        ->where('non_unique', 0)
        ->orderBy('seq_in_index')
        ->pluck('column_name')
        ->all();

    expect($generatedColumn)->not->toBeNull()
        ->and(strtolower((string) $generatedColumn->generation_expression))->toContain('status')
        ->and($indexColumns)->toBe(['library_id', 'book_id', 'user_id', 'active_reservation_marker']);
})->group('mariadb', 'database-invariants');

it('rejects waiting and ready active reservations for the same user and book', function () {
    skipUnlessMySqlLike();

    $copy = BookCopy::factory()->create(['status' => BookCopy::STATUS_AVAILABLE]);
    $member = User::factory()->member()->create(['library_id' => $copy->library_id]);

    Reservation::factory()->create([
        'library_id' => $copy->library_id,
        'book_id' => $copy->book_id,
        'user_id' => $member->id,
        'status' => Reservation::STATUS_WAITING,
    ]);

    expect(fn () => Reservation::factory()->create([
        'library_id' => $copy->library_id,
        'book_id' => $copy->book_id,
        'user_id' => $member->id,
        'assigned_book_copy_id' => $copy->id,
        'pickup_branch_id' => $copy->branch_id,
        'status' => Reservation::STATUS_READY,
        'ready_at' => now(),
        'expires_at' => now()->addDay(),
    ]))->toThrow(\Illuminate\Database\QueryException::class);
})->group('mariadb', 'database-invariants');

it('allows a new active reservation after terminal reservation statuses', function (string $status) {
    skipUnlessMySqlLike();

    $copy = BookCopy::factory()->create(['status' => BookCopy::STATUS_LOANED]);
    $member = User::factory()->member()->create(['library_id' => $copy->library_id]);

    Reservation::factory()->create([
        'library_id' => $copy->library_id,
        'book_id' => $copy->book_id,
        'user_id' => $member->id,
        'status' => $status,
    ]);

    $active = Reservation::factory()->create([
        'library_id' => $copy->library_id,
        'book_id' => $copy->book_id,
        'user_id' => $member->id,
        'status' => Reservation::STATUS_WAITING,
    ]);

    expect($active->fresh()->isActive())->toBeTrue();
})->with([
    Reservation::STATUS_CANCELLED,
    Reservation::STATUS_EXPIRED,
    Reservation::STATUS_FULFILLED,
])->group('mariadb', 'database-invariants');
