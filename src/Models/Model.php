<?php

namespace Zdp\BI\Models;

use Illuminate\Database\Eloquent\Model as BaseModel;

class Model extends BaseModel
{
    public function __construct(array $attributes = [])
    {
        $this->connection = config('bi.connection', 'mysql_bi');
        
        parent::__construct($attributes);
    }
}