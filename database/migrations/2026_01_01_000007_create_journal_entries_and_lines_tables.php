<?php

declare(strict_types=1);

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Obelaw\Ium\Accounting\Base\MigrationBase;

return new class extends MigrationBase
{
    public function up(): void
    {
        Schema::create($this->prefix . 'journal_entries', function (Blueprint $table) {
            $table->id();
            $table->string('entry_number', 64)->unique();
            $table->foreignId('journal_id')
                ->nullable()
                ->constrained($this->prefix . 'journals', indexName: 'ium_acc_je_jrn_fk')
                ->nullOnDelete();
            $table->date('entry_date')->index();
            $table->foreignId('fiscal_period_id')
                ->nullable()
                ->constrained($this->prefix . 'fiscal_periods', indexName: 'ium_acc_je_fp_fk')
                ->nullOnDelete();
            $table->string('status', 32)->default('draft')->index();
            $table->string('entry_type', 32)->default('standard')->index();
            $table->string('reference', 128)->nullable()->index();
            $table->text('narration')->nullable();
            $table->string('currency', 3)->default('USD');
            $table->decimal('exchange_rate', 18, 8)->default(1.0);
            $table->bigInteger('total_debit')->default(0);
            $table->bigInteger('total_credit')->default(0);
            $table->string('creator_id', 128)->nullable()->index();
            $table->string('approver_id', 128)->nullable();
            $table->timestamp('posted_at')->nullable()->index();
            $table->string('posted_by', 128)->nullable();
            $table->foreignId('reversed_entry_id')
                ->nullable()
                ->constrained($this->prefix . 'journal_entries', indexName: 'ium_acc_je_revd_fk')
                ->nullOnDelete();
            $table->foreignId('reversal_entry_id')
                ->nullable()
                ->constrained($this->prefix . 'journal_entries', indexName: 'ium_acc_je_revl_fk')
                ->nullOnDelete();
            $table->text('rejection_reason')->nullable();
            $table->string('idempotency_key', 191)->nullable()->unique();
            $table->string('source_type', 128)->nullable();
            $table->unsignedBigInteger('source_id')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['source_type', 'source_id'], 'ium_acc_je_source_idx');
        });

        Schema::create($this->prefix . 'journal_entry_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('journal_entry_id')
                ->constrained($this->prefix . 'journal_entries', indexName: 'ium_acc_jel_je_fk')
                ->cascadeOnDelete();
            $table->foreignId('account_id')
                ->constrained($this->prefix . 'accounts', indexName: 'ium_acc_jel_acc_fk')
                ->restrictOnDelete();
            $table->foreignId('cost_center_id')
                ->nullable()
                ->constrained($this->prefix . 'cost_centers', indexName: 'ium_acc_jel_cc_fk')
                ->nullOnDelete();
            $table->foreignId('tax_id')
                ->nullable()
                ->constrained($this->prefix . 'taxes', indexName: 'ium_acc_jel_tax_fk')
                ->nullOnDelete();
            $table->unsignedInteger('line_number');
            $table->string('type', 16); // debit, credit
            $table->bigInteger('amount'); // transaction currency minor units
            $table->bigInteger('amount_functional'); // functional base currency minor units
            $table->date('due_date')->nullable()->index();
            $table->text('narration')->nullable();
            $table->string('party_type', 64)->nullable();
            $table->unsignedBigInteger('party_id')->nullable();
            $table->bigInteger('reconciled_amount')->default(0); // transaction currency minor units
            $table->bigInteger('reconciled_functional')->default(0); // functional minor units
            $table->string('reconciliation_status', 16)->default('none')->index(); // none, open, partial, reconciled
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['party_type', 'party_id'], 'ium_acc_jel_party_idx');
            $table->index(['account_id', 'reconciliation_status'], 'ium_acc_jel_acc_rec_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists($this->prefix . 'journal_entry_lines');
        Schema::dropIfExists($this->prefix . 'journal_entries');
    }
};
