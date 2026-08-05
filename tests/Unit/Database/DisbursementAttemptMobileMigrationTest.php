<?php

declare(strict_types=1);

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

afterEach(function (): void {
    Schema::dropIfExists('disbursement_attempts');
});

it('allows a disbursement audit attempt without a contact mobile', function (): void {
    Schema::dropIfExists('disbursement_attempts');
    Schema::create('disbursement_attempts', function (Blueprint $table): void {
        $table->id();
        $table->string('mobile');
    });

    $migration = include __DIR__.'/../../../database/migrations/2026_08_05_090000_make_mobile_nullable_on_disbursement_attempts.php';
    $migration->up();

    DB::table('disbursement_attempts')->insert(['mobile' => null]);

    $column = collect(Schema::getColumns('disbursement_attempts'))
        ->firstWhere('name', 'mobile');

    expect($column['nullable'])->toBeTrue()
        ->and(DB::table('disbursement_attempts')->value('mobile'))->toBeNull();
});

it('refuses to restore the legacy requirement while null audit values exist', function (): void {
    Schema::dropIfExists('disbursement_attempts');
    Schema::create('disbursement_attempts', function (Blueprint $table): void {
        $table->id();
        $table->string('mobile')->nullable();
    });
    DB::table('disbursement_attempts')->insert(['mobile' => null]);

    $migration = include __DIR__.'/../../../database/migrations/2026_08_05_090000_make_mobile_nullable_on_disbursement_attempts.php';

    expect(fn () => $migration->down())->toThrow(RuntimeException::class);
});
