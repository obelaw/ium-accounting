<?php

declare(strict_types=1);

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Obelaw\Ium\Accounting\Base\MigrationBase;
use Obelaw\Ium\Accounting\Enums\DepreciationMethod;
use Obelaw\Ium\Accounting\Enums\DepreciationScheduleStatus;
use Obelaw\Ium\Accounting\Enums\FixedAssetStatus;

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
            $table->string('status', 32)->default(FixedAssetStatus::DRAFT->value)->index();
            $table->string('depreciation_method', 32)->default(DepreciationMethod::STRAIGHT_LINE->value);

            $table->date('purchase_date');
            $table->date('in_service_date')->index();
            $table->date('disposed_at')->nullable();

            // Monetary amounts in integer minor units (e.g. cents)
            $table->string('currency', 3)->default('USD');
            $table->bigInteger('acquisition_cost');
            $table->bigInteger('salvage_value')->default(0);
            $table->bigInteger('accumulated_depreciation')->default(0);
            $table->bigInteger('net_book_value');
            $table->unsignedInteger('useful_life_months');

            // Chart of Accounts leaf links
            $table->foreignId('asset_account_id')
                ->constrained($this->prefix . 'accounts')
                ->restrictOnDelete();

            $table->foreignId('accumulated_depreciation_account_id')
                ->constrained($this->prefix . 'accounts', indexName: 'ium_acc_fa_accum_depr_fk')
                ->restrictOnDelete();

            $table->foreignId('depreciation_expense_account_id')
                ->constrained($this->prefix . 'accounts', indexName: 'ium_acc_fa_depr_exp_fk')
                ->restrictOnDelete();

            // Analytical Cost Center dimension
            $table->foreignId('cost_center_id')
                ->nullable()
                ->constrained($this->prefix . 'cost_centers')
                ->nullOnDelete();

            // Optional Capitalization Journal Entry link
            $table->foreignId('acquisition_journal_entry_id')
                ->nullable()
                ->constrained($this->prefix . 'journal_entries', indexName: 'ium_acc_fa_acq_je_fk')
                ->nullOnDelete();

            $table->string('creator_id', 128)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create($this->prefix . 'depreciation_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fixed_asset_id')
                ->constrained($this->prefix . 'fixed_assets')
                ->cascadeOnDelete();

            $table->foreignId('fiscal_period_id')
                ->nullable()
                ->constrained($this->prefix . 'fiscal_periods')
                ->nullOnDelete();

            $table->unsignedInteger('period_sequence');
            $table->date('schedule_date')->index();

            // Monetary amounts in integer minor units (e.g. cents)
            $table->bigInteger('depreciation_amount');
            $table->bigInteger('accumulated_depreciation_after');
            $table->bigInteger('net_book_value_after');

            $table->string('status', 32)->default(DepreciationScheduleStatus::PENDING->value)->index();

            $table->foreignId('journal_entry_id')
                ->nullable()
                ->constrained($this->prefix . 'journal_entries')
                ->nullOnDelete();

            $table->timestamp('posted_at')->nullable();
            $table->string('posted_by', 128)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['fixed_asset_id', 'period_sequence'], 'ium_acc_depr_seq_unq');
            $table->unique(['fixed_asset_id', 'schedule_date'], 'ium_acc_depr_date_unq');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists($this->prefix . 'depreciation_schedules');
        Schema::dropIfExists($this->prefix . 'fixed_assets');
    }
};
