<?php

declare(strict_types=1);

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Obelaw\Ium\Accounting\Base\MigrationBase;

return new class extends MigrationBase {
    public function up(): void
    {
        Schema::create($this->prefix . 'journal_entry_lines', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('journal_entry_id')->index();
            $table->unsignedBigInteger('account_id')->index();
            $table->unsignedBigInteger('cost_center_id')->nullable()->index();
            $table->unsignedInteger('line_number')->default(1);
            $table->string('type'); // debit, credit
            $table->bigInteger('amount');
            $table->bigInteger('amount_functional');
            $table->text('narration')->nullable();
            $table->nullableMorphs('party');
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->foreign('journal_entry_id')
                ->references('id')
                ->on($this->prefix . 'journal_entries')
                ->cascadeOnDelete();

            $table->foreign('account_id')
                ->references('id')
                ->on($this->prefix . 'accounts')
                ->restrictOnDelete();

            $table->foreign('cost_center_id')
                ->references('id')
                ->on($this->prefix . 'cost_centers')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists($this->prefix . 'journal_entry_lines');
    }
};
