<?php

declare(strict_types=1);

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Obelaw\Ium\Accounting\Base\MigrationBase;

return new class extends MigrationBase
{
    public function up(): void
    {
        Schema::create($this->prefix . 'fixed_assets', function (Blueprint $table) {
            $table->id();
            $table->string('asset_code', 64)->unique();
            $table->string('name');
            $table->string('serial_number', 128)->nullable()->index();
            $table->text('description')->nullable();
            $table->string('status', 32)->default('draft')->index();
            $table->string('depreciation_method', 32)->default('straight_line');
            $table->unsignedInteger('declining_rate_bps')->nullable(); // optional basis points for declining balance (e.g. 2000 = 20%/yr)

            $table->date('purchase_date');
            $table->date('in_service_date')->index();
            $table->date('disposed_at')->nullable();
            $table->bigInteger('disposal_proceeds')->nullable();

            $table->string('currency', 3)->default('USD');
            $table->bigInteger('acquisition_cost');
            $table->bigInteger('salvage_value')->default(0);
            $table->bigInteger('accumulated_depreciation')->default(0);
            $table->bigInteger('net_book_value');
            $table->unsignedInteger('useful_life_months');

            $table->foreignId('asset_account_id')
                ->constrained($this->prefix . 'accounts', indexName: 'ium_acc_fa_asset_fk')
                ->restrictOnDelete();
            $table->foreignId('accumulated_depreciation_account_id')
                ->constrained($this->prefix . 'accounts', indexName: 'ium_acc_fa_accum_fk')
                ->restrictOnDelete();
            $table->foreignId('depreciation_expense_account_id')
                ->constrained($this->prefix . 'accounts', indexName: 'ium_acc_fa_exp_fk')
                ->restrictOnDelete();
            $table->foreignId('disposal_gain_account_id')
                ->nullable()
                ->constrained($this->prefix . 'accounts', indexName: 'ium_acc_fa_gain_fk')
                ->restrictOnDelete();
            $table->foreignId('disposal_loss_account_id')
                ->nullable()
                ->constrained($this->prefix . 'accounts', indexName: 'ium_acc_fa_loss_fk')
                ->restrictOnDelete();
            $table->foreignId('cost_center_id')
                ->nullable()
                ->constrained($this->prefix . 'cost_centers', indexName: 'ium_acc_fa_cc_fk')
                ->nullOnDelete();
            $table->foreignId('acquisition_journal_entry_id')
                ->nullable()
                ->constrained($this->prefix . 'journal_entries', indexName: 'ium_acc_fa_acq_je_fk')
                ->nullOnDelete();
            $table->foreignId('disposal_journal_entry_id')
                ->nullable()
                ->constrained($this->prefix . 'journal_entries', indexName: 'ium_acc_fa_disp_je_fk')
                ->nullOnDelete();

            $table->string('creator_id', 128)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create($this->prefix . 'depreciation_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fixed_asset_id')
                ->constrained($this->prefix . 'fixed_assets', indexName: 'ium_acc_ds_fa_fk')
                ->cascadeOnDelete();
            $table->foreignId('fiscal_period_id')
                ->nullable()
                ->constrained($this->prefix . 'fiscal_periods', indexName: 'ium_acc_ds_fp_fk')
                ->nullOnDelete();
            $table->unsignedInteger('period_sequence');
            $table->date('schedule_date')->index();
            $table->bigInteger('depreciation_amount');
            $table->bigInteger('accumulated_depreciation_after');
            $table->bigInteger('net_book_value_after');
            $table->string('status', 32)->default('pending')->index();
            $table->foreignId('journal_entry_id')
                ->nullable()
                ->constrained($this->prefix . 'journal_entries', indexName: 'ium_acc_ds_je_fk')
                ->nullOnDelete();
            $table->timestamp('posted_at')->nullable();
            $table->string('posted_by', 128)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['fixed_asset_id', 'period_sequence'], 'ium_acc_ds_seq_unq');
            $table->unique(['fixed_asset_id', 'schedule_date'], 'ium_acc_ds_date_unq');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists($this->prefix . 'depreciation_schedules');
        Schema::dropIfExists($this->prefix . 'fixed_assets');
    }
};
