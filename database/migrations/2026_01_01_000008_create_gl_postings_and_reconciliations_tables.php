<?php

declare(strict_types=1);

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Obelaw\Ium\Accounting\Base\MigrationBase;

return new class extends MigrationBase
{
    public function up(): void
    {
        Schema::create($this->prefix . 'gl_postings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')
                ->constrained($this->prefix . 'accounts', indexName: 'ium_acc_gl_acc_fk')
                ->restrictOnDelete();
            $table->foreignId('journal_id')
                ->nullable()
                ->constrained($this->prefix . 'journals', indexName: 'ium_acc_gl_jrn_fk')
                ->nullOnDelete();
            $table->foreignId('journal_entry_id')
                ->constrained($this->prefix . 'journal_entries', indexName: 'ium_acc_gl_je_fk')
                ->restrictOnDelete();
            $table->foreignId('journal_entry_line_id')
                ->constrained($this->prefix . 'journal_entry_lines', indexName: 'ium_acc_gl_jel_fk')
                ->restrictOnDelete();
            $table->foreignId('fiscal_period_id')
                ->nullable()
                ->constrained($this->prefix . 'fiscal_periods', indexName: 'ium_acc_gl_fp_fk')
                ->nullOnDelete();
            $table->foreignId('cost_center_id')
                ->nullable()
                ->constrained($this->prefix . 'cost_centers', indexName: 'ium_acc_gl_cc_fk')
                ->nullOnDelete();
            $table->foreignId('tax_id')
                ->nullable()
                ->constrained($this->prefix . 'taxes', indexName: 'ium_acc_gl_tax_fk')
                ->nullOnDelete();
            $table->string('party_type', 64)->nullable();
            $table->unsignedBigInteger('party_id')->nullable();
            $table->date('posting_date')->index();
            $table->string('currency', 3)->default('USD');
            $table->decimal('exchange_rate', 18, 8)->default(1.0);
            $table->bigInteger('debit')->default(0); // functional minor units
            $table->bigInteger('credit')->default(0); // functional minor units
            $table->bigInteger('amount_currency')->default(0); // signed transaction currency minor units (+debit / -credit)
            $table->bigInteger('balance_after')->default(0);
            $table->text('narration')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['account_id', 'posting_date'], 'ium_acc_gl_acc_date_idx');
            $table->index(['fiscal_period_id', 'account_id'], 'ium_acc_gl_fp_acc_idx');
            $table->index(['cost_center_id', 'posting_date'], 'ium_acc_gl_cc_date_idx');
            $table->index(['party_type', 'party_id', 'posting_date'], 'ium_acc_gl_party_date_idx');
            $table->index(['tax_id', 'posting_date'], 'ium_acc_gl_tax_date_idx');
        });

        Schema::create($this->prefix . 'reconciliations', function (Blueprint $table) {
            $table->id();
            $table->string('reconciliation_number', 64)->unique();
            $table->foreignId('account_id')
                ->constrained($this->prefix . 'accounts', indexName: 'ium_acc_rec_acc_fk')
                ->restrictOnDelete();
            $table->string('party_type', 64)->nullable();
            $table->unsignedBigInteger('party_id')->nullable();
            $table->date('reconciled_date')->index();
            $table->string('currency', 3)->default('USD');
            $table->bigInteger('total_amount'); // transaction currency minor units matched
            $table->foreignId('fx_gain_loss_entry_id')
                ->nullable()
                ->constrained($this->prefix . 'journal_entries', indexName: 'ium_acc_rec_fx_je_fk')
                ->nullOnDelete();
            $table->string('actor_id', 128)->nullable();
            $table->text('notes')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['party_type', 'party_id'], 'ium_acc_rec_party_idx');
        });

        Schema::create($this->prefix . 'reconciliation_allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reconciliation_id')
                ->constrained($this->prefix . 'reconciliations', indexName: 'ium_acc_ra_rec_fk')
                ->cascadeOnDelete();
            $table->foreignId('debit_line_id')
                ->constrained($this->prefix . 'journal_entry_lines', indexName: 'ium_acc_ra_dr_fk')
                ->restrictOnDelete();
            $table->foreignId('credit_line_id')
                ->constrained($this->prefix . 'journal_entry_lines', indexName: 'ium_acc_ra_cr_fk')
                ->restrictOnDelete();
            $table->bigInteger('amount'); // matched transaction currency minor units
            $table->bigInteger('amount_functional_debit'); // functional portion on debit line
            $table->bigInteger('amount_functional_credit'); // functional portion on credit line
            $table->bigInteger('fx_difference')->default(0); // credit_func - debit_func (positive = gain on AR, negative = loss on AR)
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists($this->prefix . 'reconciliation_allocations');
        Schema::dropIfExists($this->prefix . 'reconciliations');
        Schema::dropIfExists($this->prefix . 'gl_postings');
    }
};
