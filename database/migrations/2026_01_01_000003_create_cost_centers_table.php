<?php

declare(strict_types=1);

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Obelaw\Ium\Accounting\Base\MigrationBase;

return new class extends MigrationBase
{
    public function up(): void
    {
        Schema::create($this->prefix . 'cost_centers', function (Blueprint $table) {
            $table->id();
            $table->string('code', 64)->unique();
            $table->string('name');
            $table->string('dimension_type', 32)->default('cost_center')->index();
            $table->foreignId('parent_id')
                ->nullable()
                ->constrained($this->prefix . 'cost_centers', indexName: 'ium_acc_cc_parent_fk')
                ->nullOnDelete();
            $table->boolean('is_active')->default(true)->index();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists($this->prefix . 'cost_centers');
    }
};
