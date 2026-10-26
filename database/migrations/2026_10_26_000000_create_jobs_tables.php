<?php

declare(strict_types=1);

use Momo\Database\Migrations\Migration;
use Momo\Database\Schema\Blueprint;
use Momo\Database\Schema\Schema;

return new class extends Migration {
    public function up(Schema $schema): void
    {
        $schema->create('jobs', static function (Blueprint $t): void {
            $t->string('id', 32);
            $t->primary(['id']);
            $t->string('queue', 64);
            $t->text('payload');
            $t->integer('attempts')->default(0);
            $t->bigInteger('available_at');
            $t->bigInteger('reserved_until')->nullable();
            $t->index(['queue', 'available_at']);
        });

        $schema->create('failed_jobs', static function (Blueprint $t): void {
            $t->string('id', 32);
            $t->primary(['id']);
            $t->string('queue', 64);
            $t->text('payload');
            $t->text('error');
            $t->bigInteger('failed_at')->index();
        });
    }

    public function down(Schema $schema): void
    {
        $schema->drop('failed_jobs');
        $schema->drop('jobs');
    }
};
