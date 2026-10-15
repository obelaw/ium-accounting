<?php

declare(strict_types=1);

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Obelaw\Ium\Accounting\Base\MigrationBase;

return new class extends MigrationBase {
    public function up(): void
    {
        Schema::create($this->prefix . 'gl_postings', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('account_id')->index();
            $table->unsignedBigInteger('journal_entry_id')->index();
            $table->unsignedBigInteger('journal_entry_line_id')->index();
            $table->unsignedBigInteger('fiscal_period_id')->nullable()->index();
            $table->date('posting_date')->index();
            $table->bigInteger('debit')->default(0);
            $table->bigInteger('credit')->default(0);
            $table->bigInteger('balance_after')->default(0);
            $table->text('narration')->nullable();
            $table->timestamps();

            $table->foreign('account_id')
                ->references('id')
                ->on($this->prefix . 'accounts')
                ->restrictOnDelete();

            $table->foreign('journal_entry_id')
                ->references('id')
                ->on($this->prefix . 'journal_entries')
                ->cascadeOnDelete();

            $table->foreign('journal_entry_line_id')
                ->references('id')
                ->on($this->prefix . 'journal_entry_lines')
                ->cascadeOnDelete();

            $table->foreign('fiscal_period_id')
                ->references('id')
                ->on($this->prefix . 'fiscal_periods')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists($this->prefix . 'gl_postings');
    }
};
