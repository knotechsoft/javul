<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateUnitsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('units', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('user_id')->unsigned();
            $table->foreign('user_id')->references('id')->on('users');
            $table->string('category_id')->comment='reference to unit_category. multiple categories with comma.';
            $table->string('name');
            $table->text('description');
            $table->string('credibility')->comment='platinum,gold,silver or bronze';
            $table->integer('location')->unsigned();
            $table->foreign('location')->references('id')->on('cities');
            $table->string('status')->comment="active or disabled";
            $table->integer('parent_id')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::drop('units');
    }
}