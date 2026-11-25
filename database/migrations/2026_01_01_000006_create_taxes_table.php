<?php

declare(strict_types=1);

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Obelaw\Ium\Accounting\Base\MigrationBase;

return new class extends MigrationBase
{
    public function up(): void
    {
        Schema::create($this->prefix . 'taxes', function (Blueprint $table) {
            $table->id();
            $table->string('code', 64)->unique();
            $table->string('name');
            $table->string('scope', 16)->default('both')->index(); // sales, purchase, both
            $table->string('type', 16)->default('percentage'); // percentage, fixed
            $table->unsignedBigInteger('rate_bps')->default(0); // basis points: 1500 = 15.00%
            $table->bigInteger('fixed_amount')->default(0); // integer minor units for fixed taxes
            $table->boolean('is_inclusive')->default(false);
            $table->boolean('is_withholding')->default(false);
            $table->foreignId('tax_account_id')
                ->constrained($this->prefix . 'accounts', indexName: 'ium_acc_tax_acc_fk')
                ->restrictOnDelete();
            $table->foreignId('refund_account_id')
                ->nullable()
                ->constrained($this->prefix . 'accounts', indexName: 'ium_acc_tax_ref_acc_fk')
                ->restrictOnDelete();
            $table->boolean('is_active')->default(true)->index();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists($this->prefix . 'taxes');
    }
};
