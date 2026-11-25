<?php

declare(strict_types=1);

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Obelaw\Ium\Accounting\Base\MigrationBase;

return new class extends MigrationBase
{
    public function up(): void
    {
        Schema::create($this->prefix . 'posting_rules', function (Blueprint $table) {
            $table->id();
            $table->string('event_name', 128)->unique();
            $table->foreignId('journal_id')
                ->nullable()
                ->constrained($this->prefix . 'journals', indexName: 'ium_acc_pr_jrn_fk')
                ->nullOnDelete();
            $table->string('debit_account_code', 64);
            $table->string('credit_account_code', 64);
            $table->foreignId('tax_id')
                ->nullable()
                ->constrained($this->prefix . 'taxes', indexName: 'ium_acc_pr_tax_fk')
                ->nullOnDelete();
            $table->string('description_template')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create($this->prefix . 'audit_logs', function (Blueprint $table) {
            $table->id();
            $table->string('actor_id', 128)->index();
            $table->string('action', 64)->index();
            $table->string('origin_context', 128);
            $table->string('auditable_type', 160);
            $table->unsignedBigInteger('auditable_id');
            $table->json('before_state')->nullable();
            $table->json('after_state')->nullable();
            $table->text('reason')->nullable();
            $table->timestamp('created_at')->useCurrent()->index();

            $table->index(['auditable_type', 'auditable_id'], 'ium_acc_audit_morph_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists($this->prefix . 'audit_logs');
        Schema::dropIfExists($this->prefix . 'posting_rules');
    }
};
