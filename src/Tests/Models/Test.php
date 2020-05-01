<?php

namespace WizeWiz\Obtainable\Tests\Models;

use Illuminate\Database\Eloquent\Model;
use WizeWiz\Obtainable\Concerns\IsObtainable;
use WizeWiz\Obtainable\Contracts\Obtainable;

class Test extends Model implements Obtainable {
    use IsObtainable;

    // psuedo id.
    public $id = 123;

}