<?php

declare(strict_types=1);

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Obelaw\Ium\Accounting\Base\MigrationBase;

return new class extends MigrationBase {
    public function up(): void
    {
        Schema::create($this->prefix . 'journal_entries', function (Blueprint $table) {
            $table->id();
            $table->string('entry_number')->unique();
            $table->date('entry_date');
            $table->unsignedBigInteger('fiscal_period_id')->nullable()->index();
            $table->string('status')->default('draft')->index(); // draft, pending_approval, approved, posted, reversed, rejected
            $table->string('entry_type')->default('standard'); // standard, adjusting, closing, reversal, opening
            $table->text('narration')->nullable();
            $table->string('currency', 3)->default('USD');
            $table->decimal('exchange_rate', 12, 6)->default(1.000000);
            $table->decimal('total_debit', 18, 4)->default(0.0000);
            $table->decimal('total_credit', 18, 4)->default(0.0000);
            $table->string('creator_id')->nullable()->index();
            $table->string('approver_id')->nullable()->index();
            $table->timestamp('posted_at')->nullable()->index();
            $table->string('posted_by')->nullable();
            $table->unsignedBigInteger('reversed_entry_id')->nullable()->index();
            $table->unsignedBigInteger('reversal_entry_id')->nullable()->index();
            $table->text('rejection_reason')->nullable();
            $table->nullableMorphs('source');
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->foreign('fiscal_period_id')
                ->references('id')
                ->on($this->prefix . 'fiscal_periods')
                ->nullOnDelete();

            $table->foreign('reversed_entry_id')
                ->references('id')
                ->on($this->prefix . 'journal_entries')
                ->nullOnDelete();

            $table->foreign('reversal_entry_id')
                ->references('id')
                ->on($this->prefix . 'journal_entries')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists($this->prefix . 'journal_entries');
    }
};
